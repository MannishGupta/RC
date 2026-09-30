<?php
declare(strict_types=1);
if (!defined('BASE_PATH')) return;
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="max-w-6xl mx-auto space-y-4 pb-12">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 m-0">Analytics</p>
      <h1 class="text-xl font-bold text-slate-900 m-0">Statistics Panel</h1>
      <p class="text-sm text-slate-500 mt-1 mb-0">Isolated overview — does not overlap directory or tenant grids.</p>
    </div>
    <a href="?tab=team" class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to directory</a>
  </div>
  <?php
  $partial = __DIR__ . '/../partials/admin_stats_panel.php';
  if (is_file($partial)) require $partial;
  else echo '<div class="p-4 text-amber-800 bg-amber-50 rounded-xl">Statistics partial missing.</div>';
  ?>
  <p class="text-[11px] text-slate-400">Site Developer: Arthsathi Limited · Build <?= $h(defined('APP_VERSION') ? APP_VERSION : '20260928.01') ?></p>
</div>
