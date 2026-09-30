<?php
declare(strict_types=1);
if (!defined('BASE_PATH')) { exit; }
function rc_asset(string $path): string {
    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
    $ver = defined('APP_VERSION') ? (string)APP_VERSION : '1';
    return $path . (str_contains($path, '?') ? '&' : '?') . 'v=' . rawurlencode($ver);
}
function rc_asset_mtime(string $relative): string {
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $full = BASE_PATH . '/' . $relative;
    $ver = defined('APP_VERSION') ? (string)APP_VERSION : '1';
    if (is_file($full)) { $ver .= '.' . (string)filemtime($full); }
    return '/' . $relative . '?v=' . rawurlencode($ver);
}
