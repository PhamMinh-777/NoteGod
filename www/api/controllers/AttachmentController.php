<?php
/**
 * Attachment Controller (step 3.2): upload, list (via NoteController) and remove files on a note.
 * Files go to assets/uploads/notes/ under a random name; the path is stored in `attachments`.
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../../config/database.php';

class AttachmentController {
    private const MAX_BYTES = 20 * 1024 * 1024;
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov', 'pdf', 'txt', 'csv',
                             'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];

    /** POST /api/notes/{id}/attachments  (multipart/form-data, field "file") */
    public static function upload(int $noteId): void {
        $user = Auth::requireAuth();
        $db = getDb();

        $c = $db->prepare("SELECT id FROM notes WHERE id = :id AND owner_id = :u AND deleted_at IS NULL");
        $c->execute([':id' => $noteId, ':u' => $user['id']]);
        if (!$c->fetch()) {
            Response::error('Note not found.', 404);
        }

        if (!isset($_FILES['file'])) {
            Response::error('No file was uploaded.', 400);
        }
        $f = $_FILES['file'];
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            Response::error('File is too large.', 413);
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            Response::error('Upload failed (error code ' . $f['error'] . ').', 400);
        }
        if ($f['size'] > self::MAX_BYTES) {
            Response::error('File must be 20 MB or smaller.', 413);
        }

        $orig = mb_substr(basename((string)$f['name']), 0, 200);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED, true)) {
            Response::error('This file type is not allowed.', 400);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';

        $dir = __DIR__ . '/../../assets/uploads/notes/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $stored = bin2hex(random_bytes(8)) . '.' . $ext; // never trust the original name
        if (!move_uploaded_file($f['tmp_name'], $dir . $stored)) {
            Response::error('Could not save the file on the server.', 500);
        }
        $path = '/assets/uploads/notes/' . $stored;

        $ins = $db->prepare("INSERT INTO attachments (note_id, file_name, file_path, file_size, mime_type)
                             VALUES (:n, :name, :path, :size, :mime)");
        $ins->execute([':n' => $noteId, ':name' => $orig, ':path' => $path, ':size' => (int)$f['size'], ':mime' => $mime]);

        Response::json(['file' => [
            'id'   => (int)$db->lastInsertId(),
            'name' => $orig,
            'size' => (int)$f['size'],
            'url'  => $path,
            'type' => $mime,
        ]], 201, 'File attached');
    }

    /** DELETE /api/attachments/{id} */
    public static function delete(int $id): void {
        $user = Auth::requireAuth();
        $db = getDb();

        $s = $db->prepare("SELECT a.id, a.file_path FROM attachments a
                           JOIN notes n ON n.id = a.note_id
                           WHERE a.id = :id AND n.owner_id = :u");
        $s->execute([':id' => $id, ':u' => $user['id']]);
        $row = $s->fetch();
        if (!$row) {
            Response::error('Attachment not found.', 404);
        }

        $db->prepare("DELETE FROM attachments WHERE id = :id")->execute([':id' => $id]);

        $abs = __DIR__ . '/../..' . $row['file_path'];
        if (strpos($row['file_path'], '/assets/uploads/notes/') === 0 && is_file($abs)) {
            @unlink($abs);
        }
        Response::json(null, 200, 'Attachment removed');
    }
}