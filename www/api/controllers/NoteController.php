<?php
/**
 * Note Controller
 * Simple note management (Phase 3)
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../../config/database.php';

class NoteController {

    /** Strip dangerous markup from rich text (no scripts, no attributes). */
    private static function cleanHtml(string $html): string {
        $clean = strip_tags($html, '<p><br><b><strong><i><em><u><ul><ol><li><div><h1><h2><h3>');
        return preg_replace('/<(\w+)\s[^>]*>/', '<$1>', $clean);
    }

    private static function readJson(): array {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    /**
     * GET /api/notes
     * Pinned notes first (most recently pinned on top), then newest updated.
     */
    public static function getAll(): void {
        $user = Auth::requireAuth();
        $db = getDb();

        $stmt = $db->prepare("
            SELECT id, title, plain_text_content, color, is_pinned, pinned_at, is_locked, updated_at
            FROM notes
            WHERE owner_id = :uid AND deleted_at IS NULL
            ORDER BY is_pinned DESC, pinned_at DESC, updated_at DESC
        ");
        $stmt->execute([':uid' => $user['id']]);
        $rows = $stmt->fetchAll();

        $notes = [];
        foreach ($rows as $r) {
            $locked = (bool)$r['is_locked'];
            $notes[] = [
                'id'          => (int)$r['id'],
                'title'       => $r['title'],
                // never leak the content of a locked note
                'body'        => $locked ? '' : mb_substr((string)$r['plain_text_content'], 0, 200),
                'pinned'      => (bool)$r['is_pinned'],
                'pinned_at'   => $r['pinned_at'],
                'locked'      => $locked,
                'shared'      => null,   // Phase 4
                'color'       => $r['color'] ?: 'default',
                'attachments' => 0,      // added in step 3.2
                'labels'      => [],     // added in step 3.5
                'updated'     => date('c', strtotime($r['updated_at'])),
            ];
        }

        Response::json(['notes' => $notes]);
    }

    /** GET /api/notes/{id} */
    public static function getOne(int $id): void {
        $user = Auth::requireAuth();
        $db = getDb();

        $stmt = $db->prepare("
            SELECT id, title, content_rich, plain_text_content, color, is_pinned, is_locked, updated_at
            FROM notes
            WHERE id = :id AND owner_id = :uid AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id, ':uid' => $user['id']]);
        $r = $stmt->fetch();
        if (!$r) {
            Response::error('Note not found.', 404);
        }

        $locked = (bool)$r['is_locked'];
        Response::json(['note' => [
            'id'          => (int)$r['id'],
            'title'       => $r['title'],
            'html'        => $locked ? '' : self::cleanHtml((string)$r['content_rich']),
            'body'        => $locked ? '' : (string)$r['plain_text_content'],
            'pinned'      => (bool)$r['is_pinned'],
            'locked'      => $locked,
            'shared'      => null,
            'color'       => $r['color'] ?: 'default',
            'attachments' => 0,
            'labels'      => [],
            'files'       => [],
            'updated'     => date('c', strtotime($r['updated_at'])),
        ]]);
    }

    /** POST /api/notes */
    public static function create(): void {
        $user = Auth::requireAuth();
        $data = self::readJson();

        $title = trim((string)($data['title'] ?? ''));
        $rich  = self::cleanHtml((string)($data['content_rich'] ?? ''));
        $plain = trim(html_entity_decode(strip_tags($rich)));

        if (mb_strlen($title) > 255) {
            Response::error('Title is too long.', 400);
        }

        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO notes (owner_id, title, content_rich, plain_text_content)
            VALUES (:uid, :title, :rich, :plain)
        ");
        $stmt->execute([':uid' => $user['id'], ':title' => $title, ':rich' => $rich, ':plain' => $plain]);

        Response::json(['id' => (int)$db->lastInsertId()], 201, 'Note created');
    }

    /** PUT /api/notes/{id} */
    public static function update(int $id): void {
        $user = Auth::requireAuth();
        $data = self::readJson();
        $db = getDb();

        $check = $db->prepare("SELECT id FROM notes WHERE id = :id AND owner_id = :uid AND deleted_at IS NULL");
        $check->execute([':id' => $id, ':uid' => $user['id']]);
        if (!$check->fetch()) {
            Response::error('Note not found.', 404);
        }

        $title = trim((string)($data['title'] ?? ''));
        $rich  = self::cleanHtml((string)($data['content_rich'] ?? ''));
        $plain = trim(html_entity_decode(strip_tags($rich)));

        if (mb_strlen($title) > 255) {
            Response::error('Title is too long.', 400);
        }

        $stmt = $db->prepare("
            UPDATE notes
            SET title = :title, content_rich = :rich, plain_text_content = :plain, version = version + 1
            WHERE id = :id AND owner_id = :uid
        ");
        $stmt->execute([':title' => $title, ':rich' => $rich, ':plain' => $plain, ':id' => $id, ':uid' => $user['id']]);

        Response::json(['id' => $id], 200, 'Note saved');
    }

    /** DELETE /api/notes/{id} (soft delete) */
    public static function delete(int $id): void {
        $user = Auth::requireAuth();
        $db = getDb();

        $stmt = $db->prepare("UPDATE notes SET deleted_at = NOW() WHERE id = :id AND owner_id = :uid AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':uid' => $user['id']]);

        if ($stmt->rowCount() === 0) {
            Response::error('Note not found.', 404);
        }
        Response::json(null, 200, 'Note deleted');
    }
}