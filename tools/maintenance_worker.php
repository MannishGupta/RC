<?php
// Version: 3.1.0
declare(strict_types=1);

/**
 * CLI/cron: purge obsolete sessions and temp cache fragments.
 * Usage: php tools/maintenance_worker.php [--days=14] [--dry-run]
 */
$base = dirname(__DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}

$days = 14;
$dry = in_array('--dry-run', $argv ?? [], true);
foreach ($argv ?? [] as $a) {
    if (str_starts_with($a, '--days=')) {
        $days = max(1, (int) substr($a, 7));
    }
}
$maxAge = $days * 86400;
$now = time();
$removed = 0;
$freed = 0;

$roots = [];
$tenants = $base . '/tenants';
if (is_dir($tenants)) {
    foreach (scandir($tenants) ?: [] as $d) {
        if ($d === '.' || $d === '..' || str_starts_with($d, '.')) {
            continue;
        }
        $p = $tenants . '/' . $d . '/data';
        if (is_dir($p)) {
            $roots[] = $p;
        }
    }
}
if (is_dir($base . '/data')) {
    $roots[] = $base . '/data';
}

foreach ($roots as $root) {
    foreach (['sessions', 'tmp', 'cache'] as $sub) {
        $dir = $root . '/' . $sub;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir . '/' . $f;
            if (!is_file($path)) {
                continue;
            }
            $mtime = (int) (@filemtime($path) ?: 0);
            $size = (int) (@filesize($path) ?: 0);
            $stale = $mtime > 0 && ($now - $mtime) > $maxAge;
            $empty = $size === 0 && $sub === 'sessions';
            if ($stale || $empty) {
                if ($dry) {
                    echo "[dry-run] $path\n";
                    $removed++;
                    continue;
                }
                if (@unlink($path)) {
                    $removed++;
                    $freed += $size;
                }
            }
        }
    }
}

echo 'maintenance_worker: removed ' . $removed . ' file(s), freed ~' . round($freed / 1024) . " KB (days={$days})\n";
exit(0);
