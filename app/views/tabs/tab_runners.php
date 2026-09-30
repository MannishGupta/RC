<?php
// Version: 1.0
// Live Location & Travel History — Office Runners admin sub-tab
// Drop into dashboard custom tabs as 'runners' / require this file.
if (!defined('BASE_PATH')) {
    exit;
}
$mapsKey = '';
if (class_exists('AppDB')) {
    $cfg = AppDB::read('company') ?: [];
    if (isset($cfg[0]) && is_array($cfg[0])) {
        $cfg = $cfg[0];
    }
    $mapsKey = (string)($cfg['google_maps_api_key'] ?? $cfg['maps_api_key'] ?? '');
}
if ($mapsKey === '' && defined('GOOGLE_MAPS_API_KEY')) {
    $mapsKey = (string)GOOGLE_MAPS_API_KEY;
}
$apiBase = '/api_runner_tracking.php';
if (!empty($GLOBALS['RUNNER_TRACKING_API'])) {
    $apiBase = (string)$GLOBALS['RUNNER_TRACKING_API'];
}
?>
<!-- Live Location & Travel History — Office Runners -->
<div
  x-data="runnerTrackingApp()"
  x-init="init()"
  class="space-y-4"
  id="runner-tracking-panel"
>
  <!-- Master settings bar -->
  <div class="rounded-xl border border-slate-700/80 bg-slate-900/90 px-4 py-3 flex flex-wrap items-center gap-3 shadow-lg">
    <div class="flex items-center gap-2 min-w-0">
      <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/15 text-blue-400">
        <i class="fa-solid fa-envelope text-sm"></i>
      </span>
      <div class="min-w-0">
        <div class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Master tracking email</div>
        <div class="text-sm font-semibold text-slate-100 truncate" x-text="masterEmail || '—'"></div>
      </div>
    </div>
    <div class="ml-auto flex flex-wrap items-center gap-2">
      <template x-if="!editingEmail">
        <button type="button" @click="editingEmail = true; emailDraft = masterEmail"
                class="h-8 px-3 rounded-lg text-xs font-bold bg-slate-800 border border-slate-600 text-slate-200 hover:border-blue-400 hover:text-white">
          <i class="fa-solid fa-pen mr-1"></i> Update
        </button>
      </template>
      <template x-if="editingEmail">
        <div class="flex flex-wrap items-center gap-2">
          <input type="email" x-model="emailDraft" placeholder="admin.logistics@company.com"
                 class="h-8 w-64 max-w-full rounded-lg bg-slate-950 border border-slate-600 px-3 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
          <button type="button" @click="saveMasterEmail()" :disabled="savingEmail"
                  class="h-8 px-3 rounded-lg text-xs font-bold bg-blue-600 text-white hover:bg-blue-500 disabled:opacity-60">
            Save
          </button>
          <button type="button" @click="editingEmail = false"
                  class="h-8 px-3 rounded-lg text-xs font-bold text-slate-400 hover:text-white">Cancel</button>
        </div>
      </template>
      <button type="button" @click="refreshAll()" class="h-8 w-8 rounded-lg border border-slate-600 text-slate-300 hover:text-white hover:border-blue-400" title="Refresh">
        <i class="fa-solid fa-arrows-rotate text-xs" :class="loading && 'fa-spin'"></i>
      </button>
    </div>
  </div>

  <!-- Status strip -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
    <div class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-3">
      <div class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Total runners</div>
      <div class="mt-1 text-2xl font-black text-slate-100" x-text="counts.total"></div>
    </div>
    <div class="rounded-xl border-2 border-solid border-sky-400 bg-slate-900 px-3 py-3" title="Moving = solid border">
      <div class="text-[10px] font-bold uppercase tracking-widest text-sky-300">● On duty / moving</div>
      <div class="mt-1 text-2xl font-black text-white" x-text="counts.moving"></div>
    </div>
    <div class="rounded-xl border-2 border-dashed border-amber-400 bg-slate-900 px-3 py-3" title="Idle = dashed border">
      <div class="text-[10px] font-bold uppercase tracking-widest text-amber-200">▲ Stationary / idle</div>
      <div class="mt-1 text-2xl font-black text-white" x-text="counts.idle"></div>
    </div>
    <div class="rounded-xl border-2 border-dotted border-slate-400 bg-slate-900/80 px-3 py-3" title="Offline = dotted border">
      <div class="text-[10px] font-bold uppercase tracking-widest text-slate-300">■ Offline</div>
      <div class="mt-1 text-2xl font-black text-white" x-text="counts.offline"></div>
    </div>
  </div>

  <!-- Map + side list -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
    <div class="lg:col-span-2 rounded-xl border border-slate-700 overflow-hidden bg-slate-900 shadow-lg relative">
      <div class="absolute top-3 right-3 z-10 flex gap-2">
        <button type="button" @click="fitAllRunners()"
                class="h-9 px-3 rounded-lg text-xs font-bold bg-slate-950/90 border border-slate-600 text-slate-100 backdrop-blur hover:border-blue-400">
          <i class="fa-solid fa-expand mr-1"></i> Fit all
        </button>
      </div>
      <div id="runner-map" class="w-full h-[380px] sm:h-[440px] bg-slate-950" role="img" aria-label="Live runner locations map"></div>
      <div class="rc-map-legend px-3 py-2 border-t border-slate-800 bg-slate-950" aria-hidden="false">
        <span>● Moving (solid)</span>
        <span>▲ Idle (dashed)</span>
        <span>■ Offline (dotted)</span>
      </div>
      <p class="px-3 py-2 text-[10px] text-slate-500 border-t border-slate-800" x-show="!mapsReady">
        Map loads when Google Maps API key is configured (company.google_maps_api_key or GOOGLE_MAPS_API_KEY).
      </p>
    </div>
    <div class="rounded-xl border border-slate-700 bg-slate-900 max-h-[440px] overflow-y-auto">
      <div class="sticky top-0 z-[1] px-3 py-2 border-b border-slate-800 bg-slate-900/95 text-[10px] font-bold uppercase tracking-widest text-slate-500">
        Live roster
      </div>
      <ul class="divide-y divide-slate-800">
        <template x-for="r in runners" :key="r.id">
          <li>
            <button type="button" @click="focusRunner(r)"
                    class="w-full text-left px-3 py-2.5 hover:bg-slate-800/80 flex items-start gap-3 transition">
              <span class="mt-0.5 h-9 w-9 rounded-full flex items-center justify-center text-[11px] font-black text-white shrink-0"
                    :class="{
                      'bg-sky-600 ring-2 ring-sky-200': r.state==='moving',
                      'bg-amber-600 ring-2 ring-dashed ring-amber-200': r.state==='idle',
                      'bg-slate-600 ring-2 ring-dotted ring-slate-400': r.state==='offline'
                    }"
                    x-text="r.initials"></span>
              <div class="min-w-0 flex-1">
                <div class="text-sm font-bold text-slate-100 truncate" x-text="r.name"></div>
                <div class="flex flex-wrap gap-1.5 mt-1">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="{
                          'bg-sky-900 text-sky-100 border border-sky-400': r.state==='moving',
                          'bg-amber-900 text-amber-100 border border-dashed border-amber-400': r.state==='idle',
                          'bg-slate-800 text-slate-100 border border-dotted border-slate-400': r.state==='offline'
                        }"
                        x-text="stateLabel(r.state)"></span>
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-slate-800 text-slate-300"
                        x-show="r.battery_percentage !== null"
                        x-text="(r.battery_percentage ?? '—') + '% 🔋'"></span>
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold bg-slate-800 text-slate-400"
                        x-show="r.speed_kmh !== null"
                        x-text="(r.speed_kmh ?? 0).toFixed(1) + ' km/h'"></span>
                </div>
                <div class="text-[10px] text-slate-500 mt-1" x-text="pingAgeLabel(r)"></div>
              </div>
            </button>
          </li>
        </template>
        <li x-show="runners.length === 0" class="px-3 py-8 text-center text-sm text-slate-500">
          No active Office Runners found.
        </li>
      </ul>
    </div>
  </div>

  <!-- Travel history & playback -->
  <div class="rounded-xl border border-slate-700 bg-slate-900 p-4 space-y-3 shadow-lg">
    <div class="flex flex-wrap items-end gap-3">
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-1">Runner</label>
        <select x-model.number="historyRunnerId" @change="loadHistory()"
                class="h-9 min-w-[12rem] rounded-lg bg-slate-950 border border-slate-600 px-3 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
          <option value="0">Select runner…</option>
          <template x-for="r in runners" :key="'h-'+r.id">
            <option :value="r.id" x-text="r.name"></option>
          </template>
        </select>
      </div>
      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-1">Date</label>
        <input type="date" x-model="historyDate" @change="loadHistory()"
               class="h-9 rounded-lg bg-slate-950 border border-slate-600 px-3 text-xs text-slate-100 focus:border-blue-500 focus:outline-none">
      </div>
      <button type="button" @click="loadHistory()" class="h-9 px-4 rounded-lg text-xs font-bold bg-slate-800 border border-slate-600 text-slate-200 hover:border-blue-400">
        Load path
      </button>
      <div class="ml-auto flex items-center gap-2 text-xs text-slate-400" x-show="historyPoints.length">
        <span x-text="historyPoints.length + ' points'"></span>
        <span>·</span>
        <span x-text="(historyStats.total_distance_km ?? 0).toFixed(2) + ' km'"></span>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-3" x-show="historyPoints.length">
      <button type="button" @click="togglePlayback()"
              class="h-9 w-9 rounded-lg bg-blue-600 text-white hover:bg-blue-500 flex items-center justify-center">
        <i class="fa-solid" :class="playing ? 'fa-pause' : 'fa-play'"></i>
      </button>
      <input type="range" min="0" :max="Math.max(0, historyPoints.length - 1)" step="1"
             x-model.number="playIndex" @input="onScrub()"
             class="flex-1 min-w-[12rem] accent-blue-500">
      <span class="text-[11px] font-mono text-slate-400 w-40 truncate"
            x-text="historyPoints[playIndex] ? historyPoints[playIndex].recorded_at : ''"></span>
    </div>
  </div>

  <!-- Audit table -->
  <div class="rounded-xl border border-slate-700 bg-slate-900 overflow-hidden shadow-lg">
    <div class="px-4 py-3 border-b border-slate-800 flex flex-wrap items-center gap-2">
      <div class="text-sm font-bold text-slate-100">Travel audit</div>
      <div class="ml-auto flex flex-wrap gap-2">
        <input type="date" x-model="auditFrom" class="h-8 rounded-lg bg-slate-950 border border-slate-600 px-2 text-xs text-slate-100">
        <input type="date" x-model="auditTo" class="h-8 rounded-lg bg-slate-950 border border-slate-600 px-2 text-xs text-slate-100">
        <button type="button" @click="loadAudit()" class="h-8 px-3 rounded-lg text-xs font-bold bg-slate-800 border border-slate-600 text-slate-200">Apply</button>
        <button type="button" @click="exportCsv()" class="h-8 px-3 rounded-lg text-xs font-bold bg-emerald-700 text-white hover:bg-emerald-600">
          <i class="fa-solid fa-file-csv mr-1"></i> Export CSV
        </button>
        <button type="button" @click="exportPrint()" class="h-8 px-3 rounded-lg text-xs font-bold bg-slate-100 text-slate-900 hover:bg-white">
          <i class="fa-solid fa-print mr-1"></i> Print audit
        </button>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="text-[10px] uppercase tracking-widest text-slate-500 border-b border-slate-800 bg-slate-950/50">
            <th class="px-3 py-2 font-bold">Date</th>
            <th class="px-3 py-2 font-bold">Runner</th>
            <th class="px-3 py-2 font-bold">Start</th>
            <th class="px-3 py-2 font-bold">End</th>
            <th class="px-3 py-2 font-bold">Distance</th>
            <th class="px-3 py-2 font-bold">Active</th>
            <th class="px-3 py-2 font-bold">Idle</th>
          </tr>
        </thead>
        <tbody>
          <template x-for="row in auditRows" :key="row.id">
            <tr class="border-b border-slate-800/80 hover:bg-slate-800/40">
              <td class="px-3 py-2 text-slate-200 font-semibold whitespace-nowrap" x-text="row.summary_date"></td>
              <td class="px-3 py-2 text-slate-300" x-text="row.runner_name"></td>
              <td class="px-3 py-2 text-slate-400 whitespace-nowrap" x-text="fmtTime(row.start_time)"></td>
              <td class="px-3 py-2 text-slate-400 whitespace-nowrap" x-text="fmtTime(row.end_time)"></td>
              <td class="px-3 py-2">
                <span class="inline-flex rounded-full bg-blue-500/15 text-blue-300 px-2 py-0.5 font-bold" x-text="Number(row.total_distance_km).toFixed(2) + ' km'"></span>
              </td>
              <td class="px-3 py-2 text-emerald-400 font-semibold" x-text="Number(row.active_hours).toFixed(2) + ' h'"></td>
              <td class="px-3 py-2 text-amber-400 font-semibold" x-text="Number(row.idle_hours).toFixed(2) + ' h'"></td>
            </tr>
          </template>
          <tr x-show="auditRows.length === 0">
            <td colspan="7" class="px-3 py-10 text-center text-slate-500">No audit rows for this range.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <p class="text-xs font-bold text-rose-800 bg-rose-50 border-2 border-dashed border-rose-600 rounded-lg px-3 py-2" role="alert" x-show="errorMsg" x-text="'⚠ ' + errorMsg"></p>
</div>

<script>
(function () {
  const API = <?= json_encode($apiBase, JSON_UNESCAPED_SLASHES) ?>;
  const MAPS_KEY = <?= json_encode($mapsKey, JSON_UNESCAPED_SLASHES) ?>;

  window.runnerTrackingApp = function () {
    return {
      loading: false,
      errorMsg: '',
      masterEmail: '',
      editingEmail: false,
      emailDraft: '',
      savingEmail: false,
      runners: [],
      counts: { total: 0, moving: 0, idle: 0, offline: 0 },
      map: null,
      mapsReady: false,
      markers: {},
      infoWindow: null,
      historyRunnerId: 0,
      historyDate: new Date().toISOString().slice(0, 10),
      historyPoints: [],
      historyStats: {},
      historyPath: null,
      playMarker: null,
      playIndex: 0,
      playing: false,
      playTimer: null,
      auditFrom: new Date(Date.now() - 14 * 864e5).toISOString().slice(0, 10),
      auditTo: new Date().toISOString().slice(0, 10),
      auditRows: [],
      pollTimer: null,

      async init() {
        await this.refreshAll();
        this.loadMaps();
        this.pollTimer = setInterval(() => this.refreshRunners(true), 30000);
      },

      destroy() {
        if (this.pollTimer) clearInterval(this.pollTimer);
        if (this.playTimer) clearInterval(this.playTimer);
      },

      async api(action, opts = {}) {
        const method = (opts.method || 'GET').toUpperCase();
        let url = API + (API.indexOf('?') >= 0 ? '&' : '?') + 'action=' + encodeURIComponent(action);
        if (method === 'GET' && opts.params) {
          Object.keys(opts.params).forEach((k) => {
            if (opts.params[k] === undefined || opts.params[k] === null || opts.params[k] === '') return;
            url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(opts.params[k]);
          });
        }
        const init = {
          method,
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' }
        };
        if (method === 'POST') {
          init.headers['Content-Type'] = 'application/json';
          init.body = JSON.stringify(Object.assign({ action }, opts.body || {}));
        }
        const res = await fetch(url, init);
        if (action === 'export_csv' || action === 'export_print') {
          return res;
        }
        const data = await res.json().catch(() => ({ ok: false, error: 'Invalid JSON' }));
        if (!res.ok || data.ok === false) {
          throw new Error(data.error || ('HTTP ' + res.status));
        }
        return data;
      },

      async refreshAll() {
        this.loading = true;
        this.errorMsg = '';
        try {
          await Promise.all([this.refreshRunners(false), this.loadMasterEmail(), this.loadAudit()]);
        } catch (e) {
          this.errorMsg = e.message || String(e);
        } finally {
          this.loading = false;
        }
      },

      async loadMasterEmail() {
        const d = await this.api('master_email');
        this.masterEmail = d.master_tracking_email || '';
      },

      async saveMasterEmail() {
        this.savingEmail = true;
        this.errorMsg = '';
        try {
          const d = await this.api('master_email', { method: 'POST', body: { email: this.emailDraft } });
          this.masterEmail = d.master_tracking_email;
          this.editingEmail = false;
        } catch (e) {
          this.errorMsg = e.message || String(e);
        } finally {
          this.savingEmail = false;
        }
      },

      async refreshRunners(silent) {
        if (!silent) this.loading = true;
        try {
          const d = await this.api('runners_list');
          this.runners = d.runners || [];
          const c = { total: this.runners.length, moving: 0, idle: 0, offline: 0 };
          this.runners.forEach((r) => {
            if (r.state === 'moving') c.moving++;
            else if (r.state === 'idle') c.idle++;
            else c.offline++;
          });
          this.counts = c;
          this.syncMarkers();
        } catch (e) {
          if (!silent) this.errorMsg = e.message || String(e);
        } finally {
          if (!silent) this.loading = false;
        }
      },

      pingAgeLabel(r) {
        if (r.last_ping_age_seconds == null) return 'No ping yet';
        const s = r.last_ping_age_seconds;
        if (s < 60) return s + 's ago';
        if (s < 3600) return Math.floor(s / 60) + 'm ago';
        return Math.floor(s / 3600) + 'h ago';
      },

      fmtTime(v) {
        if (!v) return '—';
        return String(v).replace('T', ' ').slice(0, 19);
      },

      loadMaps() {
        if (!MAPS_KEY) return;
        if (window.google && window.google.maps) {
          this.initMap();
          return;
        }
        const existing = document.querySelector('script[data-runner-maps]');
        if (existing) {
          existing.addEventListener('load', () => this.initMap());
          return;
        }
        const s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(MAPS_KEY) + '&v=weekly&libraries=marker';
        s.async = true;
        s.defer = true;
        s.dataset.runnerMaps = '1';
        s.onload = () => this.initMap();
        s.onerror = () => { this.errorMsg = 'Google Maps failed to load'; };
        document.head.appendChild(s);
      },

      initMap() {
        const el = document.getElementById('runner-map');
        if (!el || !window.google) return;
        this.map = new google.maps.Map(el, {
          center: { lat: 28.6139, lng: 77.2090 },
          zoom: 11,
          mapId: 'RUNNER_LIVE_MAP',
          gestureHandling: 'greedy',
          streetViewControl: false,
          fullscreenControl: true,
          mapTypeControl: false
        });
        this.infoWindow = new google.maps.InfoWindow();
        this.mapsReady = true;
        this.syncMarkers();
      },

      stateLabel(state) {
        if (state === 'moving') return '● Moving';
        if (state === 'idle') return '▲ Idle';
        if (state === 'offline') return '■ Offline';
        return String(state || '—');
      },
      stateColor(state) {
        // Wong-safe: sky / amber / gray (not pure green/red)
        if (state === 'moving') return '#0072B2';
        if (state === 'idle') return '#E69F00';
        return '#666666'; // offline gray
      },

      syncMarkers() {
        if (!this.map || !window.google) return;
        const seen = {};
        this.runners.forEach((r) => {
          if (r.lat == null || r.lng == null) return;
          seen[r.id] = true;
          const pos = { lat: Number(r.lat), lng: Number(r.lng) };
          const color = this.stateColor(r.state);
          const title = r.name + ' · ' + r.state;
          if (this.markers[r.id]) {
            if (this.markers[r.id].content) {
              this.markers[r.id].position = pos;
              const root = this.markers[r.id].content;
              root.style.background = color;
              root.title = title;
              root.textContent = r.initials;
            } else {
              this.markers[r.id].setPosition(pos);
            }
            return;
          }
          let marker;
          if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
            const pin = document.createElement('div');
            pin.className = 'runner-adv-marker';
            pin.textContent = r.initials;
            pin.title = title;
            pin.style.cssText = 'width:36px;height:36px;border-radius:999px;display:flex;align-items:center;justify-content:center;font:800 11px system-ui;color:#fff;border:2px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.35);background:' + color + ';cursor:pointer';
            marker = new google.maps.marker.AdvancedMarkerElement({
              map: this.map,
              position: pos,
              content: pin,
              title: title
            });
            pin.addEventListener('click', () => this.openInfo(r, marker));
          } else {
            marker = new google.maps.Marker({
              map: this.map,
              position: pos,
              title: title,
              label: { text: r.initials, color: '#fff', fontWeight: '700', fontSize: '11px' },
              icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 14,
                fillColor: color,
                fillOpacity: 1,
                strokeColor: '#fff',
                strokeWeight: 2
              }
            });
            marker.addListener('click', () => this.openInfo(r, marker));
          }
          this.markers[r.id] = marker;
        });
        Object.keys(this.markers).forEach((id) => {
          if (!seen[id]) {
            const m = this.markers[id];
            if (m.map !== undefined) m.map = null;
            else if (m.setMap) m.setMap(null);
            delete this.markers[id];
          }
        });
      },

      openInfo(r, marker) {
        if (!this.infoWindow) return;
        const bat = r.battery_percentage != null ? r.battery_percentage + '%' : '—';
        const spd = r.speed_kmh != null ? Number(r.speed_kmh).toFixed(1) + ' km/h' : '—';
        const html = '<div style="font:600 12px system-ui;color:#0f172a;min-width:180px">'
          + '<div style="font-weight:800;font-size:13px;margin-bottom:4px">' + this.esc(r.name) + '</div>'
          + '<div>State: <b>' + this.esc(r.state) + '</b></div>'
          + '<div>Speed: ' + this.esc(spd) + '</div>'
          + '<div>Battery: ' + this.esc(bat) + '</div>'
          + '<div style="margin-top:6px;color:#64748b;font-size:11px" id="rw-addr-' + r.id + '">Resolving address…</div>'
          + '</div>';
        this.infoWindow.setContent(html);
        if (marker.content) {
          this.infoWindow.open({ map: this.map, anchor: marker });
        } else {
          this.infoWindow.open(this.map, marker);
        }
        this.reverseGeocode(r.lat, r.lng, 'rw-addr-' + r.id);
      },

      reverseGeocode(lat, lng, elId) {
        if (!window.google || !google.maps.Geocoder) return;
        const geo = new google.maps.Geocoder();
        geo.geocode({ location: { lat: Number(lat), lng: Number(lng) } }, (results, status) => {
          const el = document.getElementById(elId);
          if (!el) return;
          if (status === 'OK' && results && results[0]) {
            el.textContent = results[0].formatted_address;
          } else {
            el.textContent = Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5);
          }
        });
      },

      esc(s) {
        return String(s == null ? '' : s)
          .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      },

      focusRunner(r) {
        if (!this.map || r.lat == null) return;
        this.map.panTo({ lat: Number(r.lat), lng: Number(r.lng) });
        this.map.setZoom(Math.max(this.map.getZoom(), 15));
        const m = this.markers[r.id];
        if (m) this.openInfo(r, m);
      },

      fitAllRunners() {
        if (!this.map || !window.google) return;
        const bounds = new google.maps.LatLngBounds();
        let n = 0;
        this.runners.forEach((r) => {
          if (r.lat == null) return;
          bounds.extend({ lat: Number(r.lat), lng: Number(r.lng) });
          n++;
        });
        if (n === 0) return;
        if (n === 1) {
          this.map.setCenter(bounds.getCenter());
          this.map.setZoom(14);
        } else {
          this.map.fitBounds(bounds, 64);
        }
      },

      async loadHistory() {
        if (!this.historyRunnerId) return;
        this.errorMsg = '';
        try {
          const d = await this.api('daily_logs', {
            params: { runner_id: this.historyRunnerId, date: this.historyDate }
          });
          this.historyPoints = d.points || [];
          this.historyStats = d.stats || {};
          this.playIndex = 0;
          this.stopPlayback();
          this.drawHistoryPath();
        } catch (e) {
          this.errorMsg = e.message || String(e);
        }
      },

      drawHistoryPath() {
        if (!this.map || !window.google) return;
        if (this.historyPath) {
          this.historyPath.setMap(null);
          this.historyPath = null;
        }
        if (this.playMarker) {
          if (this.playMarker.map !== undefined) this.playMarker.map = null;
          else if (this.playMarker.setMap) this.playMarker.setMap(null);
          this.playMarker = null;
        }
        if (!this.historyPoints.length) return;
        const path = this.historyPoints.map((p) => ({ lat: p.lat, lng: p.lng }));
        this.historyPath = new google.maps.Polyline({
          path,
          geodesic: true,
          strokeColor: '#60a5fa',
          strokeOpacity: 0.9,
          strokeWeight: 4,
          map: this.map
        });
        const bounds = new google.maps.LatLngBounds();
        path.forEach((p) => bounds.extend(p));
        this.map.fitBounds(bounds, 48);
        const first = path[0];
        if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
          const pin = document.createElement('div');
          pin.style.cssText = 'width:28px;height:28px;border-radius:999px;background:#2563eb;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.4)';
          this.playMarker = new google.maps.marker.AdvancedMarkerElement({
            map: this.map,
            position: first,
            content: pin
          });
        } else {
          this.playMarker = new google.maps.Marker({
            map: this.map,
            position: first,
            icon: {
              path: google.maps.SymbolPath.CIRCLE,
              scale: 8,
              fillColor: '#2563eb',
              fillOpacity: 1,
              strokeColor: '#fff',
              strokeWeight: 2
            }
          });
        }
      },

      onScrub() {
        this.movePlayhead(this.playIndex);
      },

      movePlayhead(idx) {
        if (!this.historyPoints.length || !this.playMarker) return;
        const i = Math.max(0, Math.min(this.historyPoints.length - 1, idx));
        this.playIndex = i;
        const p = this.historyPoints[i];
        const pos = { lat: p.lat, lng: p.lng };
        if (this.playMarker.position !== undefined && this.playMarker.content) {
          this.playMarker.position = pos;
        } else if (this.playMarker.setPosition) {
          this.playMarker.setPosition(pos);
        }
      },

      togglePlayback() {
        if (this.playing) {
          this.stopPlayback();
          return;
        }
        if (!this.historyPoints.length) return;
        this.playing = true;
        this.playTimer = setInterval(() => {
          if (this.playIndex >= this.historyPoints.length - 1) {
            this.stopPlayback();
            return;
          }
          this.playIndex += 1;
          this.movePlayhead(this.playIndex);
        }, 400);
      },

      stopPlayback() {
        this.playing = false;
        if (this.playTimer) {
          clearInterval(this.playTimer);
          this.playTimer = null;
        }
      },

      async loadAudit() {
        try {
          const d = await this.api('daily_summaries', {
            params: {
              from: this.auditFrom,
              to: this.auditTo,
              runner_id: this.historyRunnerId || ''
            }
          });
          this.auditRows = d.rows || [];
        } catch (e) {
          this.errorMsg = e.message || String(e);
        }
      },

      exportCsv() {
        let url = API + (API.indexOf('?') >= 0 ? '&' : '?') + 'action=export_csv'
          + '&from=' + encodeURIComponent(this.auditFrom)
          + '&to=' + encodeURIComponent(this.auditTo);
        if (this.historyRunnerId) url += '&runner_id=' + encodeURIComponent(this.historyRunnerId);
        window.open(url, '_blank');
      },

      exportPrint() {
        let url = API + (API.indexOf('?') >= 0 ? '&' : '?') + 'action=export_print'
          + '&from=' + encodeURIComponent(this.auditFrom)
          + '&to=' + encodeURIComponent(this.auditTo);
        if (this.historyRunnerId) url += '&runner_id=' + encodeURIComponent(this.historyRunnerId);
        window.open(url, '_blank');
      }
    };
  };
})();
</script>
<style>
  #runner-tracking-panel .accent-blue-500 { accent-color: #3b82f6; }
</style>
