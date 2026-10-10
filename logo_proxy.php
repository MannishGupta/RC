<?php
/**
 * logo_proxy.php — fetch & cache brand logos by domain (banks / OEMs).
 * Server-side logo fetch + disk cache; works on shared hosting with allow_url_fopen.
 * Usage: /logo_proxy.php?d=icicibank.com&sz=256
 *        /logo_proxy.php?d=bmw.in&sz=256
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


// Rate-limit distinct fetches (cache hits do not count) — 30 / 10 min per IP
$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$throttleFile = BASE_PATH . '/data/logo_proxy_throttle.json';
$throttle = [];
if (is_file($throttleFile)) {
    $rawTh = json_decode((string)@file_get_contents($throttleFile), true);
    if (is_array($rawTh)) {
        $throttle = $rawTh;
    }
}
$now = time();
$window = 600;
$maxFetches = 30;
$ipKey = preg_replace('/[^0-9a-fA-F:.]/', '', $clientIp) ?: 'unknown';
$entries = is_array($throttle[$ipKey] ?? null) ? $throttle[$ipKey] : [];
$entries = array_values(array_filter($entries, static fn($ts) => is_int($ts) && ($now - $ts) < $window));
$throttle[$ipKey] = $entries;

$png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5l5+kAAAAASUVORK5CYII=');
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
    if (str_contains(strtolower($ctype), 'svg')) {
        @unlink($cacheFile);
        @unlink($metaFile);
        // fall through to re-fetch as raster or PNG fallback
    } else {
        header('Content-Type: ' . $ctype);
        header('Cache-Control: public, max-age=604800');
        header('X-Logo-Cache: hit');
        readfile($cacheFile);
        exit;
    }
}

$candidates = [
    'https://logo.debounce.com/' . rawurlencode($domain),
    'https://t2.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&size=256&url=https://' . rawurlencode($domain),
    'https://icons.duckduckgo.com/ip3/' . rawurlencode($domain) . '.ico',
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

// Distinct domain fetch counts against rate limit (cache hits already returned)
if (count($entries) >= $maxFetches) {
    http_response_code(429);
    header('Content-Type: image/png');
    header('Retry-After: 600');
    header('X-Logo-Cache: rate-limited');
    echo $png1x1;
    exit;
}
$entries[] = $now;
$throttle[$ipKey] = $entries;
@file_put_contents($throttleFile, json_encode($throttle, JSON_UNESCAPED_UNICODE), LOCK_EX);

foreach ($candidates as $url) {
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 40) {
        continue;
    }
    // Skip tiny error placeholders
    if (strlen($data) < 80) {
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
    } elseif (str_contains($data, '<svg') || str_starts_with(ltrim($data), '<?xml') || str_contains(strtolower($data), '<svg')) {
        // Never cache or serve SVG (scriptable on RC origin)
        continue;
    } elseif (str_starts_with($data, "\x00\x00\x01\x00") || str_starts_with($data, "\x00\x00\x02\x00")) {
        $ctype = 'image/x-icon';
    } else {
        $ctype = 'image/png';
    }
    break;
}

if ($bin === null || !in_array($ctype, ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/x-icon'], true)) {
    // 1x1 transparent PNG fallback — never store/serve SVG or unknown
    $bin = $png1x1;
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
