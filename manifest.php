<?php
declare(strict_types=1);
/**
 * Web App Manifest — product identity (Arthsathi Limited) + version.
 * Tenant name stays in description (licensed-to); install icon is product favicon.
 */
$rootPath = __DIR__;
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
if (is_file(BASE_PATH . '/app/bootstrap.php')) {
    require_once BASE_PATH . '/app/bootstrap.php';
}
if (is_file(BASE_PATH . '/version.php')) {
    require_once BASE_PATH . '/version.php';
}

$company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
if (isset($company[0]) && is_array($company[0]) && empty($company['name'])) {
    $company = $company[0];
}
$tenantName = trim((string)($company['name'] ?? '')) ?: 'Organization';
$ver = defined('APP_VERSION') ? (string)APP_VERSION : '1';
// Windows Installed Apps expects a dotted numeric version (not 1.0.0.0 default)
$winVer = '1.0.0.0';
if (preg_match('/^(\d{4})(\d{2})(\d{2})\.(\d+)$/', $ver, $vm)) {
    // 20261009.35 → 2026.10.9.35
    $winVer = sprintf('%d.%d.%d.%d', (int)$vm[1], (int)$vm[2], (int)$vm[3], (int)$vm[4]);
} elseif (preg_match('/^(\d+)\.(\d+)/', $ver, $vm)) {
    $winVer = $vm[1] . '.' . $vm[2] . '.0.0';
}

$publisher = 'Arthsathi Limited';
$origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Product-facing install title (version visible in the native dialog)
$name = 'Resource Centre · v' . $ver;
$short = 'RC ' . $ver;
if (mb_strlen($short) > 12) {
    $short = 'RC';
}

$descParts = [
    'Published by ' . $publisher,
    'Version ' . $ver,
    'Licensed to ' . $tenantName,
    'Team directory, digital cards, signatures, fleet, documents and compliance.',
];
$website = trim((string)($company['website'] ?? ''));
if ($website !== '') {
    $descParts[] = 'Tenant site: ' . $website;
}

// HARDCODED product identity — always Arthsathi brand (not tenant logo)
// Local path (same codebase on every host) + absolute fallback on rc.arthsathi.com
$brandLocal = [
    192 => '/assets/brand/arthsathi-icon-192.png',
    512 => '/assets/brand/arthsathi-icon-512.png',
    180 => '/assets/brand/arthsathi-icon-192.png',
];
$brandAbs = 'https://rc.arthsathi.com/assets/brand';
$iconSrc = static function (int $sz) use ($brandLocal, $brandAbs, $ver): string {
    $local = $brandLocal[$sz] ?? $brandLocal[512];
    $absPath = BASE_PATH . str_replace('/', DIRECTORY_SEPARATOR, $local);
    if (is_file($absPath)) {
        return $local . '?v=' . rawurlencode($ver);
    }
    // Host missing file → load from product origin
    $name = ($sz >= 256) ? 'arthsathi-icon-512.png' : 'arthsathi-icon-192.png';
    return $brandAbs . '/' . $name . '?v=' . rawurlencode($ver);
};
$icons = [];
foreach ([48, 72, 96, 128, 144, 152, 192, 256, 384, 512] as $sz) {
    $pick = $sz >= 256 ? 512 : 192;
    $icons[] = [
        'src' => $iconSrc($pick),
        'sizes' => $sz . 'x' . $sz,
        'type' => 'image/png',
        'purpose' => 'any',
    ];
}
$icons[] = [
    'src' => $iconSrc(512),
    'sizes' => '512x512',
    'type' => 'image/png',
    'purpose' => 'maskable',
];
// SVG mark (supported install UAs)
$svgLocal = BASE_PATH . '/assets/brand/arthsathi-icon.svg';
$icons[] = [
    'src' => is_file($svgLocal)
        ? '/assets/brand/arthsathi-icon.svg?v=' . rawurlencode($ver)
        : $brandAbs . '/arthsathi-icon.svg?v=' . rawurlencode($ver),
    'sizes' => 'any',
    'type' => 'image/svg+xml',
    'purpose' => 'any',
];

$manifest = [
    'id'               => $origin . '/',
    'name'             => $name,
    'short_name'       => $short,
    'description'      => implode(' · ', $descParts),
    'start_url'        => '/index.php?tab=team&utm_source=pwa&utm_medium=install',
    'scope'            => '/',
    'display'          => 'standalone',
    'orientation'      => 'any',
    'background_color' => '#0078D4',
    'theme_color'      => '#0078D4',
    'lang'             => 'en-IN',
    'dir'              => 'ltr',
    'icons'            => $icons,
    'categories'       => ['business', 'productivity'],
    // Non-standard but useful for tooling / future UA support
    'publisher'        => $publisher,
    'version'          => $winVer, // four-part for Windows Installed Apps
    'version_name'     => $ver,
    'related_applications' => [],
    'prefer_related_applications' => false,
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Robots-Tag: noindex');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
