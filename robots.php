<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';

/**
 * Dynamic robots.txt — host-aware Sitemap URL.
 * Served as text/plain for any domain (rc.fusionlimited.in, rc.arthsathi.com, …).
 */

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
      || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$base = ($https ? 'https://' : 'http://') . $host;

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex'); // this endpoint is the robots file itself

echo "# Organic search — Resource Center portal (India)\n";
echo "# Generated for: {$base}\n";
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Allow: /?tab=team\n";
echo "Allow: /?tab=bank\n";
echo "Allow: /?tab=statutory\n";
echo "Allow: /?tab=docs\n";
echo "Allow: /?tab=locations\n";
echo "Allow: /?tab=events\n";
echo "Allow: /?tab=status\n";
echo "Allow: /?tab=terms\n";
echo "Allow: /?tab=departments\n";
echo "Allow: /?tab=designations\n";
echo "Allow: /?tab=cartags\n";
echo "Allow: /cards/\n";
echo "Allow: /images/\n";
echo "Allow: /assets/\n";
echo "Allow: /status/\n";
echo "\n";
echo "# Admin / internal — do not index\n";
echo "Disallow: /?tab=monitor\n";
echo "Disallow: /?tab=opt\n";
echo "Disallow: /?tab=settings\n";
echo "Disallow: /?tab=leads\n";
echo "Disallow: /?tab=cctv\n";
echo "Disallow: /?tab=company\n";
echo "Disallow: /data/\n";
echo "Disallow: /app/\n";
echo "Disallow: /flush_cache.php\n";
echo "Disallow: /tools/\n";
echo "\n";
echo "# Absolute sitemap for this host (also /sitemap.xml via web.config)\n";
echo "Sitemap: {$base}/sitemap.php\n";
