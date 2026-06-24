<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/auth.php';
if (!Auth::boot()) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    echo "data: {\"type\":\"auth_error\"}\n\n";
    flush();
    exit;
}
$pdo = Auth::pdo();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);

$lastId = (int) ($_GET['last_id'] ?? 0);
$heartbeat = 0;
for (;;) {
    $stmt = $pdo->prepare('SELECT * FROM changes WHERE id > ? ORDER BY id ASC LIMIT 50');
    $stmt->execute([$lastId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $lastId = (int) $row['id'];
        $payload = [
            'id' => (int) $row['id'],
            'type' => $row['type'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'],
            'actor_id' => $row['actor_id'],
            'payload' => json_decode((string) ($row['payload_json'] ?? '{}'), true) ?: [],
            'created_at' => $row['created_at'],
        ];
        echo 'id: ' . $lastId . "\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
    $heartbeat++;
    if ($heartbeat >= 14) {
        echo ": heartbeat\n\n";
        $heartbeat = 0;
    }
    flush();
    if (connection_aborted()) {
        break;
    }
    sleep(1);
    usleep(500000);
}
