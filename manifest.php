<?php
declare(strict_types=1);
/**
 * Web App Manifest — live per-tenant branding for installable RC.
 * Served as PHP (web.config blocks static .json).
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
$name = trim((string)($company['name'] ?? '')) ?: 'Resource Centre';
$ver = defined('APP_VERSION') ? (string)APP_VERSION : '1';
$origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$short = $name;
if (mb_strlen($short) > 12) {
    $words = preg_split('/\s+/u', $name) ?: [];
    $short = count($words) > 1 ? mb_substr($words[0], 0, 12) : mb_substr($name, 0, 12);
}

$descParts = [
    $name . ' — Resource Centre',
    'Team directory, digital business cards, email signatures, fleet tags, documents, calendar and compliance.',
];
$website = trim((string)($company['website'] ?? ''));
if ($website !== '') {
    $descParts[] = 'Website: ' . $website;
}
$email = trim((string)($company['email'] ?? $company['support_email'] ?? ''));
if ($email !== '') {
    $descParts[] = 'Contact: ' . $email;
}
$phone = trim((string)($company['phone'] ?? $company['support_phone'] ?? ''));
if ($phone !== '') {
    $descParts[] = 'Phone: ' . $phone;
}
$descParts[] = 'Build ' . $ver;

$iconBase = '/tools/pwa_icon.php';
$icons = [];
foreach ([48, 72, 96, 128, 144, 152, 192, 256, 384, 512] as $sz) {
    $icons[] = [
        'src' => $iconBase . '?size=' . $sz . '&v=' . rawurlencode($ver),
        'sizes' => $sz . 'x' . $sz,
        'type' => 'image/png',
        'purpose' => 'any',
    ];
}
$icons[] = [
    'src' => $iconBase . '?size=512&maskable=1&v=' . rawurlencode($ver),
    'sizes' => '512x512',
    'type' => 'image/png',
    'purpose' => 'maskable',
];

$manifest = [
    'id'               => $origin . '/',
    'name'             => $name . ' · Resource Centre',
    'short_name'       => $short,
    'description'      => implode(' · ', $descParts),
    'start_url'        => '/index.php?tab=team&utm_source=pwa&utm_medium=install',
    'scope'            => '/',
    'display'          => 'standalone',
    'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui'],
    'orientation'      => 'any',
    'background_color' => '#FAF9F8',
    'theme_color'      => '#0078D4',
    'lang'             => 'en-IN',
    'dir'              => 'ltr',
    'categories'       => ['business', 'productivity', 'utilities'],
    'iarc_rating_id'   => '',
    'prefer_related_applications' => false,
    'icons'            => $icons,
    'shortcuts'        => [
        [
            'name' => 'Human Capital Index',
            'short_name' => 'Directory',
            'description' => 'Browse the team directory',
            'url' => '/index.php?tab=team',
            'icons' => [['src' => $iconBase . '?size=96', 'sizes' => '96x96']],
        ],
        [
            'name' => 'Vehicle Tags',
            'short_name' => 'Fleet',
            'description' => 'Fleet asset registry',
            'url' => '/index.php?tab=cartags',
            'icons' => [['src' => $iconBase . '?size=96', 'sizes' => '96x96']],
        ],
        [
            'name' => 'Document Vault',
            'short_name' => 'Docs',
            'description' => 'Corporate documents',
            'url' => '/index.php?tab=docs',
            'icons' => [['src' => $iconBase . '?size=96', 'sizes' => '96x96']],
        ],
        [
            'name' => 'Calendar',
            'short_name' => 'Events',
            'description' => 'Corporate calendar',
            'url' => '/index.php?tab=events',
            'icons' => [['src' => $iconBase . '?size=96', 'sizes' => '96x96']],
        ],
    ],
    'launch_handler' => [
        'client_mode' => 'focus-existing',
    ],
    'handle_links' => 'preferred',
    'edge_side_panel' => [
        'preferred_width' => 400,
    ],
];

// Optional: related app info for desktop (informational)
$manifest['related_applications'] = [];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Robots-Tag: noindex');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
