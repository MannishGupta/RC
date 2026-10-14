<?php

declare(strict_types=1);
require_once __DIR__ . '/lib.php';
AppLocale::boot();

header('Content-Type: application/rss+xml; charset=UTF-8');

$projects   = cachedJson(PROJ_FILE);
$components = cachedJson(COMP_FILE);
$updates    = cachedJson(UPD_FILE);
[$projMap, $compMap] = buildMaps($projects, $components);
sortUpdates($updates, $compMap, $projMap);

$company = (string)cfg('brand.company', '');
$x = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>', "\n";
echo '  <title>', $x($company . ' — Construction Progress'), "</title>\n";
echo '  <link>', $x(canonicalUrl('')), "</link>\n";
echo '  <description>', $x('Tower-wise construction milestones and site photographs for ' . (string)cfg('brand.project', '')), "</description>\n";
echo '  <language>', $x(strtolower(AppLocale::lang())), "</language>\n";
echo '  <atom:link href="', $x(canonicalUrl('feed.php')), '" rel="self" type="application/rss+xml" />', "\n";

foreach (array_slice($updates, 0, 50) as $u) {
    $c    = $compMap[$u['component_id'] ?? ''] ?? null;
    $job  = $c ? (string)($c['job'] ?? '') : (string)($u['legacy_title'] ?? 'Update');
    $pn   = $c ? (string)($projMap[$c['project_id']]['name'] ?? '') : '';
    $dt   = AppLocale::parse((string)($u['date'] ?? ''));
    $body = trim(preg_replace('/\s+/', ' ', strip_tags((string)($u['description'] ?? ''))) ?? '');
    echo "  <item>\n";
    echo '    <title>', $x(trim(($pn !== '' ? $pn . ' — ' : '') . $job . ' · ' . AppLocale::date((string)($u['date'] ?? '')))), "</title>\n";
    echo '    <link>', $x(canonicalUrl('')), "</link>\n";
    echo '    <guid isPermaLink="false">', $x('status-' . (string)($u['id'] ?? '')), "</guid>\n";
    if ($dt) { echo '    <pubDate>', $x($dt->format(DATE_RSS)), "</pubDate>\n"; }
    echo '    <description>', $x($body), "</description>\n";
    echo "  </item>\n";
}
echo "</channel></rss>\n";
