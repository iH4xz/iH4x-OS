<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/auth.php';

if (defined('AUTH_ENABLED') && AUTH_ENABLED === false) {
    header('Location: app.html');
    exit;
}
if (!Auth::hasUsers()) {
    header('Location: setup.php');
    exit;
}

$error = '';
$resetOk = false;
$resetToken = isset($_GET['reset']) ? preg_replace('/[^a-f0-9]/i', '', (string) $_GET['reset']) : '';
$inviteToken = isset($_GET['invite']) ? preg_replace('/[^a-f0-9]/i', '', (string) $_GET['invite']) : '';
$inviteRow = null;
if ($inviteToken !== '') {
    $stmt = Auth::pdo()->prepare('SELECT * FROM invite_tokens WHERE token = ? AND used_at IS NULL AND expires_at > ?');
    $stmt->execute([$inviteToken, date('c')]);
    $inviteRow = $stmt->fetch() ?: null;
    if (!$inviteRow) $error = 'Invite link expired or invalid.';
}

function getErrorI18nKey(string $error): string {
    $map = [
        'Invite link expired or invalid.' => 'auth.err.invite_invalid',
        'Fill required fields. Password must be at least 6 characters.' => 'auth.err.fill_fields',
        'Username already exists.' => 'auth.err.username_exists',
        'Password must be at least 6 characters.' => 'auth.err.password_short',
        'Reset link expired or invalid.' => 'auth.err.reset_invalid',
        'Invalid username or password.' => 'auth.err.invalid_credentials',
    ];
    return $map[$error] ?? 'auth.err.generic';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['invite_token'])) {
        $inviteToken = preg_replace('/[^a-f0-9]/i', '', (string) $_POST['invite_token']);
        $stmt = Auth::pdo()->prepare('SELECT * FROM invite_tokens WHERE token = ? AND used_at IS NULL AND expires_at > ?');
        $stmt->execute([$inviteToken, date('c')]);
        $inviteRow = $stmt->fetch() ?: null;
        if (!$inviteRow) {
            $error = 'Invite link expired or invalid.';
        } else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $display = trim((string) ($_POST['display_name'] ?? ($inviteRow['display_name'] ?? '')));
            $email = trim((string) ($_POST['email'] ?? ($inviteRow['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            if ($username === '' || $display === '' || strlen($password) < 6) {
                $error = 'Fill required fields. Password must be at least 6 characters.';
            } else {
                $exists = Auth::pdo()->prepare('SELECT 1 FROM users WHERE LOWER(username) = LOWER(?)');
                $exists->execute([$username]);
                if ($exists->fetchColumn()) {
                    $error = 'Username already exists.';
                } else {
                    $uid = bin2hex(random_bytes(8));
                    Auth::pdo()->prepare('INSERT INTO users(id, username, email, display_name, password_hash, role_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?)')
                        ->execute([$uid, $username, $email, $display, password_hash($password, PASSWORD_BCRYPT), (string) ($inviteRow['role_id'] ?: DEFAULT_ROLE), date('c')]);
                    Auth::pdo()->prepare('UPDATE invite_tokens SET used_at = ? WHERE id = ?')->execute([date('c'), $inviteRow['id']]);
                    Auth::writeLog('auth.invite_signup', 'user', $uid, ['username' => $username]);
                    Auth::login($username, $password, false);
                    header('Location: app.html');
                    exit;
                }
            }
        }
    } elseif (isset($_POST['reset_token'])) {
        $resetToken = preg_replace('/[^a-f0-9]/i', '', (string) $_POST['reset_token']);
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $stmt = Auth::pdo()->prepare('SELECT * FROM reset_tokens WHERE token = ? AND used_at IS NULL AND expires_at > ?');
            $stmt->execute([$resetToken, date('c')]);
            $row = $stmt->fetch();
            if ($row) {
                Auth::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_BCRYPT), $row['user_id']]);
                Auth::pdo()->prepare('UPDATE reset_tokens SET used_at = ? WHERE id = ?')->execute([date('c'), $row['id']]);
                $resetOk = true;
                Auth::writeLog('auth.password_reset', 'user', (string) $row['user_id'], []);
            } else {
                $error = 'Reset link expired or invalid.';
            }
        }
    } else {
        $user = Auth::login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''), !empty($_POST['remember']));
        if ($user) {
            header('Location: app.html');
            exit;
        }
        $error = 'Invalid username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(WORKSPACE_NAME) ?> | Login</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/auth.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="shared.js"></script>
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-logo"><span class="logo-dot"></span><span><?= htmlspecialchars(WORKSPACE_NAME) ?></span></div>
        <?php if ($error): ?><div class="auth-error" data-i18n="<?= htmlspecialchars(getErrorI18nKey($error)) ?>"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($resetOk): ?><div class="auth-success" data-i18n="auth.success.password_updated">Password updated. Sign in with the new password.</div><?php endif; ?>

        <?php if ($inviteRow): ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="invite_token" value="<?= htmlspecialchars($inviteToken) ?>">
                <label class="auth-field"><span data-i18n="auth.username">Username</span><input class="auth-input" type="text" name="username" required value=""></label>
                <label class="auth-field"><span data-i18n="profile.display_name">Display name</span><input class="auth-input" type="text" name="display_name" value="<?= htmlspecialchars((string) ($inviteRow['display_name'] ?? '')) ?>" required></label>
                <label class="auth-field"><span data-i18n="profile.email">Email</span><input class="auth-input" type="email" name="email" value="<?= htmlspecialchars((string) ($inviteRow['email'] ?? '')) ?>"></label>
                <label class="auth-field"><span data-i18n="auth.password">Password</span><input class="auth-input" type="password" name="password" required autocomplete="new-password"></label>
                <button class="auth-btn" type="submit" data-i18n="auth.create_account">Create account</button>
            </form>
        <?php elseif ($resetToken && !$resetOk): ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="reset_token" value="<?= htmlspecialchars($resetToken) ?>">
                <label class="auth-field">
                    <span data-i18n="auth.password">Password</span>
                    <input class="auth-input" type="password" name="password" required autocomplete="new-password">
                </label>
                <button class="auth-btn" type="submit" data-i18n="auth.reset_password">Reset password</button>
            </form>
        <?php else: ?>
            <form method="post" class="auth-form">
                <label class="auth-field">
                    <span data-i18n="auth.username">Username</span>
                    <input class="auth-input" type="text" name="username" required autocomplete="username" autofocus>
                </label>
                <label class="auth-field">
                    <span data-i18n="auth.password">Password</span>
                    <input class="auth-input" type="password" name="password" required autocomplete="current-password">
                </label>
                <label class="auth-check"><input type="checkbox" name="remember" value="1"> <span data-i18n="auth.remember">Remember me</span></label>
                <button class="auth-btn" type="submit" data-i18n="auth.sign_in">Sign in</button>
            </form>
        <?php endif; ?>
        <div class="auth-footer"><button type="button" onclick="iH4x.toggleLanguage()" data-i18n="action.toggle_lang">Toggle language</button></div>
    </main>
</body>
</html>
