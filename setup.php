<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/auth.php';

$pdo = Auth::pdo();
$done = __DIR__ . '/data/.setup_done';
if (Auth::hasUsers() || is_file($done)) {
    header('Location: login.php');
    exit;
}

$error = '';
function getErrorI18nKey(string $error): string {
    $map = [
        'Fill all fields. Password must be at least 6 characters.' => 'setup.err.fill_fields',
    ];
    return $map[$error] ?? 'setup.err.generic';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $workspace = trim((string) ($_POST['workspace'] ?? WORKSPACE_NAME));
    $username = trim((string) ($_POST['username'] ?? 'owner'));
    $display = trim((string) ($_POST['display_name'] ?? 'Owner'));
    $password = (string) ($_POST['password'] ?? '');
    $mode = $_POST['single_instance'] ?? '1';
    if ($username === '' || $display === '' || strlen($password) < 6) {
        $error = 'Fill all fields. Password must be at least 6 characters.';
    } else {
        $id = bin2hex(random_bytes(8));
        $pdo->prepare('INSERT INTO users(id, username, display_name, password_hash, role_id, is_active, created_at) VALUES (?, ?, ?, ?, "owner", 1, ?)')
            ->execute([$id, $username, $display, password_hash($password, PASSWORD_BCRYPT), date('c')]);
        if (function_exists('setSetting')) {
            setSetting($pdo, 'workspace_name', $workspace);
            setSetting($pdo, 'single_instance', $mode === '1' ? '1' : '0');
        } else {
            $pdo->prepare('CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY, value TEXT NOT NULL)')->execute();
            $pdo->prepare('INSERT OR REPLACE INTO settings(key, value) VALUES (?, ?), (?, ?)')->execute(['workspace_name', $workspace, 'single_instance', $mode === '1' ? '1' : '0']);
        }
        @file_put_contents($done, date('c'));
        Auth::writeLog('setup.completed', 'user', $id, ['workspace' => $workspace]);
        Auth::login($username, $password, false);
        header('Location: app.html');
        exit;
    }
}
?><!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="setup.title">Setup | iH4x OS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/auth.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="shared.js"></script>
</head>
<body class="auth-page">
    <main class="auth-card setup-card">
        <div class="auth-logo"><span class="logo-dot"></span><span data-i18n="setup.title">iH4x OS Setup</span></div>
        <?php if ($error): ?><div class="auth-error" data-i18n="<?= htmlspecialchars(getErrorI18nKey($error)) ?>"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" class="auth-form">
            <div class="setup-step"><strong>1</strong><span data-i18n="setup.workspace">Workspace</span></div>
            <label class="auth-field"><span data-i18n="setup.workspace_name">Workspace name</span><input class="auth-input" name="workspace" value="<?= htmlspecialchars(WORKSPACE_NAME) ?>" required></label>
            <div class="setup-step"><strong>2</strong><span data-i18n="setup.owner_account">Owner account</span></div>
            <label class="auth-field"><span data-i18n="auth.username">Username</span><input class="auth-input" name="username" value="owner" required></label>
            <label class="auth-field"><span data-i18n="profile.display_name">Display name</span><input class="auth-input" name="display_name" value="Owner" required></label>
            <label class="auth-field"><span data-i18n="auth.password">Password</span><input class="auth-input" type="password" name="password" required></label>
            <div class="setup-step"><strong>3</strong><span data-i18n="setup.deployment_mode">Deployment mode</span></div>
            <label class="auth-check"><input type="radio" name="single_instance" value="1" checked> <span data-i18n="setup.single_lan">Single LAN server</span></label>
            <label class="auth-check"><input type="radio" name="single_instance" value="0"> <span data-i18n="setup.nextcloud">NextCloud synced copies</span></label>
            <button class="auth-btn" type="submit" data-i18n="setup.finish">Finish setup</button>
        </form>
        <div class="auth-footer"><button type="button" onclick="iH4x.toggleLanguage()" data-i18n="action.toggle_lang">Toggle language</button></div>
    </main>
</body>
</html>
