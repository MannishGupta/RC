<?php
/**
 * recover_empty_data.php — Super-admin only.
 * Restores empty [] JSON from DATA_PATH/backups/*.zip and status/data → tenant status.
 * Never overwrites non-empty files.
 */
declare(strict_types=1);
$base = dirname(__DIR__);
require_once $base . '/app/bootstrap.php';

$role = (string)($_SESSION['role'] ?? '');
$ok = !empty($_SESSION['user']) && (
    $role === 'super_admin' || $role === 'superadmin'
    || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['isSuperAdmin'])
);
if (!$ok) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Super Admin only']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
$report = [
    'tenant' => defined('TENANT_ID') ? TENANT_ID : '?',
    'data_path' => defined('DATA_PATH') ? DATA_PATH : '',
    'restored' => [],
    'skipped' => [],
    'notes' => [],
];

$dataPath = defined('DATA_PATH') ? DATA_PATH : ($base . '/data');
$isEmpty = static function (string $file): bool {
    if (!is_file($file)) {
        return true;
    }
    $t = trim((string)@file_get_contents($file));
    return $t === '' || $t === '[]' || $t === 'null' || $t === '{}';
};

$bakDir = $dataPath . '/backups';
if (is_dir($bakDir)) {
    $zips = glob($bakDir . '/*.zip') ?: [];
    usort($zips, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach ($zips as $zipPath) {
        if (!class_exists('ZipArchive')) {
            $report['notes'][] = 'ZipArchive not available';
            break;
        }
        $za = new ZipArchive();
        if ($za->open($zipPath) !== true) {
            continue;
        }
        for ($i = 0; $i < $za->numFiles; $i++) {
            $name = $za->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }
            $baseName = basename(str_replace('\\', '/', $name));
            if (!preg_match('/^[a-z0-9_]+\.json$/i', $baseName)) {
                continue;
            }
            $dest = $dataPath . '/' . $baseName;
            if (!$isEmpty($dest)) {
                $report['skipped'][] = $baseName . ' (already has data)';
                continue;
            }
            $content = $za->getFromIndex($i);
            if ($content === false) {
                continue;
            }
            $trim = trim($content);
            if ($trim === '' || $trim === '[]' || $trim === '{}') {
                continue;
            }
            if (@file_put_contents($dest, $content) !== false) {
                $report['restored'][] = $baseName . ' ← ' . basename($zipPath);
            }
        }
        $za->close();
        if ($report['restored'] !== []) {
            break;
        }
    }
} else {
    $report['notes'][] = 'No backups folder at ' . $bakDir;
}

$statusTenant = $dataPath . '/status';
$statusLegacy = $base . '/status/data';
if (!is_dir($statusTenant)) {
    @mkdir($statusTenant . '/uploads', 0775, true);
}
foreach (['projects.json', 'components.json', 'updates.json', 'site_config.json'] as $f) {
    $dest = $statusTenant . '/' . $f;
    $src = $statusLegacy . '/' . $f;
    if (!$isEmpty($dest) || !is_file($src) || $isEmpty($src)) {
        continue;
    }
    if (@copy($src, $dest)) {
        $report['restored'][] = 'status/' . $f . ' ← status/data';
    }
}

if ($report['restored'] === []) {
    $report['notes'][] = 'Nothing restored. Copy a host backup of tenants/'
        . (defined('TENANT_ID') ? TENANT_ID : '{id}')
        . '/data/*.json into the live data folder, then hard-refresh.';
}

echo json_encode(['status' => 'success', 'report' => $report], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
