<?php
declare(strict_types=1);
/**
 * Persistent deleted-tenant registry so code uploads cannot resurrect tenants.
 * File: tenants/deleted.json  (outside replaceable app code; survives FTP of PHP only)
 * Version: 20261003.01
 */
final class TenantTombstone
{
    public static function path(): string
    {
        return (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/tenants/deleted.json';
    }

    /** @return array<string, array{deleted_at:string, by?:string}> */
    public static function all(): array
    {
        $file = self::path();
        if (!is_file($file)) {
            return [];
        }
        $raw = json_decode((string) @file_get_contents($file), true);
        if (!is_array($raw)) {
            return [];
        }
        // Support list form ["id1","id2"] or map form { "id": { ... } }
        $out = [];
        $isList = array_keys($raw) === range(0, count($raw) - 1);
        if ($isList) {
            foreach ($raw as $id) {
                $id = strtolower(preg_replace('/[^a-z0-9\-_]/', '', (string) $id));
                if ($id !== '') {
                    $out[$id] = ['deleted_at' => ''];
                }
            }
            return $out;
        }
        foreach ($raw as $id => $meta) {
            $id = strtolower(preg_replace('/[^a-z0-9\-_]/', '', (string) $id));
            if ($id === '') {
                continue;
            }
            $out[$id] = is_array($meta) ? $meta : ['deleted_at' => (string) $meta];
        }
        return $out;
    }

    public static function isDeleted(string $tenantId): bool
    {
        $tenantId = strtolower(preg_replace('/[^a-z0-9\-_]/', '', $tenantId));
        if ($tenantId === '' || $tenantId === 'default') {
            return false;
        }
        return isset(self::all()[$tenantId]);
    }

    public static function mark(string $tenantId, string $by = 'super_admin'): bool
    {
        $tenantId = strtolower(preg_replace('/[^a-z0-9\-_]/', '', $tenantId));
        if ($tenantId === '' || $tenantId === 'default') {
            return false;
        }
        $all = self::all();
        $all[$tenantId] = [
            'deleted_at' => date('c'),
            'by' => $by,
        ];
        return self::write($all);
    }

    /** Re-provisioning a deleted id clears the tombstone intentionally. */
    public static function clear(string $tenantId): bool
    {
        $tenantId = strtolower(preg_replace('/[^a-z0-9\-_]/', '', $tenantId));
        $all = self::all();
        if (!isset($all[$tenantId])) {
            return true;
        }
        unset($all[$tenantId]);
        return self::write($all);
    }

    /** @param array<string, array{deleted_at:string, by?:string}> $all */
    private static function write(array $all): bool
    {
        $dir = dirname(self::path());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return @file_put_contents(self::path(), $json, LOCK_EX) !== false;
    }

    /**
     * If a deleted tenant folder was re-uploaded with code, remove it again
     * (map entries already stripped on original delete).
     */
    public static function enforceMissing(): array
    {
        $removed = [];
        $base = defined('BASE_PATH') ? BASE_PATH . '/tenants' : '';
        if ($base === '' || !is_dir($base)) {
            return $removed;
        }
        foreach (self::all() as $id => $_meta) {
            if ($id === 'default') {
                continue;
            }
            $folder = $base . '/' . $id;
            if (is_dir($folder)) {
                self::rrmdir($folder);
                $removed[] = $id;
            }
        }
        return $removed;
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $path = $file->getPathname();
                if ($file->isDir()) {
                    @rmdir($path);
                } else {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        } catch (Throwable $e) {
            // best-effort
        }
    }
}
