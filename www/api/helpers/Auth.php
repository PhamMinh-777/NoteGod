<?php
/**
 * Authentication & Security Helper
 * FinalNote Project
 */

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/../../config/database.php';

class Auth {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure cookie configuration
            session_set_cookie_params([
                'lifetime' => 86400 * 30, // 30 days
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function generateToken(int $bytes = 32): string {
        return bin2hex(random_bytes($bytes));
    }

    public static function generateOtp(int $length = 6): string {
        return str_pad((string)random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    public static function loginUser(array $user): void {
        self::startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = [
            'id'                => $user['id'],
            'email'             => $user['email'],
            'display_name'      => $user['display_name'],
            'avatar_url'        => $user['avatar_url'] ?? null,
            'is_verified'       => !empty($user['email_verified_at']),
            'email_verified_at' => $user['email_verified_at'] ?? null,
            'theme'             => $user['theme'] ?? 'system',
            'font_size'         => (int)($user['font_size'] ?? 16),
        ];
    }

    public static function getCurrentUser(): ?array {
        self::startSession();
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        // Re-fetch latest user record from DB
        try {
            $db = getDb();
            $stmt = $db->prepare("SELECT id, email, display_name, avatar_url, email_verified_at, theme, font_size FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                $user['is_verified'] = !empty($user['email_verified_at']);
                $_SESSION['user'] = $user;
                return $user;
            }
        } catch (Exception $e) {
            error_log("Auth getCurrentUser error: " . $e->getMessage());
        }

        return $_SESSION['user'] ?? null;
    }

    public static function requireAuth(): array {
        $user = self::getCurrentUser();
        if (!$user) {
            Response::error('Authentication required. Please sign in.', 401);
        }
        return $user;
    }

    public static function logout(): void {
        self::startSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
}
