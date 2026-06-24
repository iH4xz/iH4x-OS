<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/auth.php';
if (!Auth::hasUsers()) { header('Location: setup.php'); exit; }
if (!Auth::boot()) { header('Location: login.php'); exit; }
Auth::requirePerm('users.view');
$user = Auth::userPayload();
?><!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | <?= htmlspecialchars(WORKSPACE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>window.__user = <?= json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/dayjs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/plugin/relativeTime.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.456.0/dist/umd/lucide.min.js"></script>
    <script src="shared.js"></script>
</head>
<body class="admin-page">
<button class="sidebar-float-toggle" id="sidebarFloatToggle" data-i18n-attr="title:action.toggle_sidebar,aria-label:action.toggle_sidebar"><i data-lucide="panel-left"></i></button>
<div class="admin-shell">
    <aside class="app-sidebar" id="sidebar">
        <div class="logo"><span class="logo-dot"></span><span>iH4x OS</span></div>
        <nav><ul id="adminNav">
            <li class="active" data-tab="users"><span class="nav-icon"><i data-lucide="users"></i></span><span data-i18n="admin.users">Users</span></li>
            <li data-tab="roles"><span class="nav-icon"><i data-lucide="shield"></i></span><span data-i18n="admin.roles">Roles</span></li>
            <li data-tab="settings"><span class="nav-icon"><i data-lucide="settings"></i></span><span data-i18n="admin.settings">System Settings</span></li>
            <li onclick="location.href='app.html'"><span class="nav-icon"><i data-lucide="arrow-left"></i></span><span data-i18n="toolbar.back">Back</span></li>
        </ul></nav>
    </aside>
    <main class="admin-content">
        <header class="admin-header">
            <div><h1 data-i18n="admin.settings">Admin</h1><p><?= htmlspecialchars($user['display_name'] ?? '') ?></p></div>
            <button class="btn-primary" id="inviteBtn" data-i18n="admin.invite">Invite User</button>
        </header>
        <section class="admin-panel active" id="tab-users">
            <div class="table-wrap"><table class="user-table"><thead><tr><th>User</th><th>Role</th><th>Last seen</th><th>Status</th><th></th></tr></thead><tbody id="usersBody"></tbody></table></div>
        </section>
        <section class="admin-panel" id="tab-roles"><div id="rolesHost"></div></section>
        <section class="admin-panel" id="tab-settings"><div id="settingsHost"></div></section>
    </main>
</div>
<div class="side-panel" id="detailPanel" aria-hidden="true"></div>
<script src="assets/admin.js"></script>
</body>
</html>
