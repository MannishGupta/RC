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
<section class="mt-8 rc-mm rounded-2xl border shadow-sm overflow-hidden" id="rc-module-matrix"
         x-data="rcModuleMatrix()" x-init="load()">
  <div class="rc-mm__head px-5 py-4 border-b flex flex-wrap items-center justify-between gap-3">
    <div>
      <div class="rc-mm__kicker text-[10px] font-bold uppercase tracking-wider">Tenant capability control</div>
      <h2 class="rc-mm__title text-base font-extrabold m-0">Module matrix · <span class="font-mono" x-text="tenantId"><?= htmlspecialchars($tid, ENT_QUOTES, 'UTF-8') ?></span></h2>
      <p class="rc-mm__hint text-xs m-0 mt-0.5">Enable or disable modules for this tenant. Disabled items hide from Company Admin and Visitor navigation.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" @click="enableAll()" class="rc-mm__btn h-9 px-3 rounded-lg text-xs font-bold border">Enable all</button>
      <button type="button" @click="coreOnly()" class="rc-mm__btn h-9 px-3 rounded-lg text-xs font-bold border">Core only</button>
      <button type="button" @click="disableTrials()" class="rc-mm__btn rc-mm__btn--warn h-9 px-3 rounded-lg text-xs font-bold border">Disable trial packs</button>
      <button type="button" @click="save()" :disabled="busy" class="rc-mm__btn rc-mm__btn--pri h-9 px-4 rounded-lg text-xs font-bold disabled:opacity-50">
        <span x-show="!busy">Save matrix</span>
        <span x-show="busy" x-cloak>Saving…</span>
      </button>
    </div>
  </div>

  <!-- Advert toggle callout (also listed under Platform) -->
  <div class="rc-mm__ad px-5 py-3 border-b flex flex-wrap items-center gap-3" x-show="!loading && ('subscription_ad' in state)" x-cloak>
    <label class="rc-mm__row flex items-center gap-3 cursor-pointer flex-1 min-w-0">
      <input type="checkbox" class="rounded border-slate-300"
             :checked="!!state['subscription_ad']"
             @change="state['subscription_ad'] = $event.target.checked">
      <span class="min-w-0">
        <span class="rc-mm__label block text-sm font-bold">Subscription advert strip</span>
        <span class="rc-mm__sub block text-[11px]">Top banner with Arthsathi mark + “Pay subscription”. Uncheck and Save to hide it for this tenant.</span>
      </span>
    </label>
    <span class="rc-mm__badge text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-full border">Platform</span>
  </div>

  <div class="p-4" x-show="err" x-cloak>
    <p class="text-sm text-rose-700 bg-rose-50 border border-rose-100 rounded-lg px-3 py-2 m-0" x-text="err"></p>
  </div>
  <div class="p-4" x-show="msg" x-cloak>
    <p class="text-sm text-emerald-800 bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2 m-0" x-text="msg"></p>
  </div>
  <div class="p-4 rc-mm__hint text-sm" x-show="loading">Loading catalogue…</div>
  <div class="divide-y" x-show="!loading" style="border-color:var(--rc-border)">
    <template x-for="grp in groups" :key="grp">
      <div class="p-4">
        <h3 class="rc-mm__group text-[10px] font-black uppercase tracking-wider mb-3" x-text="grp"></h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
          <template x-for="m in modulesIn(grp)" :key="m.id">
            <label class="rc-mm__row flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition">
              <input type="checkbox" class="mt-1 rounded"
                     :checked="!!state[m.id]"
                     @change="state[m.id] = $event.target.checked">
              <span class="min-w-0">
                <span class="rc-mm__label block text-sm font-bold" x-text="m.label || m.id"></span>
                <span class="rc-mm__sub block text-[10px] font-mono" x-text="m.id"></span>
              </span>
            </label>
          </template>
        </div>
      </div>
    </template>
  </div>
</section>
<style>
/* Module matrix — token colours (readable light / dark / reserve) */
#rc-module-matrix.rc-mm {
  background: var(--rc-card) !important;
  border-color: var(--rc-border) !important;
  color: var(--rc-ink) !important;
}
#rc-module-matrix .rc-mm__head {
  background: var(--rc-paper) !important;
  border-color: var(--rc-border) !important;
}
#rc-module-matrix .rc-mm__kicker { color: var(--rc-accent) !important; }
#rc-module-matrix .rc-mm__title { color: var(--rc-ink) !important; }
#rc-module-matrix .rc-mm__hint,
#rc-module-matrix .rc-mm__sub,
#rc-module-matrix .rc-mm__group { color: var(--rc-ink-muted) !important; opacity: 1 !important; }
#rc-module-matrix .rc-mm__label { color: var(--rc-ink) !important; opacity: 1 !important; }
#rc-module-matrix .rc-mm__row {
  background: var(--rc-card) !important;
  border-color: var(--rc-border) !important;
  color: var(--rc-ink) !important;
}
#rc-module-matrix .rc-mm__row:hover {
  border-color: var(--rc-accent) !important;
  background: var(--rc-paper) !important;
}
#rc-module-matrix .rc-mm__btn {
  background: var(--rc-card) !important;
  color: var(--rc-ink) !important;
  border-color: var(--rc-border) !important;
}
#rc-module-matrix .rc-mm__btn--pri {
  background: var(--rc-accent) !important;
  color: #fff !important;
  border-color: var(--rc-accent) !important;
}
#rc-module-matrix .rc-mm__btn--warn {
  background: #fffbeb !important;
  color: #92400e !important;
  border-color: #fcd34d !important;
}
#rc-module-matrix .rc-mm__ad {
  background: var(--rc-paper) !important;
  border-color: var(--rc-border) !important;
}
#rc-module-matrix .rc-mm__badge {
  color: var(--rc-ink-muted) !important;
  border-color: var(--rc-border) !important;
  background: var(--rc-card) !important;
}
html[data-theme="light"] #rc-module-matrix .rc-mm__label,
html[data-theme="reserve"] #rc-module-matrix .rc-mm__label {
  color: #1c1917 !important;
}
html[data-theme="light"] #rc-module-matrix .rc-mm__sub,
html[data-theme="reserve"] #rc-module-matrix .rc-mm__sub,
html[data-theme="light"] #rc-module-matrix .rc-mm__group,
html[data-theme="reserve"] #rc-module-matrix .rc-mm__group {
  color: #57534e !important;
}
</style>
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
        // Ensure advert key exists even if catalogue was cached mid-deploy
        if (!('subscription_ad' in st)) st['subscription_ad'] = true;
        if (!this.catalogue['subscription_ad']) {
          this.catalogue['subscription_ad'] = {
            label: 'Subscription advert strip',
            group: 'Platform',
            features: {}
          };
        }
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
          this.msg = 'Matrix saved for tenant ' + (j.tenant_id || this.tenantId) + '. Refresh to apply nav and advert changes.';
        }
      } catch (e) {
        this.err = String(e.message || e);
      }
      this.busy = false;
    }
  };
}
</script>
