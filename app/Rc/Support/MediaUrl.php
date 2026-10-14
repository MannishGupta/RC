<?php
declare(strict_types=1);
namespace App\Rc\Support;

final class MediaUrl
{
    public static function image(string $filename, string $fallback = ''): string
    {
        $filename = basename(str_replace(['\\', "\0"], ['/', ''], $filename));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return $fallback;
        }
        $base = self::appBase();
        return $base . '/media_serve.php?t=img&f=' . rawurlencode($filename);
    }

    public static function document(string $filename, string $fallback = ''): string
    {
        $filename = basename(str_replace(['\\', "\0"], ['/', ''], $filename));
        if ($filename === '') {
            return $fallback;
        }
        $base = self::appBase();
        return $base . '/media_serve.php?t=doc&f=' . rawurlencode($filename);
    }

    public static function absolute(string $pathOrUrl): string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $pathOrUrl) || str_starts_with($pathOrUrl, 'data:')) {
            return $pathOrUrl;
        }
        if (str_starts_with($pathOrUrl, '//')) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            return ($https ? 'https:' : 'http:') . $pathOrUrl;
        }
        $base = self::publicOrigin();
        if (str_starts_with($pathOrUrl, '/')) {
            return $base . $pathOrUrl;
        }
        return $base . '/' . ltrim($pathOrUrl, '/');
    }

    private static function appBase(): string
    {
         
        return '';
    }

    private static function publicOrigin(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https://' : 'http://') . $host;
    }
}
