<?php
declare(strict_types=1);
/**
 * Fragment cache for public card HTML.
 * Version: 260926.33
 */
if (!defined('BASE_PATH')) { exit; }

class CardCache
{
    public static function dir(): string
    {
        $root = defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data');
        $d = rtrim(str_replace('\\', '/', (string) $root), '/') . '/cache/cards';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d;
    }

    public static function key(string $card, string $slug): string
    {
        $ver = defined('APP_VERSION') ? APP_VERSION : '1';
        $tenant = defined('TENANT_ID') ? TENANT_ID : 'default';
        return hash('sha256', $tenant . '|' . $card . '|' . strtolower($slug) . '|' . $ver);
    }

    public static function get(string $card, string $slug): ?string
    {
        $f = self::dir() . '/' . self::key($card, $slug) . '.html';
        if (!is_file($f)) {
            return null;
        }
        // 1 hour soft TTL
        if (filemtime($f) < time() - 3600) {
            return null;
        }
        $html = @file_get_contents($f);
        return is_string($html) && $html !== '' ? $html : null;
    }

    public static function put(string $card, string $slug, string $html): void
    {
        if ($html === '' || strlen($html) < 100) {
            return;
        }
        $f = self::dir() . '/' . self::key($card, $slug) . '.html';
        if (class_exists('PathJail')) {
            try {
                PathJail::assertWritable($f, defined('DATA_PATH') ? DATA_PATH : null);
            } catch (Throwable $e) {
                return;
            }
        }
        @file_put_contents($f, $html, LOCK_EX);
    }

    public static function bust(?string $slug = null): void
    {
        $dir = self::dir();
        foreach (glob($dir . '/*.html') ?: [] as $f) {
            if ($slug === null) {
                @unlink($f);
                continue;
            }
            // bust all versions for this deploy will happen via APP_VERSION in key
            @unlink($f);
        }
    }
}
