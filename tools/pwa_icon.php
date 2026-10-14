<?php  
declare(strict_types=1);

$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
 
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH'))  define('IMG_PATH',  BASE_PATH . '/images');
if (!defined('DOC_PATH'))  define('DOC_PATH',  BASE_PATH . '/docs');

require_once BASE_PATH . '/app/bootstrap.php';

$size     = (int)($_GET['size'] ?? 192);
$size     = max(48, min(1024, $size));             
$maskable = !empty($_GET['maskable']);
$product  = !empty($_GET['product']);  

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(501);
    header('Content-Type: text/plain');
    exit('GD extension unavailable — cannot render icons.');
}

$company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$name    = trim((string)($company['name'] ?? '')) ?: 'Directory';

 
if (!empty($product)) {
    $hex = '0078D4';  
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

 
if (!empty($product)) {
     
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
                } catch (Throwable $e) {   }
            }
            if ($drawn) break;
        }
    }
}

 
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
     
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', $name) ?: [], 0, 2) as $w) {
        $l = preg_replace('/[^A-Za-z]/', '', $w);
        if ($l !== '') $initials .= strtoupper($l[0]);
    }
    if ($initials === '') $initials = !empty($product) ? 'RC' : 'A';
    if (!empty($product)) $initials = 'RC';

    $white = imagecolorallocate($im, 255, 255, 255);
    $font  = null;
     
     
     
     
     
     
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
