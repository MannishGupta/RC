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
<div id="rc-bday-strip" class="rc-live-card rounded-xl border shadow-sm overflow-hidden min-w-0 min-h-0 flex flex-col" role="region" aria-label="Upcoming birthdays and anniversaries">
  <div class="rc-live-card__hd px-2.5 py-1.5 border-b flex items-center justify-between gap-1 shrink-0">
    <div class="flex items-center gap-1.5 min-w-0">
      <span class="text-sm shrink-0" aria-hidden="true">🎉</span>
      <div class="min-w-0">
        <p class="rc-live-kicker text-[9px] font-bold uppercase tracking-[0.12em] m-0 truncate">Celebrations</p>
        <p class="rc-live-value text-xs font-bold m-0 leading-tight"><?= count($items) ?> upcoming</p>
      </div>
    </div>
    <span class="rc-live-kicker text-[9px] font-semibold tabular-nums shrink-0"><?= $h($today->format('d M')) ?></span>
  </div>
  <div class="flex gap-1.5 overflow-x-auto p-2 scroll-smooth flex-1 min-h-0" style="-webkit-overflow-scrolling:touch">
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
    <div class="rc-live-subcard shrink-0 w-36 rounded-lg border p-2 shadow-sm flex flex-col gap-1">
      <div class="flex items-center gap-2">
        <div class="rc-live-avatar w-8 h-8 rounded-md overflow-hidden border flex items-center justify-center text-[10px] font-bold shrink-0">
          <?php if ($it['photo'] !== ''): ?>
            <img src="/images/<?= $h(rawurlencode(basename($it['photo']))) ?>" alt="" width="32" height="32" class="w-full h-full object-cover" loading="lazy" onerror="this.remove()">
          <?php else: ?>
            <?= $h(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?>
          <?php endif; ?>
        </div>
        <div class="min-w-0">
          <div class="rc-live-value text-xs font-bold truncate"><?= $h($it['name']) ?></div>
          <div class="rc-live-meta text-[11px] font-semibold"><?= $h($it['icon'] . ' ' . $it['label']) ?></div>
        </div>
      </div>
      <div class="flex items-center justify-between gap-1">
        <span class="text-[11px] font-bold <?= $it['days'] === 0 ? 'rc-live-pill rc-live-pill rc-live-pill--hot' : 'rc-live-pill' ?> border rounded-full px-2 py-0.5"><?= $h($it['when_label']) ?></span>
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
