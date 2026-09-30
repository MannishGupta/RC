<?php
/**
 * Birthday & work-anniversary strip — next 7 days (IST).
 */
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$team = [];
if (class_exists('AppDB')) {
    $raw = AppDB::read('team');
    if (is_array($raw)) {
        $team = array_values(array_filter($raw, 'is_array'));
    }
}
$tz = new DateTimeZone('Asia/Kolkata');
$today = new DateTime('today', $tz);
$horizon = (clone $today)->modify('+7 days');
$items = [];

foreach ($team as $m) {
    $name = trim((string)($m['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $slug = (string)($m['slug'] ?? $m['id'] ?? '');
    $phone = (string)($m['phone'] ?? $m['mobile'] ?? '');
    $photo = (string)($m['photo'] ?? $m['image'] ?? '');

    // Birthday
    $dob = trim((string)($m['dob'] ?? $m['birthday'] ?? $m['birth_date'] ?? ''));
    if ($dob !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dob, $dm)) {
        try {
            $mmdd = $dm[2] . '-' . $dm[3];
            $next = DateTime::createFromFormat('Y-m-d', $today->format('Y') . '-' . $mmdd, $tz);
            if (!$next) {
                continue;
            }
            if ($next < $today) {
                $next->modify('+1 year');
            }
            if ($next <= $horizon) {
                $days = (int)$today->diff($next)->days;
                $items[] = [
                    'kind' => 'birthday',
                    'icon' => '🎂',
                    'label' => 'Birthday',
                    'name' => $name,
                    'slug' => $slug,
                    'phone' => $phone,
                    'photo' => $photo,
                    'when' => $next->format('Y-m-d'),
                    'when_label' => $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : $next->format('D, d M')),
                    'days' => $days,
                    'sort' => $days,
                ];
            }
        } catch (Throwable $e) {
        }
    }

    // Work anniversary
    $doj = trim((string)($m['doj'] ?? $m['joining_date'] ?? $m['date_of_joining'] ?? $m['join_date'] ?? ''));
    if ($doj !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $doj, $jm)) {
        try {
            $mmdd = $jm[2] . '-' . $jm[3];
            $next = DateTime::createFromFormat('Y-m-d', $today->format('Y') . '-' . $mmdd, $tz);
            if (!$next) {
                continue;
            }
            if ($next < $today) {
                $next->modify('+1 year');
            }
            if ($next <= $horizon) {
                $years = (int)$today->format('Y') - (int)$jm[1];
                if ($next->format('Y') > $today->format('Y') || ($next == $today && (int)$jm[2] === (int)$today->format('m') && (int)$jm[3] === (int)$today->format('d'))) {
                    // years of service at this anniversary
                    $years = (int)$next->format('Y') - (int)$jm[1];
                }
                $days = (int)$today->diff($next)->days;
                $items[] = [
                    'kind' => 'anniversary',
                    'icon' => '🏅',
                    'label' => $years > 0 ? ($years . 'y work anniversary') : 'Work anniversary',
                    'name' => $name,
                    'slug' => $slug,
                    'phone' => $phone,
                    'photo' => $photo,
                    'when' => $next->format('Y-m-d'),
                    'when_label' => $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : $next->format('D, d M')),
                    'days' => $days,
                    'sort' => $days,
                ];
            }
        } catch (Throwable $e) {
        }
    }
}

usort($items, static fn($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['name'], $b['name']));
$items = array_slice($items, 0, 12);
if (!$items) {
    return;
}
?>
<div id="rc-bday-strip" class="mb-4 rounded-2xl border border-amber-200/80 bg-gradient-to-r from-amber-50 via-white to-rose-50 shadow-sm overflow-hidden" role="region" aria-label="Upcoming birthdays and anniversaries">
  <div class="px-4 py-2.5 border-b border-amber-100/80 flex items-center justify-between gap-2">
    <div class="flex items-center gap-2 min-w-0">
      <span class="text-lg" aria-hidden="true">🎉</span>
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-amber-800/80 m-0">Celebrations · next 7 days</p>
        <p class="text-sm font-bold text-slate-900 m-0"><?= count($items) ?> upcoming</p>
      </div>
    </div>
    <span class="text-[10px] font-semibold text-slate-500 tabular-nums"><?= $h($today->format('d M Y')) ?> IST</span>
  </div>
  <div class="flex gap-2 overflow-x-auto p-3 scroll-smooth" style="-webkit-overflow-scrolling:touch">
    <?php foreach ($items as $it):
      $wa = '';
      if ($it['phone'] !== '') {
        $digits = preg_replace('/\D+/', '', $it['phone']);
        if (strlen($digits) >= 10) {
          if (strlen($digits) === 10) {
            $digits = '91' . $digits;
          }
          $msg = rawurlencode(
            $it['kind'] === 'birthday'
              ? ('Happy Birthday ' . $it['name'] . '! 🎂 Wishing you a wonderful year ahead.')
              : ('Congratulations ' . $it['name'] . ' on your work anniversary! 🏅')
          );
          $wa = 'https://wa.me/' . $digits . '?text=' . $msg;
        }
      }
    ?>
    <div class="shrink-0 w-56 rounded-xl border border-slate-200 bg-white p-3 shadow-sm flex flex-col gap-2">
      <div class="flex items-center gap-2">
        <div class="w-10 h-10 rounded-lg overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center text-sm font-bold text-slate-600 shrink-0">
          <?php if ($it['photo'] !== ''): ?>
            <img src="/images/<?= $h(rawurlencode(basename($it['photo']))) ?>" alt="" width="40" height="40" class="w-full h-full object-cover" loading="lazy" onerror="this.remove()">
          <?php else: ?>
            <?= $h(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?>
          <?php endif; ?>
        </div>
        <div class="min-w-0">
          <div class="text-sm font-bold text-slate-900 truncate"><?= $h($it['name']) ?></div>
          <div class="text-[11px] font-semibold text-slate-600"><?= $h($it['icon'] . ' ' . $it['label']) ?></div>
        </div>
      </div>
      <div class="flex items-center justify-between gap-1">
        <span class="text-[11px] font-bold <?= $it['days'] === 0 ? 'text-rose-700 bg-rose-50 border-rose-200' : 'text-amber-800 bg-amber-50 border-amber-200' ?> border rounded-full px-2 py-0.5"><?= $h($it['when_label']) ?></span>
        <?php if ($wa !== ''): ?>
          <a href="<?= $h($wa) ?>" target="_blank" rel="noopener" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-900 inline-flex items-center gap-1" aria-label="Wish <?= $h($it['name']) ?> on WhatsApp">
            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Wish
          </a>
        <?php elseif ($it['slug'] !== ''): ?>
          <a href="?card=business&amp;slug=<?= $h(rawurlencode($it['slug'])) ?>" class="text-[11px] font-bold text-blue-700 hover:underline">Card</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
