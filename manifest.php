<?php // Version: 260916.14
declare(strict_types=1);
/**
 * manifest.php — Web App Manifest, served as PHP rather than a static file.
 *
 * WHY NOT manifest.json
 * web.config denies the .json extension outright
 * (`<add fileExtension=".json" allowed="false" />`) to keep the data directory
 * unreachable. A static manifest.json would therefore 404. Relaxing that rule
 * means editing web.config, which has broken static-file serving on this host
 * twice already — so the manifest is emitted by PHP with the correct
 * Content-Type instead. Same result, none of that risk.
 *
 * SIDE BENEFIT: the manifest tracks live company data. Rename the
 * organisation or change its logo in Company Setup and the installed app
 * name and icon follow, with no file to regenerate.
 *
 * STATUS: this makes the site installable via "Add to Home Screen" on iOS and
 * Android today — home-screen icon, standalone window, no browser chrome.
 * Full PWA behaviour (offline access, push) additionally needs a service
 * worker, which is deliberately NOT registered yet: a service worker must be
 * served as application/javascript, and static-file MIME handling on this
 * host is still unresolved. Registering one that silently fails to install is
 * worse than not having one.
 */

$rootPath = __DIR__;
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH')) define('IMG_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/images');
if (!defined('DOC_PATH')) define('DOC_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/documents');

require_once BASE_PATH . '/app/bootstrap.php';

$company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$name    = trim((string)($company['name'] ?? '')) ?: 'Corporate Directory';

// Short name appears under the home-screen icon; anything past ~12 characters
// is truncated by both platforms, so derive a sensible abbreviation rather
// than letting the OS cut mid-word.
$short = $name;
if (mb_strlen($short) > 12) {
    $words = preg_split('/\s+/', $name) ?: [];
    $short = count($words) > 1
        ? mb_substr($words[0], 0, 12)
        : mb_substr($name, 0, 12);
}

$manifest = [
    'name'             => $name,
    'short_name'       => $short,
    'description'      => 'Team directory, digital cards and vehicle tags for ' . $name . '.',
    'start_url'        => '/index.php?tab=team',
    'scope'            => '/',
    // 'standalone' hides browser chrome, which is the point of installing.
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#f8fafc',
    'theme_color'      => '#0f172a',
    'lang'             => 'en-IN',
    'dir'              => 'ltr',
    'categories'       => ['business', 'productivity'],
    'icons' => [
        // Rendered on demand from the company logo — see tools/pwa_icon.php.
        ['src' => '/tools/pwa_icon.php?size=192', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => '/tools/pwa_icon.php?size=512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        // 'maskable' lets Android crop to its adaptive-icon shape without
        // clipping the logo, provided the safe zone is respected.
        ['src' => '/tools/pwa_icon.php?size=512&maskable=1', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    // Long-press shortcuts on the home-screen icon.
    'shortcuts' => [
        ['name' => 'Directory',    'url' => '/index.php?tab=team'],
        ['name' => 'Vehicle Tags', 'url' => '/index.php?tab=cartags'],
        ['name' => 'Documents',    'url' => '/index.php?tab=docs'],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
