<?php
/**
 * Platform health + one-click tools launch pad.
 * Every operational path is a button — no need to remember URLs.
 */
if (!defined('BASE_PATH')) {
    exit;
}
$__isSa = !empty($isSuperAdmin);
$__isAd = !empty($isAdmin) || $__isSa;
?>
<div class="space-y-5" x-data="valueHealth()" x-init="run()">
  <div class="flex flex-wrap justify-between gap-2">
    <div>
      <h2 class="text-base font-bold text-slate-800">Platform Health &amp; Tools</h2>
      <p class="text-xs text-slate-500">Writable paths, runtime, backups, and one-click openers for every deployment utility.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <button type="button" @click="run()" class="h-9 px-3 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700">Recheck health</button>
      <button type="button" @click="backup()" class="h-9 px-3 rounded-lg bg-emerald-600 text-white text-xs font-bold" x-show="isAdmin">Create backup zip</button>
    </div>
  </div>
  <p class="text-xs font-semibold" :class="msg.indexOf('fail')>=0 || msg.indexOf('FAIL')>=0 ? 'text-rose-700' : 'text-emerald-700'" x-text="msg"></p>

  <!-- Launch pad: no raw URLs required -->
  <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
      <h3 class="text-sm font-bold text-slate-800">Operations launch pad</h3>
      <p class="text-[11px] text-slate-500 mt-0.5">Open any function with a button. Prefer these over typing paths.</p>
    </div>
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">

      <a href="/health.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-heart-pulse text-emerald-600"></i>
        <span class="rc-tool-tile__t">Uptime probe</span>
        <span class="rc-tool-tile__d">JSON status for monitors · no login</span>
      </a>

      <?php if ($__isAd): ?>
      <button type="button" @click="backup()" class="rc-tool-tile text-left w-full">
        <i class="fa-solid fa-file-zipper text-emerald-600"></i>
        <span class="rc-tool-tile__t">Backup data zip</span>
        <span class="rc-tool-tile__d">Snapshot tenant data / config / media</span>
      </button>
      <a href="/tools/backup.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-download text-blue-600"></i>
        <span class="rc-tool-tile__t">Backup tool page</span>
        <span class="rc-tool-tile__d">Alternate backup UI if configured</span>
      </a>
      <a href="/tools/diagnostics.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-stethoscope text-indigo-600"></i>
        <span class="rc-tool-tile__t">Diagnostics</span>
        <span class="rc-tool-tile__d">Host / path / extension checks</span>
      </a>
      <a href="/tools/flush_cache.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-broom text-amber-600"></i>
        <span class="rc-tool-tile__t">Flush cache</span>
        <span class="rc-tool-tile__d">Clear app / card caches</span>
      </a>
      <a href="/tools/opcache_status.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-microchip text-slate-600"></i>
        <span class="rc-tool-tile__t">Opcache status</span>
        <span class="rc-tool-tile__d">PHP opcache snapshot</span>
      </a>
      <?php endif; ?>

      <a href="/tools/print_directory.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-print text-slate-700"></i>
        <span class="rc-tool-tile__t">Print directory</span>
        <span class="rc-tool-tile__d">Human Capital print cards</span>
      </a>
      <a href="/tools/export_team_csv.php" class="rc-tool-tile">
        <i class="fa-solid fa-file-csv text-slate-700"></i>
        <span class="rc-tool-tile__t">Export team CSV</span>
        <span class="rc-tool-tile__d">Download roster spreadsheet</span>
      </a>
      <a href="/tools/export_vcf.php" class="rc-tool-tile">
        <i class="fa-solid fa-address-book text-emerald-700"></i>
        <span class="rc-tool-tile__t">vCard export</span>
        <span class="rc-tool-tile__d">All contacts for phone import</span>
      </a>
      <a href="/blood_report.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-droplet text-rose-600"></i>
        <span class="rc-tool-tile__t">Blood report roster</span>
        <span class="rc-tool-tile__d">Team blood groups (signed-in)</span>
      </a>
      <a href="/janam_patri.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-om text-amber-700"></i>
        <span class="rc-tool-tile__t">Janam Patri entry</span>
        <span class="rc-tool-tile__d">Open chart form (or use Team person tools)</span>
      </a>
      <a href="?tab=team" class="rc-tool-tile">
        <i class="fa-solid fa-users text-blue-700"></i>
        <span class="rc-tool-tile__t">Human Capital Index</span>
        <span class="rc-tool-tile__d">Directory, cards, signatures, reports</span>
      </a>
      <a href="?tab=numero" class="rc-tool-tile">
        <i class="fa-solid fa-wand-magic-sparkles text-violet-600"></i>
        <span class="rc-tool-tile__t">Vedic Numero</span>
        <span class="rc-tool-tile__d">Numerology module tab</span>
      </a>

      <?php if ($__isSa): ?>
      <a href="?tab=monitor" class="rc-tool-tile">
        <i class="fa-solid fa-heart-pulse text-red-600"></i>
        <span class="rc-tool-tile__t">Monitor</span>
        <span class="rc-tool-tile__d">Infrastructure telemetry</span>
      </a>
      <a href="?tab=opt" class="rc-tool-tile">
        <i class="fa-solid fa-gauge-high text-orange-600"></i>
        <span class="rc-tool-tile__t">System optimizer</span>
        <span class="rc-tool-tile__d">Image / asset optimisation</span>
      </a>
      <a href="?tab=tenants" class="rc-tool-tile">
        <i class="fa-solid fa-building-user text-indigo-700"></i>
        <span class="rc-tool-tile__t">Tenant setup</span>
        <span class="rc-tool-tile__d">Hosts, modules, matrix</span>
      </a>
      <a href="/tools/file_tree.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-folder-tree text-slate-600"></i>
        <span class="rc-tool-tile__t">File tree</span>
        <span class="rc-tool-tile__d">Deploy path inventory</span>
      </a>
      <?php endif; ?>


      <?php if ($__isSa): ?>
      <a href="?tab=monitor" class="rc-tool-tile">
        <i class="fa-solid fa-heart-pulse text-rose-600"></i>
        <span class="rc-tool-tile__t">Monitor / Diagnostics</span>
        <span class="rc-tool-tile__d">System About, libraries, host map, logs</span>
      </a>
      <a href="?tab=opt" class="rc-tool-tile">
        <i class="fa-solid fa-gauge-high text-indigo-600"></i>
        <span class="rc-tool-tile__t">Optimisation Suite</span>
        <span class="rc-tool-tile__d">JSON normalise, media optimise, layout migrate</span>
      </a>
      <a href="?tab=tenants" class="rc-tool-tile">
        <i class="fa-solid fa-building-user text-slate-700"></i>
        <span class="rc-tool-tile__t">Tenant Setup</span>
        <span class="rc-tool-tile__d">Module matrix, Windows installers, portfolio</span>
      </a>
      <button type="button" class="rc-tool-tile text-left w-full" onclick="document.getElementById('rc-build-installers-btn')?location.href='?tab=tenants':location.href='?tab=tenants'">
        <i class="fa-brands fa-windows text-sky-600"></i>
        <span class="rc-tool-tile__t">Windows Installers</span>
        <span class="rc-tool-tile__d">Build ARP installers for all tenants</span>
      </button>
      <?php endif; ?>

      <?php if ($__isAd): ?>
      <button type="button" class="rc-tool-tile text-left w-full" @click="flushCache()">
        <i class="fa-solid fa-broom text-amber-600"></i>
        <span class="rc-tool-tile__t">Flush OPcache</span>
        <span class="rc-tool-tile__d">Clear PHP opcode cache on this host</span>
      </button>
      <a href="/tools/opcache_status.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-microchip text-slate-600"></i>
        <span class="rc-tool-tile__t">OPcache status</span>
        <span class="rc-tool-tile__d">Memory, hit rate, cached scripts</span>
      </a>
      <a href="/tools/diagnostics.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-stethoscope text-teal-600"></i>
        <span class="rc-tool-tile__t">Diagnostics report</span>
        <span class="rc-tool-tile__d">Extended runtime diagnostics</span>
      </a>
      <a href="/tools/file_tree.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-folder-tree text-amber-700"></i>
        <span class="rc-tool-tile__t">File tree</span>
        <span class="rc-tool-tile__d">Deployed path inventory</span>
      </a>
      <a href="/tools/flush_cache.php" target="_blank" rel="noopener" class="rc-tool-tile">
        <i class="fa-solid fa-eraser text-rose-600"></i>
        <span class="rc-tool-tile__t">Flush cache (page)</span>
        <span class="rc-tool-tile__d">Alternate flush entry</span>
      </a>
      <?php endif; ?>

    </div>
  </section>

  <?php if ($__isAd): ?>
  <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
    <h3 class="text-sm font-bold text-slate-800 mb-2">CRM role pack</h3>
    <p class="text-[11px] text-slate-500 mb-2">Non-admin users only see tabs allowed by this pack.</p>
    <div class="flex flex-wrap gap-2 items-center">
      <select x-model="crmPack" class="h-9 px-2 rounded-lg border text-sm">
        <option value="logistics">Logistics</option>
        <option value="hr">HR</option>
        <option value="readonly">Read only</option>
      </select>
      <button type="button" @click="savePack()" class="h-9 px-3 rounded-lg bg-blue-600 text-white text-xs font-bold">Apply pack</button>
    </div>
    <p class="text-[11px] text-slate-400 mt-2">Scheduled reminders: use Task Scheduler against the reminders tool (token required) — do not share the token in chat.</p>
  </div>
  <?php endif; ?>

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
<style>
.rc-tool-tile{
  display:flex; flex-direction:column; align-items:flex-start; gap:4px;
  padding:12px 14px; border-radius:12px; border:1px solid #e2e8f0;
  background:#fafbfc; text-decoration:none; color:inherit;
  transition:border-color .15s, background .15s, box-shadow .15s;
  cursor:pointer; font:inherit;
}
.rc-tool-tile:hover{
  border-color:#93c5fd; background:#eff6ff; box-shadow:0 1px 2px rgba(15,23,42,.06);
}
.rc-tool-tile i{ font-size:1.1rem; margin-bottom:2px; }
.rc-tool-tile__t{ font-size:13px; font-weight:800; color:#0f172a; }
.rc-tool-tile__d{ font-size:11px; color:#64748b; line-height:1.35; }
</style>
<script>
function valueHealth() {
  return {
    checks: [], msg: '', crmPack: 'logistics',
    isAdmin: <?= $__isAd ? 'true' : 'false' ?>,
    async run() {
      const fd = new FormData(); fd.append('action','health');
      try {
        const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
        this.checks = r.checks || r.items || [];
        this.msg = r.status === 'ok' || r.status === 'success' ? 'Health check complete' : (r.message || '');
      } catch (e) {
        this.msg = 'Health check failed: ' + (e.message || e);
      }
    },
    async backup() {
      this.msg = 'Creating backup…';
      const fd = new FormData(); fd.append('action','backup_create');
      try {
        const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
        this.msg = (r.status==='ok'||r.status==='success')
          ? ('Backup ready' + (r.file ? (' · ' + r.file) : '') + (r.files ? (' · ' + r.files + ' files') : ''))
          : (r.message || 'Backup failed');
        if (r.download || r.url) window.open(r.download || r.url, '_blank');
      } catch (e) {
        this.msg = 'Backup failed: ' + (e.message || e);
      }
    },
    async savePack() {
      const fd = new FormData();
      fd.append('action','role_set_crm_pack');
      fd.append('pack', this.crmPack);
      try {
        const r = await (await fetch('api_value.php',{method:'POST',body:fd,credentials:'same-origin'})).json();
        this.msg = r.status==='ok' ? ('CRM pack set to ' + r.crm_pack + ' — users should refresh') : (r.message || 'Failed');
      } catch (e) {
        this.msg = String(e.message||e);
      }
    }
  };
}
</script>
