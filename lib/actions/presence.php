<?php
declare(strict_types=1);

function presencePing(PDO $pdo, array $data): array
{
    if (!Auth::$user) fail(401, 'auth_required', 'Authentication required.');
    $entityType = cleanToken((string) ($data['entity_type'] ?? 'note'), 'note');
    $entityId = normalizeId((string) ($data['entity_id'] ?? $data['entityId'] ?? ''));
    $action = cleanToken((string) ($data['action'] ?? 'viewing'), 'viewing');
    if (!$entityId) fail(400, 'invalid_entity', 'Invalid entity id.');
    $now = date('c');
    $pdo->prepare('INSERT INTO presence(user_id, entity_type, entity_id, action, last_ping) VALUES (?, ?, ?, ?, ?)
        ON CONFLICT(user_id, entity_id) DO UPDATE SET action = excluded.action, last_ping = excluded.last_ping, entity_type = excluded.entity_type')
        ->execute([Auth::$user['id'], $entityType, $entityId, $action, $now]);
    Auth::writeLog('presence.ping', $entityType, $entityId, ['action' => $action]);
    return ['success' => true];
}

function presenceLeave(PDO $pdo, array $data): array
{
    if (!Auth::$user) fail(401, 'auth_required', 'Authentication required.');
    $entityId = normalizeId((string) ($data['entity_id'] ?? $data['entityId'] ?? ''));
    if ($entityId) {
        $pdo->prepare('DELETE FROM presence WHERE user_id = ? AND entity_id = ?')->execute([Auth::$user['id'], $entityId]);
    } else {
        $pdo->prepare('DELETE FROM presence WHERE user_id = ?')->execute([Auth::$user['id']]);
    }
    return ['success' => true];
}

function presenceForEntity(PDO $pdo, string $entityId): array
{
    $stmt = $pdo->prepare('
        SELECT p.user_id, p.entity_type, p.entity_id, p.action, p.last_ping, u.display_name, u.username
        FROM presence p
        JOIN users u ON u.id = p.user_id
        WHERE p.entity_id = ? AND p.last_ping >= datetime("now", "-15 seconds")
        ORDER BY p.last_ping DESC
    ');
    $stmt->execute([$entityId]);
    return ['presence' => $stmt->fetchAll()];
}
