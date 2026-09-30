<?php
/**
 * flush_cache.php — manual OPcache flush (admin session preferred).
 * Version: 260917.04
 *
 * Also invoked conceptually by System Optimise via AppCacheFlush::all('optimise').
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
define('DATA_PATH', BASE_PATH . '/data');

require_once BASE_PATH . '/app/bootstrap.php';

if (class_exists('AppAuth')) {
    AppAuth::initSession();
}

$by = (string)($_SESSION['user'] ?? 'anonymous');
// Allow CLI / local, or any logged-in user; refuse pure anonymous web hits.
$isCli = (PHP_SAPI === 'cli');
$signed = !empty($_SESSION['user']);
if (!$isCli && !$signed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Sign in required to flush cache.\n";
    exit;
}

$result = class_exists('AppCacheFlush')
    ? AppCacheFlush::all('manual')
    : ['opcache' => function_exists('opcache_reset') ? (bool)@opcache_reset() : false, 'stamp' => false, 'version' => defined('APP_VERSION') ? APP_VERSION : '?'];

if (class_exists('AppLog')) {
    AppLog::info('OPcache flushed via flush_cache.php', ['by' => $by] + $result);
}

header('Content-Type: text/plain; charset=UTF-8');
echo "OPcache: " . ($result['opcache'] ? 'cleared' : 'unavailable or failed') . "\n";
echo "Version stamp: " . ($result['version'] ?? '') . "\n";
echo "Done.\n";
