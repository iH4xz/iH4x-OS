<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!extension_loaded('pdo_sqlite')) {
    http_response_code(500);
    exit("PDO SQLite is not enabled.\n");
}

if (!is_dir(dirname(DB_PATH))) {
    @mkdir(dirname(DB_PATH), 0777, true);
}

$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$ensureColumn = static function (string $table, string $column, string $sql) use ($pdo): void {
    try {
        $cols = array_map(static fn($row) => (string) $row['name'], $pdo->query("PRAGMA table_info($table)")->fetchAll());
        if (!in_array($column, $cols, true)) {
            $pdo->exec($sql);
        }
    } catch (Throwable) {}
};

$ensureColumn('notes', 'created_by', 'ALTER TABLE notes ADD COLUMN created_by TEXT NULL');
$ensureColumn('folders', 'created_by', 'ALTER TABLE folders ADD COLUMN created_by TEXT NULL');

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

$permissions = [
    'notes.create' => ['Create notes', 'Notes'], 'notes.edit.own' => ['Edit own notes', 'Notes'],
    'notes.edit.any' => ['Edit any note', 'Notes'], 'notes.delete.own' => ['Delete own notes', 'Notes'],
    'notes.delete.any' => ['Delete any note', 'Notes'], 'notes.view.private' => ['View private notes', 'Notes'],
    'folders.create' => ['Create folders', 'Folders'], 'folders.manage' => ['Manage folders', 'Folders'],
    'folders.delete' => ['Delete folders', 'Folders'], 'share.create' => ['Create share links', 'Sharing'],
    'share.manage' => ['Manage shares', 'Sharing'], 'collab.invite' => ['Invite collaborators', 'Sharing'],
    'users.view' => ['View users', 'Admin'], 'users.invite' => ['Invite users', 'Admin'],
    'users.edit' => ['Edit users', 'Admin'], 'users.deactivate' => ['Deactivate users', 'Admin'],
    'roles.manage' => ['Manage roles', 'Admin'], 'system.settings' => ['System settings', 'Admin'],
    'admin.backup' => ['Backup workspace', 'Admin'],
];
$now = date('c');
foreach ($permissions as $key => [$label, $group]) {
    $pdo->prepare('INSERT OR IGNORE INTO permissions(id, perm_key, label, group_name) VALUES (?, ?, ?, ?)')->execute([$key, $key, $label, $group]);
}
foreach (['owner' => 'Owner', 'admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'] as $id => $name) {
    $pdo->prepare('INSERT OR IGNORE INTO roles(id, name, is_system, created_at) VALUES (?, ?, 1, ?)')->execute([$id, $name, $now]);
}
$sets = [
    'owner' => array_keys($permissions),
    'admin' => array_keys($permissions),
    'editor' => ['notes.create', 'notes.edit.own', 'notes.delete.own', 'folders.create', 'share.create', 'collab.invite'],
    'viewer' => [],
];
foreach ($sets as $role => $perms) {
    foreach ($perms as $perm) {
        $pdo->prepare('INSERT OR IGNORE INTO role_permissions(role_id, perm_key) VALUES (?, ?)')->execute([$role, $perm]);
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Phase 2 migration complete.'], JSON_UNESCAPED_UNICODE) . "\n";
