<?php
declare(strict_types=1);

/*
 * One-time JSON/file storage to SQLite migrator.
 *
 * The API now performs an idempotent migration on first boot. Running this
 * script explicitly triggers that same path and returns a small JSON summary.
 */

$_GET = ['action' => 'list', 'view' => 'all', 'sort' => 'updated'];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require __DIR__ . '/api.php';
