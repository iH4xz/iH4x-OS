<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/auth.php';
if (!Auth::boot()) { header('Location: login.php'); exit; }
Auth::requirePerm('admin.backup');
$pdo = Auth::pdo();

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZipArchive is not available.');
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ih4x-backup-' . date('Ymd-His') . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Unable to create backup.');
}

if (is_file(DB_PATH)) {
    $zip->addFile(DB_PATH, 'data/database.sqlite');
}
$attachmentsDir = __DIR__ . '/data/attachments';
if (is_dir($attachmentsDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($attachmentsDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $local = 'data/attachments/' . str_replace('\\', '/', $it->getSubPathName());
        $zip->addFile($file->getPathname(), $local);
    }
}

$folders = $pdo->query('SELECT id, parent_id, name FROM folders')->fetchAll();
$folderMap = [];
foreach ($folders as $f) $folderMap[$f['id']] = $f;
$notes = $pdo->query('SELECT id, folder_id, title, content FROM notes WHERE deleted_at IS NULL ORDER BY updated_at DESC')->fetchAll();
foreach ($notes as $note) {
    $parts = [];
    $fid = $note['folder_id'];
    $guard = 0;
    while ($fid && isset($folderMap[$fid]) && $guard++ < 60) {
        array_unshift($parts, preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $folderMap[$fid]['name']) ?: 'folder');
        $fid = $folderMap[$fid]['parent_id'];
    }
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $note['title']) ?: $note['id'];
    $path = 'export/' . ($parts ? implode('/', $parts) . '/' : '') . $name . '.md';
    $markdown = "# " . $note['title'] . "\n\n" . html_entity_decode(strip_tags((string) $note['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $zip->addFromString($path, $markdown);
}

$zip->close();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="ih4x-workspace-' . date('Ymd-His') . '.zip"');
header('Content-Length: ' . (string) filesize($tmp));
readfile($tmp);
@unlink($tmp);
