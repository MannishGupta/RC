<?php
/**
 * Sunday weekly compressed backup (or force with ?force=1).
 * CLI: php tools/weekly_backup.php
 * Web: Super Admin only.
 */
declare(strict_types=1);
$base = dirname(__DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}
require_once $base . '/app/TenantBackup.php';

$force = false;
if (PHP_SAPI === 'cli') {
    $force = in_array('--force', $argv ?? [], true);
} else {
    // Minimal gate: require RC session super admin when hit via HTTP
    if (is_file($base . '/app/bootstrap.php')) {
        require_once $base . '/app/bootstrap.php';
    }
    $u = (string)($_SESSION['user'] ?? '');
    $super = ($u === 'super_admin') || !empty($_SESSION['is_super_admin']);
    if (!$super) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Super Admin only']);
        exit;
    }
    $force = !empty($_GET['force']) || !empty($_POST['force']);
}

$r = TenantBackup::weeklySundayBackup($force);
if (PHP_SAPI === 'cli') {
    fwrite(STDOUT, ($r['message'] ?? json_encode($r)) . PHP_EOL);
    exit(!empty($r['ok']) ? 0 : 1);
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($r, JSON_UNESCAPED_UNICODE);
