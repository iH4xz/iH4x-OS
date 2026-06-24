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
    <title>HQ | <?= htmlspecialchars(WORKSPACE_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/hq.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>window.__user = <?= json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/dayjs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1.11.13/plugin/relativeTime.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.456.0/dist/umd/lucide.min.js"></script>
    <script src="shared.js"></script>
</head>
<body class="hq-page">
<button class="sidebar-float-toggle" id="sidebarFloatToggle" data-i18n-attr="title:action.toggle_sidebar,aria-label:action.toggle_sidebar"><i data-lucide="panel-left"></i></button>
<div class="hq-shell">
    <aside class="app-sidebar" id="sidebar">
        <div class="logo"><span class="logo-dot"></span><span>Company HQ</span></div>
        <nav><ul>
            <li onclick="location.href='app.html'"><span class="nav-icon"><i data-lucide="layout-grid"></i></span><span data-i18n="nav.all">All Notes</span></li>
            <li onclick="location.href='admin.php'"><span class="nav-icon"><i data-lucide="shield"></i></span><span data-i18n="hq.go_admin">Admin Panel</span></li>
        </ul></nav>
    </aside>
    <main class="hq-content">
        <header class="hq-header"><h1>Company HQ</h1><p><?= htmlspecialchars($user['display_name'] ?? '') ?></p></header>
        <section class="hq-widgets">
            <article class="hq-card"><h2 class="hq-card-title" data-i18n="hq.active_users">Active Users</h2><div class="presence-list" id="activeUsers"></div></article>
            <article class="hq-card"><h2 class="hq-card-title" data-i18n="hq.recent_activity">Recent Activity</h2><div class="activity-feed" id="activityFeed"></div></article>
            <article class="hq-card"><h2 class="hq-card-title" data-i18n="hq.storage">Storage</h2><div id="storageBox"></div></article>
        </section>
    </main>
</div>
<script src="assets/hq.js"></script>
</body>
</html>
