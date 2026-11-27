<?php
/**
 * User Controller
 * Handles profile viewing, avatar upload/remove, password rotation, and UI preferences
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../../config/database.php';

class UserController {
    /**
     * GET /api/user/profile
     */
    public static function getProfile(): void {
        $currentUser = Auth::requireAuth();
        $db = getDb();
        $stmt = $db->prepare("
            SELECT id, email, display_name, avatar_url, email_verified_at, theme, font_size, default_note_color
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $currentUser['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('User not found', 404);
        }

        $user['is_verified'] = !empty($user['email_verified_at']);
        Response::json(['user' => $user]);
    }

    /**
     * POST or PATCH /api/user/profile
     * Supports multipart/form-data for avatar upload + JSON/form fields
     */
    public static function updateProfile(): void {
        $currentUser = Auth::requireAuth();
        $db = getDb();

        $displayName = isset($_POST['display_name']) ? trim($_POST['display_name']) : null;
        if ($displayName === null) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['display_name'])) {
                $displayName = trim($input['display_name']);
            }
        }

        if ($displayName !== null) {
            if (empty($displayName)) {
                Response::error('Display name cannot be empty.', 400);
            }
            if (mb_strlen($displayName) > 50) {
                Response::error('Display name must not exceed 50 characters.', 400);
            }
        }

        $avatarUrl = null;

        // Check avatar file upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['avatar'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                Response::error('File upload failed with error code: ' . $file['error'], 400);
            }

            // Max 2MB
            if ($file['size'] > 2 * 1024 * 1024) {
                Response::error('Avatar image must not exceed 2MB in size.', 400);
            }

            // Verify MIME type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowedMimes[$mime])) {
                Response::error('Unsupported file format. Only JPG, PNG, and WebP images are allowed.', 400);
            }

            $ext = $allowedMimes[$mime];
            $filename = 'avatar_' . $currentUser['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../assets/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $destination = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                Response::error('Failed to save uploaded avatar file on server.', 500);
            }

            $avatarUrl = '/assets/uploads/avatars/' . $filename;
        }

        // Build dynamic SQL update
        $updates = [];
        $params = [':id' => $currentUser['id']];

        if ($displayName !== null) {
            $updates[] = 'display_name = :display_name';
            $params[':display_name'] = $displayName;
        }
        if ($avatarUrl !== null) {
            $updates[] = 'avatar_url = :avatar_url';
            $params[':avatar_url'] = $avatarUrl;
        }

        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        // Return updated user
        self::getProfile();
    }

    /**
     * DELETE /api/user/avatar
     */
    public static function removeAvatar(): void {
        $currentUser = Auth::requireAuth();
        $db = getDb();
        $stmt = $db->prepare("UPDATE users SET avatar_url = NULL WHERE id = :id");
        $stmt->execute([':id' => $currentUser['id']]);

        self::getProfile();
    }

    /**
     * POST /api/user/change-password
     */
    public static function changePassword(): void {
        $currentUser = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $currentPassword = $data['current_password'] ?? ($data['current'] ?? '');
        $newPassword     = $data['new_password'] ?? ($data['password'] ?? '');
        $confirmPassword = $data['confirm_password'] ?? ($data['password2'] ?? '');

        if (empty($currentPassword) || empty($newPassword)) {
            Response::error('Current password and new password are required.', 400);
        }
        if (strlen($newPassword) < 8) {
            Response::error('New password must be at least 8 characters long.', 400);
        }
        if ($newPassword !== $confirmPassword) {
            Response::error('New passwords do not match.', 400);
        }
        if ($newPassword === $currentPassword) {
            Response::error('New password must be different from current password.', 400);
        }

        $db = getDb();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $currentUser['id']]);
        $user = $stmt->fetch();

        if (!$user || !Auth::verifyPassword($currentPassword, $user['password_hash'])) {
            Response::error('Current password is incorrect.', 401);
        }

        $newHash = Auth::hashPassword($newPassword);
        $upd = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $upd->execute([
            ':hash' => $newHash,
            ':id'   => $currentUser['id']
        ]);

        Response::json(null, 200, 'Password changed successfully');
    }

    /**
     * PUT /api/user/preferences
     */
    public static function updatePreferences(): void {
        $currentUser = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $theme            = $data['theme'] ?? null;
        $fontSize         = isset($data['font_size']) ? (int)$data['font_size'] : null;
        $defaultNoteColor = $data['default_note_color'] ?? ($data['color'] ?? null);

        $updates = [];
        $params = [':id' => $currentUser['id']];

        if ($theme !== null) {
            if (!in_array($theme, ['light', 'dark', 'system'])) {
                Response::error('Invalid theme. Allowed values: light, dark, system.', 400);
            }
            $updates[] = 'theme = :theme';
            $params[':theme'] = $theme;
        }

        if ($fontSize !== null) {
            if ($fontSize < 12 || $fontSize > 24) {
                Response::error('Font size must be between 12 and 24 pixels.', 400);
            }
            $updates[] = 'font_size = :font_size';
            $params[':font_size'] = $fontSize;
        }

        if ($defaultNoteColor !== null) {
            $allowedColors = ['default', 'lavender', 'butter', 'mint', 'rose', 'sky'];
            if (!in_array($defaultNoteColor, $allowedColors)) {
                Response::error('Invalid default note color.', 400);
            }
            $updates[] = 'default_note_color = :color';
            $params[':color'] = $defaultNoteColor;
        }

        if (!empty($updates)) {
            $db = getDb();
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        self::getProfile();
    }
}
