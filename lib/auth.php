<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

final class Auth
{
    public static ?array $user = null;
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        if (function_exists('db')) {
            self::$pdo = db();
        } else {
            if (!is_dir(dirname(DB_PATH))) {
                @mkdir(dirname(DB_PATH), 0777, true);
            }
            self::$pdo = new PDO('sqlite:' . DB_PATH);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
        }
        self::ensureSchema(self::$pdo);
        return self::$pdo;
    }

    public static function ensureSchema(PDO $pdo): void
    {
        self::ensureColumn($pdo, 'notes', 'created_by', "ALTER TABLE notes ADD COLUMN created_by TEXT NULL");
        self::ensureColumn($pdo, 'folders', 'created_by', "ALTER TABLE folders ADD COLUMN created_by TEXT NULL");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id TEXT PRIMARY KEY,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL DEFAULT '',
                display_name TEXT NOT NULL,
                avatar_type TEXT NOT NULL DEFAULT 'initials',
                avatar_data TEXT NOT NULL DEFAULT '',
                password_hash TEXT NOT NULL,
                role_id TEXT NOT NULL DEFAULT 'editor',
                is_active INTEGER NOT NULL DEFAULT 1,
                last_seen TEXT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS roles (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                is_system INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT ''
            );
            CREATE TABLE IF NOT EXISTS permissions (
                id TEXT PRIMARY KEY,
                perm_key TEXT NOT NULL UNIQUE,
                label TEXT NOT NULL,
                group_name TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS role_permissions (
                role_id TEXT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
                perm_key TEXT NOT NULL,
                PRIMARY KEY (role_id, perm_key)
            );
            CREATE TABLE IF NOT EXISTS user_permissions (
                user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                perm_key TEXT NOT NULL,
                granted INTEGER NOT NULL DEFAULT 1,
                PRIMARY KEY (user_id, perm_key)
            );
            CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token TEXT NOT NULL UNIQUE,
                ip TEXT NOT NULL DEFAULT '',
                user_agent TEXT NOT NULL DEFAULT '',
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS reset_tokens (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                used_at TEXT NULL
            );
            CREATE TABLE IF NOT EXISTS invite_tokens (
                id TEXT PRIMARY KEY,
                token TEXT NOT NULL UNIQUE,
                role_id TEXT NOT NULL DEFAULT 'editor',
                email TEXT NOT NULL DEFAULT '',
                display_name TEXT NOT NULL DEFAULT '',
                created_by TEXT NOT NULL DEFAULT '',
                expires_at TEXT NOT NULL,
                used_at TEXT NULL,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NULL,
                action TEXT NOT NULL,
                entity_type TEXT NOT NULL DEFAULT '',
                entity_id TEXT NOT NULL DEFAULT '',
                meta_json TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token);
            CREATE INDEX IF NOT EXISTS idx_activity_created ON activity_log(created_at DESC);
        ");
        self::ensureColumn($pdo, 'users', 'email', "ALTER TABLE users ADD COLUMN email TEXT NOT NULL DEFAULT ''");
        self::ensureColumn($pdo, 'roles', 'created_at', "ALTER TABLE roles ADD COLUMN created_at TEXT NOT NULL DEFAULT ''");
        self::seedRolesAndPermissions($pdo);
    }

    public static function ensureColumn(PDO $pdo, string $table, string $column, string $sql): void
    {
        try {
            $cols = array_map(static fn($row) => (string) $row['name'], $pdo->query("PRAGMA table_info($table)")->fetchAll());
            if (!in_array($column, $cols, true)) {
                $pdo->exec($sql);
            }
        } catch (Throwable) {
            // Phase 1 tables may not exist yet; api.php creates them before writes.
        }
    }

    public static function permissionMap(): array
    {
        return [
            'notes.create' => ['Create notes', 'Notes'],
            'notes.edit.own' => ['Edit own notes', 'Notes'],
            'notes.edit.any' => ['Edit any note', 'Notes'],
            'notes.delete.own' => ['Delete own notes', 'Notes'],
            'notes.delete.any' => ['Delete any note', 'Notes'],
            'notes.view.private' => ['View private notes', 'Notes'],
            'folders.create' => ['Create folders', 'Folders'],
            'folders.manage' => ['Manage folders', 'Folders'],
            'folders.delete' => ['Delete folders', 'Folders'],
            'share.create' => ['Create share links', 'Sharing'],
            'share.manage' => ['Manage shares', 'Sharing'],
            'collab.invite' => ['Invite collaborators', 'Sharing'],
            'users.view' => ['View users', 'Admin'],
            'users.invite' => ['Invite users', 'Admin'],
            'users.edit' => ['Edit users', 'Admin'],
            'users.deactivate' => ['Deactivate users', 'Admin'],
            'roles.manage' => ['Manage roles', 'Admin'],
            'system.settings' => ['System settings', 'Admin'],
            'admin.backup' => ['Backup workspace', 'Admin'],
        ];
    }

    private static function seedRolesAndPermissions(PDO $pdo): void
    {
        $now = date('c');
        foreach (self::permissionMap() as $key => [$label, $group]) {
            $pdo->prepare('INSERT OR IGNORE INTO permissions(id, perm_key, label, group_name) VALUES (?, ?, ?, ?)')
                ->execute([$key, $key, $label, $group]);
        }
        $roles = ['owner' => 'Owner', 'admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'];
        foreach ($roles as $id => $name) {
            $pdo->prepare('INSERT OR IGNORE INTO roles(id, name, is_system, created_at) VALUES (?, ?, 1, ?)')
                ->execute([$id, $name, $now]);
        }
        $all = array_keys(self::permissionMap());
        $sets = [
            'owner' => $all,
            'admin' => $all,
            'editor' => ['notes.create', 'notes.edit.own', 'notes.delete.own', 'folders.create', 'share.create', 'collab.invite'],
            'viewer' => [],
        ];
        foreach ($sets as $role => $perms) {
            foreach ($perms as $perm) {
                $pdo->prepare('INSERT OR IGNORE INTO role_permissions(role_id, perm_key) VALUES (?, ?)')
                    ->execute([$role, $perm]);
            }
        }
    }

    public static function hasUsers(): bool
    {
        return (int) self::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    }

    public static function boot(): ?array
    {
        if (defined('AUTH_ENABLED') && AUTH_ENABLED === false) {
            self::$user = ['id' => 'system', 'username' => 'owner', 'display_name' => 'Owner', 'role_id' => 'owner', 'is_active' => 1];
            return self::$user;
        }
        $token = $_COOKIE['ih4x_session'] ?? '';
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            $token = $m[1];
        }
        if ($token === '') {
            return null;
        }
        $stmt = self::pdo()->prepare('SELECT u.* FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.token = ? AND s.expires_at > ? AND u.is_active = 1');
        $stmt->execute([$token, date('c')]);
        $user = $stmt->fetch() ?: null;
        if (!$user) {
            return null;
        }
        self::$user = $user;
        self::touchLastSeen();
        return self::$user;
    }

    public static function guardRequest(): void
    {
        $action = (string) ($_GET['action'] ?? '');
        $r = trim((string) ($_GET['r'] ?? ''), '/');
        $public = in_array($action, ['login', 'setup.status'], true) || in_array($r, ['csrf'], true);
        if ($public || (defined('AUTH_ENABLED') && AUTH_ENABLED === false)) {
            self::boot();
            return;
        }
        if (!self::hasUsers()) {
            self::jsonFail(428, 'setup_required', 'Setup is required.');
        }
        if (!self::boot()) {
            self::jsonFail(401, 'auth_required', 'Session expired. Please sign in again.');
        }
    }

    public static function requirePerm(string $perm): void
    {
        if (!self::$user) {
            self::boot();
        }
        if (!self::$user || !self::can($perm)) {
            if (PHP_SAPI !== 'cli' && str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
                self::jsonFail(403, 'forbidden', 'Permission denied.');
            }
            http_response_code(403);
            exit('Permission denied.');
        }
    }

    public static function can(string $perm): bool
    {
        if (!self::$user) {
            return false;
        }
        if ((self::$user['role_id'] ?? '') === 'owner') {
            return true;
        }
        $pdo = self::pdo();
        $stmt = $pdo->prepare('SELECT granted FROM user_permissions WHERE user_id = ? AND perm_key = ?');
        $stmt->execute([self::$user['id'], $perm]);
        $override = $stmt->fetchColumn();
        if ($override !== false) {
            return (bool) $override;
        }
        $stmt = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = ? AND perm_key = ?');
        $stmt->execute([self::$user['role_id'], $perm]);
        return (bool) $stmt->fetchColumn();
    }

    public static function login(string $username, string $password, bool $remember): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch() ?: null;
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return null;
        }
        session_regenerate_id(true);
        $token = bin2hex(random_bytes(32));
        $ttl = $remember ? 60 * 60 * 24 * 30 : (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400);
        $expires = date('c', time() + $ttl);
        self::pdo()->prepare('INSERT INTO sessions(id, user_id, token, ip, user_agent, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([bin2hex(random_bytes(8)), $user['id'], $token, (string) ($_SERVER['REMOTE_ADDR'] ?? ''), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $expires, date('c')]);
        setcookie('ih4x_session', $token, ['expires' => time() + $ttl, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        self::$user = $user;
        self::writeLog('auth.login', 'user', (string) $user['id'], []);
        return $user;
    }

    public static function logout(): void
    {
        $token = $_COOKIE['ih4x_session'] ?? '';
        if ($token !== '') {
            self::pdo()->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
        }
        setcookie('ih4x_session', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        self::$user = null;
    }

    public static function touchLastSeen(): void
    {
        if (!self::$user || (time() - (int) ($_SESSION['last_seen_touch'] ?? 0) < 60)) {
            return;
        }
        $_SESSION['last_seen_touch'] = time();
        self::pdo()->prepare('UPDATE users SET last_seen = ? WHERE id = ?')->execute([date('c'), self::$user['id']]);
    }

    public static function writeLog(string $action, string $type = '', string $id = '', array $meta = []): void
    {
        try {
            self::pdo()->prepare('INSERT INTO activity_log(user_id, action, entity_type, entity_id, meta_json, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([self::$user['id'] ?? null, $action, $type, $id, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', date('c')]);
        } catch (Throwable) {}
    }

    public static function writeChange(string $type, string $entityType, string $entityId, array $payload = []): void
    {
        try {
            self::pdo()->exec("
                CREATE TABLE IF NOT EXISTS changes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    type TEXT NOT NULL,
                    entity_type TEXT NOT NULL,
                    entity_id TEXT NOT NULL,
                    actor_id TEXT NULL,
                    payload_json TEXT NOT NULL DEFAULT '{}',
                    created_at TEXT NOT NULL
                )
            ");
            self::pdo()->prepare('INSERT INTO changes(type, entity_type, entity_id, actor_id, payload_json, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$type, $entityType, $entityId, self::$user['id'] ?? null, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', date('c')]);
        } catch (Throwable) {}
    }

    public static function userPayload(): ?array
    {
        if (!self::$user) {
            return null;
        }
        return [
            'id' => self::$user['id'],
            'username' => self::$user['username'],
            'display_name' => self::$user['display_name'],
            'avatar_type' => self::$user['avatar_type'] ?? 'initials',
            'avatar_data' => self::$user['avatar_data'] ?? '',
            'role_id' => self::$user['role_id'],
            'permissions' => self::effectivePermissions((string) self::$user['id'], (string) self::$user['role_id']),
        ];
    }

    public static function effectivePermissions(string $userId, string $roleId): array
    {
        $pdo = self::pdo();
        $perms = [];
        $stmt = $pdo->prepare('SELECT perm_key FROM role_permissions WHERE role_id = ?');
        $stmt->execute([$roleId]);
        foreach ($stmt->fetchAll() as $row) {
            $perms[(string) $row['perm_key']] = true;
        }
        $stmt = $pdo->prepare('SELECT perm_key, granted FROM user_permissions WHERE user_id = ?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            if ((int) $row['granted']) $perms[(string) $row['perm_key']] = true;
            else unset($perms[(string) $row['perm_key']]);
        }
        return array_keys($perms);
    }

    public static function jsonFail(int $status, string $code, string $message): void
    {
        if (function_exists('fail')) {
            fail($status, $code, $message);
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'code' => $code, 'message' => $message, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
