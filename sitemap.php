<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';

/**
 * Dynamic XML sitemap for organic search.
 * Public tabs only (no admin-only tools).
 */

$base = '';
if (isset($_SERVER['HTTP_HOST'])) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $base = ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
}

$tabs = [
    'team'       => ['prio' => '1.0', 'freq' => 'weekly'],
    'bank'       => ['prio' => '0.9', 'freq' => 'monthly'],
    'statutory'  => ['prio' => '0.9', 'freq' => 'monthly'],
    'docs'       => ['prio' => '0.8', 'freq' => 'weekly'],
    'locations'  => ['prio' => '0.8', 'freq' => 'monthly'],
    'events'     => ['prio' => '0.7', 'freq' => 'weekly'],
    'departments'=> ['prio' => '0.6', 'freq' => 'monthly'],
    'designations'=>['prio' => '0.6', 'freq' => 'monthly'],
    'cartags'    => ['prio' => '0.5', 'freq' => 'monthly'],
    'status'     => ['prio' => '0.7', 'freq' => 'weekly'],
    'terms'      => ['prio' => '0.3', 'freq' => 'yearly'],
];

$lastmod = date('Y-m-d');

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($base . '/', ENT_XML1) ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
<?php foreach ($tabs as $tab => $meta): ?>
  <url>
    <loc><?= htmlspecialchars($base . '/?tab=' . rawurlencode($tab), ENT_XML1) ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq><?= $meta['freq'] ?></changefreq>
    <priority><?= $meta['prio'] ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
