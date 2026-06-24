<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
if (!extension_loaded('pdo_sqlite')) exit("PDO SQLite is not enabled.\n");
if (!is_dir(dirname(DB_PATH))) @mkdir(dirname(DB_PATH), 0777, true);
$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("
CREATE TABLE IF NOT EXISTS note_shares (
    note_id TEXT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    access_level TEXT NOT NULL DEFAULT 'view',
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (note_id, user_id)
);
CREATE TABLE IF NOT EXISTS folder_shares (
    folder_id TEXT NOT NULL REFERENCES folders(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    access_level TEXT NOT NULL DEFAULT 'view',
    created_by TEXT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (folder_id, user_id)
);
CREATE TABLE IF NOT EXISTS share_tokens (
    id TEXT PRIMARY KEY,
    entity_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE,
    access_level TEXT NOT NULL DEFAULT 'view',
    expires_at TEXT NULL,
    created_by TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS presence (
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    entity_type TEXT NOT NULL DEFAULT 'note',
    entity_id TEXT NOT NULL,
    action TEXT NOT NULL DEFAULT 'viewing',
    last_ping TEXT NOT NULL,
    PRIMARY KEY (user_id, entity_id)
);
CREATE TABLE IF NOT EXISTS changes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT NOT NULL,
    actor_id TEXT NULL,
    payload_json TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS note_links (
    source_id TEXT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    target_id TEXT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    PRIMARY KEY (source_id, target_id)
);
CREATE TABLE IF NOT EXISTS mentions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    note_id TEXT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_changes_created ON changes(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_presence_entity ON presence(entity_id);
");
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Phase 3 migration complete.'], JSON_UNESCAPED_UNICODE) . "\n";
