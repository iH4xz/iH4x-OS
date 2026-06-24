<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/actions/users.php';
require_once __DIR__ . '/lib/actions/roles.php';
require_once __DIR__ . '/lib/actions/sharing.php';
require_once __DIR__ . '/lib/actions/presence.php';

$baseDir = __DIR__;
$dataDir = $baseDir . '/data';
$attachmentsDir = $dataDir . '/attachments';
$legacyNotesDir = $baseDir . '/notes';
$legacyTrashDir = $baseDir . '/trash';
$legacyVersionsDir = $baseDir . '/versions';
$legacyUploadsDir = $baseDir . '/uploads';
$legacyMetaFile = $baseDir . '/metadata.json';
$legacySettingsFile = $baseDir . '/settings.json';
$dbPath = defined('DB_PATH') ? DB_PATH : $dataDir . '/database.sqlite';

foreach ([$dataDir, $attachmentsDir, $legacyNotesDir, $legacyTrashDir, $legacyVersionsDir, $legacyUploadsDir] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $payload += ['success' => $status >= 200 && $status < 300];
    $payload['csrfToken'] = csrfToken();
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(int $status, string $code, string $message): void
{
    respond($status, ['success' => false, 'code' => $code, 'message' => $message, 'error' => $message]);
}

function requireCsrf(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    $header = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($header === '' || !hash_equals(csrfToken(), $header)) {
        fail(403, 'csrf_required', 'Missing or invalid CSRF token.');
    }
}

function db(): PDO
{
    static $pdo = null;
    static $lockHandle = null;
    global $dbPath;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!extension_loaded('pdo_sqlite')) {
        fail(500, 'sqlite_unavailable', 'PDO SQLite is not enabled in this PHP installation.');
    }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    if (defined('SINGLE_INSTANCE') && SINGLE_INSTANCE === false) {
        $lockPath = dirname($dbPath) . '/write.lock';
        if (!is_file($lockPath)) @touch($lockPath);
        $lockHandle = @fopen($lockPath, 'c+');
        if ($lockHandle) {
            @flock($lockHandle, LOCK_EX);
            register_shutdown_function(static function () use (&$lockHandle): void {
                if ($lockHandle) {
                    @flock($lockHandle, LOCK_UN);
                    @fclose($lockHandle);
                    $lockHandle = null;
                }
            });
        }
    }
    ensureSchema($pdo);
    migrateLegacyJson($pdo);
    return $pdo;
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS folders (
            id TEXT PRIMARY KEY,
            parent_id TEXT NULL REFERENCES folders(id) ON DELETE SET NULL,
            name TEXT NOT NULL,
            color TEXT NOT NULL DEFAULT 'slate',
            icon TEXT NOT NULL DEFAULT 'folder',
            position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS notes (
            id TEXT PRIMARY KEY,
            folder_id TEXT NULL REFERENCES folders(id) ON DELETE SET NULL,
            title TEXT NOT NULL DEFAULT 'Untitled',
            content TEXT NOT NULL DEFAULT '',
            snippet TEXT NOT NULL DEFAULT '',
            color TEXT NOT NULL DEFAULT 'slate',
            pinned INTEGER NOT NULL DEFAULT 0,
            favorited INTEGER NOT NULL DEFAULT 0,
            archived INTEGER NOT NULL DEFAULT 0,
            deleted_at TEXT NULL,
            word_count INTEGER NOT NULL DEFAULT 0,
            position INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_notes_folder ON notes(folder_id);
        CREATE INDEX IF NOT EXISTS idx_notes_deleted ON notes(deleted_at);
        CREATE TABLE IF NOT EXISTS note_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            note_id TEXT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
            content TEXT NOT NULL,
            snapshot_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        );
        CREATE TABLE IF NOT EXISTS note_tags (
            note_id TEXT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
            tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
            PRIMARY KEY (note_id, tag_id)
        );
        CREATE TABLE IF NOT EXISTS attachments (
            id TEXT PRIMARY KEY,
            note_id TEXT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
            filename TEXT NOT NULL,
            stored_name TEXT NOT NULL,
            mime TEXT NOT NULL,
            size INTEGER NOT NULL,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
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
    ");
    ensureColumn($pdo, 'notes', 'folder_id', "ALTER TABLE notes ADD COLUMN folder_id TEXT NULL REFERENCES folders(id) ON DELETE SET NULL");
    ensureColumn($pdo, 'notes', 'content', "ALTER TABLE notes ADD COLUMN content TEXT NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'notes', 'snippet', "ALTER TABLE notes ADD COLUMN snippet TEXT NOT NULL DEFAULT ''");
    ensureColumn($pdo, 'notes', 'favorited', "ALTER TABLE notes ADD COLUMN favorited INTEGER NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'notes', 'deleted_at', "ALTER TABLE notes ADD COLUMN deleted_at TEXT NULL");
    ensureColumn($pdo, 'notes', 'word_count', "ALTER TABLE notes ADD COLUMN word_count INTEGER NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'notes', 'position', "ALTER TABLE notes ADD COLUMN position INTEGER NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'notes', 'created_by', "ALTER TABLE notes ADD COLUMN created_by TEXT NULL");
    ensureColumn($pdo, 'folders', 'position', "ALTER TABLE folders ADD COLUMN position INTEGER NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'folders', 'created_by', "ALTER TABLE folders ADD COLUMN created_by TEXT NULL");
}

function ensureColumn(PDO $pdo, string $table, string $column, string $sql): void
{
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $cols = array_map(static fn($row) => (string) $row['name'], $stmt->fetchAll());
    if (!in_array($column, $cols, true)) {
        $pdo->exec($sql);
    }
}

function migrateLegacyJson(PDO $pdo): void
{
    global $legacyMetaFile, $legacySettingsFile, $legacyNotesDir, $legacyTrashDir, $legacyVersionsDir;
    $count = (int) $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();
    if ($count === 0 && is_file($legacyMetaFile)) {
        $raw = file_get_contents($legacyMetaFile);
        $meta = $raw === false ? [] : json_decode($raw, true);
        if (is_array($meta)) {
            $pdo->beginTransaction();
            try {
                foreach ($meta as $id => $rec) {
        if (!is_array($rec)) {
            continue;
        }
                    $id = normalizeId((string) ($rec['id'] ?? $id)) ?? bin2hex(random_bytes(8));
                    $deletedAt = empty($rec['deletedAt']) ? null : (string) $rec['deletedAt'];
                    $content = readLegacyNote($id, $deletedAt !== null);
                    upsertNote($pdo, [
            'id' => $id,
                        'folder_id' => null,
                        'title' => normalizeTitle((string) ($rec['title'] ?? 'Untitled')),
                        'content' => $content,
                        'snippet' => plainSnippet($content),
                        'color' => cleanToken((string) ($rec['color'] ?? 'slate'), 'slate'),
                        'pinned' => !empty($rec['pinned']) ? 1 : 0,
                        'favorited' => !empty($rec['favorite']) || !empty($rec['favorited']) ? 1 : 0,
                        'archived' => !empty($rec['archived']) ? 1 : 0,
                        'deleted_at' => $deletedAt,
                        'word_count' => wordCount($content),
                        'position' => (int) ($rec['order'] ?? 0),
                        'created_at' => (string) ($rec['createdAt'] ?? date('c')),
                        'updated_at' => (string) ($rec['updatedAt'] ?? date('c')),
                    ]);
                    replaceTags($pdo, $id, is_array($rec['tags'] ?? null) ? $rec['tags'] : []);
                    $versionDir = $legacyVersionsDir . DIRECTORY_SEPARATOR . $id;
                    foreach (glob($versionDir . DIRECTORY_SEPARATOR . '*.html') ?: [] as $file) {
                        $contentVersion = file_get_contents($file);
                        if ($contentVersion !== false) {
                            insertVersion($pdo, $id, $contentVersion, legacyVersionTs(basename($file, '.html')));
                        }
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }

    $existing = $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    if ((int) $existing === 0) {
        $settings = ['language' => 'en', 'theme' => 'dark'];
        if (is_file($legacySettingsFile)) {
            $raw = file_get_contents($legacySettingsFile);
            $decoded = $raw === false ? null : json_decode($raw, true);
            if (is_array($decoded)) {
                $settings = array_merge($settings, array_intersect_key($decoded, $settings));
            }
        }
        foreach ($settings as $key => $value) {
            setSetting($pdo, $key, (string) $value);
        }
    }
    backfillLegacyContent($pdo);
}

function backfillLegacyContent(PDO $pdo): void
{
    $rows = $pdo->query("SELECT id, deleted_at, content FROM notes WHERE content = '' OR snippet = ''")->fetchAll();
    foreach ($rows as $row) {
        $content = readLegacyNote((string) $row['id'], !empty($row['deleted_at']));
        if ($content === '') {
            continue;
        }
        $pdo->prepare('UPDATE notes SET content = ?, snippet = ?, word_count = ? WHERE id = ?')->execute([
            $content,
            plainSnippet($content),
            wordCount($content),
            (string) $row['id'],
        ]);
    }
}

function readLegacyNote(string $id, bool $fromTrash = false): string
{
    global $legacyNotesDir, $legacyTrashDir;
    $dir = $fromTrash ? $legacyTrashDir : $legacyNotesDir;
    foreach ([$dir . DIRECTORY_SEPARATOR . $id . '.html', $dir . DIRECTORY_SEPARATOR . $id . '.md'] as $path) {
        if (is_file($path)) {
            $content = file_get_contents($path);
            return $content === false ? '' : $content;
        }
    }
    return '';
}

function legacyVersionTs(string $ts): string
{
    $m = [];
    if (preg_match('/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})-(\d{3})$/', $ts, $m)) {
        return sprintf('%s-%s-%sT%s:%s:%s.%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6], $m[7]);
    }
    return date('c');
}

function normalizeId(?string $id): ?string
{
    if ($id === null || $id === '') {
    return null;
    }
    return preg_match('/^[a-zA-Z0-9_-]+$/', $id) ? $id : null;
}

function normalizeTitle(string $title): string
{
    $title = trim($title);
    return mb_substr($title !== '' ? $title : 'Untitled', 0, 200);
}

function cleanToken(string $token, string $fallback = ''): string
{
    $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $token) ?: '';
    return $clean !== '' ? $clean : $fallback;
}

function cleanTags(array $tags): array
{
    $out = [];
    foreach ($tags as $tag) {
        $tag = trim((string) $tag);
        $tag = preg_replace('/[^\p{L}\p{N}_\- ]/u', '', $tag) ?: '';
        $tag = mb_substr($tag, 0, 32);
        if ($tag !== '') {
            $out[$tag] = true;
        }
    }
    return array_keys($out);
}

function plainSnippet(string $html, int $len = 240): string
{
    $text = preg_replace('/<style.*?<\/style>/is', '', $html);
    $text = preg_replace('/<script.*?<\/script>/is', '', (string) $text);
    $text = strip_tags((string) $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', (string) $text);
    $text = trim((string) $text);
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}

function wordCount(string $html): int
{
    $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
}

function jsonInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw ?: 'null', true);
    return is_array($data) ? $data : [];
}

function upsertNote(PDO $pdo, array $note): void
{
    $stmt = $pdo->prepare("
        INSERT INTO notes (id, folder_id, title, content, snippet, color, pinned, favorited, archived, deleted_at, word_count, position, created_at, updated_at)
        VALUES (:id, :folder_id, :title, :content, :snippet, :color, :pinned, :favorited, :archived, :deleted_at, :word_count, :position, :created_at, :updated_at)
        ON CONFLICT(id) DO UPDATE SET
            folder_id = excluded.folder_id,
            title = excluded.title,
            content = excluded.content,
            snippet = excluded.snippet,
            color = excluded.color,
            pinned = excluded.pinned,
            favorited = excluded.favorited,
            archived = excluded.archived,
            deleted_at = excluded.deleted_at,
            word_count = excluded.word_count,
            position = excluded.position,
            created_at = excluded.created_at,
            updated_at = excluded.updated_at
    ");
    $stmt->execute($note);
}

function getNote(PDO $pdo, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function noteMeta(PDO $pdo, array $row, bool $withContent = false): array
{
    $tags = noteTags($pdo, (string) $row['id']);
    $meta = [
        'id' => $row['id'],
        'folderId' => $row['folder_id'],
        'folder_id' => $row['folder_id'],
        'title' => $row['title'],
        'snippet' => $row['snippet'],
        'tags' => $tags,
        'color' => $row['color'],
        'pinned' => (bool) $row['pinned'],
        'favorite' => (bool) $row['favorited'],
        'favorited' => (bool) $row['favorited'],
        'archived' => (bool) $row['archived'],
        'deletedAt' => $row['deleted_at'],
        'deleted_at' => $row['deleted_at'],
        'wordCount' => (int) $row['word_count'],
        'order' => (int) $row['position'],
        'position' => (int) $row['position'],
        'createdAt' => $row['created_at'],
        'created_at' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
        'updated_at' => $row['updated_at'],
        'date' => date('M d, Y', strtotime((string) $row['updated_at']) ?: time()),
    ];
    if ($withContent) {
        $meta['content'] = renderInternalLinks((string) $row['content']);
    }
    return $meta;
}

function noteTags(PDO $pdo, string $noteId): array
{
    $stmt = $pdo->prepare('SELECT t.name FROM tags t JOIN note_tags nt ON nt.tag_id = t.id WHERE nt.note_id = ? ORDER BY t.name COLLATE NOCASE');
    $stmt->execute([$noteId]);
    return array_map(static fn($r) => (string) $r['name'], $stmt->fetchAll());
}

function replaceTags(PDO $pdo, string $noteId, array $tags): void
{
    $tags = cleanTags($tags);
    $pdo->prepare('DELETE FROM note_tags WHERE note_id = ?')->execute([$noteId]);
    foreach ($tags as $tag) {
        $pdo->prepare('INSERT OR IGNORE INTO tags(name) VALUES (?)')->execute([$tag]);
        $tagId = (int) $pdo->lastInsertId();
        if ($tagId === 0) {
            $stmt = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
            $stmt->execute([$tag]);
            $tagId = (int) $stmt->fetchColumn();
        }
        $pdo->prepare('INSERT OR IGNORE INTO note_tags(note_id, tag_id) VALUES (?, ?)')->execute([$noteId, $tagId]);
    }
}

function insertVersion(PDO $pdo, string $noteId, string $content, ?string $snapshotAt = null): void
{
    $snapshotAt = $snapshotAt ?: date('c');
    $pdo->prepare('INSERT INTO note_versions(note_id, content, snapshot_at) VALUES (?, ?, ?)')->execute([$noteId, $content, $snapshotAt]);
    $ids = $pdo->prepare('SELECT id FROM note_versions WHERE note_id = ? ORDER BY snapshot_at DESC, id DESC LIMIT -1 OFFSET 20');
    $ids->execute([$noteId]);
    foreach ($ids->fetchAll() as $row) {
        $pdo->prepare('DELETE FROM note_versions WHERE id = ?')->execute([(int) $row['id']]);
    }
}

function getSettings(PDO $pdo): array
{
    $settings = ['language' => 'en', 'theme' => 'dark'];
    foreach ($pdo->query('SELECT key, value FROM settings') as $row) {
        $settings[(string) $row['key']] = (string) $row['value'];
    }
    return $settings;
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
}

function folderRows(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM folders ORDER BY parent_id IS NOT NULL, parent_id, position, name COLLATE NOCASE')->fetchAll();
}

function folderDescendants(PDO $pdo, ?string $folderId): array
{
    if ($folderId === null || $folderId === '') {
        return [];
    }
    $rows = folderRows($pdo);
    $children = [];
    foreach ($rows as $row) {
        $children[(string) ($row['parent_id'] ?? '')][] = (string) $row['id'];
    }
    $ids = [$folderId];
    for ($i = 0; $i < count($ids); $i++) {
        foreach ($children[$ids[$i]] ?? [] as $child) {
            $ids[] = $child;
        }
    }
    return $ids;
}

function folderPath(PDO $pdo, ?string $folderId): array
{
    if (!$folderId) {
        return [];
    }
    $path = [];
    $guard = 0;
    while ($folderId && $guard++ < 80) {
        $stmt = $pdo->prepare('SELECT id, parent_id, name, color, icon FROM folders WHERE id = ?');
        $stmt->execute([$folderId]);
        $row = $stmt->fetch();
        if (!$row) {
            break;
        }
        array_unshift($path, ['id' => $row['id'], 'name' => $row['name'], 'color' => $row['color'], 'icon' => $row['icon']]);
        $folderId = $row['parent_id'] ?: null;
    }
    return $path;
}

function folderCounts(PDO $pdo): array
{
    $rows = folderRows($pdo);
    $counts = [];
    foreach ($rows as $row) {
        $ids = folderDescendants($pdo, (string) $row['id']);
        if (!$ids) {
            $counts[(string) $row['id']] = 0;
            continue;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE deleted_at IS NULL AND archived = 0 AND folder_id IN ($placeholders)");
        $stmt->execute($ids);
        $counts[(string) $row['id']] = (int) $stmt->fetchColumn();
    }
    return $counts;
}

function folderTree(PDO $pdo): array
{
    $rows = folderRows($pdo);
    $counts = folderCounts($pdo);
    $byParent = [];
    foreach ($rows as $row) {
        $row['count'] = $counts[(string) $row['id']] ?? 0;
        $row['children'] = [];
        $byParent[(string) ($row['parent_id'] ?? '')][] = $row;
    }
    $build = function (?string $parentId) use (&$build, &$byParent): array {
        $key = (string) ($parentId ?? '');
        $out = [];
        foreach ($byParent[$key] ?? [] as $row) {
            $out[] = [
                'id' => $row['id'],
                'parentId' => $row['parent_id'],
                'parent_id' => $row['parent_id'],
                'name' => $row['name'],
                'color' => $row['color'],
                'icon' => $row['icon'],
                'position' => (int) $row['position'],
                'count' => (int) $row['count'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
                'children' => $build((string) $row['id']),
            ];
        }
        return $out;
    };
    return $build(null);
}

function listNotes(PDO $pdo, array $params): array
{
    $view = (string) ($params['view'] ?? 'all');
    $sort = (string) ($params['sort'] ?? 'updated');
    $q = trim((string) ($params['q'] ?? ''));
    $tag = isset($params['tag']) ? (string) $params['tag'] : null;
    $folderId = normalizeId(isset($params['folder']) ? (string) $params['folder'] : (isset($params['folder_id']) ? (string) $params['folder_id'] : null));

    $where = [];
    $args = [];
    if ($view === 'trash') {
        $where[] = 'n.deleted_at IS NOT NULL';
    } else {
        $where[] = 'n.deleted_at IS NULL';
        if ($view === 'archive') {
            $where[] = 'n.archived = 1';
        } else {
            $where[] = 'n.archived = 0';
        }
        if ($view === 'pinned') {
            $where[] = 'n.pinned = 1';
        }
        if ($view === 'favorites') {
            $where[] = 'n.favorited = 1';
        }
    }
    if ($folderId !== null && !in_array($view, ['trash', 'archive'], true)) {
        $folderIds = folderDescendants($pdo, $folderId);
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        $where[] = 'n.folder_id IN (' . $placeholders . ')';
        array_push($args, ...$folderIds);
    }
    if ($tag !== null && $tag !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM note_tags nt JOIN tags t ON t.id = nt.tag_id WHERE nt.note_id = n.id AND t.name = ?)';
        $args[] = $tag;
    }
    if ($q !== '') {
        $where[] = '(LOWER(n.title) LIKE ? OR LOWER(n.snippet) LIKE ? OR LOWER(n.content) LIKE ?)';
        $needle = '%' . mb_strtolower($q) . '%';
        array_push($args, $needle, $needle, $needle);
    }
    if ($view === 'mentions' && Auth::$user) {
        $where[] = 'EXISTS (SELECT 1 FROM mentions m WHERE m.note_id = n.id AND m.user_id = ?)';
        $args[] = Auth::$user['id'];
    }

    $order = match ($sort) {
        'created' => 'n.created_at DESC',
        'title' => 'n.title COLLATE NOCASE ASC',
        'length' => 'n.word_count DESC',
        default => 'n.pinned DESC, n.position ASC, n.updated_at DESC',
    };
    $stmt = $pdo->prepare('SELECT n.* FROM notes n WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order);
    $stmt->execute($args);
    return array_map(static function ($row) use ($pdo) {
        $meta = noteMeta($pdo, $row);
        if (Auth::$user) {
            $s = $pdo->prepare('SELECT 1 FROM note_shares WHERE note_id = ? AND user_id = ?');
            $s->execute([$row['id'], Auth::$user['id']]);
            $meta['isShared'] = (bool) $s->fetchColumn();
        } else {
            $meta['isShared'] = false;
        }
        return $meta;
    }, $stmt->fetchAll());
}

function counts(PDO $pdo): array
{
    $sql = [
        'all' => "deleted_at IS NULL AND archived = 0",
        'pinned' => "deleted_at IS NULL AND archived = 0 AND pinned = 1",
        'favorites' => "deleted_at IS NULL AND archived = 0 AND favorited = 1",
        'archive' => "deleted_at IS NULL AND archived = 1",
        'trash' => "deleted_at IS NOT NULL",
    ];
    $out = [];
    foreach ($sql as $key => $where) {
        $out[$key] = (int) $pdo->query("SELECT COUNT(*) FROM notes WHERE $where")->fetchColumn();
    }
    return $out;
}

function resolveWikiLinks(PDO $pdo, string $content): string
{
    return preg_replace_callback('/\[\[([^\]\[]+)\]\]/u', static function (array $m) use ($pdo): string {
        $title = trim($m[1]);
        $stmt = $pdo->prepare('SELECT id, title FROM notes WHERE deleted_at IS NULL AND LOWER(title) = LOWER(?) LIMIT 1');
        $stmt->execute([$title]);
        $row = $stmt->fetch();
        if (!$row) {
            return '<span class="broken-internal-link" data-missing-title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">[[' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ']]</span>';
        }
        return '<a class="internal-note-link" href="ih4x://note/' . htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') . '" data-note-id="' . htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8') . '</a>';
    }, $content) ?? $content;
}

function renderInternalLinks(string $content): string
{
    $content = preg_replace('/href=("|\')ih4x:\/\/note\/([a-zA-Z0-9_-]+)\1/i', 'href="app.html#note/$2"', $content) ?? $content;
    $content = preg_replace('/ih4x:\/\/note\/([a-zA-Z0-9_-]+)/i', 'app.html#note/$1', $content) ?? $content;
    return $content;
}

function syncNoteLinks(PDO $pdo, string $sourceId, string $content): void
{
    $pdo->prepare('DELETE FROM note_links WHERE source_id = ?')->execute([$sourceId]);
    if (preg_match_all('/ih4x:\/\/note\/([a-zA-Z0-9_-]+)/i', $content, $m)) {
        foreach (array_unique($m[1]) as $targetId) {
            $tid = normalizeId((string) $targetId);
            if ($tid) {
                $pdo->prepare('INSERT OR IGNORE INTO note_links(source_id, target_id) VALUES (?, ?)')->execute([$sourceId, $tid]);
            }
        }
    }
}

function resolveMentions(PDO $pdo, string $noteId, string $content): string
{
    $pdo->prepare('DELETE FROM mentions WHERE note_id = ?')->execute([$noteId]);
    if (!preg_match_all('/(^|\\s)@([\\p{L}\\p{N}._-]{2,40})/u', strip_tags($content), $matches)) {
        return $content;
    }
    $now = date('c');
    foreach (array_unique($matches[2]) as $username) {
        $stmt = $pdo->prepare('SELECT id, display_name FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) continue;
        $pdo->prepare('INSERT INTO mentions(note_id, user_id, created_at) VALUES (?, ?, ?)')->execute([$noteId, $user['id'], $now]);
        $content = preg_replace('/(^|\\s)@' . preg_quote($username, '/') . '\\b/u', '$1<span class="mention" data-user-id="' . htmlspecialchars((string) $user['id'], ENT_QUOTES, 'UTF-8') . '">@' . htmlspecialchars((string) ($user['display_name'] ?: $username), ENT_QUOTES, 'UTF-8') . '</span>', $content) ?? $content;
    }
    return $content;
}

function saveNote(PDO $pdo, array $data): array
{
    $id = normalizeId(is_string($data['id'] ?? null) ? $data['id'] : null) ?? bin2hex(random_bytes(8));
    $existing = getNote($pdo, $id);
    $content = resolveWikiLinks($pdo, (string) ($data['content'] ?? ''));
    $content = resolveMentions($pdo, $id, $content);
    if ($existing && (string) $existing['content'] !== $content) {
        insertVersion($pdo, $id, (string) $existing['content']);
    }
    $now = date('c');
    $folderId = normalizeId(is_string($data['folder_id'] ?? null) ? $data['folder_id'] : (is_string($data['folderId'] ?? null) ? $data['folderId'] : null));
    if ($folderId === null && $existing) {
        $folderId = $existing['folder_id'] ?: null;
    }
    upsertNote($pdo, [
            'id' => $id,
        'folder_id' => $folderId,
        'title' => normalizeTitle((string) ($data['title'] ?? ($existing['title'] ?? 'Untitled'))),
        'content' => $content,
            'snippet' => plainSnippet($content),
        'color' => cleanToken((string) ($data['color'] ?? ($existing['color'] ?? 'slate')), 'slate'),
        'pinned' => $existing ? (int) $existing['pinned'] : 0,
        'favorited' => $existing ? (int) $existing['favorited'] : 0,
        'archived' => $existing ? (int) $existing['archived'] : 0,
        'deleted_at' => null,
        'word_count' => wordCount($content),
        'position' => $existing ? (int) $existing['position'] : 0,
        'created_at' => $existing ? (string) $existing['created_at'] : $now,
        'updated_at' => $now,
    ]);
    replaceTags($pdo, $id, is_array($data['tags'] ?? null) ? $data['tags'] : ($existing ? noteTags($pdo, $id) : []));
    if (!$existing && Auth::$user) {
        $pdo->prepare('UPDATE notes SET created_by = ? WHERE id = ?')->execute([Auth::$user['id'], $id]);
    }
    syncNoteLinks($pdo, $id, $content);
    Auth::writeLog('note.saved', 'note', $id, ['title' => (string) ($data['title'] ?? 'Untitled')]);
    Auth::writeChange($existing ? 'note.updated' : 'note.created', 'note', $id, []);
    $row = getNote($pdo, $id);
    return ['id' => $id, 'updatedAt' => $now, 'meta' => noteMeta($pdo, $row ?: [])];
}

function patchNote(PDO $pdo, array $data): array
{
        $id = normalizeId(is_string($data['id'] ?? null) ? $data['id'] : null);
    if ($id === null || !($row = getNote($pdo, $id))) {
        fail(400, 'invalid_note', 'Invalid note id.');
    }
    $sets = [];
    $args = [];
    foreach (['pinned' => 'pinned', 'favorite' => 'favorited', 'favorited' => 'favorited', 'archived' => 'archived'] as $key => $col) {
        if (array_key_exists($key, $data)) {
            $sets[$col] = "$col = ?";
            $args[$col] = !empty($data[$key]) ? 1 : 0;
        }
    }
    if (array_key_exists('color', $data)) {
        $sets['color'] = 'color = ?';
        $args['color'] = cleanToken((string) $data['color'], 'slate');
    }
    if (array_key_exists('title', $data)) {
        $sets['title'] = 'title = ?';
        $args['title'] = normalizeTitle((string) $data['title']);
    }
    if (array_key_exists('order', $data) || array_key_exists('position', $data)) {
        $sets['position'] = 'position = ?';
        $args['position'] = (int) ($data['position'] ?? $data['order']);
    }
    if (array_key_exists('folder_id', $data) || array_key_exists('folderId', $data)) {
        $sets['folder_id'] = 'folder_id = ?';
        $args['folder_id'] = normalizeId(is_string($data['folder_id'] ?? null) ? $data['folder_id'] : (is_string($data['folderId'] ?? null) ? $data['folderId'] : null));
    }
    if ($sets) {
        $sets['updated_at'] = 'updated_at = ?';
        $args['updated_at'] = date('c');
        $pdo->prepare('UPDATE notes SET ' . implode(', ', array_values($sets)) . ' WHERE id = ?')->execute([...array_values($args), $id]);
    }
    if (array_key_exists('tags', $data) && is_array($data['tags'])) {
        replaceTags($pdo, $id, $data['tags']);
    }
    Auth::writeLog('note.updated', 'note', $id, []);
    Auth::writeChange('note.updated', 'note', $id, []);
    return ['meta' => noteMeta($pdo, getNote($pdo, $id) ?: $row)];
}

function createFolder(PDO $pdo, array $data): array
{
    $now = date('c');
    $parentId = normalizeId(is_string($data['parent_id'] ?? null) ? $data['parent_id'] : (is_string($data['parentId'] ?? null) ? $data['parentId'] : null));
    $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM folders WHERE parent_id IS ?');
    $maxStmt->execute([$parentId]);
    $folder = [
        'id' => normalizeId(is_string($data['id'] ?? null) ? $data['id'] : null) ?? bin2hex(random_bytes(8)),
        'parent_id' => $parentId,
        'name' => normalizeTitle((string) ($data['name'] ?? 'New Folder')),
        'color' => cleanToken((string) ($data['color'] ?? 'slate'), 'slate'),
        'icon' => mb_substr(trim((string) ($data['icon'] ?? 'folder')) ?: 'folder', 0, 32),
        'position' => (int) $maxStmt->fetchColumn(),
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $pdo->prepare('INSERT INTO folders(id, parent_id, name, color, icon, position, created_at, updated_at) VALUES(:id, :parent_id, :name, :color, :icon, :position, :created_at, :updated_at)')->execute($folder);
    if (Auth::$user) {
        $pdo->prepare('UPDATE folders SET created_by = ? WHERE id = ?')->execute([Auth::$user['id'], $folder['id']]);
    }
    Auth::writeLog('folder.created', 'folder', (string) $folder['id'], ['name' => $folder['name']]);
    Auth::writeChange('folder.updated', 'folder', (string) $folder['id'], ['name' => $folder['name']]);
    return $folder;
}

function updateFolder(PDO $pdo, string $id, array $data): array
{
    $stmt = $pdo->prepare('SELECT * FROM folders WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        fail(404, 'folder_not_found', 'Folder not found.');
    }
    $sets = [];
    $args = [];
    if (array_key_exists('name', $data)) {
        $sets[] = 'name = ?';
        $args[] = normalizeTitle((string) $data['name']);
    }
    if (array_key_exists('color', $data)) {
        $sets[] = 'color = ?';
        $args[] = cleanToken((string) $data['color'], 'slate');
    }
    if (array_key_exists('icon', $data)) {
        $sets[] = 'icon = ?';
        $args[] = mb_substr(trim((string) $data['icon']) ?: 'folder', 0, 32);
    }
    if (array_key_exists('position', $data)) {
        $sets[] = 'position = ?';
        $args[] = (int) $data['position'];
    }
    if (array_key_exists('parent_id', $data) || array_key_exists('parentId', $data)) {
        $parentId = normalizeId(is_string($data['parent_id'] ?? null) ? $data['parent_id'] : (is_string($data['parentId'] ?? null) ? $data['parentId'] : null));
        if ($parentId === $id || ($parentId !== null && in_array($parentId, folderDescendants($pdo, $id), true))) {
            fail(400, 'invalid_folder_parent', 'A folder cannot be moved inside itself.');
        }
        $sets[] = 'parent_id = ?';
        $args[] = $parentId;
    }
    if ($sets) {
        $sets[] = 'updated_at = ?';
        $args[] = date('c');
        $args[] = $id;
        $pdo->prepare('UPDATE folders SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
    }
    Auth::writeLog('folder.updated', 'folder', $id, []);
    Auth::writeChange('folder.updated', 'folder', $id, []);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: $row;
}

function deleteFolder(PDO $pdo, string $id): void
{
    foreach (folderDescendants($pdo, $id) as $folderId) {
        $pdo->prepare('UPDATE notes SET folder_id = NULL, updated_at = ? WHERE folder_id = ?')->execute([date('c'), $folderId]);
    }
    $ids = array_reverse(folderDescendants($pdo, $id));
    foreach ($ids as $folderId) {
        $pdo->prepare('DELETE FROM folders WHERE id = ?')->execute([$folderId]);
    }
    Auth::writeLog('folder.deleted', 'folder', $id, []);
    Auth::writeChange('folder.updated', 'folder', $id, ['deleted' => true]);
}

function bulkOperation(PDO $pdo, array $ids, string $op): void
{
    $now = date('c');
    foreach ($ids as $rawId) {
        $id = normalizeId(is_string($rawId) ? $rawId : null);
        if (!$id || !getNote($pdo, $id)) {
            continue;
        }
        match ($op) {
            'archive' => $pdo->prepare('UPDATE notes SET archived = 1, updated_at = ? WHERE id = ?')->execute([$now, $id]),
            'unarchive' => $pdo->prepare('UPDATE notes SET archived = 0, updated_at = ? WHERE id = ?')->execute([$now, $id]),
            'favorite' => $pdo->prepare('UPDATE notes SET favorited = 1, updated_at = ? WHERE id = ?')->execute([$now, $id]),
            'unfavorite' => $pdo->prepare('UPDATE notes SET favorited = 0, updated_at = ? WHERE id = ?')->execute([$now, $id]),
            'pin' => $pdo->prepare('UPDATE notes SET pinned = 1, updated_at = ? WHERE id = ?')->execute([$now, $id]),
            'unpin' => $pdo->prepare('UPDATE notes SET pinned = 0, updated_at = ? WHERE id = ?')->execute([$now, $id]),
            'trash' => $pdo->prepare('UPDATE notes SET deleted_at = ?, updated_at = ? WHERE id = ?')->execute([$now, $now, $id]),
            'restore' => $pdo->prepare('UPDATE notes SET deleted_at = NULL, updated_at = ? WHERE id = ?')->execute([$now, $id]),
            'delete' => $pdo->prepare('DELETE FROM notes WHERE id = ?')->execute([$id]),
            default => null,
        };
        Auth::writeLog('note.' . $op, 'note', $id, []);
        Auth::writeChange('note.' . $op, 'note', $id, []);
    }
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$r = trim((string) ($_GET['r'] ?? ''), '/');
$action = (string) ($_GET['action'] ?? '');
$input = jsonInput();

Auth::guardRequest();

if ($method !== 'GET' && $method !== 'HEAD') {
    requireCsrf();
}

try {
    $pdo = db();

    if ($r === 'csrf') {
        respond(200, ['success' => true]);
    }

    if ($r !== '') {
        $parts = explode('/', $r);
        if ($parts[0] === 'notes') {
            if ($method === 'GET' && count($parts) === 1) {
                respond(200, ['notes' => listNotes($pdo, $_GET), 'counts' => counts($pdo), 'folders' => folderTree($pdo)]);
            }
            if ($method === 'GET' && isset($parts[1])) {
                $id = normalizeId($parts[1]);
                $row = $id ? getNote($pdo, $id) : null;
                if (!$row) fail(404, 'note_not_found', 'Note not found.');
                $meta = noteMeta($pdo, $row, true);
                respond(200, ['id' => $id, 'title' => $meta['title'], 'content' => $meta['content'], 'meta' => $meta, 'breadcrumbs' => folderPath($pdo, $row['folder_id'])]);
            }
            if ($method === 'POST' && count($parts) === 1) {
                respond(200, saveNote($pdo, $input));
            }
            if (in_array($method, ['PUT', 'PATCH'], true) && isset($parts[1])) {
                $input['id'] = $parts[1];
                respond(200, patchNote($pdo, $input));
            }
            if ($method === 'DELETE' && isset($parts[1])) {
                $hard = !empty($_GET['hard']);
                bulkOperation($pdo, [$parts[1]], $hard ? 'delete' : 'trash');
                respond(200, ['success' => true]);
            }
        }
        if ($parts[0] === 'folders') {
            if ($method === 'GET') respond(200, ['folders' => folderTree($pdo), 'counts' => folderCounts($pdo)]);
            if ($method === 'POST') respond(200, ['folder' => createFolder($pdo, $input), 'folders' => folderTree($pdo)]);
            if (in_array($method, ['PUT', 'PATCH'], true) && isset($parts[1])) respond(200, ['folder' => updateFolder($pdo, normalizeId($parts[1]) ?? '', $input), 'folders' => folderTree($pdo)]);
            if ($method === 'DELETE' && isset($parts[1])) {
                deleteFolder($pdo, normalizeId($parts[1]) ?? '');
                respond(200, ['success' => true, 'folders' => folderTree($pdo)]);
            }
        }
        if ($parts[0] === 'bulk' && $method === 'POST') {
            if (($input['op'] ?? '') === 'move') {
                $folderId = normalizeId(is_string($input['folder_id'] ?? null) ? $input['folder_id'] : (is_string($input['folderId'] ?? null) ? $input['folderId'] : null));
                foreach ((array) ($input['ids'] ?? []) as $rawId) {
                    $id = normalizeId(is_string($rawId) ? $rawId : null);
                    if ($id) $pdo->prepare('UPDATE notes SET folder_id = ?, updated_at = ? WHERE id = ?')->execute([$folderId, date('c'), $id]);
                }
            } else {
                bulkOperation($pdo, (array) ($input['ids'] ?? []), (string) ($input['op'] ?? ''));
            }
            respond(200, ['success' => true]);
        }
        if ($parts[0] === 'search') {
            respond(200, ['notes' => listNotes($pdo, ['q' => (string) ($_GET['q'] ?? ''), 'view' => 'all'])]);
        }
        if ($parts[0] === 'attachments' && isset($parts[1])) {
            $stmt = $pdo->prepare('SELECT * FROM attachments WHERE id = ?');
            $stmt->execute([normalizeId($parts[1]) ?? '']);
            $att = $stmt->fetch();
            if (!$att) fail(404, 'attachment_not_found', 'Attachment not found.');
            $path = $attachmentsDir . DIRECTORY_SEPARATOR . $att['note_id'] . DIRECTORY_SEPARATOR . $att['stored_name'];
            if (!is_file($path)) fail(404, 'attachment_not_found', 'Attachment file not found.');
            header('Content-Type: ' . $att['mime']);
            header('Content-Length: ' . (string) filesize($path));
            header('Content-Disposition: inline; filename="' . rawurlencode((string) $att['filename']) . '"');
            readfile($path);
            exit;
        }
        fail(404, 'route_not_found', 'API route not found.');
    }

    switch ($action) {
        case 'me':
            respond(200, ['user' => Auth::userPayload(), 'authEnabled' => defined('AUTH_ENABLED') ? AUTH_ENABLED : true]);
        case 'login':
            $user = Auth::login(trim((string) ($input['username'] ?? '')), (string) ($input['password'] ?? ''), !empty($input['remember']));
            if (!$user) fail(401, 'invalid_login', 'Invalid username or password.');
            respond(200, ['user' => Auth::userPayload()]);
        case 'logout':
            Auth::logout();
            respond(200, ['success' => true]);
        case 'users.list':
            respond(200, usersList($pdo));
        case 'users.save':
            respond(200, usersSave($pdo, $input));
        case 'users.patch':
            respond(200, usersPatch($pdo, $input));
        case 'users.invite':
            respond(200, usersInvite($pdo, $input));
        case 'users.reset':
            respond(200, usersResetLink($pdo, $input));
        case 'roles.list':
            respond(200, rolesList($pdo));
        case 'roles.save':
            respond(200, rolesSave($pdo, $input));
        case 'admin.settings':
            respond(200, adminSettings($pdo, $input));
        case 'hq.stats':
            respond(200, hqStats($pdo));
        case 'sharing.users':
            respond(200, sharingListUsers($pdo));
        case 'share.get':
            $id = normalizeId((string) ($_GET['id'] ?? ''));
            if (!$id) fail(400, 'invalid_note', 'Invalid note id.');
            respond(200, sharingGetNote($pdo, $id));
        case 'share.save':
            respond(200, sharingSave($pdo, $input));
        case 'shared.list':
            respond(200, sharedList($pdo));
        case 'presence.ping':
            respond(200, presencePing($pdo, $input));
        case 'presence.leave':
            respond(200, presenceLeave($pdo, $input));
        case 'presence.list':
            $id = normalizeId((string) ($_GET['id'] ?? ''));
            if (!$id) fail(400, 'invalid_entity', 'Invalid entity id.');
            respond(200, presenceForEntity($pdo, $id));
        case 'note.backlinks':
            $id = normalizeId((string) ($_GET['id'] ?? ''));
            if (!$id) fail(400, 'invalid_note', 'Invalid note id.');
            $stmt = $pdo->prepare('
                SELECT n.id, n.title, n.updated_at
                FROM note_links l
                JOIN notes n ON n.id = l.source_id
                WHERE l.target_id = ? AND n.deleted_at IS NULL
                ORDER BY n.updated_at DESC
            ');
            $stmt->execute([$id]);
            respond(200, ['backlinks' => $stmt->fetchAll()]);
        case 'settings':
            if ($method === 'POST') {
                if (isset($input['language']) && in_array($input['language'], ['en', 'ar'], true)) setSetting($pdo, 'language', $input['language']);
                if (isset($input['theme']) && in_array($input['theme'], ['dark', 'light'], true)) setSetting($pdo, 'theme', $input['theme']);
            }
            respond(200, ['settings' => getSettings($pdo)]);
        case 'list':
            respond(200, ['notes' => listNotes($pdo, $_GET), 'counts' => counts($pdo), 'folders' => folderTree($pdo)]);
        case 'get':
            $id = normalizeId($_GET['id'] ?? null);
            $row = $id ? getNote($pdo, $id) : null;
            if (!$row) fail(404, 'note_not_found', 'Note not found.');
            $meta = noteMeta($pdo, $row, true);
            respond(200, ['id' => $id, 'title' => $meta['title'], 'content' => $meta['content'], 'meta' => $meta, 'breadcrumbs' => folderPath($pdo, $row['folder_id'])]);
        case 'save':
            respond(200, saveNote($pdo, $input));
        case 'patch':
            respond(200, patchNote($pdo, $input));
        case 'reorder':
            foreach ((array) ($input['ids'] ?? []) as $i => $rawId) {
                $id = normalizeId(is_string($rawId) ? $rawId : null);
                if ($id) $pdo->prepare('UPDATE notes SET position = ? WHERE id = ?')->execute([(int) $i, $id]);
            }
            respond(200, ['success' => true]);
        case 'bulk':
            bulkOperation($pdo, (array) ($input['ids'] ?? []), (string) ($input['op'] ?? ''));
            respond(200, ['success' => true]);
        case 'delete':
            $id = normalizeId($_GET['id'] ?? null);
            if (!$id) fail(400, 'invalid_note', 'Invalid note id.');
            bulkOperation($pdo, [$id], !empty($_GET['hard']) ? 'delete' : 'trash');
            respond(200, ['success' => true]);
        case 'restore':
            $id = normalizeId($_GET['id'] ?? (is_string($input['id'] ?? null) ? $input['id'] : null));
            if (!$id) fail(400, 'invalid_note', 'Invalid note id.');
            bulkOperation($pdo, [$id], 'restore');
            respond(200, ['success' => true, 'meta' => noteMeta($pdo, getNote($pdo, $id) ?: [])]);
        case 'duplicate':
            $id = normalizeId($_GET['id'] ?? (is_string($input['id'] ?? null) ? $input['id'] : null));
            $row = $id ? getNote($pdo, $id) : null;
            if (!$row) fail(400, 'invalid_note', 'Invalid note id.');
            $newId = bin2hex(random_bytes(8));
            $row['id'] = $newId;
            $row['title'] = mb_substr((string) $row['title'] . ' (copy)', 0, 200);
            $row['pinned'] = 0;
            $row['archived'] = 0;
            $row['deleted_at'] = null;
            $row['created_at'] = $row['updated_at'] = date('c');
            upsertNote($pdo, $row);
            replaceTags($pdo, $newId, noteTags($pdo, $id));
            respond(200, ['id' => $newId, 'meta' => noteMeta($pdo, getNote($pdo, $newId) ?: [])]);
        case 'versions':
            $id = normalizeId($_GET['id'] ?? null);
            if (!$id) fail(400, 'invalid_note', 'Invalid note id.');
            if (isset($_GET['ts'])) {
                $stmt = $pdo->prepare('SELECT id, content, snapshot_at FROM note_versions WHERE note_id = ? AND id = ?');
                $stmt->execute([$id, (int) $_GET['ts']]);
                $v = $stmt->fetch();
                if (!$v) fail(404, 'version_not_found', 'Version not found.');
                respond(200, ['ts' => (string) $v['id'], 'content' => $v['content']]);
            }
            $stmt = $pdo->prepare('SELECT id, snapshot_at FROM note_versions WHERE note_id = ? ORDER BY snapshot_at DESC, id DESC');
            $stmt->execute([$id]);
            $versions = array_map(static fn($v) => ['ts' => (string) $v['id'], 'mtime' => strtotime((string) $v['snapshot_at']) ?: time()], $stmt->fetchAll());
            respond(200, ['versions' => $versions]);
        case 'restoreVersion':
            $id = normalizeId(is_string($input['id'] ?? null) ? $input['id'] : null);
            $versionId = (int) ($input['ts'] ?? 0);
            $row = $id ? getNote($pdo, $id) : null;
            if (!$row || $versionId <= 0) fail(400, 'invalid_request', 'Invalid request.');
            $stmt = $pdo->prepare('SELECT content FROM note_versions WHERE note_id = ? AND id = ?');
            $stmt->execute([$id, $versionId]);
            $content = $stmt->fetchColumn();
            if ($content === false) fail(404, 'version_not_found', 'Version not found.');
            insertVersion($pdo, $id, (string) $row['content']);
            $pdo->prepare('UPDATE notes SET content = ?, snippet = ?, word_count = ?, updated_at = ? WHERE id = ?')->execute([(string) $content, plainSnippet((string) $content), wordCount((string) $content), date('c'), $id]);
            respond(200, ['content' => renderInternalLinks((string) $content), 'meta' => noteMeta($pdo, getNote($pdo, $id) ?: [])]);
        case 'upload':
        $id = normalizeId($_GET['id'] ?? null);
            if (!$id || !getNote($pdo, $id)) fail(400, 'invalid_note', 'Invalid note id.');
            if (empty($_FILES['file'])) fail(400, 'no_file', 'No file uploaded.');
            $file = $_FILES['file'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) fail(400, 'upload_error', 'Upload error.');
            if (($file['size'] ?? 0) > 20 * 1024 * 1024) fail(413, 'file_too_large', 'File exceeds 20MB.');
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? 'application/octet-stream');
            if ($finfo) finfo_close($finfo);
            $extMap = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($extMap[$mime])) fail(415, 'unsupported_file', 'Unsupported image type.');
            $attachmentId = bin2hex(random_bytes(8));
            $noteDir = $GLOBALS['attachmentsDir'] . DIRECTORY_SEPARATOR . $id;
            if (!is_dir($noteDir)) @mkdir($noteDir, 0777, true);
            $stored = $attachmentId . '.' . $extMap[$mime];
            if (!move_uploaded_file($file['tmp_name'], $noteDir . DIRECTORY_SEPARATOR . $stored)) fail(500, 'store_failed', 'Failed to store file.');
            $pdo->prepare('INSERT INTO attachments(id, note_id, filename, stored_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$attachmentId, $id, (string) ($file['name'] ?? $stored), $stored, (string) $mime, (int) ($file['size'] ?? 0), date('c')]);
            respond(200, ['url' => 'data/attachments/' . rawurlencode($id) . '/' . rawurlencode($stored), 'attachment' => ['id' => $attachmentId]]);
        case 'tags':
            $rows = $pdo->query("SELECT t.name, COUNT(nt.note_id) AS count FROM tags t JOIN note_tags nt ON nt.tag_id = t.id JOIN notes n ON n.id = nt.note_id WHERE n.deleted_at IS NULL AND n.archived = 0 GROUP BY t.id, t.name ORDER BY t.name COLLATE NOCASE")->fetchAll();
            respond(200, ['tags' => array_map(static fn($r) => ['name' => $r['name'], 'count' => (int) $r['count']], $rows)]);
        case 'export':
            $id = normalizeId($_GET['id'] ?? null);
            $row = $id ? getNote($pdo, $id) : null;
            if (!$row) fail(400, 'invalid_note', 'Invalid note id.');
        $format = (string) ($_GET['format'] ?? 'html');
            $title = (string) $row['title'];
        $safeTitle = preg_replace('/[^A-Za-z0-9._-]+/', '_', $title) ?: 'note';
            $content = renderInternalLinks((string) $row['content']);
        if ($format === 'txt') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $safeTitle . '.txt"');
                echo $title . "\n\n" . plainSnippet($content, 1000000);
            exit;
        }
        if ($format === 'md') {
                $md = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $content) ?? $content);
            header('Content-Type: text/markdown; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $safeTitle . '.md"');
                echo "# " . $title . "\n\n" . html_entity_decode($md, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.html"');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title></head><body>' . $content . '</body></html>';
        exit;
    default:
            fail(400, 'unsupported_action', 'Unsupported action.');
    }
} catch (PDOException $e) {
    fail(500, 'database_error', $e->getMessage());
} catch (Throwable $e) {
    fail(500, 'server_error', $e->getMessage());
}
