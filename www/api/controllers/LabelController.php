<?php
/**
 * Label Controller (step 3.5): list, add, rename, delete.
 * Deleting a label never deletes notes; renaming updates every note automatically.
 */

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../../config/database.php';

class LabelController {

    private static function readJson(): array {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private static function cleanName($v): string {
        $n = trim((string)$v);
        if ($n === '') Response::error('Label name is required.', 400);
        if (mb_strlen($n) > 30) Response::error('Label must be 30 characters or less.', 400);
        return $n;
    }

    private static function findId(PDO $db, int $uid, string $name) {
        $s = $db->prepare("SELECT id FROM labels WHERE user_id = :u AND name = :n");
        $s->execute([':u' => $uid, ':n' => $name]);
        return $s->fetchColumn();
    }

    /** GET /api/labels */
    public static function getAll(): void {
        $user = Auth::requireAuth();
        $db = getDb();
        $s = $db->prepare("SELECT l.id, l.name, COUNT(nl.note_id) AS notes
                           FROM labels l LEFT JOIN note_labels nl ON nl.label_id = l.id
                           WHERE l.user_id = :u GROUP BY l.id, l.name ORDER BY l.name");
        $s->execute([':u' => $user['id']]);
        Response::json(['labels' => $s->fetchAll()]);
    }

    /** POST /api/labels  {name} */
    public static function create(): void {
        $user = Auth::requireAuth();
        $db = getDb();
        $name = self::cleanName(self::readJson()['name'] ?? '');
        if (self::findId($db, (int)$user['id'], $name)) {
            Response::error('That label already exists.', 409);
        }
        $db->prepare("INSERT INTO labels (user_id, name) VALUES (:u, :n)")->execute([':u' => $user['id'], ':n' => $name]);
        Response::json(['id' => (int)$db->lastInsertId(), 'name' => $name], 201, 'Label created');
    }

    /** PUT /api/labels  {old_name, name} */
    public static function rename(): void {
        $user = Auth::requireAuth();
        $db = getDb();
        $data = self::readJson();
        $old = trim((string)($data['old_name'] ?? ''));
        $new = self::cleanName($data['name'] ?? '');

        $id = self::findId($db, (int)$user['id'], $old);
        if (!$id) Response::error('Label not found.', 404);
        $other = self::findId($db, (int)$user['id'], $new);
        if ($other && (int)$other !== (int)$id) Response::error('That label already exists.', 409);

        $db->prepare("UPDATE labels SET name = :n WHERE id = :id AND user_id = :u")
           ->execute([':n' => $new, ':id' => $id, ':u' => $user['id']]);
        Response::json(['name' => $new], 200, 'Label renamed');
    }

    /** DELETE /api/labels?name=... */
    public static function delete(): void {
        $user = Auth::requireAuth();
        $db = getDb();
        $name = trim((string)($_GET['name'] ?? ''));
        $id = self::findId($db, (int)$user['id'], $name);
        if (!$id) Response::error('Label not found.', 404);

        $db->prepare("DELETE FROM note_labels WHERE label_id = :id")->execute([':id' => $id]); // notes are kept
        $db->prepare("DELETE FROM labels WHERE id = :id AND user_id = :u")->execute([':id' => $id, ':u' => $user['id']]);
        Response::json(null, 200, 'Label deleted');
    }
}