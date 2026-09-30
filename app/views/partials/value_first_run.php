<?php
/**
 * Co. Admin first-run checklist — dismissed via localStorage on client.
 */
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$steps = [];

$coName = '';
$coLogo = '';
if (class_exists('AppDB')) {
    $co = AppDB::read('company');
    if (is_array($co)) {
        $row = isset($co['name']) ? $co : ($co[0] ?? []);
        if (is_array($row)) {
            $coName = trim((string)($row['name'] ?? ''));
            $coLogo = trim((string)($row['logo'] ?? $row['logo_file'] ?? ''));
        }
    }
}
$teamN = 0;
$bankN = 0;
if (class_exists('AppDB')) {
    $t = AppDB::read('team');
    if (is_array($t)) {
        $teamN = count(array_filter($t, 'is_array'));
    }
    $b = AppDB::read('bank');
    if (is_array($b)) {
        $bankN = count(array_filter($b, 'is_array'));
    }
}

$steps[] = [
    'id' => 'company',
    'done' => $coName !== '' && mb_strlen($coName) >= 2,
    'title' => 'Organisation identity',
    'hint' => 'Set company name (and logo) under Company Setup.',
    'href' => '?tab=company',
    'icon' => 'fa-building',
];
$steps[] = [
    'id' => 'logo',
    'done' => $coLogo !== '',
    'title' => 'Brand logo',
    'hint' => 'Upload a logo so cards and printouts look professional.',
    'href' => '?tab=company',
    'icon' => 'fa-image',
];
$steps[] = [
    'id' => 'team3',
    'done' => $teamN >= 3,
    'title' => 'Add people (' . $teamN . '/3)',
    'hint' => 'Provision at least three contacts in Human Capital Index.',
    'href' => '?tab=team',
    'icon' => 'fa-users',
];
$steps[] = [
    'id' => 'bank',
    'done' => $bankN >= 1,
    'title' => 'Treasury / UPI',
    'hint' => 'Add one bank or UPI record for payment QR sharing.',
    'href' => '?tab=bank',
    'icon' => 'fa-building-columns',
];
$steps[] = [
    'id' => 'share',
    'done' => false, // client may mark via localStorage
    'title' => 'Share a digital card',
    'hint' => 'Open any contact → Business / ID card → Share on WhatsApp.',
    'href' => '?tab=team',
    'icon' => 'fa-share-nodes',
    'client' => true,
];

$doneN = count(array_filter($steps, static fn($s) => !empty($s['done'])));
$total = count($steps);
if ($doneN >= $total - 1) {
    // Almost complete — still show until client dismisses
}
$tenantKey = defined('TENANT_ID') ? (string)TENANT_ID : 'default';
?>
<div id="rc-first-run" class="mb-5 rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white shadow-sm overflow-hidden" x-data="rcFirstRun('<?= $h($tenantKey) ?>')" x-show="visible" x-cloak role="region" aria-label="Getting started checklist">
  <div class="px-4 py-3 border-b border-indigo-100 flex flex-wrap items-center justify-between gap-2">
    <div>
      <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-indigo-700/80 m-0">Getting started</p>
      <h3 class="text-sm font-bold text-slate-900 m-0">First-run checklist · <span x-text="doneCount"></span>/<?= (int)$total ?></h3>
    </div>
    <button type="button" @click="dismiss()" class="text-[11px] font-bold text-slate-500 hover:text-slate-800" aria-label="Dismiss checklist">Dismiss</button>
  </div>
  <div class="p-3">
    <div class="h-2 rounded-full bg-indigo-100 overflow-hidden mb-3" role="progressbar" :aria-valuenow="doneCount" aria-valuemin="0" aria-valuemax="<?= (int)$total ?>">
      <div class="h-full bg-indigo-600 transition-all duration-500" :style="'width:' + Math.round((doneCount/<?= (int)$total ?>)*100) + '%'"></div>
    </div>
    <ul class="m-0 p-0 list-none grid grid-cols-1 sm:grid-cols-2 gap-2">
      <?php foreach ($steps as $s): ?>
      <li class="flex items-start gap-2 rounded-xl border border-slate-200 bg-white p-3"
          data-step="<?= $h($s['id']) ?>"
          data-done="<?= !empty($s['done']) ? '1' : '0' ?>"
          data-client="<?= !empty($s['client']) ? '1' : '0' ?>">
        <span class="mt-0.5 w-6 h-6 rounded-full flex items-center justify-center text-xs shrink-0"
              :class="isDone('<?= $h($s['id']) ?>') ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
          <i class="fa-solid" :class="isDone('<?= $h($s['id']) ?>') ? 'fa-check' : '<?= $h($s['icon']) ?>'" aria-hidden="true"></i>
        </span>
        <div class="min-w-0 flex-1">
          <div class="text-sm font-bold text-slate-900"><?= $h($s['title']) ?></div>
          <div class="text-[11px] text-slate-600 mb-1"><?= $h($s['hint']) ?></div>
          <a href="<?= $h($s['href']) ?>" class="text-[11px] font-bold text-indigo-700 hover:underline">Open →</a>
          <?php if (!empty($s['client'])): ?>
            <button type="button" @click="markClient('<?= $h($s['id']) ?>')" class="ml-2 text-[11px] font-bold text-slate-500 hover:text-emerald-700">Mark done</button>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<script>
document.addEventListener('alpine:init', function () {
  if (window.__rcFirstRunReg) return;
  window.__rcFirstRunReg = true;
  Alpine.data('rcFirstRun', function (tenantId) {
    return {
      visible: true,
      clientDone: {},
      serverDone: {},
      init() {
        var key = 'rc_firstrun_dismiss_' + (tenantId || 'default');
        try {
          if (localStorage.getItem(key) === '1') this.visible = false;
          var raw = localStorage.getItem('rc_firstrun_client_' + (tenantId || 'default'));
          this.clientDone = raw ? JSON.parse(raw) : {};
        } catch (e) { this.clientDone = {}; }
        var self = this;
        this.$el.querySelectorAll('[data-step]').forEach(function (el) {
          self.serverDone[el.getAttribute('data-step')] = el.getAttribute('data-done') === '1';
        });
      },
      isDone(id) {
        return !!(this.serverDone[id] || this.clientDone[id]);
      },
      get doneCount() {
        var n = 0, self = this;
        Object.keys(this.serverDone).forEach(function (k) { if (self.isDone(k)) n++; });
        // ensure client-only keys counted
        Object.keys(this.clientDone).forEach(function (k) {
          if (self.clientDone[k] && !self.serverDone.hasOwnProperty(k)) n++;
        });
        // recount unique from DOM steps
        n = 0;
        var seen = {};
        this.$el.querySelectorAll('[data-step]').forEach(function (el) {
          var id = el.getAttribute('data-step');
          if (seen[id]) return;
          seen[id] = 1;
          if (self.isDone(id)) n++;
        });
        return n;
      },
      markClient(id) {
        this.clientDone[id] = true;
        try {
          localStorage.setItem('rc_firstrun_client_' + (tenantId || 'default'), JSON.stringify(this.clientDone));
        } catch (e) {}
      },
      dismiss() {
        this.visible = false;
        try {
          localStorage.setItem('rc_firstrun_dismiss_' + (tenantId || 'default'), '1');
        } catch (e) {}
      }
    };
  });
});
</script>
