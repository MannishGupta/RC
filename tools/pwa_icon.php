<?php // Version: 260916.14
declare(strict_types=1);
/**
 * tools/pwa_icon.php — Renders home-screen icons from the company logo.
 *
 * WHY GENERATED RATHER THAN UPLOADED
 * A PWA needs at minimum 192px and 512px PNGs, plus a maskable variant.
 * Asking an admin to produce three correctly-sized, correctly-padded icons —
 * and redo them whenever the logo changes — is a step that quietly never
 * happens, leaving the installed app showing a blank or wrong icon. Rendering
 * from the logo already in Company Setup means the icon simply tracks it.
 *
 * MASKABLE SAFE ZONE
 * Android crops adaptive icons to a circle, squircle or rounded square
 * depending on the launcher. The spec guarantees only the central 80% is
 * always visible, so `?maskable=1` insets the logo to ~60% of the canvas and
 * fills the surround with the brand colour. Without that inset, a logo laid
 * edge-to-edge loses its outer ring on most Android launchers.
 *
 * Output is cached by the browser for a day; regeneration is cheap and the
 * request only happens at install time.
 */

$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
// BUG FIX: this file defined DATA_PATH/IMG_PATH/DOC_PATH directly from
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH'))  define('IMG_PATH',  BASE_PATH . '/images');
if (!defined('DOC_PATH'))  define('DOC_PATH',  BASE_PATH . '/docs');

require_once BASE_PATH . '/app/bootstrap.php';

$size     = (int)($_GET['size'] ?? 192);
$size     = max(48, min(1024, $size));            // clamp: no arbitrary canvas sizes
$maskable = !empty($_GET['maskable']);
$product  = !empty($_GET['product']); // Arthsathi product mark for install prompt

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(501);
    header('Content-Type: text/plain');
    exit('GD extension unavailable — cannot render icons.');
}

$company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$name    = trim((string)($company['name'] ?? '')) ?: 'Directory';

// Product mode (install prompt): Arthsathi brand colour + product favicon/icon
if (!empty($product)) {
    $hex = '0078D4'; // Fluent product blue
    $name = 'Resource Centre';
} else {
    $hex = ltrim((string)($company['brand_color'] ?? '#0f172a'), '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) $hex = '0f172a';
}
[$bR, $bG, $bB] = [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];

$im = imagecreatetruecolor($size, $size);
imagealphablending($im, true);
imagesavealpha($im, true);
imagefilledrectangle($im, 0, 0, $size, $size, imagecolorallocate($im, $bR, $bG, $bB));

$logoFile = '';
$drawn = false;

// 1) Product install icons: Arthsathi favicon / brand assets (raster siblings preferred)
if (!empty($product)) {
    // HARDCODED — only Arthsathi product brand (never tenant logo)
    $candidates = [
        BASE_PATH . '/assets/brand/arthsathi-icon-512.png',
        BASE_PATH . '/assets/brand/arthsathi-icon-192.png',
        BASE_PATH . '/assets/brand/arthsathi-icon.png',
        BASE_PATH . '/favicon-512.png',
        BASE_PATH . '/favicon-192.png',
        BASE_PATH . '/apple-touch-icon-180.png',
    ];
    foreach ($candidates as $cand) {
        if (is_file($cand)) { $logoFile = $cand; break; }
    }
    // SVG via Imagick when no PNG shipped
    if ($logoFile === '') {
        foreach ([BASE_PATH . '/assets/brand/arthsathi-icon.svg', BASE_PATH . '/assets/brand/arthsathi.svg', BASE_PATH . '/favicon.svg'] as $svg) {
            if (!is_file($svg)) continue;
            if (extension_loaded('imagick')) {
                try {
                    $imx = new Imagick();
                    $imx->setBackgroundColor(new ImagickPixel('transparent'));
                    $imx->readImage($svg);
                    $imx->setImageFormat('png32');
                    $imx->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, true);
                    $blob = $imx->getImageBlob();
                    $imx->clear();
                    $src = @imagecreatefromstring($blob);
                    if ($src) {
                        $inset = $maskable ? 0.60 : 0.72;
                        $box = (int)round($size * $inset);
                        $sw = imagesx($src); $sh = imagesy($src);
                        $scale = min($box / max(1,$sw), $box / max(1,$sh));
                        $dw = max(1, (int)round($sw * $scale));
                        $dh = max(1, (int)round($sh * $scale));
                        $dx = (int)(($size - $dw) / 2);
                        $dy = (int)(($size - $dh) / 2);
                        imagealphablending($im, true);
                        imagecopyresampled($im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
                        imagedestroy($src);
                        $drawn = true;
                    }
                } catch (Throwable $e) { /* fall through */ }
            }
            if ($drawn) break;
        }
    }
}

// 2) Tenant company logo (non-product mode, or product fallback if no brand file)
if ($logoFile === '' && empty($product) && !empty($company['logo'])) {
    $logoFile = (defined('IMG_PATH') ? IMG_PATH : (BASE_PATH . '/images')) . DIRECTORY_SEPARATOR . basename((string)$company['logo']);
}
if (!$drawn && $logoFile !== '' && is_file($logoFile)) {
    $info = @getimagesize($logoFile);
    $src = null;
    if ($info) {
        $src = match ($info['mime']) {
            'image/jpeg', 'image/pjpeg' => @imagecreatefromjpeg($logoFile),
            'image/png'                 => @imagecreatefrompng($logoFile),
            'image/webp'                => @imagecreatefromwebp($logoFile),
            'image/gif'                 => @imagecreatefromgif($logoFile),
            default                     => null,
        };
    }
    if ($src) {
        // 60% inset for maskable (safe zone), 72% otherwise.
        $inset = $maskable ? 0.60 : 0.72;
        $box   = (int)round($size * $inset);
        $sw = imagesx($src); $sh = imagesy($src);
        $scale = min($box / $sw, $box / $sh);
        $dw = max(1, (int)round($sw * $scale));
        $dh = max(1, (int)round($sh * $scale));
        $dx = (int)(($size - $dw) / 2);
        $dy = (int)(($size - $dh) / 2);

        imagealphablending($im, true);
        imagecopyresampled($im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
        $drawn = true;
    }
}

if (!$drawn) {
    // No logo on file — render initials rather than shipping a blank tile.
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', $name) ?: [], 0, 2) as $w) {
        $l = preg_replace('/[^A-Za-z]/', '', $w);
        if ($l !== '') $initials .= strtoupper($l[0]);
    }
    if ($initials === '') $initials = !empty($product) ? 'RC' : 'A';
    if (!empty($product)) $initials = 'RC';

    $white = imagecolorallocate($im, 255, 255, 255);
    $font  = null;
    // Local Inter, if it has been uploaded to assets/fonts/, takes priority
    // over the system fallback — same font family as the OG-image generator
    // (app/bootstrap.php's AppMedia::getFont()) once that path was corrected,
    // so a PWA install icon's initials and an OG-image's initials render in
    // the same typeface rather than two different ones depending on which
    // code path happened to generate the image.
    foreach ([
        BASE_PATH . '/assets/fonts/Inter-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        'C:\\Windows\\Fonts\\arialbd.ttf',
    ] as $f) { if (is_file($f)) { $font = $f; break; } }

    if ($font && function_exists('imagettftext')) {
        $pt = (int)round($size * ($maskable ? 0.26 : 0.34));
        $bb = imagettfbbox($pt, 0, $font, $initials);
        $tw = abs($bb[2] - $bb[0]); $th = abs($bb[7] - $bb[1]);
        imagettftext($im, $pt, 0, (int)(($size - $tw) / 2), (int)(($size + $th) / 2), $white, $font, $initials);
    } else {
        // GD bitmap fallback, scaled up — ugly but never blank.
        $tmp = imagecreatetruecolor(imagefontwidth(5) * strlen($initials) + 4, imagefontheight(5) + 4);
        imagefilledrectangle($tmp, 0, 0, imagesx($tmp), imagesy($tmp), imagecolorallocate($tmp, $bR, $bG, $bB));
        imagestring($tmp, 5, 2, 2, $initials, imagecolorallocate($tmp, 255, 255, 255));
        $sc = imagescale($tmp, (int)($size * 0.5), (int)($size * 0.28), IMG_NEAREST_NEIGHBOUR);
        imagecopy($im, $sc, (int)(($size - imagesx($sc)) / 2), (int)(($size - imagesy($sc)) / 2), 0, 0, imagesx($sc), imagesy($sc));
        imagedestroy($tmp); imagedestroy($sc);
    }
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($im);
imagedestroy($im);
