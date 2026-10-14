<?php
declare(strict_types=1);
namespace App\Rc\Ui;

final class Asset
{
    public static function url(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $v = defined('APP_VERSION') ? (string) APP_VERSION : '1';
        $sep = str_contains($relativePath, '?') ? '&' : '?';
        return '/' . $relativePath . $sep . 'v=' . rawurlencode($v);
    }

    public static function css(string $relativePath): string
    {
        return '<link rel="stylesheet" href="' . htmlspecialchars(self::url($relativePath), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function js(string $relativePath, bool $defer = true): string
    {
        $d = $defer ? ' defer' : '';
        return '<script src="' . htmlspecialchars(self::url($relativePath), ENT_QUOTES, 'UTF-8') . '"' . $d . '></script>';
    }
}
