<?php
/**
 * social_icon.php — small brand PNG icons for email signatures.
 * iOS Mail / Gmail treat data:image/svg+xml as attachments; hosted PNG works.
 * Usage: /social_icon.php?n=linkedin&s=36
 */
declare(strict_types=1);

$n = strtolower(preg_replace('/[^a-z]/', '', (string)($_GET['n'] ?? '')) ?? '');
if ($n === 'x') {
    $n = 'twitter';
}
$s = (int)($_GET['s'] ?? 36);
if ($s < 16) {
    $s = 16;
}
if ($s > 64) {
    $s = 64;
}

$colors = [
    'linkedin'  => [0x0A, 0x66, 0xC2],
    'twitter'   => [0x00, 0x00, 0x00],
    'instagram' => [0xE1, 0x30, 0x6C],
    'facebook'  => [0x18, 0x77, 0xF2],
    'youtube'   => [0xFF, 0x00, 0x00],
    'whatsapp'  => [0x25, 0xD3, 0x66],
];
$letters = [
    'linkedin'  => 'in',
    'twitter'   => 'X',
    'instagram' => 'ig',
    'facebook'  => 'f',
    'youtube'   => 'YT',
    'whatsapp'  => 'wa',
];

if ($n === '' || !isset($colors[$n])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unknown network';
    exit;
}


// Prefer pre-shipped PNG (no GD required)
$static = dirname(__FILE__) . '/assets/icons/social/' . $n . '.png';
if (is_file($static) && is_readable($static)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('X-Social-Icon-Cache: static');
    readfile($static);
    exit;
}

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(501);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'GD required';
    exit;
}

$base = dirname(__FILE__);
$cacheDir = $base . '/data/cache/logos';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$cacheFile = $cacheDir . '/social_' . $n . '_' . $s . '.png';
if (is_file($cacheFile) && filesize($cacheFile) > 50) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('X-Social-Icon-Cache: hit');
    readfile($cacheFile);
    exit;
}

$im = imagecreatetruecolor($s, $s);
imagealphablending($im, false);
imagesavealpha($im, true);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
imagefilledrectangle($im, 0, 0, $s, $s, $transparent);
imagealphablending($im, true);

[$r, $g, $b] = $colors[$n];
$bg = imagecolorallocate($im, $r, $g, $b);
$white = imagecolorallocate($im, 255, 255, 255);
$rad = (int)max(3, $s * 0.18);
// Rounded rect approximation
imagefilledrectangle($im, $rad, 0, $s - $rad - 1, $s - 1, $bg);
imagefilledrectangle($im, 0, $rad, $s - 1, $s - $rad - 1, $bg);
imagefilledellipse($im, $rad, $rad, $rad * 2, $rad * 2, $bg);
imagefilledellipse($im, $s - $rad - 1, $rad, $rad * 2, $rad * 2, $bg);
imagefilledellipse($im, $rad, $s - $rad - 1, $rad * 2, $rad * 2, $bg);
imagefilledellipse($im, $s - $rad - 1, $s - $rad - 1, $rad * 2, $rad * 2, $bg);

$text = $letters[$n];
$font = 5; // built-in
$tw = imagefontwidth($font) * strlen($text);
$th = imagefontheight($font);
$x = (int)(($s - $tw) / 2);
$y = (int)(($s - $th) / 2);
imagestring($im, $font, $x, $y, $text, $white);

ob_start();
imagepng($im);
$png = ob_get_clean();
imagedestroy($im);

if ($png !== false && $png !== '') {
    @file_put_contents($cacheFile, $png, LOCK_EX);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');
header('X-Social-Icon-Cache: miss');
echo $png !== false ? $png : '';
