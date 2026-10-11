<?php
declare(strict_types=1);
/**
 * status/serve.php — stream upload files from tenant OR legacy status/uploads.
 * Query: ?f=uploads/…  (no .., no absolute paths)
 */
require_once __DIR__ . '/lib.php';

$rel = (string)($_GET['f'] ?? '');
$rel = str_replace('\\', '/', $rel);
$rel = ltrim($rel, '/');
if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Bad path');
}
if (!str_starts_with($rel, 'uploads/')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Forbidden');
}
$sub = substr($rel, strlen('uploads/'));
$candidates = [];
if (defined('UPLOAD_DIR') && UPLOAD_DIR !== '') {
    $candidates[] = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, UPLOAD_DIR), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
}
$candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $sub);

$real = false;
$rootOk = false;
foreach ($candidates as $abs) {
    if (!is_file($abs)) {
        continue;
    }
    $r = realpath($abs);
    if ($r === false) {
        continue;
    }
    // Jail: must stay under UPLOAD_DIR or status/uploads
    $allowedRoots = [];
    if (defined('UPLOAD_DIR') && UPLOAD_DIR !== '') {
        $tr = realpath(rtrim(UPLOAD_DIR, '/\\'));
        if ($tr !== false) {
            $allowedRoots[] = $tr;
        }
    }
    $lr = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
    if ($lr !== false) {
        $allowedRoots[] = $lr;
    }
    foreach ($allowedRoots as $root) {
        if (str_starts_with($r, $root)) {
            $real = $r;
            $rootOk = true;
            break 2;
        }
    }
}

if (!$rootOk || $real === false) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Not found');
}

$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $m = @mime_content_type($real);
    if (is_string($m) && $m !== '') {
        $mime = $m;
    }
}
$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$map = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    'mp4' => 'video/mp4', 'webm' => 'video/webm',
];
if (isset($map[$ext])) {
    $mime = $map[$ext];
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
if (str_contains($mime, 'svg')) {
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
}
header('Cache-Control: public, max-age=86400');
readfile($real);
exit;
