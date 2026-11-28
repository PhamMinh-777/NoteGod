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

    /** note id => [label names] for this user */
    private static function labelMap(PDO $db, int $uid): array {
        $s = $db->prepare("SELECT nl.note_id, l.name FROM note_labels nl
                           JOIN labels l ON l.id = nl.label_id
                           WHERE l.user_id = :u ORDER BY l.name");
        $s->execute([':u' => $uid]);
        $map = [];
        foreach ($s->fetchAll() as $r) {
            $map[(int)$r['note_id']][] = $r['name'];
        }
        return $map;
    }

    /** Replace a note's labels with the given names (creates missing labels). */
    private static function syncLabels(PDO $db, int $noteId, int $uid, array $names): void {
        $clean = [];
        foreach ($names as $n) {
            $n = mb_substr(trim((string)$n), 0, 30);
            if ($n !== '' && !in_array(mb_strtolower($n), array_map('mb_strtolower', $clean), true)) {
                $clean[] = $n;
            }
        }
        $db->prepare("DELETE FROM note_labels WHERE note_id = :n")->execute([':n' => $noteId]);
        foreach ($clean as $name) {
            $s = $db->prepare("SELECT id FROM labels WHERE user_id = :u AND name = :n");
            $s->execute([':u' => $uid, ':n' => $name]);
            $lid = $s->fetchColumn();
            if (!$lid) {
                $db->prepare("INSERT INTO labels (user_id, name) VALUES (:u, :n)")->execute([':u' => $uid, ':n' => $name]);
                $lid = $db->lastInsertId();
            }
            $db->prepare("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (:n, :l)")
               ->execute([':n' => $noteId, ':l' => $lid]);
        }
    }

    /** GET /api/notes?q= */
    public static function getAll(): void {
        $user = Auth::requireAuth();
        $db = getDb();

        $sql = "SELECT id, title, plain_text_content, color, is_pinned, pinned_at, is_locked, updated_at
                FROM notes WHERE owner_id = :uid AND deleted_at IS NULL";
        $params = [':uid' => $user['id']];

        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $sql .= " AND (title LIKE :q1 OR (is_locked = 0 AND plain_text_content LIKE :q2))";
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }

        $sql .= " ORDER BY is_pinned DESC, pinned_at DESC, updated_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $labelMap = self::labelMap($db, $user['id']);

        $notes = [];
        foreach ($rows as $r) {
            $locked = (bool)$r['is_locked'];
            $notes[] = [
                'id'          => (int)$r['id'],
                'title'       => $r['title'],
                'body'        => $locked ? '' : mb_substr((string)$r['plain_text_content'], 0, 200),
                'pinned'      => (bool)$r['is_pinned'],
                'pinned_at'   => $r['pinned_at'],
                'locked'      => $locked,
                'shared'      => null,   // Phase 4
                'color'       => $r['color'] ?: 'default',
                'attachments' => 0,      // step 3.2
                'labels'      => $labelMap[(int)$r['id']] ?? [],
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
            'labels'      => self::labelMap($db, $user['id'])[$id] ?? [],
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
        $newId = (int)$db->lastInsertId();

        if (isset($data['labels']) && is_array($data['labels'])) {
            self::syncLabels($db, $newId, (int)$user['id'], $data['labels']);
        }

        Response::json(['id' => $newId], 201, 'Note created');
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

        if (isset($data['labels']) && is_array($data['labels'])) {
            self::syncLabels($db, $id, (int)$user['id'], $data['labels']);
        }

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

    /** POST /api/notes/{id}/pin (toggle) */
    public static function togglePin(int $id): void {
        $user = Auth::requireAuth();
        $db = getDb();

        $stmt = $db->prepare("SELECT is_pinned FROM notes WHERE id = :id AND owner_id = :uid AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':uid' => $user['id']]);
        $r = $stmt->fetch();
        if (!$r) {
            Response::error('Note not found.', 404);
        }

        $pin = !$r['is_pinned'];
        $upd = $db->prepare("UPDATE notes SET is_pinned = :p, pinned_at = " . ($pin ? 'NOW()' : 'NULL') . " WHERE id = :id AND owner_id = :uid");
        $upd->execute([':p' => $pin ? 1 : 0, ':id' => $id, ':uid' => $user['id']]);

        Response::json(['pinned' => $pin]);
    }
}