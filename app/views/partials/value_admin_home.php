<?php
/**
 * Co. Admin home extras — expiry radar top-5 + recent activity.
 */
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tz = new DateTimeZone('Asia/Kolkata');
$today = new DateTime('today', $tz);

// Expiry scan from docs + cartags
$expiryItems = [];
$scanNs = ['docs' => 'Document', 'cartags' => 'Vehicle', 'statutory' => 'Statutory'];
foreach ($scanNs as $ns => $kind) {
    if (!class_exists('AppDB')) {
        break;
    }
    $rows = AppDB::read($ns);
    if (!is_array($rows)) {
        continue;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $exp = trim((string)($row['expiry'] ?? $row['valid_till'] ?? $row['expiry_date'] ?? $row['insurance_expiry'] ?? $row['fitness_expiry'] ?? ''));
        if ($exp === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $exp, $em)) {
            continue;
        }
        try {
            $ed = DateTime::createFromFormat('Y-m-d', $em[1] . '-' . $em[2] . '-' . $em[3], $tz);
            if (!$ed) {
                continue;
            }
            $days = (int)$today->diff($ed)->format('%r%a');
            if ($days > 30) {
                continue;
            }
            $title = (string)($row['title'] ?? $row['name'] ?? $row['plate'] ?? $row['tag_id'] ?? $row['doc_name'] ?? 'Record');
            $urgency = $days < 0 ? 'expired' : ($days <= 7 ? 'critical' : 'warning');
            $expiryItems[] = [
                'title' => $title,
                'kind' => $kind,
                'expiry' => $ed->format('d M Y'),
                'days' => $days,
                'urgency' => $urgency,
                'ns' => $ns,
            ];
        } catch (Throwable $e) {
        }
    }
}
usort($expiryItems, static fn($a, $b) => $a['days'] <=> $b['days']);
$expiryItems = array_slice($expiryItems, 0, 5);

// Recent activity from analytics + simple file mtimes
$activity = [];
$anPath = (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/analytics.json';
if (is_file($anPath)) {
    $an = json_decode((string)@file_get_contents($anPath), true);
    if (is_array($an)) {
        if (!empty($an['last_visit_at'])) {
            $activity[] = ['icon' => 'fa-eye', 'text' => 'Last visit recorded', 'when' => (string)$an['last_visit_at']];
        }
        if ((int)($an['share_clicks'] ?? 0) > 0) {
            $activity[] = ['icon' => 'fa-share-nodes', 'text' => (int)$an['share_clicks'] . ' share actions (lifetime)', 'when' => ''];
        }
        if ((int)($an['print_clicks'] ?? 0) > 0) {
            $activity[] = ['icon' => 'fa-print', 'text' => (int)$an['print_clicks'] . ' print actions (lifetime)', 'when' => ''];
        }
    }
}
$dataDir = defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data';
foreach (['team.json' => 'Human Capital Index', 'documents.json' => 'Documents', 'banking.json' => 'Treasury'] as $file => $label) {
    $p = $dataDir . '/' . $file;
    if (is_dir($p)) {
        $mt = @filemtime($p);
    } elseif (is_file($p)) {
        $mt = @filemtime($p);
    } else {
        continue;
    }
    if ($mt) {
        $activity[] = [
            'icon' => 'fa-clock-rotate-left',
            'text' => $label . ' updated',
            'when' => date('c', $mt),
            'sort' => $mt,
        ];
    }
}
usort($activity, static function ($a, $b) {
    return (($b['sort'] ?? 0) <=> ($a['sort'] ?? 0));
});
$activity = array_slice($activity, 0, 6);

$fmtWhen = static function (string $iso) use ($tz): string {
    if ($iso === '') {
        return '';
    }
    try {
        $d = new DateTime($iso);
        $d->setTimezone($tz);
        return $d->format('d M, h:i A') . ' IST';
    } catch (Throwable $e) {
        return $iso;
    }
};
?>
<div id="rc-admin-home-extras" class="mb-5 grid grid-cols-1 lg:grid-cols-2 gap-4" role="region" aria-label="Admin overview widgets">
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 m-0">Expiry radar</p>
        <h3 class="text-sm font-bold text-slate-900 m-0">Next 30 days · top 5</h3>
      </div>
      <a href="?tab=expiry" class="text-[11px] font-bold text-blue-700 hover:underline">Full radar →</a>
    </div>
    <?php if (!$expiryItems): ?>
      <p class="p-4 text-sm text-slate-500 m-0">No documents or vehicles expiring within 30 days. Add <code class="text-xs">expiry</code> / <code class="text-xs">valid_till</code> on vault records.</p>
    <?php else: ?>
      <ul class="divide-y divide-slate-50 m-0 p-0 list-none">
        <?php foreach ($expiryItems as $ex):
          $cls = $ex['urgency'] === 'expired' ? 'bg-red-100 text-red-800' : ($ex['urgency'] === 'critical' ? 'bg-amber-100 text-amber-900' : 'bg-blue-100 text-blue-800');
          $lbl = $ex['days'] < 0 ? ('Overdue ' . abs($ex['days']) . 'd') : ($ex['days'] . 'd left');
        ?>
        <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
          <div class="min-w-0">
            <div class="font-semibold text-slate-800 truncate"><?= $h($ex['title']) ?></div>
            <div class="text-[11px] text-slate-500"><?= $h($ex['kind']) ?> · <?= $h($ex['expiry']) ?></div>
          </div>
          <span class="shrink-0 text-[10px] font-black uppercase px-2 py-1 rounded-full <?= $h($cls) ?>"><?= $h($lbl) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 m-0">Recent activity</p>
      <h3 class="text-sm font-bold text-slate-900 m-0">Tenant pulse</h3>
    </div>
    <?php if (!$activity): ?>
      <p class="p-4 text-sm text-slate-500 m-0">Activity will appear as the team uses share, print, and updates records.</p>
    <?php else: ?>
      <ul class="divide-y divide-slate-50 m-0 p-0 list-none">
        <?php foreach ($activity as $act): ?>
        <li class="flex items-start gap-3 px-4 py-2.5 text-sm">
          <span class="mt-0.5 w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0" aria-hidden="true"><i class="fa-solid <?= $h($act['icon']) ?> text-xs"></i></span>
          <div class="min-w-0">
            <div class="font-semibold text-slate-800"><?= $h($act['text']) ?></div>
            <?php if (!empty($act['when'])): ?>
              <div class="text-[11px] text-slate-500"><?= $h($fmtWhen((string)$act['when'])) ?></div>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
