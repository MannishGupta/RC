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

// Super-admin master brand logos: ?m=banks/icici.svg or oems/tata.png
$m = (string)($_GET['m'] ?? '');
if ($m !== '') {
    $m = str_replace(['\\', "\0"], ['/', ''], $m);
    $m = ltrim($m, '/');
    if (!preg_match('#^(banks|oems)/([a-z0-9][a-z0-9\-]{0,80})\.(svg|png|webp)$#i', $m, $mm)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Bad master logo path';
        exit;
    }
    $kind = strtolower($mm[1]);
    $slug = strtolower($mm[2]);
    $ext = strtolower($mm[3]);
    $root = DATA_PATH . '/masters/logos/' . $kind;
    $path = $root . '/' . $slug . '.' . $ext;
    if (class_exists('PathJail') || is_file(BASE_PATH . '/app/PathJail.php')) {
        if (!class_exists('PathJail')) {
            require_once BASE_PATH . '/app/PathJail.php';
        }
        try {
            PathJail::assertWritable($path, $root);
        } catch (Throwable $e) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden';
            exit;
        }
    }
    $real = realpath($path);
    $rootReal = realpath($root);
    if ($real === false || $rootReal === false || !str_starts_with($real, $rootReal) || !is_file($real)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }
    $ctype = match ($ext) {
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $ctype);
    header('X-Content-Type-Options: nosniff');
    if ($ext === 'svg') {
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:");
    }
    header('Cache-Control: public, max-age=604800');
    if (preg_match('/^company-(logo|favicon|cover)\./i', basename($real ?? ''))) { header('Cache-Control: no-cache, must-revalidate'); header('Pragma: no-cache'); }
    readfile($real);
    exit;
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
    (defined('DATA_PATH') ? DATA_PATH : '') . '/media/locations',
    (defined('DATA_PATH') ? DATA_PATH : '') . '/locations',
    (defined('DATA_PATH') ? DATA_PATH : '') . '/uploads',
    BASE_PATH . '/uploads',
    BASE_PATH . '/assets/locations',
] as $extra) {
    if ($extra !== '' && is_dir($extra) && !in_array($extra, $searchDirs, true)) {
        $searchDirs[] = $extra;
    }
}

$resolved = null;

// Fast path: primary image dir only (avoids multi-dir + scandir on the hot path)
if ($resolved === null && defined('IMG_PATH') && is_dir(IMG_PATH)) {
    $cand = rtrim(IMG_PATH, '/\\') . DIRECTORY_SEPARATOR . $f;
    if (is_file($cand) && is_readable($cand)) {
        $resolved = $cand;
    }
}

$mediaType = strtolower(trim((string)($_GET['t'] ?? 'img')));
if ($mediaType === 'doc' || $mediaType === 'docs') {
    $docPath = defined('DOC_PATH') ? DOC_PATH : (DATA_PATH . '/media/docs');
    if (is_dir($docPath)) {
        array_unshift($searchDirs, $docPath);
    }
}
// Also look in common mediakit subfolders
foreach ([
    (defined('DATA_PATH') ? DATA_PATH : '') . '/media/images/mediakit',
    (defined('IMG_PATH') ? IMG_PATH : '') . '/mediakit',
    (defined('DOC_PATH') ? DOC_PATH : ''),
] as $extraMk) {
    if ($extraMk !== '' && is_dir($extraMk) && !in_array($extraMk, $searchDirs, true)) {
        $searchDirs[] = $extraMk;
    }
}

if ($resolved === null) {
foreach ($searchDirs as $dir) {
    $candidate = $dir . DIRECTORY_SEPARATOR . $f;
    if (is_file($candidate) && is_readable($candidate)) {
        $resolved = $candidate;
        break;
    }
}
}

// Sibling extension fallback (optimizer may have rewritten .png → .webp)
if ($resolved === null) {
    $stem = pathinfo($f, PATHINFO_FILENAME);
    $alts = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'svg'];
    foreach ($searchDirs as $dir) {
        foreach ($alts as $a) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $stem . '.' . $a;
            if (is_file($candidate) && is_readable($candidate)) {
                $resolved = $candidate;
                $f = $stem . '.' . $a;
                break 2;
            }
        }
    }
}

// Linux case-insensitive fallback only (Windows FS is already case-insensitive)
if ($resolved === null && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    $lower = strtolower($f);
    foreach ($searchDirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
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
    'ico'  => 'image/x-icon',
    'bmp'  => 'image/bmp',
    'avif' => 'image/avif',
    'svg'  => 'image/svg+xml',
    'pdf'  => 'application/pdf',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mov'  => 'video/quicktime',
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
if ($ext === 'pdf') {
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $f) . '"');
}
if ($ext === 'svg') {
    // Allow SVG in <img> (sandbox blocks some browsers from painting the image)
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:");
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $f) . '"');
}
header('Content-Length: ' . (string)$size);
// Company brand images keep a FIXED filename (company-logo.*, company-favicon.*,
// company-cover.*), so the bytes behind a URL change when the logo is replaced.
// 'immutable' + 7 days made browsers keep showing the previous file for up to a
// week on every page that requests the plain /images/ URL (public cards, favicon,
// share previews). They must revalidate; the ETag below makes that a cheap 304.
if (preg_match('/^company-(logo|favicon|cover)\./i', basename($real))) {
    header('Cache-Control: no-cache, must-revalidate');
} else {
    header('Cache-Control: public, max-age=604800, immutable');
}
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
