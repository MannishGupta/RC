<?php
/**
 * Company / Tenant Admin — Statistics panel
 * Counts live JSON namespaces for the active tenant.
 */
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$nsList = [
    'team' => ['label' => 'Human Capital', 'icon' => 'fa-users', 'tone' => 'sky'],
    'bank' => ['label' => 'Treasury', 'icon' => 'fa-building-columns', 'tone' => 'indigo'],
    'docs' => ['label' => 'Documents', 'icon' => 'fa-folder-open', 'tone' => 'amber'],
    'locations' => ['label' => 'Locations', 'icon' => 'fa-location-dot', 'tone' => 'emerald'],
    'events' => ['label' => 'Events', 'icon' => 'fa-calendar', 'tone' => 'violet'],
    'cartags' => ['label' => 'Fleet tags', 'icon' => 'fa-car', 'tone' => 'slate'],
    'leads' => ['label' => 'Leads', 'icon' => 'fa-handshake', 'tone' => 'rose'],
    'cctv' => ['label' => 'CCTV', 'icon' => 'fa-video', 'tone' => 'cyan'],
];
$counts = [];
$total = 0;
foreach ($nsList as $ns => $meta) {
    $n = 0;
    if (class_exists('AppDB')) {
        $rows = AppDB::read($ns);
        if (is_array($rows)) {
            // company may be object
            if ($ns === 'company' && isset($rows['name'])) {
                $n = 1;
            } else {
                $n = count(array_filter($rows, 'is_array'));
            }
        }
    }
    $counts[$ns] = $n;
    $total += $n;
}
$anPath = (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/analytics.json';
$visits = 0;
$share = 0;
$print = 0;
if (is_file($anPath)) {
    $an = json_decode((string)@file_get_contents($anPath), true);
    if (is_array($an)) {
        $visits = (int)($an['visits'] ?? 0);
        $share = (int)($an['share_clicks'] ?? $an['share'] ?? 0);
        $print = (int)($an['print_clicks'] ?? $an['print'] ?? 0);
    }
}
$coName = 'Organisation';
if (class_exists('AppDB')) {
    $co = AppDB::read('company');
    if (is_array($co) && !empty($co['name'])) {
        $coName = (string)$co['name'];
    } elseif (is_array($co) && isset($co[0]['name'])) {
        $coName = (string)$co[0]['name'];
    }
}
$tenantId = defined('TENANT_ID') ? (string)TENANT_ID : 'default';
?>
<div id="rc-admin-stats" class="mb-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden" role="region" aria-label="Organisation statistics">
  <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 bg-gradient-to-r from-slate-50 to-white">
    <div>
      <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500 m-0">Statistics panel</p>
      <h2 class="text-base font-bold text-slate-900 m-0"><?= $h($coName) ?></h2>
    </div>
    <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
      <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2.5 py-1 text-slate-700">Tenant · <?= $h($tenantId) ?></span>
      <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 border border-sky-200 px-2.5 py-1 text-sky-800">Σ <?= (int)$total ?> records</span>
      <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2.5 py-1 text-indigo-800"><?= (int)$visits ?> visits</span>
      <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-1 text-amber-900"><?= (int)$share ?> shares · <?= (int)$print ?> prints</span>
    </div>
  </div>
  <div class="p-4 grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
    <?php foreach ($nsList as $ns => $meta):
      $tone = $meta['tone'];
    ?>
    <a href="?tab=<?= rawurlencode($ns) ?>" class="group rounded-xl border border-slate-200 bg-slate-50/80 hover:bg-white hover:border-<?= $h($tone) ?>-300 hover:shadow-md transition p-3 text-center no-underline">
      <div class="text-<?= $h($tone) ?>-600 text-lg mb-1"><i class="fa-solid <?= $h($meta['icon']) ?>" aria-hidden="true"></i></div>
      <div class="text-xl font-black tabular-nums text-slate-900 group-hover:text-<?= $h($tone) ?>-700"><?= (int)$counts[$ns] ?></div>
      <div class="text-[10px] font-bold uppercase tracking-wide text-slate-500 mt-0.5"><?= $h($meta['label']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
