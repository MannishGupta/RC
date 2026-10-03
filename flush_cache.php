<?php
/**
 * flush_cache.php — manual OPcache flush (administrator only).
 * Version: 20260929.20
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
require_once BASE_PATH . '/app/bootstrap.php';

if (class_exists('AppAuth') && method_exists('AppAuth', 'initSession')) {
    AppAuth::initSession();
} elseif (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$isCli = (PHP_SAPI === 'cli');
$user = (string)($_SESSION['user'] ?? '');
$true = (string)($_SESSION['true_role'] ?? $user);
$isAdmin = $isCli || in_array($user, ['super_admin', 'admin'], true)
    || in_array($true, ['super_admin', 'admin'], true);

if (!$isAdmin) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    echo "Administrator sign-in required to flush cache.\n";
    if (class_exists('AppLog')) {
        AppLog::warn('flush_cache denied', ['user' => $user !== '' ? $user : 'anonymous']);
    }
    exit;
}

// Simple file-based rate limit: max 1 flush / 30s per tenant data dir
$rateFile = (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/.flush_cache_rate';
$now = time();
if (!$isCli && is_file($rateFile)) {
    $last = (int)@file_get_contents($rateFile);
    if ($last > 0 && ($now - $last) < 30) {
        http_response_code(429);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Retry-After: 30');
        echo "Rate limited. Wait before flushing again.\n";
        exit;
    }
}
@file_put_contents($rateFile, (string)$now, LOCK_EX);

$result = class_exists('AppCacheFlush')
    ? AppCacheFlush::all('manual')
    : [
        'opcache' => function_exists('opcache_reset') ? (bool)@opcache_reset() : false,
        'stamp' => false,
        'version' => defined('APP_VERSION') ? APP_VERSION : '?',
    ];

if (class_exists('AppLog')) {
    AppLog::info('OPcache flushed via flush_cache.php', ['by' => $user !== '' ? $user : 'cli'] + $result);
}

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
echo "OPcache: " . (!empty($result['opcache']) ? 'cleared' : 'unavailable or failed') . "\n";
echo "Version stamp: " . ($result['version'] ?? '') . "\n";
echo "Done.\n";
