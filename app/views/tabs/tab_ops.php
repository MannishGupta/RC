<?php
/** Field Operations — attendance, visits, geofences, WhatsApp templates. Version: 260921.40 */
if (!defined('BASE_PATH')) exit;
?>
<div class="space-y-4" x-data="valueOps()" x-init="init()">
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h2 class="text-base font-bold text-slate-800">Field Operations Hub</h2>
      <p class="text-xs text-slate-500 mt-0.5">Duty check-in, visit proof, geo-zones, and Roman-Hindi dispatch phrases.</p>
    </div>
    <a href="?tab=tracking" class="text-xs font-bold text-blue-600 hover:underline">Open live tracking →</a>
  </div>

  <div class="grid md:grid-cols-2 gap-4">
    <!-- Attendance -->
    <section class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-800 mb-3"><i class="fa-solid fa-user-clock text-blue-500 mr-1"></i> Duty check-in</h3>
      <div class="grid grid-cols-2 gap-2 mb-2">
        <div>
          <label class="text-[10px] font-bold uppercase text-slate-400" for="att-name">Name</label>
          <input id="att-name" x-model="att.name" class="w-full h-9 px-2 rounded-lg border border-slate-200 text-sm" placeholder="e.g. Rajesh">
        </div>
        <div>
          <label class="text-[10px] font-bold uppercase text-slate-400" for="att-id">Employee ID</label>
          <input id="att-id" x-model="att.employee_id" class="w-full h-9 px-2 rounded-lg border border-slate-200 text-sm" placeholder="EMP-101">
        </div>
      </div>
      <div class="flex gap-2 mb-3">
        <button type="button" @click="checkIn('in')" class="flex-1 h-9 rounded-lg bg-sky-600 text-white text-xs font-bold">Check In</button>
        <button type="button" @click="checkIn('out')" class="flex-1 h-9 rounded-lg bg-slate-700 text-white text-xs font-bold">Check Out</button>
      </div>
      <p class="text-[11px] text-slate-400 mb-2" x-text="msg"></p>
      <ul class="max-h-40 overflow-auto text-xs space-y-1">
        <template x-for="r in attendance.slice().reverse().slice(0,12)" :key="r.id">
          <li class="flex justify-between gap-2 border-b border-slate-50 py-1">
            <span><span class="font-bold" x-text="r.kind"></span> · <span x-text="r.name || r.employee_id"></span></span>
            <span class="text-slate-400 tabular-nums" x-text="(r.at||'').replace('T',' ').slice(0,16)"></span>
          </li>
        </template>
      </ul>
    </section>

    <!-- Visits -->
    <section class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-800 mb-3"><i class="fa-solid fa-clipboard-check text-violet-500 mr-1"></i> Visit / delivery log</h3>
      <div class="grid grid-cols-2 gap-2 mb-2">
        <input x-model="vis.name" class="h-9 px-2 rounded-lg border border-slate-200 text-sm" placeholder="Field staff name">
        <select x-model="vis.status" class="h-9 px-2 rounded-lg border border-slate-200 text-sm">
          <option value="reached">Reached</option>
          <option value="delivered">Delivered</option>
          <option value="partial">Partial</option>
          <option value="failed">Failed</option>
        </select>
      </div>
      <label class="text-[10px] font-bold uppercase text-slate-400" for="vis-dispatch">Link dispatch (optional)</label>
      <select id="vis-dispatch" x-model="vis.dispatch_id" class="w-full h-9 px-2 rounded-lg border border-slate-200 text-sm mb-2">
        <option value="">— No dispatch link —</option>
        <template x-for="d in dispatches" :key="d.id">
          <option :value="d.id" x-text="(d.id || '') + ' · ' + (d.title || '') + ' (' + (d.status||'') + ')'"></option>
        </template>
      </select>
      <input x-model="vis.note" class="w-full h-9 px-2 rounded-lg border border-slate-200 text-sm mb-2" placeholder="Note / POD reference">
      <button type="button" @click="logVisit()" class="w-full h-9 rounded-lg bg-violet-600 text-white text-xs font-bold mb-3">Log visit</button>
      <ul class="max-h-40 overflow-auto text-xs space-y-1">
        <template x-for="r in visits.slice().reverse().slice(0,12)" :key="r.id">
          <li class="flex justify-between gap-2 border-b border-slate-50 py-1">
            <span><span class="font-bold uppercase text-[10px]" x-text="r.status"></span> · <span x-text="r.name"></span></span>
            <span class="text-slate-400" x-text="(r.at||'').slice(0,16)"></span>
          </li>
        </template>
      </ul>
    </section>
  </div>

  <div class="grid md:grid-cols-2 gap-4">
    <section class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-800 mb-3"><i class="fa-solid fa-draw-polygon text-amber-500 mr-1"></i> Geo-fences</h3>
      <p class="text-[11px] text-slate-500 mb-2">Zones for future enter/leave alerts (stored for map modules).</p>
      <template x-for="g in geofences" :key="g.id">
        <div class="flex items-center justify-between text-xs py-1.5 border-b border-slate-50">
          <span class="font-semibold text-slate-700" x-text="g.name"></span>
          <span class="text-slate-400 tabular-nums" x-text="(g.radius_m||0)+' m'"></span>
        </div>
      </template>
      <div class="grid grid-cols-2 gap-2 mt-3" x-show="isAdmin">
        <input x-model="gf.name" class="h-8 px-2 rounded border text-xs" placeholder="Zone name">
        <input x-model="gf.radius_m" type="number" class="h-8 px-2 rounded border text-xs" placeholder="Radius m">
        <input x-model="gf.lat" class="h-8 px-2 rounded border text-xs" placeholder="Lat">
        <input x-model="gf.lng" class="h-8 px-2 rounded border text-xs" placeholder="Lng">
        <button type="button" @click="saveFence()" class="col-span-2 h-8 rounded-lg bg-amber-600 text-white text-xs font-bold">Save zone</button>
      </div>
    </section>

    <section class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
      <h3 class="text-sm font-bold text-slate-800 mb-3"><i class="fa-brands fa-whatsapp text-sky-300 mr-1"></i> Roman-Hindi templates</h3>
      <template x-for="t in templates" :key="t.id">
        <button type="button" class="w-full text-left mb-2 p-2 rounded-lg border border-slate-100 hover:border-emerald-200 hover:bg-sky-50 text-xs"
                @click="copyText(t.hi)">
          <div class="font-bold text-slate-600 uppercase text-[10px]" x-text="t.id"></div>
          <div class="text-slate-800" x-text="t.hi"></div>
        </button>
      </template>
    </section>
  </div>

  <section class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
      <h3 class="text-sm font-bold text-slate-800"><i class="fa-solid fa-bell text-rose-500 mr-1"></i> Expiry &amp; SLA reminders</h3>
      <button type="button" @click="scanReminders()" class="h-8 px-3 rounded-lg bg-rose-600 text-white text-xs font-bold">Scan now</button>
    </div>
    <p class="text-[11px] text-slate-500 mb-2">Documents nearing expiry and dispatches past ETA. Copy Roman-Hindi text for WhatsApp.</p>
    <template x-if="reminders.length===0"><p class="text-xs text-slate-400">No open reminders. Run scan after adding expiry dates on documents.</p></template>
    <ul class="space-y-2 max-h-48 overflow-auto">
      <template x-for="r in reminders" :key="r.type + r.id + r.when">
        <li class="flex flex-wrap items-start justify-between gap-2 text-xs border border-slate-100 rounded-lg p-2">
          <div class="min-w-0">
            <span class="font-bold uppercase text-[10px] text-slate-500" x-text="r.type"></span>
            <div class="font-semibold text-slate-800" x-text="r.title"></div>
            <div class="text-slate-500" x-text="r.wa_hi"></div>
          </div>
          <button type="button" class="shrink-0 text-emerald-700 font-bold" @click="copyText(r.wa_hi)">Copy WA</button>
        </li>
      </template>
    </ul>
  </section>
</div>
<script>
function valueOps() {
  return {
    isAdmin: window.__RC_IS_ADMIN__ === true || (window.Alpine && Alpine.store('appState')?.isAdmin),
    att: { name: '', employee_id: '' },
    vis: { name: '', status: 'reached', note: '', dispatch_id: '' },
    dispatches: [], reminders: [],
    gf: { name: '', lat: '', lng: '', radius_m: 250 },
    attendance: [], visits: [], geofences: [], templates: [],
    msg: '',
    async init() {
      await this.reload();
      const tr = await this.api({ action: 'dispatch_templates' });
      if (tr.status === 'ok') this.templates = tr.templates || [];
      const dl = await this.api({ action: 'dispatch_list' });
      if (dl.status === 'ok') this.dispatches = dl.dispatches || [];
      await this.scanReminders();
    },
    async scanReminders() {
      const r = await this.api({ action: 'reminders_scan' });
      if (r.status === 'ok') this.reminders = r.items || [];
    },
    async api(body) {
      const fd = new FormData();
      Object.entries(body).forEach(([k,v]) => fd.append(k, v == null ? '' : v));
      const r = await fetch('api_value.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      return r.json();
    },
    async reload() {
      const a = await this.api({ action: 'vp_list', type: 'attendance' });
      const v = await this.api({ action: 'vp_list', type: 'visits' });
      const g = await this.api({ action: 'vp_list', type: 'geofences' });
      if (a.status === 'ok') this.attendance = a.data || [];
      if (v.status === 'ok') this.visits = v.data || [];
      if (g.status === 'ok') this.geofences = g.data || [];
    },
    geo() {
      return new Promise(resolve => {
        if (!navigator.geolocation) return resolve({});
        navigator.geolocation.getCurrentPosition(
          p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
          () => resolve({}),
          { enableHighAccuracy: true, timeout: 8000 }
        );
      });
    },
    async checkIn(kind) {
      const pos = await this.geo();
      const res = await this.api({ action: 'attendance_check', kind, name: this.att.name, employee_id: this.att.employee_id, ...pos });
      this.msg = res.status === 'ok' ? ('Recorded ' + kind) : (res.message || 'Failed');
      await this.reload();
    },
    async logVisit() {
      const pos = await this.geo();
      await this.api({ action: 'visit_log', name: this.vis.name, status: this.vis.status, note: this.vis.note, dispatch_id: this.vis.dispatch_id, ...pos });
      this.vis.note = '';
      await this.reload();
    },
    async saveFence() {
      await this.api({ action: 'geofence_save', ...this.gf, active: 1 });
      await this.reload();
    },
    copyText(t) {
      navigator.clipboard?.writeText(t);
      this.msg = 'Template copied';
    }
  };
}
</script>
