<?php
/**
 * Tenant capability control — module matrix (Super Admin only).
 * Loads/saves via api_modules.php → tenants/{id}/data/config/modules.json
 */
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$tid = defined('TENANT_ID') ? (string)TENANT_ID : 'default';
?>
<section class="mt-8 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" id="rc-module-matrix"
         x-data="rcModuleMatrix()" x-init="load()">
  <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50">
    <div>
      <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">Tenant capability control</div>
      <h2 class="text-base font-extrabold text-slate-900 m-0">Module matrix · <span class="font-mono text-indigo-700" x-text="tenantId"><?= htmlspecialchars($tid, ENT_QUOTES, 'UTF-8') ?></span></h2>
      <p class="text-xs text-slate-500 m-0 mt-0.5">Enable or disable modules for this tenant. Disabled items hide from Company Admin and Visitor navigation.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" @click="enableAll()" class="h-9 px-3 rounded-lg text-xs font-bold border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">Enable all</button>
      <button type="button" @click="coreOnly()" class="h-9 px-3 rounded-lg text-xs font-bold border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">Core only</button>
      <button type="button" @click="disableTrials()" class="h-9 px-3 rounded-lg text-xs font-bold border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-900">Disable trial packs</button>
      <button type="button" @click="save()" :disabled="busy" class="h-9 px-4 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-50">
        <span x-show="!busy">Save matrix</span>
        <span x-show="busy" x-cloak>Saving…</span>
      </button>
    </div>
  </div>
  <div class="p-4" x-show="err" x-cloak>
    <p class="text-sm text-rose-700 bg-rose-50 border border-rose-100 rounded-lg px-3 py-2 m-0" x-text="err"></p>
  </div>
  <div class="p-4" x-show="msg" x-cloak>
    <p class="text-sm text-emerald-800 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2 m-0" x-text="msg"></p>
  </div>
  <div class="p-4 text-sm text-slate-500" x-show="loading">Loading catalogue…</div>
  <div class="divide-y divide-slate-100" x-show="!loading">
    <template x-for="grp in groups" :key="grp">
      <div class="p-4">
        <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-400 mb-3" x-text="grp"></h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
          <template x-for="m in modulesIn(grp)" :key="m.id">
            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 hover:border-indigo-200 hover:bg-indigo-50/40 cursor-pointer transition">
              <input type="checkbox" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                     :checked="!!state[m.id]"
                     @change="state[m.id] = $event.target.checked">
              <span class="min-w-0">
                <span class="block text-sm font-bold text-slate-800" x-text="m.label"></span>
                <span class="block text-[10px] font-mono text-slate-400" x-text="m.id"></span>
              </span>
            </label>
          </template>
        </div>
      </div>
    </template>
  </div>
</section>
<script>
function rcModuleMatrix() {
  return {
    loading: true,
    busy: false,
    err: '',
    msg: '',
    tenantId: <?= json_encode($tid, JSON_UNESCAPED_UNICODE) ?>,
    catalogue: {},
    state: {},
    groups: [],
    trialIds: ['cctv','leads','status','tracking','dispatch','ops','assets','expiry','audit','health','numero'],
    coreIds: ['team','bank','docs','events','locations','terms','access','company','designations','departments'],
    modulesIn(grp) {
      return Object.keys(this.catalogue).filter(id => (this.catalogue[id].group || 'Other') === grp)
        .map(id => ({ id, label: this.catalogue[id].label || id, group: this.catalogue[id].group }));
    },
    async load() {
      this.loading = true; this.err = ''; this.msg = '';
      try {
        const r = await fetch('api_modules.php', { credentials: 'same-origin' });
        const j = await r.json();
        if (!j.ok) {
          this.err = (j.error || 'Load failed') + (j.hint ? ' — ' + j.hint : '');
          this.loading = false;
          return;
        }
        this.catalogue = j.catalogue || {};
        this.tenantId = j.tenant_id || this.tenantId;
        const mods = (j.config && j.config.modules) ? j.config.modules : {};
        const st = {};
        Object.keys(this.catalogue).forEach(id => {
          st[id] = mods[id] ? !!mods[id].enabled : true;
        });
        this.state = st;
        const gs = [...new Set(Object.values(this.catalogue).map(m => m.group || 'Other'))];
        this.groups = gs;
      } catch (e) {
        this.err = String(e.message || e);
      }
      this.loading = false;
    },
    enableAll() {
      Object.keys(this.state).forEach(k => { this.state[k] = true; });
    },
    coreOnly() {
      Object.keys(this.state).forEach(k => {
        this.state[k] = this.coreIds.includes(k);
      });
    },
    disableTrials() {
      this.trialIds.forEach(k => { if (k in this.state) this.state[k] = false; });
    },
    async save() {
      this.busy = true; this.err = ''; this.msg = '';
      try {
        const modules = {};
        Object.keys(this.state).forEach(id => {
          modules[id] = { enabled: !!this.state[id], features: {} };
        });
        const r = await fetch('api_modules.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ modules })
        });
        const j = await r.json();
        if (!j.ok) {
          this.err = (j.error || 'Save failed') + (j.hint ? ' — ' + j.hint : '');
        } else {
          this.msg = 'Matrix saved for tenant ' + (j.tenant_id || this.tenantId) + '. Company Admin will only see enabled modules after refresh.';
        }
      } catch (e) {
        this.err = String(e.message || e);
      }
      this.busy = false;
    }
  };
}
</script>
