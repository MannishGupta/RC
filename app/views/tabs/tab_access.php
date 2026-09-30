<?php
/** Access Mode panel — all three roles */
declare(strict_types=1);
$role = $_SESSION['user'] ?? 'public';
if ($role === 'crm') $role = 'public';
$labels = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Co. Admin',
    'public'      => 'Visitor',
];
$label = $labels[$role] ?? 'Visitor';
$desc = [
    'super_admin' => 'Global control plane: tenant setup, infrastructure telemetry, platform optimisation, and changelog. No tenant operational dashboard in this session.',
    'admin'       => 'Tenant administrator: full create, read, update, and delete on this organisation’s data. No cross-tenant privileges.',
    'public'      => 'Visitor: view, share, and print directories and reports. No add, edit, delete, or company setup access.',
];
$d = $desc[$role] ?? $desc['public'];
$tenant = defined('TENANT_ID') ? TENANT_ID : 'default';
$host = $_SERVER['HTTP_HOST'] ?? '';
$ver = defined('APP_VERSION') ? APP_VERSION : '20260925.01';
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$badge = [
    'super_admin' => 'bg-violet-100 text-violet-800 border-violet-200',
    'admin'       => 'bg-blue-100 text-blue-800 border-blue-200',
    'public'      => 'bg-slate-100 text-slate-700 border-slate-200',
];
$bc = $badge[$role] ?? $badge['public'];
?>
<div class="max-w-2xl space-y-4">
  <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 m-0 mb-2">Access Mode</p>
    <div class="flex flex-wrap items-center gap-3 mb-3">
      <span class="inline-flex items-center rounded-full border px-3 py-1 text-sm font-bold <?= $bc ?>"><?= $h($label) ?></span>
      <span class="text-xs text-slate-500 font-mono"><?= $h($ver) ?></span>
    </div>
    <p class="text-sm text-slate-600 m-0 leading-relaxed"><?= $h($d) ?></p>
  </div>

  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm grid sm:grid-cols-2 gap-4 text-sm">
    <div>
      <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Host</div>
      <div class="font-mono text-slate-800 mt-1"><?= $h($host) ?></div>
    </div>
    <div>
      <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tenant</div>
      <div class="font-semibold text-slate-800 mt-1"><?= $h($tenant) ?></div>
    </div>
  </div>

  <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
    <p class="font-bold text-slate-800 m-0 mb-2">Credential map</p>
    <ul class="m-0 pl-4 space-y-1 list-disc">
      <li><strong>Super Admin</strong> — global tenants, monitor, optimisation, changelog</li>
      <li><strong>Co. Admin</strong> — full CRUD on this tenant; organisational setup</li>
      <li><strong>Visitor</strong> — view / share / print only; no company setup</li>
    </ul>
  </div>
</div>
