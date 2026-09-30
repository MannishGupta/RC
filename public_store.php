<?php
declare(strict_types=1);
/**
 * Public store / premises locator — minimal SEO page. Version: 260921.40
 */
define('BASE_PATH', str_replace('\\', '/', __DIR__));
require_once BASE_PATH . '/app/tenant_bootstrap.php';
$company = [];
foreach (['data/company.json', 'data/settings.json'] as $rel) {
    $p = BASE_PATH . '/' . $rel;
    if (is_file($p)) {
        $j = json_decode((string)@file_get_contents($p), true);
        if (is_array($j)) {
            $company = $j;
            break;
        }
    }
}
$name = htmlspecialchars((string)($company['name'] ?? 'Our Locations'), ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="en-IN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $name ?> — Locations</title>
  <meta name="description" content="Public premises directory for <?= $name ?>">
  <meta name="robots" content="index,follow">
  <style>
    body{font-family:system-ui,sans-serif;margin:0;background:#f8fafc;color:#0f172a}
    main{max-width:40rem;margin:0 auto;padding:1.5rem}
    h1{font-size:1.5rem;margin:0 0 .5rem}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1rem;margin:.75rem 0}
    a{color:#2563eb}
  </style>
</head>
<body>
<main>
  <h1><?= $name ?></h1>
  <p>Public premises directory.</p>
  <div id="list">Loading…</div>
  <p style="font-size:.75rem;color:#64748b;margin-top:2rem"><a href="index.php">Staff portal</a> · <a href="index.php?tab=terms&amp;policy=privacy">Privacy</a></p>
</main>
<script>
fetch('api_value.php?action=public_locations').then(r=>r.json()).then(d=>{
  const el=document.getElementById('list');
  if(!d.locations||!d.locations.length){el.textContent='No public locations published yet.';return;}
  el.innerHTML=d.locations.map(L=>`<div class="card"><strong>${L.name||''}</strong><div>${L.address||''}</div>${L.phone?`<div>${L.phone}</div>`:''}</div>`).join('');
}).catch(()=>{document.getElementById('list').textContent='Unable to load locations.';});
</script>
</body>
</html>
