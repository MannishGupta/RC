<?php
/**
 * Version: 1.0
 * Resource Center tab: Delivery Routing & Dispatch
 */
if (!defined('BASE_PATH')) {
    exit;
}

$dispatchPhp = BASE_PATH . '/dispatch/dispatch.php';
$dispatchUrl = 'dispatch/dispatch.php';
$found = is_file($dispatchPhp);
?>
<div class="w-full space-y-3">
  <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 flex flex-wrap items-center justify-between gap-2">
    <div>
      <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Logistics</div>
      <h2 class="text-lg font-extrabold text-slate-800">Dispatch &amp; live routes</h2>
      <p class="text-xs text-slate-500">Map routes · vehicle → staff · Roman Hindi WhatsApp dispatch</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="<?= htmlspecialchars($dispatchUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener"
         class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-slate-900 text-white text-xs font-bold">
        <i class="fa-solid fa-up-right-from-square text-[10px]"></i> Open full console
      </a>
      <a href="<?= htmlspecialchars($dispatchUrl . '?view=mobile', ENT_QUOTES) ?>" target="_blank" rel="noopener"
         class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg bg-sky-600 text-white text-xs font-bold">
        <i class="fa-solid fa-mobile-screen text-[10px]"></i> Staff mobile
      </a>
    </div>
  </div>

  <?php if (!$found): ?>
  <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
    <strong>dispatch/</strong> module missing at <code><?= htmlspecialchars($dispatchPhp, ENT_QUOTES) ?></code>.
    Re-upload the <code>dispatch/</code> folder from the FTP pack.
  </div>
  <?php else: ?>
  <div class="rounded-xl border border-slate-200 bg-slate-50 overflow-hidden">
    <iframe
      src="<?= htmlspecialchars($dispatchUrl, ENT_QUOTES) ?>"
      title="Dispatch console"
      class="w-full border-0 bg-white"
      style="height:75vh;min-height:520px"
      loading="lazy"
      referrerpolicy="same-origin"
    ></iframe>
  </div>
  <?php endif; ?>
</div>
