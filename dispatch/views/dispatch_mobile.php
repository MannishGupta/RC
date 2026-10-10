<?php
/**
 * Mobile view for runners/drivers — simple Roman Hindi job cards
 */
if (!defined('DISPATCH_ROOT')) {
    exit;
}
$team = d_team();
$runnerId = trim((string) ($_GET['runner_id'] ?? ''));
$routes = d_read('routes');
$active = array_values(array_filter($routes, static function ($r) use ($runnerId) {
    if (!is_array($r) || ($r['status'] ?? '') !== 'active') {
        return false;
    }
    if ($runnerId === '') {
        return true;
    }
    return (string) ($r['runner_id'] ?? '') === $runnerId;
}));
?>
<!DOCTYPE html>
<html lang="hi" data-theme="reserve">
<head>
<?php if (defined('BASE_PATH') && is_file(BASE_PATH . '/app/views/partials/rc_theme_head.php')) { require BASE_PATH . '/app/views/partials/rc_theme_head.php'; } ?>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>Aapka kaam · Dispatch</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body { font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
  </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen pb-24">
  <header class="sticky top-0 z-10 bg-emerald-600 text-white px-4 py-3 shadow">
    <div class="text-xs font-bold uppercase tracking-wider opacity-90">Field dispatch</div>
    <h1 class="text-lg font-black">Aaj ke delivery tasks</h1>
    <p class="text-xs text-emerald-100 mt-0.5">Seedha language · map open karke chalo</p>
  </header>

  <div class="p-3 space-y-3 max-w-lg mx-auto">
    <form method="get" class="rounded-xl bg-white border border-slate-200 p-3 shadow-sm">
      <input type="hidden" name="view" value="mobile">
      <label class="text-xs font-bold text-slate-500">Apna naam select karo</label>
      <select name="runner_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" onchange="this.form.submit()">
        <option value="">— Saari jobs —</option>
        <?php foreach ($team as $t): ?>
        <option value="<?= d_h($t['id']) ?>" <?= $runnerId === $t['id'] ? 'selected' : '' ?>><?= d_h($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <?php if ($active === []): ?>
    <div class="rounded-xl bg-white border border-dashed border-slate-300 p-8 text-center text-slate-500 text-sm">
      Abhi koi active delivery nahi hai.<br>Office se naya task aate hi yahan dikhega.
    </div>
    <?php endif; ?>

    <?php foreach ($active as $r):
      $map = d_maps_link((float) ($r['dest_lat'] ?? 0), (float) ($r['dest_lng'] ?? 0), (string) ($r['destination'] ?? ''));
      $msg = d_hinglish_message($r);
    ?>
    <article class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
      <div class="bg-slate-900 text-white px-4 py-2 flex justify-between items-center">
        <span class="font-bold text-sm"><?= d_h($r['title'] ?? 'Delivery') ?></span>
        <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-300">Active</span>
      </div>
      <div class="p-4 space-y-3 text-sm">
        <p class="whitespace-pre-wrap text-slate-800 leading-relaxed"><?= d_h($msg) ?></p>
        <?php if (!empty($r['vehicle_label'])): ?>
        <p class="text-xs text-slate-500">Gaadi: <strong><?= d_h($r['vehicle_label']) ?></strong></p>
        <?php endif; ?>
        <div class="flex flex-col gap-2">
          <a href="<?= d_h($map) ?>" target="_blank" rel="noopener"
             class="block text-center py-3 rounded-xl bg-blue-600 text-white font-bold text-sm">
            🗺️ Map kholo · yahan jaana hai
          </a>
          <?php if (!empty($r['contact_phone'])):
            $tel = preg_replace('/\D+/', '', (string) $r['contact_phone']);
          ?>
          <a href="tel:<?= d_h($tel) ?>" class="block text-center py-3 rounded-xl bg-slate-800 text-white font-bold text-sm">
            📞 Receiver ko call karo
          </a>
          <?php endif; ?>
          <button type="button" class="btn-done w-full py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm"
                  data-id="<?= d_h($r['id']) ?>">
            ✅ Kaam ho gaya · mark complete
          </button>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <script>
  document.querySelectorAll('.btn-done').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Confirm: delivery complete?')) return;
      const res = await fetch('dispatch.php?action=update_route_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_route_status', id: btn.dataset.id, status: 'completed' })
      });
      const data = await res.json();
      if (data.ok) location.reload();
      else alert(data.error || 'Error');
    });
  });
  </script>
</body>
</html>
