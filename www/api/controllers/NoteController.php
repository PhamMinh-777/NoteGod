<?php
/**
 * Note Controller
 * Simple note management (Phase 3)
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../../config/database.php';

class NoteController {
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
}