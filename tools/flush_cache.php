<?php
// flush_cache.php
// Version: 260916.14
// CHANGELOG: previously had ZERO authentication — anyone who requested this
// URL could force an opcache reset. Now requires an active admin/crm session,
// mirroring the pattern already used correctly in print.php.

$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
// BUG FIX: this file defined DATA_PATH/IMG_PATH/DOC_PATH directly from
// BASE_PATH, completely bypassing tenant resolution -- on a multi-tenant
// deployment (tenants/{id}/data/, resolved by host in
// app/tenant_bootstrap.php), every request to this file read and wrote
// the WRONG tenant's data regardless of which domain it was requested on.
// Fixed to require tenant_bootstrap.php first (as index.php and the
// correctly-wired standalone entry points already do) and guard every
// fallback define with if (!defined(...)) so tenant_bootstrap's
// resolution always wins when available, with these as the fallback for
// a genuine single-tenant install with no tenants/ folder at all.
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('SESSION_PATH')) define('SESSION_PATH', DATA_PATH . '/sessions');

if (!file_exists(SESSION_PATH)) @mkdir(SESSION_PATH, 0755, true);
require_once BASE_PATH . '/app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) AppAuth::initSession();
if (empty($_SESSION['user']) || ($_SESSION['user'] !== 'admin' && $_SESSION['user'] !== 'crm')) {
    http_response_code(403);
    die("<div style='padding:20px;font-family:sans-serif;color:red;font-weight:bold;'>Access Denied. Please log in via the main dashboard.</div>");
}

header('Content-Type: text/plain; charset=utf-8');

if (function_exists('opcache_reset')) {
    opcache_reset();
    if (class_exists('AppLog')) AppLog::info('OPcache flushed via flush_cache.php', ['by' => $_SESSION['user']]);
    echo "Cache cleared.\n";
} else {
    echo "OPcache is not enabled on this host — nothing to flush.\n";
}
