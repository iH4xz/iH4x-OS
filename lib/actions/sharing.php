<?php
declare(strict_types=1);

function sharingListUsers(PDO $pdo): array
{
    Auth::requirePerm('share.create');
    $users = $pdo->query('SELECT id, username, display_name, role_id, is_active FROM users WHERE is_active = 1 ORDER BY display_name COLLATE NOCASE')->fetchAll();
    return ['users' => $users];
}

function sharingGetNote(PDO $pdo, string $noteId): array
{
    Auth::requirePerm('share.create');
    $stmt = $pdo->prepare('SELECT ns.*, u.display_name, u.username FROM note_shares ns JOIN users u ON u.id = ns.user_id WHERE ns.note_id = ? ORDER BY u.display_name COLLATE NOCASE');
    $stmt->execute([$noteId]);
    $items = $stmt->fetchAll();
    $linkStmt = $pdo->prepare('SELECT * FROM share_tokens WHERE entity_type = "note" AND entity_id = ? ORDER BY created_at DESC LIMIT 1');
    $linkStmt->execute([$noteId]);
    $token = $linkStmt->fetch() ?: null;
    return ['collaborators' => $items, 'link' => $token];
}

function sharingSave(PDO $pdo, array $data): array
{
    Auth::requirePerm('share.create');
    $noteId = normalizeId((string) ($data['note_id'] ?? $data['noteId'] ?? ''));
    if (!$noteId) fail(400, 'invalid_note', 'Invalid note id.');
    if (isset($data['user_id']) || isset($data['userId'])) {
        $userId = normalizeId((string) ($data['user_id'] ?? $data['userId'] ?? ''));
        $access = ($data['access_level'] ?? $data['accessLevel'] ?? 'view') === 'edit' ? 'edit' : 'view';
        if (!$userId) fail(400, 'invalid_user', 'Invalid user id.');
        $pdo->prepare('INSERT INTO note_shares(note_id, user_id, access_level, created_by, created_at) VALUES (?, ?, ?, ?, ?)
            ON CONFLICT(note_id, user_id) DO UPDATE SET access_level = excluded.access_level')
            ->execute([$noteId, $userId, $access, Auth::$user['id'] ?? null, date('c')]);
        Auth::writeLog('share.note_user', 'note', $noteId, ['user_id' => $userId, 'access' => $access]);
    }
    if (!empty($data['remove_user_id'])) {
        $uid = normalizeId((string) $data['remove_user_id']);
        $pdo->prepare('DELETE FROM note_shares WHERE note_id = ? AND user_id = ?')->execute([$noteId, $uid]);
        Auth::writeLog('share.note_user_removed', 'note', $noteId, ['user_id' => $uid]);
    }
    if (array_key_exists('enable_link', $data)) {
        if (!empty($data['enable_link'])) {
            $token = bin2hex(random_bytes(18));
            $pdo->prepare('INSERT INTO share_tokens(id, entity_type, entity_id, token, access_level, expires_at, created_by, created_at) VALUES (?, "note", ?, ?, ?, ?, ?, ?)')
                ->execute([bin2hex(random_bytes(8)), $noteId, $token, ($data['link_access'] ?? 'view') === 'edit' ? 'edit' : 'view', !empty($data['expires_at']) ? (string) $data['expires_at'] : null, Auth::$user['id'] ?? '', date('c')]);
        } else {
            $pdo->prepare('DELETE FROM share_tokens WHERE entity_type = "note" AND entity_id = ?')->execute([$noteId]);
        }
    }
    return sharingGetNote($pdo, $noteId);
}

function sharedList(PDO $pdo): array
{
    if (!Auth::$user) {
        return ['notes' => []];
    }
    $stmt = $pdo->prepare('
        SELECT n.id, n.title, n.snippet, n.updated_at, ns.access_level
        FROM note_shares ns
        JOIN notes n ON n.id = ns.note_id
        WHERE ns.user_id = ? AND n.deleted_at IS NULL
        ORDER BY n.updated_at DESC
    ');
    $stmt->execute([Auth::$user['id']]);
    return ['notes' => $stmt->fetchAll()];
}
