<?php
declare(strict_types=1);
if (!defined('BASE_PATH')) { exit; }
class TenantBackup {
    public static function rotateLogs(string $dataPath, int $maxBytes = 2000000): int {
        $log = rtrim(str_replace('\\', '/', $dataPath), '/') . '/logs/app.log';
        if (!is_file($log)) return 0;
        $sz = (int)filesize($log);
        if ($sz <= $maxBytes) return 0;
        $arch = $log . '.' . date('Ymd-His') . '.bak';
        @rename($log, $arch);
        @file_put_contents($log, '[' . date('c') . "] Log rotated (previous {$sz} bytes)\n", LOCK_EX);
        $baks = glob(dirname($log) . '/app.log.*.bak') ?: [];
        rsort($baks);
        foreach (array_slice($baks, 5) as $old) @unlink($old);
        return 1;
    }
    public static function backupAllTenants(): array {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'message' => 'ZipArchive not available'];
        }
        $tenantsDir = BASE_PATH . '/tenants';
        if (!is_dir($tenantsDir)) return ['ok' => false, 'message' => 'No tenants directory'];
        $outDir = BASE_PATH . '/data/backups';
        if (!is_dir($outDir)) @mkdir($outDir, 0775, true);
        if (!is_dir($outDir) || !is_writable($outDir)) {
            $outDir = $tenantsDir . '/default/data/backups';
            if (!is_dir($outDir)) @mkdir($outDir, 0775, true);
        }
        $name = 'RC-data-' . date('Ymd-His') . '.zip';
        $zipPath = $outDir . '/' . $name;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'message' => 'Cannot create zip'];
        }
        if (is_file($tenantsDir . '/map.json')) {
            $zip->addFile($tenantsDir . '/map.json', 'tenants/map.json');
        }
        if (!class_exists('TenantPaths') && is_file(BASE_PATH . '/app/TenantPaths.php')) {
            require_once BASE_PATH . '/app/TenantPaths.php';
        }
        $tenantIds = class_exists('TenantPaths') ? TenantPaths::listIds() : [];
        if ($tenantIds === []) {
            foreach (scandir($tenantsDir) ?: [] as $d) {
                if ($d === '.' || $d === '..' || $d === 'map.json' || str_starts_with($d, '.')) continue;
                if (preg_match('/\.(md|txt|json|htaccess)$/i', $d)) continue;
                if (!is_dir($tenantsDir . '/' . $d)) continue;
                $tenantIds[] = $d;
            }
        }
        foreach ($tenantIds as $d) {
            $cfg = $tenantsDir . '/' . $d . '/config.json';
            $data = $tenantsDir . '/' . $d . '/data';
            if (is_dir($tenantsDir . '/' . $d) && is_file($cfg)) $zip->addFile($cfg, "tenants/{$d}/config.json");
            if (!is_dir($data)) continue;
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($data, FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if (!$file->isFile()) continue;
                $full = str_replace('\\', '/', $file->getPathname());
                $rel = 'tenants/' . $d . '/data/' . ltrim(substr($full, strlen(rtrim(str_replace('\\','/',$data),'/'))), '/');
                if (preg_match('#/(sessions|cache|tmp|backups)/#', $rel)) continue;
                $zip->addFile($full, $rel);
            }
        }
        $zip->close();
        $bytes = is_file($zipPath) ? (int)filesize($zipPath) : 0;
        $all = glob($outDir . '/RC-data-*.zip') ?: [];
        rsort($all);
        foreach (array_slice($all, 10) as $old) @unlink($old);
        return ['ok' => true, 'path' => $zipPath, 'bytes' => $bytes, 'message' => "Backup written: {$name}"];
    }
}
