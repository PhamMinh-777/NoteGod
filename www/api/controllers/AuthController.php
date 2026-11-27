<?php
/**
 * Auth Controller
 * Handles registration, auto-login, email verification, login/logout, OTP password reset
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Mailer.php';
require_once __DIR__ . '/../../config/database.php';

class AuthController {
    /**
     * POST /api/auth/register
     */
    public static function register(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($data['email'] ?? '');
        $displayName = trim($data['display_name'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? ($data['password2'] ?? '');

        // Validations
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('A valid email address is required.', 400, ['email' => 'Invalid email format']);
        }
        if (empty($displayName)) {
            Response::error('Display name is required.', 400, ['display_name' => 'Name cannot be empty']);
        }
        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters long.', 400, ['password' => 'Too short']);
        }
        if ($password !== $confirmPassword) {
            Response::error('Passwords do not match.', 400, ['confirm_password' => 'Mismatch']);
        }

        $db = getDb();

        // Check duplicate email
        $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            Response::error('An account with this email already exists.', 409, ['email' => 'Duplicate']);
        }

        // Generate activation token & hash password
        $passwordHash = Auth::hashPassword($password);
        $verificationToken = Auth::generateToken(32);

        $insertStmt = $db->prepare("
            INSERT INTO users (email, display_name, password_hash, verification_token, email_verified_at)
            VALUES (:email, :display_name, :password_hash, :verification_token, NULL)
        ");
        $insertStmt->execute([
            ':email'              => strtolower($email),
            ':display_name'       => $displayName,
            ':password_hash'      => $passwordHash,
            ':verification_token' => $verificationToken,
        ]);

        $newUserId = (int)$db->lastInsertId();

        // Send activation email asynchronously / via mailer
        Mailer::sendActivationEmail($email, $displayName, $verificationToken);

        // Auto-login newly registered user
        $newUser = [
            'id'                => $newUserId,
            'email'             => strtolower($email),
            'display_name'      => $displayName,
            'avatar_url'        => null,
            'email_verified_at' => null,
            'theme'             => 'system',
            'font_size'         => 16
        ];
        Auth::loginUser($newUser);

        Response::json([
            'user'             => $_SESSION['user'],
            'auto_logged_in'   => true,
            'is_verified'      => false,
            'message'          => 'Account registered successfully. An activation email has been sent.'
        ], 201, 'Account created successfully');
    }

    /**
     * POST /api/auth/login
     */
    public static function login(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::error('Email and password are required.', 400);
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
            Response::error('Invalid email or password.', 401);
        }

        Auth::loginUser($user);

        Response::json([
            'user' => $_SESSION['user']
        ], 200, 'Logged in successfully');
    }

    /**
     * POST /api/auth/logout
     */
    public static function logout(): void {
        Auth::logout();
        Response::json(null, 200, 'Logged out successfully');
    }

    /**
     * GET /api/auth/me
     */
    public static function me(): void {
        $user = Auth::getCurrentUser();
        if (!$user) {
            Response::error('Not authenticated', 401);
        }
        Response::json(['user' => $user]);
    }

    /**
     * POST /api/auth/verify
     */
    public static function verifyEmail(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $token = trim($data['token'] ?? ($_GET['token'] ?? ''));

        if (empty($token)) {
            Response::error('Activation token is required.', 400);
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT id, email, display_name FROM users WHERE verification_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Invalid or expired activation link.', 400);
        }

        // Activate account
        $updateStmt = $db->prepare("UPDATE users SET email_verified_at = NOW(), verification_token = NULL WHERE id = :id");
        $updateStmt->execute([':id' => $user['id']]);

        // Refresh active session if logged in as this user
        if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']) {
            $_SESSION['user']['is_verified'] = true;
            $_SESSION['user']['email_verified_at'] = date('Y-m-d H:i:s');
        }

        Response::json([
            'activated' => true,
            'message'   => 'Account activated successfully. You can now use all features without restriction.'
        ], 200, 'Account activated');
    }

    /**
     * POST /api/auth/resend-verification
     */
    public static function resendVerification(): void {
        $currentUser = Auth::requireAuth();
        $db = getDb();
        $stmt = $db->prepare("SELECT id, email, display_name, email_verified_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $currentUser['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('User not found', 404);
        }
        if (!empty($user['email_verified_at'])) {
            Response::error('Account is already activated.', 400);
        }

        $token = Auth::generateToken(32);
        $upd = $db->prepare("UPDATE users SET verification_token = :token WHERE id = :id");
        $upd->execute([':token' => $token, ':id' => $user['id']]);

        Mailer::sendActivationEmail($user['email'], $user['display_name'], $token);

        Response::json([
            'message' => 'A new activation link has been sent to your email.'
        ], 200, 'Verification email resent');
    }

    /**
     * POST /api/auth/forgot-password
     */
    public static function forgotPassword(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($data['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Valid email is required.', 400);
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT id, display_name FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Always return success to prevent user email enumeration
        if ($user) {
            $otp = Auth::generateOtp(6);
            $otpHash = Auth::hashPassword($otp);
            $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 mins

            $upd = $db->prepare("
                UPDATE users
                SET reset_otp_hash = :otp_hash,
                    reset_otp_expires_at = :expires_at,
                    reset_otp_attempts = 0
                WHERE id = :id
            ");
            $upd->execute([
                ':otp_hash'   => $otpHash,
                ':expires_at' => $expiresAt,
                ':id'         => $user['id']
            ]);

            Mailer::sendResetOtp($email, $user['display_name'], $otp);
        }

        Response::json([
            'message' => 'If this email is registered, a 6-digit verification code has been dispatched.'
        ], 200, 'Password reset initiated');
    }

    /**
     * POST /api/auth/reset-password
     */
    public static function resetPassword(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($data['email'] ?? '');
        $otp = trim($data['otp'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? ($data['password2'] ?? '');

        if (empty($email) || empty($otp) || empty($password)) {
            Response::error('Email, OTP code and new password are required.', 400);
        }
        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters long.', 400);
        }
        if ($password !== $confirmPassword) {
            Response::error('Passwords do not match.', 400);
        }

        $db = getDb();
        $stmt = $db->prepare("
            SELECT id, reset_otp_hash, reset_otp_expires_at, reset_otp_attempts
            FROM users
            WHERE LOWER(email) = LOWER(:email)
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || empty($user['reset_otp_hash']) || strtotime($user['reset_otp_expires_at']) < time()) {
            Response::error('OTP is invalid or has expired. Please request a new code.', 400);
        }

        if ($user['reset_otp_attempts'] >= 5) {
            Response::error('Too many failed OTP attempts. Please request a new code.', 429);
        }

        if (!Auth::verifyPassword($otp, $user['reset_otp_hash'])) {
            $db->prepare("UPDATE users SET reset_otp_attempts = reset_otp_attempts + 1 WHERE id = :id")
               ->execute([':id' => $user['id']]);
            Response::error('Incorrect OTP code. Please check your email.', 400);
        }

        // Success - rotate password & invalidate OTP
        $newHash = Auth::hashPassword($password);
        $upd = $db->prepare("
            UPDATE users
            SET password_hash = :hash,
                reset_otp_hash = NULL,
                reset_otp_expires_at = NULL,
                reset_otp_attempts = 0
            WHERE id = :id
        ");
        $upd->execute([
            ':hash' => $newHash,
            ':id'   => $user['id']
        ]);

        Response::json([
            'message' => 'Password reset successfully. Please log in with your new password.'
        ], 200, 'Password reset successful');
    }
}
