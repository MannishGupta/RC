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

// Prefer product (Arthsathi) icons for the install prompt — not tenant monogram
$iconBase = '/tools/pwa_icon.php?product=1';
$icons = [];
foreach ([48, 72, 96, 128, 144, 152, 192, 256, 384, 512] as $sz) {
    $icons[] = [
        'src' => $iconBase . '&size=' . $sz . '&v=' . rawurlencode($ver),
        'sizes' => $sz . 'x' . $sz,
        'type' => 'image/png',
        'purpose' => 'any',
    ];
}
$icons[] = [
    'src' => $iconBase . '&size=512&maskable=1&v=' . rawurlencode($ver),
    'sizes' => '512x512',
    'type' => 'image/png',
    'purpose' => 'maskable',
];
// Also advertise static product SVG where supported
if (is_file(BASE_PATH . '/favicon.svg')) {
    array_unshift($icons, [
        'src' => '/favicon.svg?v=' . rawurlencode($ver),
        'sizes' => 'any',
        'type' => 'image/svg+xml',
        'purpose' => 'any',
    ]);
}

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
    'screenshots'      => [
        [
            'src' => '/tools/pwa_screenshot.php?form=wide&v=' . rawurlencode($ver),
            'sizes' => '1280x720',
            'type' => 'image/png',
            'form_factor' => 'wide',
            'label' => 'Resource Centre desktop',
        ],
        [
            'src' => '/tools/pwa_screenshot.php?form=narrow&v=' . rawurlencode($ver),
            'sizes' => '750x1334',
            'type' => 'image/png',
            'form_factor' => 'narrow',
            'label' => 'Resource Centre mobile',
        ],
    ],
    // Non-standard but useful for tooling / future UA support
    'publisher'        => $publisher,
    'version'          => $ver,
    'related_applications' => [],
    'prefer_related_applications' => false,
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Robots-Tag: noindex');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
