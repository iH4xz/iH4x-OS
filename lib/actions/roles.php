<?php
declare(strict_types=1);

function rolesList(PDO $pdo): array
{
    Auth::requirePerm('users.view');
    $roles = $pdo->query('SELECT id, name, is_system, created_at FROM roles ORDER BY is_system DESC, name COLLATE NOCASE')->fetchAll();
    foreach ($roles as &$role) {
        $stmt = $pdo->prepare('SELECT perm_key FROM role_permissions WHERE role_id = ? ORDER BY perm_key');
        $stmt->execute([$role['id']]);
        $role['is_system'] = (bool) $role['is_system'];
        $role['permissions'] = array_map(static fn($row) => (string) $row['perm_key'], $stmt->fetchAll());
    }
    unset($role);
    $perms = [];
    foreach (Auth::permissionMap() as $key => [$label, $group]) {
        $perms[] = ['key' => $key, 'label' => $label, 'group' => $group];
    }
    return ['roles' => $roles, 'permissions' => $perms];
}

function rolesSave(PDO $pdo, array $data): array
{
    Auth::requirePerm('roles.manage');
    $id = normalizeId(is_string($data['id'] ?? null) ? $data['id'] : null);
    $isNew = !$id;
    $id = $id ?: cleanToken(strtolower((string) ($data['name'] ?? 'role')), 'role') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing && !empty($existing['is_system']) && isset($data['delete'])) {
        fail(400, 'system_role', 'System roles cannot be deleted.');
    }
    if (!empty($data['delete'])) {
        $pdo->prepare('DELETE FROM roles WHERE id = ? AND is_system = 0')->execute([$id]);
        Auth::writeLog('role.deleted', 'role', $id, []);
        return rolesList($pdo);
    }
    $name = trim((string) ($data['name'] ?? ($existing['name'] ?? 'Custom role')));
    $pdo->prepare('INSERT INTO roles(id, name, is_system, created_at) VALUES (?, ?, 0, ?)
        ON CONFLICT(id) DO UPDATE SET name = excluded.name')
        ->execute([$id, $name !== '' ? $name : 'Custom role', date('c')]);
    if (isset($data['permissions']) && is_array($data['permissions'])) {
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$id]);
        foreach ($data['permissions'] as $perm) {
            $pdo->prepare('INSERT OR IGNORE INTO role_permissions(role_id, perm_key) VALUES (?, ?)')->execute([$id, (string) $perm]);
        }
    } elseif ($isNew && isset($data['clone_from'])) {
        $stmt = $pdo->prepare('SELECT perm_key FROM role_permissions WHERE role_id = ?');
        $stmt->execute([cleanToken((string) $data['clone_from'], 'viewer')]);
        foreach ($stmt->fetchAll() as $row) {
            $pdo->prepare('INSERT OR IGNORE INTO role_permissions(role_id, perm_key) VALUES (?, ?)')->execute([$id, (string) $row['perm_key']]);
        }
    }
    Auth::writeLog($isNew ? 'role.created' : 'role.updated', 'role', $id, ['name' => $name]);
    return rolesList($pdo);
}

function adminSettings(PDO $pdo, array $data): array
{
    Auth::requirePerm('system.settings');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['workspace_name', 'default_language', 'default_role', 'max_upload_mb'] as $key) {
            if (isset($data[$key])) {
                setSetting($pdo, $key, (string) $data[$key]);
            }
        }
        Auth::writeLog('settings.updated', 'settings', 'system', []);
    }
    $settings = getSettings($pdo);
    $settings += [
        'workspace_name' => defined('WORKSPACE_NAME') ? WORKSPACE_NAME : 'iH4x OS',
        'default_role' => defined('DEFAULT_ROLE') ? DEFAULT_ROLE : 'editor',
        'max_upload_mb' => defined('MAX_UPLOAD_MB') ? (string) MAX_UPLOAD_MB : '20',
    ];
    return ['settings' => $settings];
}

function hqStats(PDO $pdo): array
{
    Auth::requirePerm('users.view');
    $active = $pdo->query("SELECT id, username, display_name, role_id, last_seen FROM users WHERE last_seen >= datetime('now', '-15 minutes') ORDER BY last_seen DESC")->fetchAll();
    $activity = $pdo->query('SELECT a.*, u.display_name, u.username FROM activity_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 30')->fetchAll();
    $dbSize = is_file(DB_PATH) ? filesize(DB_PATH) : 0;
    $attachments = 0;
    $dir = __DIR__ . '/../../data/attachments';
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $attachments += $file->getSize();
        }
    }
    return ['activeUsers' => $active, 'activity' => $activity, 'storage' => ['database' => $dbSize, 'attachments' => $attachments, 'total' => $dbSize + $attachments]];
}
