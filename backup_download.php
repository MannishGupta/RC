<?php
declare(strict_types=1);
/**
 * Root backup entry — use when /tools/ is blocked by IIS hiddenSegments.
 * Version: 20260929.20
 *
 * HARDENED: session + role gate runs HERE before including tools/backup.php,
 * so a missing/broken tools path cannot skip auth. No user-supplied file path
 * is accepted on this entry point (full archive only).
 */
$rootPath = str_replace('\\', '/', __DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $rootPath);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
if (!defined('SESSION_PATH')) {
    define('SESSION_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/sessions');
}
if (!is_dir(SESSION_PATH)) {
    @mkdir(SESSION_PATH, 0755, true);
}
require_once BASE_PATH . '/app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    if (class_exists('AppAuth') && method_exists('AppAuth', 'initSession')) {
        AppAuth::initSession();
    } else {
        @session_start();
    }
}

$user = (string)($_SESSION['user'] ?? '');
$true = (string)($_SESSION['true_role'] ?? $user);
$allowed = in_array($user, ['super_admin', 'admin'], true)
    || in_array($true, ['super_admin', 'admin'], true);

if (!$allowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo "Access denied. Administrator sign-in required.\n";
    if (class_exists('AppLog')) {
        AppLog::warn('backup_download denied', [
            'user' => $user !== '' ? $user : 'anonymous',
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }
    exit;
}

// Reject any attempt to pass a free-form path (this endpoint only runs full backup)
foreach (['file', 'path', 'f', 'name', 'download'] as $k) {
    if (isset($_GET[$k]) || isset($_POST[$k])) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Invalid request.\n";
        exit;
    }
}

$backup = BASE_PATH . '/tools/backup.php';
if (!is_file($backup)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Backup module unavailable.\n";
    exit;
}

// tools/backup.php will re-check auth; double gate is intentional.
require $backup;
