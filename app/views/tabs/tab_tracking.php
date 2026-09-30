<?php
// Version: 1.5 — Workforce Geolocation & Compliance & HR violation logs (dashboard tab)
if (!defined('BASE_PATH')) {
    exit;
}
$runnersUrl = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
if ($runnersUrl === '' || $runnersUrl === '.') {
    $runnersUrl = '/runners.php';
} else {
    $runnersUrl = $runnersUrl . '/runners.php';
}
$runnersUrl = str_replace('\\', '/', $runnersUrl);
// Prefer site-root runners.php
$runnersUrl = '/runners.php';

$policyPath = BASE_PATH . '/data/runners/policy_settings.json';
$violPath = BASE_PATH . '/data/runners/location_violations_log.json';
$statePath = BASE_PATH . '/data/runners/runners_state.json';

$policy = [];
$violations = [];
$state = [];
if (is_file($policyPath)) {
    $policy = json_decode((string)@file_get_contents($policyPath), true) ?: [];
}
if (is_file($violPath)) {
    $violations = json_decode((string)@file_get_contents($violPath), true) ?: [];
}
if (is_file($statePath)) {
    $state = json_decode((string)@file_get_contents($statePath), true) ?: [];
}
if (!is_array($violations)) {
    $violations = [];
}
if (!is_array($state)) {
    $state = [];
}
$openViol = array_values(array_filter($violations, static function ($v) {
    return is_array($v) && (($v['status'] ?? '') === 'OPEN');
}));
$openCount = count($openViol);
$totalRunners = is_array($state) ? count($state) : 0;
$inViol = 0;
foreach ($state as $r) {
    if (is_array($r) && (($r['compliance'] ?? '') === 'IN_VIOLATION')) {
        $inViol++;
    }
}
$hours = $policy['working_hours'] ?? ['start' => '09:00', 'end' => '19:00'];
$email = (string)($policy['master_tracking_email'] ?? 'admin.logistics@company.com');
$timeout = (int)($policy['heartbeat_timeout_minutes'] ?? 10);
$mandatory = $policy['mandatory_designations'] ?? ['Driver', 'Office Runner'];
?>
<div class="space-y-4">
  <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-950" role="note">
    <strong>Privacy (DPDP):</strong> Location data is personal data. Restrict access to authorised staff.
    <a class="font-bold underline" href="?tab=terms&amp;policy=location">Location notice</a>
    · <a class="font-bold underline" href="?tab=terms&amp;policy=privacy">Privacy policy</a>
  </div>
  <div class="flex flex-wrap items-center gap-3">
    <div class="min-w-0">
      <h2 class="text-base font-bold text-slate-800">Workforce Geolocation & Compliance &amp; HR Policy Logs</h2>
      <p class="text-xs text-slate-500 mt-0.5">
        Working hours <?= htmlspecialchars((string)($hours['start'] ?? '09:00')) ?>–<?= htmlspecialchars((string)($hours['end'] ?? '19:00')) ?>
        · Heartbeat <?= (int)$timeout ?> min
        · Master <?= htmlspecialchars($email) ?>
      </p>
    </div>
    <div class="ml-auto flex flex-wrap gap-2">
      <a href="<?= htmlspecialchars($runnersUrl) ?>" target="_blank" rel="noopener"
         class="h-9 px-4 rounded-lg text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 inline-flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-map-location-dot"></i> Open Command Centre Map
      </a>
      <a href="/track_share.php" target="_blank" rel="noopener"
         class="h-9 px-3 rounded-lg text-xs font-bold text-sky-800 bg-sky-50 border border-sky-100 inline-flex items-center gap-1.5">
        <i class="fa-solid fa-mobile-screen"></i> Authorize Browser Geolocation
      </a>
      <a href="<?= htmlspecialchars($runnersUrl) ?>?action=export_violations_csv&status=ALL" target="_blank"
         class="h-9 px-3 rounded-lg text-xs font-bold text-emerald-700 bg-sky-50 border border-emerald-100 inline-flex items-center gap-1.5">
        <i class="fa-solid fa-file-csv"></i> Violation CSV
      </a>
      <a href="<?= htmlspecialchars($runnersUrl) ?>?action=export_violations_print&status=ALL" target="_blank"
         class="h-9 px-3 rounded-lg text-xs font-bold text-slate-700 bg-white border border-slate-200 inline-flex items-center gap-1.5">
        <i class="fa-solid fa-print"></i> Print log
      </a>
    </div>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
    <div class="rounded-xl border border-slate-200 bg-white p-3">
      <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Monitored workforce</div>
      <div class="text-2xl font-black text-slate-800 mt-1"><?= (int)$totalRunners ?></div>
    </div>
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
      <div class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Active compliance exceptions</div>
      <div class="text-2xl font-black text-rose-600 mt-1"><?= (int)$openCount ?></div>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
      <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600">In violation (state)</div>
      <div class="text-2xl font-black text-amber-700 mt-1"><?= (int)$inViol ?></div>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3">
      <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Mandated designations</div>
      <div class="text-xs font-semibold text-slate-700 mt-2 leading-relaxed">
        <?= htmlspecialchars(implode(', ', array_map('strval', (array)$mandatory))) ?>
      </div>
    </div>
  </div>

  <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center gap-2">
      <span class="text-sm font-bold text-slate-800">Open violation incidents</span>
      <?php if ($openCount > 0): ?>
      <span class="inline-flex items-center rounded-full bg-rose-100 text-rose-700 text-[10px] font-bold px-2 py-0.5"><?= (int)$openCount ?> active</span>
      <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100 bg-slate-50">
            <th class="px-3 py-2 font-bold">Incident</th>
            <th class="px-3 py-2 font-bold">Employee</th>
            <th class="px-3 py-2 font-bold">Type</th>
            <th class="px-3 py-2 font-bold">Detected</th>
            <th class="px-3 py-2 font-bold">Last known</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($openViol === []): ?>
          <tr><td colspan="5" class="px-3 py-10 text-center text-slate-400">No open violations. Heartbeat and turn-off events appear here during working hours.</td></tr>
          <?php else: ?>
          <?php foreach ($openViol as $v): ?>
          <tr class="border-b border-slate-50 hover:bg-slate-50/80">
            <td class="px-3 py-2 font-mono text-[11px] text-slate-600"><?= htmlspecialchars((string)($v['incident_id'] ?? '')) ?></td>
            <td class="px-3 py-2">
              <div class="font-bold text-slate-800"><?= htmlspecialchars((string)($v['name'] ?? '')) ?></div>
              <div class="text-slate-400"><?= htmlspecialchars((string)($v['designation'] ?? '')) ?></div>
            </td>
            <td class="px-3 py-2">
              <span class="inline-flex rounded-full bg-rose-50 text-rose-700 px-2 py-0.5 text-[10px] font-bold">
                <?= htmlspecialchars((string)($v['violation_type'] ?? '')) ?>
              </span>
            </td>
            <td class="px-3 py-2 text-slate-600 whitespace-nowrap"><?= htmlspecialchars((string)($v['detected_at'] ?? '')) ?></td>
            <td class="px-3 py-2 text-slate-500 max-w-[14rem] truncate" title="<?= htmlspecialchars((string)($v['last_known_address'] ?? '')) ?>">
              <?= htmlspecialchars((string)($v['last_known_address'] ?? trim(($v['last_known_lat'] ?? '') . ', ' . ($v['last_known_lng'] ?? ''), ' ,'))) ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 text-sm font-bold text-slate-800">Recent violation log (all statuses)</div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100 bg-slate-50">
            <th class="px-3 py-2 font-bold">Incident</th>
            <th class="px-3 py-2 font-bold">Employee</th>
            <th class="px-3 py-2 font-bold">Type</th>
            <th class="px-3 py-2 font-bold">Disconnected</th>
            <th class="px-3 py-2 font-bold">Reconnected</th>
            <th class="px-3 py-2 font-bold">Mins</th>
            <th class="px-3 py-2 font-bold">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $recent = $violations;
          usort($recent, static function ($a, $b) {
              return strcmp((string)($b['detected_at'] ?? ''), (string)($a['detected_at'] ?? ''));
          });
          $recent = array_slice($recent, 0, 40);
          if ($recent === []):
          ?>
          <tr><td colspan="7" class="px-3 py-8 text-center text-slate-400">Log is empty. Incidents are written to <code class="text-[10px] bg-slate-100 px-1 rounded">data/runners/location_violations_log.json</code>.</td></tr>
          <?php else: foreach ($recent as $v): ?>
          <tr class="border-b border-slate-50">
            <td class="px-3 py-2 font-mono text-[11px]"><?= htmlspecialchars((string)($v['incident_id'] ?? '')) ?></td>
            <td class="px-3 py-2 font-semibold text-slate-800"><?= htmlspecialchars((string)($v['name'] ?? '')) ?></td>
            <td class="px-3 py-2"><?= htmlspecialchars((string)($v['violation_type'] ?? '')) ?></td>
            <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars((string)($v['detected_at'] ?? '')) ?></td>
            <td class="px-3 py-2 whitespace-nowrap"><?= htmlspecialchars((string)($v['resolved_at'] ?? '—')) ?></td>
            <td class="px-3 py-2"><?= htmlspecialchars((string)($v['duration_minutes'] ?? '—')) ?></td>
            <td class="px-3 py-2">
              <?php $st = (string)($v['status'] ?? ''); ?>
              <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold <?= $st === 'OPEN' ? 'bg-rose-100 text-rose-700' : 'bg-sky-50 text-emerald-700' ?>">
                <?= htmlspecialchars($st) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-xs text-slate-600 space-y-1">
    <div class="font-bold text-slate-700">How to use</div>
    <p>1. Open <strong>full map cockpit</strong> for live pins, path playback, and HR policy settings.</p>
    <p>2. Device apps POST to <code class="bg-white px-1 rounded border">/runners.php?action=update_location</code> (lat/lng) or <code class="bg-white px-1 rounded border">report_turn_off</code> when sharing is disabled.</p>
    <p>3. During working hours, missed heartbeats auto-create <code class="bg-white px-1 rounded border">SIGNAL_LOST_HEARTBEAT_TIMEOUT</code> incidents for mandatory designations.</p>
  </div>
</div>
