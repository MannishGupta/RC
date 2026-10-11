<?php
/* ─────────────────────────────────────────────────────────────────────────
   sitemap.php — XML sitemap with image entries.

   Served as .php, not sitemap.xml, on purpose: the resource centre's root
   web.config rewrites ^sitemap.xml$ to its OWN sitemap.php, and that rule is
   anchored at the application root, so it would not reach a file here. Submit
   this URL directly in Search Console.
   ───────────────────────────────────────────────────────────────────────── */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
AppLocale::boot();

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

$projects   = cachedJson(PROJ_FILE);
$components = cachedJson(COMP_FILE);
$updates    = cachedJson(UPD_FILE);
[$projMap, $compMap] = buildMaps($projects, $components);
sortUpdates($updates, $compMap, $projMap);

$newest = null;
foreach ($updates as $u) {
    $d = AppLocale::parse((string)($u['date'] ?? ''));
    if ($d && ($newest === null || $d > $newest)) { $newest = $d; }
}
$lastmod = ($newest ?? new DateTimeImmutable())->format('Y-m-d');
$x = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">', "\n";

/* Home — carries the photographs, so Google Images can index them. */
echo "  <url>\n    <loc>", $x(canonicalUrl('')), "</loc>\n";
echo "    <lastmod>", $x($lastmod), "</lastmod>\n";
echo "    <changefreq>weekly</changefreq>\n    <priority>1.0</priority>\n";
$seen = 0;
foreach ($updates as $u) {
    $c   = $compMap[$u['component_id'] ?? ''] ?? null;
    $job = $c ? (string)($c['job'] ?? '') : (string)($u['legacy_title'] ?? '');
    $pn  = $c ? (string)($projMap[$c['project_id']]['name'] ?? '') : '';
    foreach ((array)($u['images'] ?? []) as $img) {
        if ($seen >= 1000) { break 2; }   // sitemap limit per URL
        $seen++;
        echo "    <image:image>\n";
        echo "      <image:loc>", $x(canonicalUrl(assetPath((string)$img))), "</image:loc>\n";
        echo "      <image:title>", $x(trim($pn . ' ' . $job)), "</image:title>\n";
        echo "      <image:caption>", $x(trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string)($u['description'] ?? ''))) ?? '', 0, 180))), "</image:caption>\n";
        echo "    </image:image>\n";
    }
}
echo "  </url>\n";

foreach ([['report.php', 'monthly', '0.6'], ['privacy.php', 'yearly', '0.3']] as [$p, $freq, $pri]) {
    echo "  <url>\n    <loc>", $x(canonicalUrl($p)), "</loc>\n";
    echo "    <lastmod>", $x($lastmod), "</lastmod>\n";
    echo "    <changefreq>", $x($freq), "</changefreq>\n    <priority>", $x($pri), "</priority>\n  </url>\n";
}
echo "</urlset>\n";
