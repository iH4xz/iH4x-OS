<?php
declare(strict_types=1);

function usersList(PDO $pdo): array
{
    Auth::requirePerm('users.view');
    $users = $pdo->query('SELECT id, username, email, display_name, avatar_type, avatar_data, role_id, is_active, last_seen, created_at FROM users ORDER BY created_at DESC')->fetchAll();
    $roles = $pdo->query('SELECT id, name, is_system FROM roles ORDER BY is_system DESC, name COLLATE NOCASE')->fetchAll();
    foreach ($users as &$user) {
        $user['is_active'] = (bool) $user['is_active'];
        $user['permissions'] = Auth::effectivePermissions((string) $user['id'], (string) $user['role_id']);
    }
    unset($user);
    return ['users' => $users, 'roles' => $roles, 'currentUser' => Auth::userPayload()];
}

function usersSave(PDO $pdo, array $data): array
{
    $currentId = Auth::$user['id'] ?? '';
    $targetId = normalizeId(is_string($data['id'] ?? null) ? $data['id'] : null) ?: $currentId;
    if ($targetId !== $currentId) {
        Auth::requirePerm('users.edit');
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        Auth::requirePerm('users.invite');
        $targetId = bin2hex(random_bytes(8));
    }
    $username = trim((string) ($data['username'] ?? ($existing['username'] ?? '')));
    $display = trim((string) ($data['display_name'] ?? ($data['displayName'] ?? ($existing['display_name'] ?? $username))));
    $email = trim((string) ($data['email'] ?? ($existing['email'] ?? '')));
    $roleId = cleanToken((string) ($data['role_id'] ?? ($data['roleId'] ?? ($existing['role_id'] ?? (defined('DEFAULT_ROLE') ? DEFAULT_ROLE : 'editor')))), 'editor');
    if ($targetId === $currentId && !Auth::can('users.edit')) {
        $roleId = (string) ($existing['role_id'] ?? 'editor');
        $username = (string) ($existing['username'] ?? $username);
    }
    if ($username === '' || $display === '') {
        fail(400, 'invalid_user', 'Username and display name are required.');
    }
    $passwordHash = (string) ($existing['password_hash'] ?? '');
    if (!empty($data['password'])) {
        $passwordHash = password_hash((string) $data['password'], PASSWORD_BCRYPT);
    } elseif ($passwordHash === '') {
        $passwordHash = password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT);
    }
    $now = date('c');
    $pdo->prepare('INSERT INTO users(id, username, email, display_name, avatar_type, avatar_data, password_hash, role_id, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON CONFLICT(id) DO UPDATE SET username = excluded.username, email = excluded.email, display_name = excluded.display_name, avatar_type = excluded.avatar_type, avatar_data = excluded.avatar_data, password_hash = excluded.password_hash, role_id = excluded.role_id')
        ->execute([
            $targetId,
            $username,
            $email,
            $display,
            (string) ($data['avatar_type'] ?? ($existing['avatar_type'] ?? 'initials')),
            (string) ($data['avatar_data'] ?? ($existing['avatar_data'] ?? '')),
            $passwordHash,
            $roleId,
            (string) ($existing['created_at'] ?? $now),
        ]);
    Auth::writeLog($existing ? 'user.updated' : 'user.created', 'user', $targetId, ['username' => $username]);
    if (!Auth::can('users.view')) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$targetId]);
        Auth::$user = $stmt->fetch() ?: Auth::$user;
        return ['currentUser' => Auth::userPayload()];
    }
    return usersList($pdo);
}

function usersPatch(PDO $pdo, array $data): array
{
    Auth::requirePerm('users.edit');
    $id = normalizeId(is_string($data['id'] ?? null) ? $data['id'] : null);
    if (!$id) fail(400, 'invalid_user', 'Invalid user id.');
    $sets = [];
    $args = [];
    foreach (['role_id' => 'role_id', 'roleId' => 'role_id', 'display_name' => 'display_name', 'displayName' => 'display_name', 'email' => 'email'] as $key => $col) {
        if (array_key_exists($key, $data)) {
            $sets[$col] = "$col = ?";
            $args[$col] = $col === 'role_id' ? cleanToken((string) $data[$key], 'editor') : trim((string) $data[$key]);
        }
    }
    if (array_key_exists('is_active', $data) || array_key_exists('active', $data)) {
        Auth::requirePerm('users.deactivate');
        $sets['is_active'] = 'is_active = ?';
        $args['is_active'] = !empty($data['is_active'] ?? $data['active']) ? 1 : 0;
    }
    if ($sets) {
        $pdo->prepare('UPDATE users SET ' . implode(', ', array_values($sets)) . ' WHERE id = ?')->execute([...array_values($args), $id]);
    }
    if (isset($data['permissions']) && is_array($data['permissions'])) {
        $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$id]);
        foreach ($data['permissions'] as $perm => $granted) {
            $pdo->prepare('INSERT INTO user_permissions(user_id, perm_key, granted) VALUES (?, ?, ?)')->execute([$id, (string) $perm, !empty($granted) ? 1 : 0]);
        }
    }
    Auth::writeLog('user.patched', 'user', $id, []);
    return usersList($pdo);
}

function usersResetLink(PDO $pdo, array $data): array
{
    Auth::requirePerm('users.edit');
    $id = normalizeId(is_string($data['id'] ?? null) ? $data['id'] : null);
    if (!$id) fail(400, 'invalid_user', 'Invalid user id.');
    $token = bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO reset_tokens(id, user_id, token, expires_at) VALUES (?, ?, ?, ?)')->execute([bin2hex(random_bytes(8)), $id, $token, date('c', time() + 86400)]);
    $base = rtrim((string) (defined('APP_URL') ? APP_URL : ''), '/');
    $url = ($base !== '' ? $base . '/' : '') . 'login.php?reset=' . rawurlencode($token);
    Auth::writeLog('user.reset_link', 'user', $id, []);
    return ['resetLink' => $url];
}

function usersInvite(PDO $pdo, array $data): array
{
    Auth::requirePerm('users.invite');
    $token = bin2hex(random_bytes(24));
    $roleId = cleanToken((string) ($data['role_id'] ?? $data['roleId'] ?? (defined('DEFAULT_ROLE') ? DEFAULT_ROLE : 'editor')), 'editor');
    $email = trim((string) ($data['email'] ?? ''));
    $displayName = trim((string) ($data['display_name'] ?? $data['displayName'] ?? ''));
    $expires = date('c', time() + 86400 * 7);
    $pdo->prepare('INSERT INTO invite_tokens(id, token, role_id, email, display_name, created_by, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([bin2hex(random_bytes(8)), $token, $roleId, $email, $displayName, (string) (Auth::$user['id'] ?? ''), $expires, date('c')]);
    Auth::writeLog('user.invited', 'invite', $token, ['role' => $roleId, 'email' => $email]);
    return ['inviteLink' => baseUrl() . '/login.php?invite=' . rawurlencode($token), 'expiresAt' => $expires];
}

function baseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : ((($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http');
    $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host)) {
        $addr = (string) ($_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()));
        if ($addr !== '') {
            $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
            $default = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
            $host = $addr . ($default ? '' : ':' . $port);
        }
    }
    $script = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $script = rtrim($script, '/');
    return $scheme . '://' . $host . ($script !== '' ? $script : '');
}
