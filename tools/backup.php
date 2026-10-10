<?php
declare(strict_types=1);
/**
 * tools/backup.php — Full multi-tenant data backup ZIP.
 * Version: 20260928.05
 *
 * Includes ALL tenants' data trees, map.json, tenant config.json files,
 * and shared settings. Super Admin and Co. Admin (admin) only.
 */
$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
if (!defined('SESSION_PATH')) {
    define('SESSION_PATH', DATA_PATH . '/sessions');
}
if (!file_exists(SESSION_PATH)) {
    @mkdir(SESSION_PATH, 0755, true);
}
require_once BASE_PATH . '/app/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    AppAuth::initSession();
}

$user = (string)($_SESSION['user'] ?? '');
$true = (string)($_SESSION['true_role'] ?? $user);
$allowed = in_array($user, ['super_admin', 'admin'], true)
    || in_array($true, ['super_admin', 'admin'], true);
if (!$allowed) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo "<div style='padding:20px;font-family:sans-serif;color:#b91c1c;font-weight:bold;'>Access Denied. Administrator sign-in required.</div>";
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(501);
    echo '<div style="padding:24px;font-family:sans-serif">ZIP support unavailable (php_zip).</div>';
    exit;
}

/**
 * @param array<string,string> $out
 */
function rc_backup_collect(string $dir, string $prefix, array &$out): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $dirNorm = rtrim(str_replace('\\', '/', $dir), '/');
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $abs = str_replace('\\', '/', $f->getPathname());
        $rel = $prefix . '/' . ltrim(substr($abs, strlen($dirNorm)), '/');
        $name = $f->getFilename();
        if (strpos($rel, '/sessions/') !== false) {
            continue;
        }
        if (strpos($rel, '/logs/') !== false) {
            continue;
        }
        if (strpos($rel, '/backups/') !== false) {
            continue;
        }
        if (strpos($rel, '/cache/') !== false) {
            continue;
        }
        if (strpos($rel, '/tmp/') !== false) {
            continue;
        }
        if (preg_match('/\.tmp$/i', $name)) {
            continue;
        }
        if ($name === '.numero_meanings_cache.php' || $name === '.opcache_version' || $name === '.gitkeep') {
            continue;
        }
        $out[$rel] = $abs;
    }
}

$files = [];
$tenantsRoot = BASE_PATH . '/tenants';

// Global map + every tenant
if (is_dir($tenantsRoot)) {
    $mapFile = $tenantsRoot . '/map.json';
    if (is_file($mapFile)) {
        $files['tenants/map.json'] = $mapFile;
    }
    if (!class_exists('TenantPaths') && is_file(BASE_PATH . '/app/TenantPaths.php')) {
        require_once BASE_PATH . '/app/TenantPaths.php';
    }
    $tenantList = class_exists('TenantPaths') ? TenantPaths::listIds() : [];
    if ($tenantList === []) {
        foreach (scandir($tenantsRoot) ?: [] as $tid) {
            if ($tid === '.' || $tid === '..' || $tid === 'map.json' || str_starts_with((string)$tid, '.')) continue;
            if (preg_match('/\.(md|txt|json|htaccess)$/i', (string)$tid)) continue;
            if (!@is_dir($tenantsRoot . '/' . $tid)) continue;
            $tenantList[] = $tid;
        }
    }
    foreach ($tenantList as $tid) {
        $tPath = $tenantsRoot . '/' . $tid;
        if (!@is_dir($tPath)) {
            continue;
        }
        $cfg = $tPath . '/config.json';
        if (is_file($cfg)) {
            $files['tenants/' . $tid . '/config.json'] = $cfg;
        }
        $data = $tPath . '/data';
        if (is_dir($data)) {
            rc_backup_collect($data, 'tenants/' . $tid . '/data', $files);
        }
    }
}

// Active DATA_PATH (current host tenant) — may overlap tenants/{id}/data
rc_backup_collect(DATA_PATH, 'data', $files);

// Legacy root folders (images/, docs/, storage/, dispatch/data) retired — not included in backup.


$stamp = "RC multi-tenant backup\n"
    . 'Build: ' . (defined('APP_VERSION') ? APP_VERSION : 'unknown') . "\n"
    . 'Generated: ' . date('c') . "\n"
    . 'Operator: ' . $user . (isset($_SESSION['true_role']) ? ' (true=' . $_SESSION['true_role'] . ')' : '') . "\n"
    . 'Host: ' . (string)($_SERVER['HTTP_HOST'] ?? '') . "\n"
    . 'Files: ' . count($files) . "\n"
    . "Includes: all tenants/*/data, map.json, config.json, active data/\n"
    . "Excludes: sessions, logs, backups, cache, tmp\n";

$tmp = tempnam(sys_get_temp_dir(), 'rcbak_');
if ($tmp === false) {
    http_response_code(500);
    echo 'Cannot create temp file';
    exit;
}
$zipPath = $tmp . '.zip';
@unlink($tmp);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Cannot create ZIP';
    exit;
}
$zip->addFromString('BACKUP-STAMP.txt', $stamp);

$manifest = [
    'app_version' => defined('APP_VERSION') ? (string)APP_VERSION : 'unknown',
    'generated_at' => date('c'),
    'operator' => $user,
    'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
    'tenant_count' => count($tenantList ?? []),
    'tenants' => array_values($tenantList ?? []),
    'file_count' => 0,
    'files' => [],
];
foreach ($files as $rel => $abs) {
    if (!is_readable($abs)) {
        continue;
    }
    $bin = @file_get_contents($abs);
    if ($bin === false) {
        continue;
    }
    $manifest['files'][$rel] = [
        'size' => strlen($bin),
        'sha256' => hash('sha256', $bin),
    ];
    $zip->addFromString($rel, $bin);
}
$manifest['file_count'] = count($manifest['files']);
$zip->addFromString('MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$zip->close();

$ver = defined('APP_VERSION') ? preg_replace('/[^0-9.]/', '', (string)APP_VERSION) : date('Ymd');
$download = 'RC-' . $ver . '-full-backup-' . date('Ymd-His') . '.zip';
$size = (int) @filesize($zipPath);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $download . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store');
readfile($zipPath);
@unlink($zipPath);
exit;
