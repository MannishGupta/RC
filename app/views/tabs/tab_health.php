<?php
/** Platform health + one-click backup. Version: 260921.48 */
if (!defined('BASE_PATH')) exit;
?>
<div class="space-y-4" x-data="valueHealth()" x-init="run()">
  <div class="flex flex-wrap justify-between gap-2">
    <div>
      <h2 class="text-base font-bold text-slate-800">Platform Health &amp; Backup</h2>
      <p class="text-xs text-slate-500">Writable paths, runtime version, and data snapshot zip (JSON + config + media under <code>data/</code>; app PHP excluded).</p>
    </div>
    <div class="flex gap-2">
      <button type="button" @click="run()" class="h-9 px-3 rounded-lg border text-xs font-bold">Recheck</button>
      <button type="button" @click="backup()" class="h-9 px-3 rounded-lg bg-emerald-600 text-white text-xs font-bold" x-show="isAdmin">Create backup zip</button>
    </div>
  </div>
  <p class="text-xs text-emerald-700 font-semibold" x-text="msg"></p>
  <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm" x-show="isAdmin">
    <h3 class="text-sm font-bold text-slate-800 mb-2">CRM role pack</h3>
    <p class="text-[11px] text-slate-500 mb-2">Non-admin users only see tabs allowed by this pack (see data/value/roles.json).</p>
    <div class="flex flex-wrap gap-2 items-center">
      <select x-model="crmPack" class="h-9 px-2 rounded-lg border text-sm">
        <option value="logistics">Logistics</option>
        <option value="hr">HR</option>
        <option value="readonly">Read only</option>
      </select>
      <button type="button" @click="savePack()" class="h-9 px-3 rounded-lg bg-blue-600 text-white text-xs font-bold">Apply pack</button>
    </div>
    <p class="text-[11px] text-slate-400 mt-2">Scheduled reminders: hit <code>tools/reminders_cron.php?token=YOUR_SECRET</code> via Task Scheduler daily.</p>
  </div>
  <ul class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-50 shadow-sm">
    <template x-for="c in checks" :key="c.name">
      <li class="flex items-center justify-between px-4 py-3 text-sm">
        <span class="font-medium text-slate-700" x-text="c.name"></span>
        <span class="text-xs font-bold" :class="c.ok ? 'text-emerald-600' : 'text-red-600'"
              x-text="c.ok ? ('OK' + (c.value!=null ? ' · '+c.value : '')) : 'FAIL'"></span>
      </li>
    </template>
  </ul>
</div>
<script>
function valueHealth() {
  return {
    checks: [], msg: '', crmPack: 'logistics',
    isAdmin: true,
    async run() {
      const fd = new FormData(); fd.append('action','health');
      const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
      if (r.status==='ok') this.checks = r.checks || [];
    },
    async backup() {
      this.msg = 'Creating snapshot…';
      const fd = new FormData(); fd.append('action','backup_create');
      const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
      this.msg = r.status==='ok'
        ? ('Backup ready: ' + r.path + ' · ' + (r.files||'?') + ' files · ' + (r.bytes||0) + ' bytes'
           + (r.kinds ? (' · JSON ' + r.kinds.json + ' / config ' + r.kinds.config_php + ' / media ' + r.kinds.media) : ''))
        : (r.message || 'Backup failed');
    },
    async savePack() {
      const fd = new FormData();
      fd.append('action','role_set_crm_pack');
      fd.append('pack', this.crmPack);
      const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
      this.msg = r.status==='ok' ? ('CRM pack set to ' + r.crm_pack + ' — CRM users must re-login / refresh') : (r.message || 'Failed');
    }
  };
}
</script>
