<?php
declare(strict_types=1);
/**
 * ValueStore — atomic JSON flat-file storage for Value Pack modules.
 * Version: 260921.40
 */
final class ValueStore
{
    public static function dir(): string
    {
        $d = defined('VALUE_DATA_PATH') ? VALUE_DATA_PATH : (BASE_PATH . '/data/value');
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    public static function path(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_\-]/', '', strtolower($name)) ?: 'unknown';
        return self::dir() . '/' . $name . '.json';
    }

    public static function read(string $name, $default = [])
    {
        $path = self::path($name);
        if (!is_file($path)) {
            return $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        return $data === null ? $default : $data;
    }

    public static function write(string $name, $data): bool
    {
        $path = self::path($name);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $path . '.tmp.' . getmypid();
        $fh = @fopen($tmp, 'cb');
        if (!$fh) {
            return false;
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                fclose($fh);
                @unlink($tmp);
                return false;
            }
            ftruncate($fh, 0);
            fwrite($fh, $json);
            fflush($fh);
            flock($fh, LOCK_UN);
            fclose($fh);
            return @rename($tmp, $path);
        } catch (\Throwable $e) {
            @fclose($fh);
            @unlink($tmp);
            return false;
        }
    }

    public static function append(string $name, array $row, int $max = 5000): bool
    {
        $list = self::read($name, []);
        if (!is_array($list)) {
            $list = [];
        }
        $list[] = $row;
        if (count($list) > $max) {
            $list = array_slice($list, -$max);
        }
        return self::write($name, $list);
    }

    public static function audit(string $action, string $entity, array $meta = []): void
    {
        self::append('audit_log', [
            'id' => 'AUD-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6),
            'at' => date('c'),
            'action' => $action,
            'entity' => $entity,
            'meta' => $meta,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ], 8000);
    }
}
