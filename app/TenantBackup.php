<?php
declare(strict_types=1);
if (!defined('BASE_PATH')) { exit; }

class TenantBackup
{
    public static function rotateLogs(string $dataPath, int $maxBytes = 2000000): int
    {
        $log = rtrim(str_replace('\\', '/', $dataPath), '/') . '/logs/app.log';
        if (!is_file($log)) {
            return 0;
        }
        $sz = (int) filesize($log);
        if ($sz <= $maxBytes) {
            return 0;
        }
        $arch = $log . '.' . date('Ymd-His') . '.bak';
        @rename($log, $arch);
        @file_put_contents($log, '[' . date('c') . "] Log rotated (previous {$sz} bytes)\n", LOCK_EX);
        $baks = glob(dirname($log) . '/app.log.*.bak') ?: [];
        rsort($baks);
        foreach (array_slice($baks, 5) as $old) {
            @unlink($old);
        }
        return 1;
    }

     
    public static function backupRoot(): string
    {
        $outDir = rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/data/backups';
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }
        if (!is_dir($outDir) || !is_writable($outDir)) {
            $outDir = rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/tenants/default/data/backups';
            if (!is_dir($outDir)) {
                @mkdir($outDir, 0775, true);
            }
        }
        return $outDir;
    }

    public static function weeklyDir(): string
    {
        $d = self::backupRoot() . '/weekly';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    

    public static function backupAllTenants(array $opts = []): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'message' => 'ZipArchive not available'];
        }
        $tenantsDir = BASE_PATH . '/tenants';
        if (!is_dir($tenantsDir)) {
            return ['ok' => false, 'message' => 'No tenants directory'];
        }

        $outDir = isset($opts['out_dir']) ? rtrim((string) $opts['out_dir'], '/\\') : self::backupRoot();
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0775, true);
        }
        if (!is_dir($outDir) || !is_writable($outDir)) {
            return ['ok' => false, 'message' => 'Backup folder not writable: ' . $outDir];
        }

        $name = (string) ($opts['name'] ?? ('RC-data-' . date('Ymd-His') . '.zip'));
        $name = basename(str_replace(['..', '/', '\\'], '', $name));
        if ($name === '' || !str_ends_with(strtolower($name), '.zip')) {
            $name .= '.zip';
        }
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
                if ($d === '.' || $d === '..' || $d === 'map.json' || str_starts_with((string) $d, '.')) {
                    continue;
                }
                if (preg_match('/\.(md|txt|json|htaccess)$/i', (string) $d)) {
                    continue;
                }
                if (!is_dir($tenantsDir . '/' . $d)) {
                    continue;
                }
                $tenantIds[] = $d;
            }
        }

        foreach ($tenantIds as $d) {
            $cfg = $tenantsDir . '/' . $d . '/config.json';
            $data = $tenantsDir . '/' . $d . '/data';
            if (is_dir($tenantsDir . '/' . $d) && is_file($cfg)) {
                $zip->addFile($cfg, "tenants/{$d}/config.json");
            }
            if (!is_dir($data)) {
                continue;
            }
            $dataNorm = rtrim(str_replace('\\', '/', $data), '/');
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($data, FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $full = str_replace('\\', '/', $file->getPathname());
                $rel = 'tenants/' . $d . '/data/' . ltrim(substr($full, strlen($dataNorm)), '/');
                if (preg_match('#/(sessions|cache|tmp|backups)/#', $rel)) {
                    continue;
                }
                $zip->addFile($full, $rel);
            }
        }
        $zip->close();
        $bytes = is_file($zipPath) ? (int) filesize($zipPath) : 0;

        $keep = (int) ($opts['keep'] ?? 10);
        $prefix = str_starts_with($name, 'RC-weekly-') ? 'RC-weekly-' : 'RC-data-';
        $all = glob($outDir . '/' . $prefix . '*.zip') ?: [];
        rsort($all);
        foreach (array_slice($all, max(1, $keep)) as $old) {
            @unlink($old);
        }

        return [
            'ok' => true,
            'path' => $zipPath,
            'bytes' => $bytes,
            'message' => 'Backup written: ' . $name . ' (' . self::human($bytes) . ')',
        ];
    }

    

    public static function weeklySundayBackup(bool $force = false): array
    {
        $prevTz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kolkata');
        try {
            $isSunday = ((int) date('w') === 0);
            $weekKey = date('o-\WW');  
            $dir = self::weeklyDir();
            $marker = $dir . '/.last-week';

            if (!$force && !$isSunday) {
                return ['ok' => true, 'skipped' => true, 'message' => 'Weekly backup runs on Sunday IST only'];
            }
            if (!$force && is_file($marker) && trim((string) @file_get_contents($marker)) === $weekKey) {
                return ['ok' => true, 'skipped' => true, 'message' => 'Weekly backup already taken for ' . $weekKey];
            }

            $name = 'RC-weekly-' . date('Ymd') . '.zip';
            $r = self::backupAllTenants([
                'out_dir' => $dir,
                'name' => $name,
                'keep' => 8,
            ]);
            if (!empty($r['ok'])) {
                @file_put_contents($marker, $weekKey . "\n", LOCK_EX);
            }
            return $r;
        } finally {
            @date_default_timezone_set($prevTz);
        }
    }

    private static function human(int $b): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $x = (float) $b;
        while ($x >= 1024 && $i < count($u) - 1) {
            $x /= 1024;
            $i++;
        }
        return (string) (round($x * 10) / 10) . ' ' . $u[$i];
    }
}
