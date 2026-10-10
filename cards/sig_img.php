<?php
declare(strict_types=1);
/**
 * Email-safe signature images — always PNG/JPEG.
 * Outlook blanks WebP/SVG. Prefer pre-rendered company-logo.sig.png (from
 * browser or server at upload); then raster siblings; then Imagick/CLI SVG.
 */
$rootPath = file_exists(__DIR__ . '/../app/bootstrap.php') ? dirname(__DIR__) : __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $rootPath);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
if (!defined('IMG_PATH')) {
    define('IMG_PATH', DATA_PATH . '/media/images');
}
require_once BASE_PATH . '/app/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$kind = strtolower(trim((string)($_GET['kind'] ?? 'photo')));
if (!in_array($kind, ['photo', 'logo'], true)) {
    $kind = 'photo';
}
$slug = trim((string)($_GET['slug'] ?? ''));
$w = max(64, min(1024, (int)($_GET['w'] ?? ($kind === 'logo' ? 560 : 256))));
$h = max(64, min(1024, (int)($_GET['h'] ?? ($kind === 'logo' ? 224 : 256))));

$ctx = ($slug !== '' && class_exists('CardContext')) ? CardContext::get($slug) : null;
$person = is_array($ctx['person'] ?? null) ? $ctx['person'] : [];
$company = is_array($ctx['company'] ?? null) ? $ctx['company'] : [];

$dirs = array_values(array_filter([
    defined('IMG_PATH') ? IMG_PATH : null,
    defined('DATA_PATH') ? DATA_PATH . '/media/images' : null,
    defined('DATA_PATH') ? DATA_PATH . '/images' : null,
    defined('DATA_PATH') ? DATA_PATH . '/media' : null,
    BASE_PATH . '/images',
    BASE_PATH . '/data/media/images',
]));

$findInDirs = static function (string $name) use ($dirs): string {
    $name = str_replace(["\\", "\0"], ['/', ''], $name);
    $name = basename($name);
    if ($name === '' || $name === '.' || $name === '..') {
        return '';
    }
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $cand = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (is_file($cand) && is_readable($cand)) {
            return $cand;
        }
    }
    $lower = strtolower($name);
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        foreach (@scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strtolower($entry) === $lower) {
                $cand = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $entry;
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
    }
    return '';
};

/** Ordered candidate paths for logo (email-safe raster first). */
$logoCandidates = static function () use ($company, $findInDirs): array {
    $out = [];
    $push = static function (string $p) use (&$out): void {
        if ($p !== '' && is_file($p) && !in_array($p, $out, true)) {
            $out[] = $p;
        }
    };
    // Explicit sidecar fields
    foreach (['logo_sig', 'logo_png', 'logo_raster'] as $k) {
        $f = trim((string)($company[$k] ?? ''));
        if ($f !== '') {
            $push($findInDirs($f));
        }
    }
    $logo = basename((string)($company['logo'] ?? ''));
    $stem = $logo !== '' ? pathinfo($logo, PATHINFO_FILENAME) : 'company-logo';
    // Conventional email PNG next to SVG
    foreach ([
        $stem . '.sig.png',
        'company-logo.sig.png',
        $stem . '.png',
        'company-logo.png',
        $stem . '.jpg',
        $stem . '.jpeg',
        $stem . '.webp',
        $logo,
    ] as $n) {
        if ($n !== '' && $n !== '.') {
            $push($findInDirs($n));
        }
    }
    return $out;
};

$photoCandidates = static function () use ($person, $findInDirs): array {
    $out = [];
    $f = basename((string)($person['photo'] ?? ''));
    if ($f === '') {
        return $out;
    }
    $stem = pathinfo($f, PATHINFO_FILENAME);
    foreach ([$stem . '.sig.png', $stem . '.png', $stem . '.jpg', $stem . '.jpeg', $f, $stem . '.webp'] as $n) {
        $p = $findInDirs($n);
        if ($p !== '' && !in_array($p, $out, true)) {
            $out[] = $p;
        }
    }
    return $out;
};

$candidates = $kind === 'logo' ? $logoCandidates() : $photoCandidates();

$label = $kind === 'logo'
    ? (string)($company['name'] ?? 'Org')
    : (string)($person['name'] ?? 'User');
$hex = ltrim((string)($company['brand_color'] ?? '1e3a5f'), '#');
if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
    $hex = '1e3a5f';
}
$logoBg = class_exists('AppMedia') && method_exists('AppMedia', 'logoPlateBg')
    ? AppMedia::logoPlateBg(is_array($company) ? $company : [])
    : 'light';

$sendPng = static function (string $pngBinary): void {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('ETag: "' . md5($pngBinary) . '"');
    echo $pngBinary;
    exit;
};

$sendPlaceholder = static function (string $label, int $w, int $h, string $hex) use ($sendPng): void {
    if (!function_exists('imagecreatetruecolor')) {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Image unavailable';
        exit;
    }
    $im = imagecreatetruecolor($w, $h);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $bg = imagecolorallocate($im, $r, $g, $b);
    $fg = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    $letter = mb_strtoupper(mb_substr(trim($label) !== '' ? $label : '?', 0, 1));
    $font = 5;
    $tw = imagefontwidth($font) * strlen($letter);
    $th = imagefontheight($font);
    imagestring($im, $font, (int)(($w - $tw) / 2), (int)(($h - $th) / 2), $letter, $fg);
    ob_start();
    imagepng($im);
    $bin = (string)ob_get_clean();
    imagedestroy($im);
    $sendPng($bin);
};

$rasterizeSvg = static function (string $path, string $blob, int $w, int $h) : ?string {
    // 1) Imagick — try multiple read strategies (policy may block some)
    if (class_exists('Imagick')) {
        $attempts = [];
        $attempts[] = static function () use ($blob, $w, $h) {
            $im = new Imagick();
            $im->setResolution(192, 192);
            $im->readImageBlob($blob);
            return $im;
        };
        $attempts[] = static function () use ($path, $w, $h) {
            $im = new Imagick();
            $im->setResolution(192, 192);
            $im->readImage($path);
            return $im;
        };
        $attempts[] = static function () use ($blob, $w, $h) {
            $im = new Imagick();
            $im->setResolution(192, 192);
            $im->setFormat('SVG');
            $im->readImageBlob($blob);
            return $im;
        };
        foreach ($attempts as $fn) {
            try {
                $im = $fn();
                if (!$im) {
                    continue;
                }
                $im->setImageBackgroundColor(new ImagickPixel('transparent'));
                $im->setImageFormat('png32');
                if (method_exists($im, 'mergeImageLayers')) {
                    @$im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                }
                $im->thumbnailImage($w, $h, true, true);
                $png = $im->getImageBlob();
                $im->clear();
                $im->destroy();
                if (is_string($png) && strlen($png) > 32) {
                    return $png;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
    }

    // 2) CLI tools (often disabled on shared hosts — best-effort)
    $tmpSvg = sys_get_temp_dir() . '/rc_sig_' . bin2hex(random_bytes(6)) . '.svg';
    $tmpPng = sys_get_temp_dir() . '/rc_sig_' . bin2hex(random_bytes(6)) . '.png';
    $wrote = @file_put_contents($tmpSvg, $blob);
    if ($wrote !== false && function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
        $cmds = [
            'rsvg-convert -w ' . (int)max($w * 2, 64) . ' -h ' . (int)max($h * 2, 64) . ' -f png -o ' . escapeshellarg($tmpPng) . ' ' . escapeshellarg($tmpSvg) . ' 2>/dev/null',
            'magick -background none -density 384 ' . escapeshellarg($tmpSvg) . ' -resize ' . (int)($w * 2) . 'x' . (int)($h * 2) . ' -resize ' . (int)$w . 'x' . (int)$h . ' ' . escapeshellarg($tmpPng) . ' 2>/dev/null',
            'convert -background none -density 384 ' . escapeshellarg($tmpSvg) . ' -resize ' . (int)($w * 2) . 'x' . (int)($h * 2) . ' -resize ' . (int)$w . 'x' . (int)$h . ' ' . escapeshellarg($tmpPng) . ' 2>/dev/null',
        ];
        foreach ($cmds as $cmd) {
            @exec($cmd, $o, $code);
            if ($code === 0 && is_file($tmpPng) && filesize($tmpPng) > 32) {
                $png = (string)@file_get_contents($tmpPng);
                @unlink($tmpSvg);
                @unlink($tmpPng);
                if ($png !== '') {
                    return $png;
                }
            }
        }
    }
    @unlink($tmpSvg);
    @unlink($tmpPng);
    return null;
};

$gdFromFile = static function (string $path, string $blob, string $ext) {
    if (!function_exists('imagecreatefromstring') && !function_exists('imagecreatetruecolor')) {
        return null;
    }
    $im = @imagecreatefromstring($blob);
    if ($im) {
        return $im;
    }
    if (in_array($ext, ['jpg', 'jpeg', 'jfif'], true) && function_exists('imagecreatefromjpeg')) {
        return @imagecreatefromjpeg($path);
    }
    if ($ext === 'png' && function_exists('imagecreatefrompng')) {
        return @imagecreatefrompng($path);
    }
    if ($ext === 'gif' && function_exists('imagecreatefromgif')) {
        return @imagecreatefromgif($path);
    }
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($path);
    }
    return null;
};

$fitToPng = static function ($im, int $w, int $h, string $kind, string $logoBg) use ($sendPng): void {
    $srcW = imagesx($im);
    $srcH = imagesy($im);
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    // Email clients: transparent often becomes black; for dark-ink logos use white plate, white-ink use dark
    if ($kind === 'logo' && $logoBg === 'dark') {
        $bg = imagecolorallocate($dst, 27, 26, 25); // #1B1A19
        imagefilledrectangle($dst, 0, 0, $w, $h, $bg);
        imagealphablending($dst, true);
    } elseif ($kind === 'logo') {
        $bg = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $w, $h, $bg);
        imagealphablending($dst, true);
    } else {
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
        imagealphablending($dst, true);
    }
    $scale = ($kind === 'photo')
        ? max($w / max(1, $srcW), $h / max(1, $srcH))
        : min($w / max(1, $srcW), $h / max(1, $srcH));
    $nw = (int)round($srcW * $scale);
    $nh = (int)round($srcH * $scale);
    $ox = (int)(($w - $nw) / 2);
    $oy = (int)(($h - $nh) / 2);
    imagecopyresampled($dst, $im, $ox, $oy, 0, 0, $nw, $nh, $srcW, $srcH);
    imagedestroy($im);
    ob_start();
    imagepng($dst, null, 6);
    $bin = (string)ob_get_clean();
    imagedestroy($dst);
    $sendPng($bin);
};

if ($candidates === []) {
    $sendPlaceholder($label, $w, $h, $hex);
}

foreach ($candidates as $abs) {
    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $blob = @file_get_contents($abs);
    if ($blob === false || $blob === '') {
        continue;
    }

    if (in_array($ext, ['svg', 'svgz'], true)) {
        $png = $rasterizeSvg($abs, $blob, $w, $h);
        if ($png !== null) {
            $sendPng($png);
        }
        continue; // try next candidate (e.g. missing sig.png already tried first)
    }

    $im = $gdFromFile($abs, $blob, $ext);
    if ($im) {
        $fitToPng($im, $w, $h, $kind, $logoBg);
    }

    // Imagick for webp/other
    if (class_exists('Imagick')) {
        try {
            $imk = new Imagick();
            $imk->readImageBlob($blob);
            $imk->setImageFormat('png32');
            $imk->thumbnailImage($w, $h, true, true);
            $png = $imk->getImageBlob();
            $imk->clear();
            $imk->destroy();
            if (is_string($png) && strlen($png) > 32) {
                $sendPng($png);
            }
        } catch (Throwable $e) {
            // next
        }
    }
}

$sendPlaceholder($label, $w, $h, $hex);
