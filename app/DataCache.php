<?php
/**
 * AppDataCache — optional SQLite mirror of JSON namespaces for faster reads
 * and stable ETags. Falls back to file cache when SQLite is unavailable.
 *
 * Version: 260926.29
 */
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    exit('No direct script access');
}

class AppDataCache
{
    private static ?\PDO $pdo = null;
    private static string $root = '';

    public static function dataRoot(): string
    {
        if (!empty($GLOBALS['RC_OPT_DATA_PATH']) && is_string($GLOBALS['RC_OPT_DATA_PATH'])) {
            return rtrim(str_replace('\\', '/', $GLOBALS['RC_OPT_DATA_PATH']), '/');
        }
        if (defined('DATA_PATH')) {
            return rtrim(str_replace('\\', '/', (string) DATA_PATH), '/');
        }
        return rtrim(str_replace('\\', '/', BASE_PATH . '/data'), '/');
    }

    public static function cacheDir(): string
    {
        $d = self::dataRoot() . '/cache';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    public static function sqliteAvailable(): bool
    {
        return class_exists('PDO') && in_array('sqlite', \PDO::getAvailableDrivers(), true);
    }

    private static function pdo(): ?\PDO
    {
        if (!self::sqliteAvailable()) {
            return null;
        }
        $root = self::dataRoot();
        if (self::$pdo instanceof \PDO && self::$root === $root) {
            return self::$pdo;
        }
        self::$pdo = null;
        self::$root = $root;
        $dir = self::cacheDir();
        $dbFile = $dir . '/rc_data.sqlite';
        try {
            $pdo = new \PDO('sqlite:' . $dbFile, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA synchronous=NORMAL');
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS ns_cache (
                    ns TEXT PRIMARY KEY NOT NULL,
                    payload TEXT NOT NULL,
                    etag TEXT NOT NULL,
                    row_count INTEGER NOT NULL DEFAULT 0,
                    updated_at TEXT NOT NULL
                )'
            );
            self::$pdo = $pdo;
            return self::$pdo;
        } catch (\Throwable $e) {
            if (class_exists('AppLog')) {
                AppLog::error('AppDataCache SQLite open failed: ' . $e->getMessage());
            }
            return null;
        }
    }

    public static function etagFor($data): string
    {
        $json = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '';
        }
        return hash('sha256', $json);
    }

    /**
     * @param mixed $data
     */
    public static function put(string $ns, $data): void
    {
        $ns = strtolower(trim($ns));
        if ($ns === '') {
            return;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        $etag = self::etagFor($json);
        $count = is_array($data) ? (isset($data[0]) || $data === [] ? count($data) : 1) : 0;
        $now = date('c');

        $pdo = self::pdo();
        if ($pdo) {
            try {
                $st = $pdo->prepare(
                    'INSERT INTO ns_cache (ns, payload, etag, row_count, updated_at)
                     VALUES (:ns, :payload, :etag, :cnt, :at)
                     ON CONFLICT(ns) DO UPDATE SET
                       payload=excluded.payload,
                       etag=excluded.etag,
                       row_count=excluded.row_count,
                       updated_at=excluded.updated_at'
                );
                $st->execute([
                    ':ns' => $ns,
                    ':payload' => $json,
                    ':etag' => $etag,
                    ':cnt' => $count,
                    ':at' => $now,
                ]);
                return;
            } catch (\Throwable $e) {
                // fall through to file cache
            }
        }

        $file = self::cacheDir() . '/ns_' . preg_replace('/[^a-z0-9_\-]/', '', $ns) . '.json';
        @file_put_contents(
            $file,
            json_encode(['etag' => $etag, 'count' => $count, 'updated_at' => $now, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * @return array{data:mixed,etag:string}|null
     */
    public static function get(string $ns): ?array
    {
        $ns = strtolower(trim($ns));
        if ($ns === '') {
            return null;
        }
        $pdo = self::pdo();
        if ($pdo) {
            try {
                $st = $pdo->prepare('SELECT payload, etag FROM ns_cache WHERE ns = :ns LIMIT 1');
                $st->execute([':ns' => $ns]);
                $row = $st->fetch();
                if ($row && isset($row['payload'])) {
                    $data = json_decode((string) $row['payload'], true);
                    return ['data' => $data, 'etag' => (string) $row['etag']];
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $file = self::cacheDir() . '/ns_' . preg_replace('/[^a-z0-9_\-]/', '', $ns) . '.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = json_decode((string) @file_get_contents($file), true);
        if (!is_array($raw) || !array_key_exists('data', $raw)) {
            return null;
        }
        return ['data' => $raw['data'], 'etag' => (string) ($raw['etag'] ?? '')];
    }

    public static function invalidate(string $ns): void
    {
        $ns = strtolower(trim($ns));
        $pdo = self::pdo();
        if ($pdo) {
            try {
                $st = $pdo->prepare('DELETE FROM ns_cache WHERE ns = :ns');
                $st->execute([':ns' => $ns]);
            } catch (\Throwable $e) {
            }
        }
        $file = self::cacheDir() . '/ns_' . preg_replace('/[^a-z0-9_\-]/', '', $ns) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function flushAll(): void
    {
        self::$pdo = null;
        $dir = self::cacheDir();
        $db = $dir . '/rc_data.sqlite';
        if (is_file($db)) {
            @unlink($db);
        }
        foreach (glob($dir . '/ns_*.json') ?: [] as $f) {
            @unlink($f);
        }
        // recreate empty sqlite on next put
    }

    /**
     * Rebuild cache for common namespaces from AppDB (optimizer / boot).
     */
    public static function rebuildFromAppDb(): array
    {
        $done = [];
        if (!class_exists('AppDB')) {
            return $done;
        }
        $list = [
            'team', 'company', 'bank', 'docs', 'events', 'locations',
            'departments', 'designations', 'cartags', 'cctv', 'leads', 'statutory',
        ];
        foreach ($list as $ns) {
            try {
                $data = AppDB::read($ns);
                self::put($ns, $data);
                $done[$ns] = is_array($data) ? (isset($data[0]) || $data === [] ? count($data) : 1) : 0;
            } catch (\Throwable $e) {
                $done[$ns] = 'error';
            }
        }
        return $done;
    }
}
