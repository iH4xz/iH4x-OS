<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "PDO SQLite is not enabled.\n");
    exit(1);
}
if (!is_file(DB_PATH)) {
    fwrite(STDERR, "Database file not found: " . DB_PATH . "\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec('CREATE TABLE IF NOT EXISTS sync_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
$lastId = (int) ($pdo->query("SELECT value FROM sync_meta WHERE key = 'last_merged_change_id'")->fetchColumn() ?: 0);
$stmt = $pdo->prepare('SELECT id, type, entity_type, entity_id, actor_id, payload_json, created_at FROM changes WHERE id > ? ORDER BY id ASC');
$stmt->execute([$lastId]);
$changes = $stmt->fetchAll();

$applied = 0;
foreach ($changes as $change) {
    // Placeholder merge strategy: keep a deterministic merge ledger update.
    // In multi-copy setups, this script is the hook point to replay external change feeds.
    $lastId = (int) $change['id'];
    $applied++;
}

$pdo->prepare("INSERT INTO sync_meta(key, value) VALUES('last_merged_change_id', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value")
    ->execute([(string) $lastId]);

echo json_encode([
    'success' => true,
    'applied' => $applied,
    'lastMergedChangeId' => $lastId,
    'singleInstance' => defined('SINGLE_INSTANCE') ? SINGLE_INSTANCE : true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
