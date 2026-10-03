<?php
/**
 * logo_proxy.php — fetch & cache brand logos by domain (banks / OEMs).
 * Avoids browser-side Clearbit blocks; works on shared hosting with allow_url_fopen.
 * Usage: /logo_proxy.php?d=icicibank.com&sz=128
 *        /logo_proxy.php?d=bmw.in&sz=128
 */
declare(strict_types=1);

$base = __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}

$domain = strtolower(trim((string)($_GET['d'] ?? $_GET['domain'] ?? '')));
$domain = preg_replace('/^https?:\/\//', '', $domain) ?? '';
$domain = preg_replace('/\/.*$/', '', $domain) ?? '';
$domain = preg_replace('/[^a-z0-9.\-]/', '', $domain) ?? '';
$sz = (int)($_GET['sz'] ?? 128);
if ($sz < 32) $sz = 32;
if ($sz > 256) $sz = 256;

if ($domain === '' || str_contains($domain, '..')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bad domain';
    exit;
}

$cacheDir = BASE_PATH . '/data/cache/logos';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cacheKey = preg_replace('/[^a-z0-9.\-]/', '_', $domain) . '_' . $sz;
$cacheFile = $cacheDir . '/' . $cacheKey . '.img';
$metaFile = $cacheDir . '/' . $cacheKey . '.meta';

// Serve cache if fresh (< 30 days)
if (is_file($cacheFile) && is_readable($cacheFile) && (time() - (int)@filemtime($cacheFile)) < 2592000) {
    $ctype = 'image/png';
    if (is_file($metaFile)) {
        $ctype = trim((string)@file_get_contents($metaFile)) ?: $ctype;
    }
    header('Content-Type: ' . $ctype);
    header('Cache-Control: public, max-age=604800');
    header('X-Logo-Cache: hit');
    readfile($cacheFile);
    exit;
}

$candidates = [
    'https://www.google.com/s2/favicons?domain=' . rawurlencode($domain) . '&sz=' . $sz,
    'https://icons.duckduckgo.com/ip3/' . rawurlencode($domain) . '.ico',
    'https://logo.clearbit.com/' . rawurlencode($domain),
];

$bin = null;
$ctype = 'image/png';
$ctx = stream_context_create([
    'http' => [
        'timeout' => 6,
        'user_agent' => 'ResourceCentre-LogoProxy/1.0',
        'follow_location' => 1,
        'max_redirects' => 3,
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

foreach ($candidates as $url) {
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 40) {
        continue;
    }
    // Skip tiny error placeholders
    if (strlen($data) < 80 && str_contains($url, 'clearbit')) {
        continue;
    }
    $bin = $data;
    if (str_starts_with($data, "\x89PNG")) {
        $ctype = 'image/png';
    } elseif (str_starts_with($data, "\xff\xd8\xff")) {
        $ctype = 'image/jpeg';
    } elseif (str_starts_with($data, 'GIF')) {
        $ctype = 'image/gif';
    } elseif (str_starts_with($data, 'RIFF')) {
        $ctype = 'image/webp';
    } elseif (str_contains($data, '<svg') || str_starts_with(ltrim($data), '<?xml')) {
        $ctype = 'image/svg+xml';
    } elseif (str_starts_with($data, "\x00\x00\x01\x00") || str_starts_with($data, "\x00\x00\x02\x00")) {
        $ctype = 'image/x-icon';
    } else {
        $ctype = 'image/png';
    }
    break;
}

if ($bin === null) {
    // 1x1 transparent PNG fallback so UI does not show broken icon
    $bin = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5l5+kAAAAASUVORK5CYII=');
    $ctype = 'image/png';
    header('X-Logo-Cache: miss-empty');
} else {
    @file_put_contents($cacheFile, $bin, LOCK_EX);
    @file_put_contents($metaFile, $ctype, LOCK_EX);
    header('X-Logo-Cache: miss-store');
}

header('Content-Type: ' . $ctype);
header('Cache-Control: public, max-age=86400');
echo $bin;
