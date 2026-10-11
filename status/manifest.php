<?php
/* manifest.php — web app manifest. A .php endpoint because the resource
   centre's root web.config disallows .json via requestFiltering. */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
AppLocale::boot();

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

$company = (string)cfg('brand.company', '');
echo json_encode([
    'name'             => $company . ' — Construction Progress',
    'short_name'       => 'Progress',
    'description'      => 'Tower-wise construction milestones and site photographs for ' . (string)cfg('brand.project', ''),
    'start_url'        => canonicalUrl(''),
    'scope'            => canonicalUrl(''),
    'display'          => 'standalone',
    'orientation'      => 'any',
    'lang'             => AppLocale::lang(),
    'dir'              => 'ltr',
    'background_color' => '#ffffff',
    'theme_color'      => '#0f6cbd',
    'categories'       => ['business', 'utilities'],
    'icons'            => [
        ['src' => canonicalUrl(assetPath('assets/favicon.ico')), 'sizes' => '48x48', 'type' => 'image/x-icon'],
        ['src' => canonicalUrl(assetPath('assets/ag-image.png')), 'sizes' => 'any', 'type' => 'image/png', 'purpose' => 'any'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
