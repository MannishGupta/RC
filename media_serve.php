<?php
/**
 * Version: 260921.51
 * Public media gateway — serves files from data/media/images (and legacy images/)
 * under the stable public URL shape /images/{filename}.
 *
 * Why: IMG_PATH moved under data/, and .htaccess denies HTTP access to /data/.
 * Card/team markup still uses /images/photo.webp → 404 on Linux without this bridge.
 */
declare(strict_types=1);

$base = __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
if (!defined('IMG_PATH')) {
    define('IMG_PATH', DATA_PATH . '/media/images');
}

$f = (string)($_GET['f'] ?? '');
$f = str_replace(['\\', "\0"], ['/', ''], $f);
$f = basename($f);
if ($f === '' || $f === '.' || $f === '..') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

$searchDirs = [];
if (is_dir(IMG_PATH)) {
    $searchDirs[] = IMG_PATH;
}
$legacy = BASE_PATH . '/images';
if (is_dir($legacy)) {
    $searchDirs[] = $legacy;
}
foreach ([
    (defined('DATA_PATH') ? DATA_PATH : '') . '/images',
    (defined('DATA_PATH') ? DATA_PATH : '') . '/media',
    (defined('DATA_PATH') ? DATA_PATH : '') . '/media/logos',
    (defined('DATA_PATH') ? DATA_PATH : '') . '/locations',
] as $extra) {
    if ($extra !== '' && is_dir($extra) && !in_array($extra, $searchDirs, true)) {
        $searchDirs[] = $extra;
    }
}

$resolved = null;

$mediaType = strtolower(trim((string)($_GET['t'] ?? 'img')));
if ($mediaType === 'doc' || $mediaType === 'docs') {
    $docPath = defined('DOC_PATH') ? DOC_PATH : (DATA_PATH . '/media/docs');
    if (is_dir($docPath)) {
        array_unshift($searchDirs, $docPath);
    }
}
foreach ($searchDirs as $dir) {
    $candidate = $dir . DIRECTORY_SEPARATOR . $f;
    if (is_file($candidate) && is_readable($candidate)) {
        $resolved = $candidate;
        break;
    }
}

// Linux case-insensitive fallback (Windows hosts are usually case-insensitive)
if ($resolved === null) {
    $lower = strtolower($f);
    foreach ($searchDirs as $dir) {
        $list = @scandir($dir);
        if (!is_array($list)) {
            continue;
        }
        foreach ($list as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower($entry) === $lower) {
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path) && is_readable($path)) {
                    $resolved = $path;
                    break 2;
                }
            }
        }
    }
}

if ($resolved === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Not found';
    exit;
}

$real = realpath($resolved);
$allowed = false;
foreach ($searchDirs as $dir) {
    $root = realpath($dir);
    if ($root && $real && str_starts_with($real, $root)) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$map = [
    'webp' => 'image/webp',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
    'bmp'  => 'image/bmp',
    'avif' => 'image/avif',
];
if (!isset($map[$ext])) {
    http_response_code(415);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unsupported type';
    exit;
}

$mtime = (int) @filemtime($real);
$size  = (int) @filesize($real);
$etag  = '"' . md5($real . '|' . $mtime . '|' . $size) . '"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $map[$ext]);
header('Content-Length: ' . (string)$size);
header('Cache-Control: public, max-age=604800, immutable');
header('Access-Control-Allow-Origin: *');
header('Cross-Origin-Resource-Policy: cross-origin');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('X-Content-Type-Options: nosniff');

$fp = fopen($real, 'rb');
if ($fp) {
    fpassthru($fp);
    fclose($fp);
} else {
    if (!headers_sent()) {
    header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
    header('X-Content-Type-Options: nosniff');
}
readfile($real);
}
exit;
