<?php
if (!defined('BASE_PATH')) { define('BASE_PATH', __DIR__); }
require_once BASE_PATH . '/app/tenant_bootstrap.php';

// Version: 1.6 — Supervisor cockpit UI (required by runners.php)
if (!defined('RT_ROOT')) {
    exit;
}
/** @var string $self */
/** @var string $mapsKey */

// Team Directory contacts for Logistics Admin picker
$teamContacts = [];
$teamFile = RT_ROOT . '/data/team.json';
if (is_file($teamFile)) {
    $traw = json_decode((string) @file_get_contents($teamFile), true);
    if (isset($traw['items']) && is_array($traw['items'])) {
        $traw = $traw['items'];
    }
    if (is_array($traw)) {
        foreach ($traw as $m) {
            if (!is_array($m)) {
                continue;
            }
            $email = trim((string) ($m['email'] ?? ''));
            $id = (string) ($m['id'] ?? $m['slug'] ?? '');
            $name = trim((string) ($m['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }
            $teamContacts[] = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'designation' => (string) ($m['designation_name'] ?? $m['designation'] ?? ''),
                'phone' => (string) ($m['phone'] ?? $m['mobile'] ?? ''),
            ];
        }
    }
}
usort($teamContacts, static function ($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});
?>
<!DOCTYPE html>
<html lang="en-IN" data-theme="reserve">
<head>
<?php if (defined('BASE_PATH') && is_file(BASE_PATH . '/app/views/partials/rc_theme_head.php')) { require BASE_PATH . '/app/views/partials/rc_theme_head.php'; } ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b1220">
<title>Workforce Geolocation · Compliance Command Centre</title>
<style>
:root{--bg:#0b1220;--s:#131c2e;--s2:#1a2438;--bd:rgba(148,163,184,.14);--t:#f1f5f9;--m:#94a3b8;--em:#34d399;--am:#fbbf24;--rs:#f87171;--r:14px}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,Segoe UI,sans-serif;background:var(--bg);color:var(--t);min-height:100vh}
.wrap{max-width:1180px;margin:0 auto;padding:14px}
.hdr{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:12px 14px;background:var(--s);border:1px solid var(--bd);border-radius:var(--r);margin-bottom:12px}
.hdr h1{font-size:15px;font-weight:800;margin:0}.muted{color:var(--m);font-size:12px}
.btn{height:32px;padding:0 12px;border-radius:10px;border:1px solid var(--bd);background:var(--s2);color:var(--t);font-size:12px;font-weight:700;cursor:pointer}
.btn:hover{border-color:#64748b}.btn-p{background:#2563eb;border-color:#2563eb;color:#fff}.btn-d{background:rgba(248,113,113,.12);border-color:rgba(248,113,113,.35);color:#fecaca}
.input{height:32px;padding:0 10px;border-radius:10px;border:1px solid var(--bd);background:#0a0f1a;color:var(--t);font-size:12px}
.pill-row{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:12px}
@media(min-width:720px){.pill-row{grid-template-columns:repeat(5,1fr)}}
.pill{background:var(--s);border:1px solid var(--bd);border-radius:var(--r);padding:12px}.pill .l{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--m)}.pill .n{font-size:22px;font-weight:900;margin-top:4px}
.n-em{color:var(--em)}.n-am{color:var(--am)}.n-rs{color:var(--rs)}
.viol-badge{display:inline-flex;align-items:center;gap:6px;height:32px;padding:0 12px;border-radius:999px;font-size:12px;font-weight:800;background:rgba(239,68,68,.15);color:#fecaca;border:1px solid rgba(239,68,68,.4);cursor:pointer;animation:pulse 1.6s ease-in-out infinite}
.viol-badge[data-count="0"]{animation:none;background:rgba(51,65,85,.5);color:var(--m);border-color:var(--bd)}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.45)}50%{box-shadow:0 0 0 8px rgba(239,68,68,0)}}
.grid{display:grid;grid-template-columns:1fr;gap:12px}@media(min-width:900px){.grid{grid-template-columns:2fr 1fr}}
.card{background:var(--s);border:1px solid var(--bd);border-radius:var(--r);overflow:hidden}
#map{width:100%;height:400px;background:#0a0f1a}
.roster{max-height:400px;overflow:auto}
.roster button{display:flex;gap:10px;width:100%;padding:10px 12px;border:0;border-bottom:1px solid var(--bd);background:transparent;color:inherit;text-align:left;cursor:pointer}
.roster button:hover{background:rgba(255,255,255,.03)}
.av{width:36px;height:36px;border-radius:999px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;color:#fff;flex-shrink:0}
.av-m{background:#059669}.av-i{background:#d97706;color:#0f172a}.av-o{background:#475569}.av-v{background:#dc2626;animation:pulse 1.4s ease-in-out infinite}
.badge{display:inline-flex;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:800}
.b-m{background:rgba(16,185,129,.15);color:var(--em)}.b-i{background:rgba(245,158,11,.15);color:var(--am)}.b-o{background:rgba(100,116,139,.25);color:var(--m)}.b-v{background:rgba(239,68,68,.2);color:#fecaca}
.toolbar{padding:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:end}
table{width:100%;border-collapse:collapse;font-size:12px}th{text-align:left;padding:8px 10px;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--m);border-bottom:1px solid var(--bd)}td{padding:9px 10px;border-bottom:1px solid var(--bd);color:#cbd5e1}
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.65);display:none;align-items:center;justify-content:center;z-index:100;padding:16px}
.modal-bg.open{display:flex}
.modal{background:var(--s);border:1px solid var(--bd);border-radius:16px;max-width:560px;width:100%;max-height:80vh;overflow:auto;padding:16px;box-shadow:0 24px 60px rgba(0,0,0,.5)}
.modal h2{margin:0 0 12px;font-size:16px}
.vrow{padding:10px 0;border-bottom:1px solid var(--bd)}.vrow:last-child{border-bottom:0}
.err{color:var(--rs);font-size:12px;margin-top:8px}
.chk{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--m);cursor:pointer}
.range{flex:1;min-width:140px;accent-color:#3b82f6}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <div>
      <h1>Workforce Geolocation · Compliance Command Centre</h1>
      <div class="muted">Policy master · <span id="masterEmail">—</span> · Hours <span id="hoursLbl">—</span></div>
    </div>
    <button type="button" class="viol-badge" id="violBadge" data-count="0">⚠️ <span id="violCount">0</span> Active Violations</button>
    <div style="margin-left:auto;display:flex;flex-wrap:wrap;gap:6px">
      <button class="btn" type="button" id="btnPolicy">Compliance Policy</button>
      <button class="btn" type="button" id="btnViolLog">Violations Log</button>
      <button class="btn" type="button" id="btnRefresh">Refresh</button>
      <button class="btn" type="button" id="btnFit">Fit all</button>
    </div>
  </div>
  <div class="pill-row">
    <div class="pill"><div class="l">Total</div><div class="n" id="cTotal">0</div></div>
    <div class="pill"><div class="l">Moving</div><div class="n n-em" id="cMoving">0</div></div>
    <div class="pill"><div class="l">Idle</div><div class="n n-am" id="cIdle">0</div></div>
    <div class="pill"><div class="l">Offline</div><div class="n" id="cOffline">0</div></div>
    <div class="pill"><div class="l">In violation</div><div class="n n-rs" id="cViol">0</div></div>
  </div>
  <div class="toolbar card" style="margin-bottom:12px;border-radius:var(--r)">
    <label class="chk"><input type="checkbox" id="onlyViol"> Show only violations / disconnected</label>
  </div>
  <div class="grid">
    <div class="card"><div id="map"></div></div>
    <div class="card roster" id="roster"></div>
  </div>
  <div class="card" style="margin-top:12px">
    <div class="toolbar">
      <div><div class="muted" style="font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px">Runner</div><select class="input" id="histRunner" style="min-width:150px"></select></div>
      <div><div class="muted" style="font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px">Date</div><input class="input" type="date" id="histDate"></div>
      <button class="btn" type="button" id="btnLoadPath">Load path</button>
      <button class="btn btn-p" type="button" id="btnPlay">Play</button>
      <input class="range" type="range" id="scrub" min="0" max="0" value="0">
      <span class="muted" id="scrubLabel" style="font-family:ui-monospace,monospace;font-size:11px"></span>
    </div>
  </div>
  <div class="card" style="margin-top:12px">
    <div class="toolbar">
      <strong style="font-size:13px">Travel audit</strong>
      <input class="input" type="date" id="auditFrom">
      <input class="input" type="date" id="auditTo">
      <button class="btn" type="button" id="btnAudit">Apply</button>
      <button class="btn btn-p" type="button" id="btnCsv">CSV travel</button>
      <button class="btn btn-d" type="button" id="btnViolCsv">Violation CSV</button>
      <button class="btn" type="button" id="btnViolPrint">Print violations</button>
    </div>
    <div style="overflow:auto">
      <table><thead><tr><th>Date</th><th>Name</th><th>Start</th><th>End</th><th>Distance</th><th>Active</th><th>Idle</th></tr></thead><tbody id="auditBody"></tbody></table>
    </div>
  </div>
  <p class="err" id="err"></p>
</div>
<div class="modal-bg" id="modalViol" role="dialog" aria-modal="true"><div class="modal"><div style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><h2 style="flex:1">Active violations</h2><button class="btn" type="button" id="closeViol">Close</button></div><div id="violList"></div></div></div>
<div class="modal-bg" id="modalPolicy" role="dialog" aria-modal="true"><div class="modal" style="max-width:440px"><div style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><h2 style="flex:1">Compliance Policy settings</h2><button class="btn" type="button" id="closePolicy">Close</button></div><div style="display:grid;gap:10px">
<label class="muted">Logistics Administrator (Human Capital Index)<br>
<select class="input" id="polAdmin" style="width:100%;margin-top:4px">
<option value="">— Select a team contact —</option>
</select>
<span class="muted" style="display:block;margin-top:4px;font-size:11px;line-height:1.35">Ops contact who receives location shares and is shown as Policy master. Email is filled from their Directory profile.</span>
</label>
<label class="muted">Logistics Control Email<br><input class="input" id="polEmail" style="width:100%;margin-top:4px" placeholder="name@company.com" autocomplete="email">
<span class="muted" style="display:block;margin-top:4px;font-size:11px;line-height:1.35">Inbox / Google account that receives live location shares from runners. Must be a valid email.</span>
</label>
<div style="display:flex;gap:8px;flex-wrap:wrap"><label class="muted">Start<br><input class="input" id="polStart" type="time" style="margin-top:4px"></label><label class="muted">End<br><input class="input" id="polEnd" type="time" style="margin-top:4px"></label><label class="muted">Timeout (min)<br><input class="input" id="polTimeout" type="number" min="1" style="width:90px;margin-top:4px"></label></div>
<label class="muted">Legacy name list — optional fallback (prefer Designation → Mandatory live tracking tick)<br><input class="input" id="polDesig" style="width:100%;margin-top:4px"></label>
<label class="muted">Mandatory departments (comma-separated)<br><input class="input" id="polDept" style="width:100%;margin-top:4px"></label>
<button class="btn btn-p" type="button" id="btnSavePolicy">Commit Policy</button>
</div></div></div>
<div class="modal-bg" id="modalLog" role="dialog" aria-modal="true"><div class="modal" style="max-width:720px"><div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap"><h2 style="flex:1">Compliance Exception Ledger</h2><select class="input" id="logFilter"><option value="ALL">All</option><option value="OPEN">Open</option><option value="RESOLVED">Resolved</option></select><button class="btn" type="button" id="closeLog">Close</button></div><div id="logList" style="font-size:12px"></div></div></div>
<script>
(function () {
  const API = <?= json_encode($self, JSON_UNESCAPED_SLASHES) ?>;
  const MAPS_KEY = <?= json_encode($mapsKey, JSON_UNESCAPED_SLASHES) ?>;
  const TEAM_CONTACTS = <?= json_encode($teamContacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const S = { runners: [], openViolations: [], map: null, markers: {}, info: null, path: null, playMarker: null, points: [], playIdx: 0, playing: false, timer: null, onlyViol: false, policy: null };
  function $(id) { return document.getElementById(id); }
  function err(m) { $('err').textContent = m || ''; }
  function esc(s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  async function api(action, opts) {
    opts = opts || {};
    const method = (opts.method || 'GET').toUpperCase();
    let url = API + '?action=' + encodeURIComponent(action);
    if (method === 'GET' && opts.params) {
      Object.keys(opts.params).forEach(function (k) {
        if (opts.params[k] == null || opts.params[k] === '') return;
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(opts.params[k]);
      });
    }
    const init = { method: method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (method === 'POST') {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(Object.assign({ action: action }, opts.body || {}));
    }
    const res = await fetch(url, init);
    const data = await res.json().catch(function () { return { ok: false, error: 'Bad JSON' }; });
    if (!res.ok || data.ok === false) throw new Error(data.error || ('HTTP ' + res.status));
    return data;
  }
  function sc(r) {
    if (r.compliance === 'IN_VIOLATION') return 'v';
    if (r.status === 'MOVING') return 'm';
    if (r.status === 'IDLE') return 'i';
    return 'o';
  }
  function color(r) {
    if (r.compliance === 'IN_VIOLATION') return '#dc2626';
    if (r.status === 'MOVING') return '#10b981';
    if (r.status === 'IDLE') return '#f59e0b';
    return '#64748b';
  }
  function renderRoster() {
    const box = $('roster');
    box.innerHTML = '';
    let list = S.runners.slice();
    if (S.onlyViol) list = list.filter(function (r) { return r.compliance === 'IN_VIOLATION' || r.status === 'OFFLINE'; });
    if (!list.length) { box.innerHTML = '<div class="muted" style="padding:24px;text-align:center">No runners match this filter.</div>'; return; }
    list.forEach(function (r) {
      const c = sc(r);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.innerHTML = '<span class="av av-' + c + '">' + esc(r.initials || '?') + '</span><div style="min-width:0;flex:1"><div style="font-weight:700;font-size:13px">' + esc(r.name) + '</div><div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:5px"><span class="badge b-' + c + '">' + esc(r.compliance) + '</span><span class="badge b-o">' + esc(r.status) + '</span>' + (r.battery_percentage != null ? '<span class="badge b-o">' + r.battery_percentage + '%</span>' : '') + '</div><div class="muted" style="margin-top:4px;font-size:10px">' + esc(r.last_ping || 'No ping') + '</div></div>';
      btn.addEventListener('click', function () { focusRunner(r); });
      box.appendChild(btn);
    });
    const sel = $('histRunner');
    const cur = sel.value;
    sel.innerHTML = '<option value="">Select…</option>';
    S.runners.forEach(function (r) { const o = document.createElement('option'); o.value = r.id; o.textContent = r.name; sel.appendChild(o); });
    if (cur) sel.value = cur;
  }
  function syncMarkers() {
    if (!S.map || !window.google) return;
    const seen = {};
    S.runners.forEach(function (r) {
      if (S.onlyViol && r.compliance !== 'IN_VIOLATION' && r.status !== 'OFFLINE') return;
      if (r.last_lat == null || r.last_lng == null) return;
      seen[r.id] = true;
      const pos = { lat: Number(r.last_lat), lng: Number(r.last_lng) };
      const c = color(r);
      if (S.markers[r.id]) {
        const m = S.markers[r.id];
        if (m.content) { m.position = pos; m.content.style.background = c; m.content.textContent = r.initials; }
        else if (m.setPosition) m.setPosition(pos);
        return;
      }
      let marker;
      if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
        const pin = document.createElement('div');
        pin.textContent = r.initials || '?';
        pin.style.cssText = 'width:36px;height:36px;border-radius:999px;display:flex;align-items:center;justify-content:center;font:800 11px system-ui;color:#fff;border:2px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,.4);cursor:pointer;background:' + c;
        if (r.compliance === 'IN_VIOLATION') pin.style.animation = 'pulse 1.4s ease-in-out infinite';
        marker = new google.maps.marker.AdvancedMarkerElement({ map: S.map, position: pos, content: pin, title: r.name });
        pin.addEventListener('click', function () { openInfo(r, marker); });
      } else {
        marker = new google.maps.Marker({ map: S.map, position: pos, title: r.name, label: { text: r.initials || '?', color: '#fff', fontWeight: '700', fontSize: '11px' }, icon: { path: google.maps.SymbolPath.CIRCLE, scale: 14, fillColor: c, fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 } });
        marker.addListener('click', function () { openInfo(r, marker); });
      }
      S.markers[r.id] = marker;
    });
    Object.keys(S.markers).forEach(function (id) {
      if (!seen[id]) {
        const m = S.markers[id];
        if (m.map !== undefined) m.map = null; else if (m.setMap) m.setMap(null);
        delete S.markers[id];
      }
    });
  }
  function openInfo(r, marker) {
    if (!S.info) return;
    const html = '<div style="font:600 12px system-ui;color:#0f172a;min-width:190px"><div style="font-weight:800;margin-bottom:4px">' + esc(r.name) + '</div><div>Compliance: <b>' + esc(r.compliance) + '</b></div><div>Status: ' + esc(r.status) + '</div><div>Speed: ' + (r.speed_kmh != null ? Number(r.speed_kmh).toFixed(1) + ' km/h' : '—') + '</div><div>Battery: ' + (r.battery_percentage != null ? r.battery_percentage + '%' : '—') + '</div><div style="margin-top:6px;color:#64748b;font-size:11px" id="addr-' + esc(r.id) + '">' + esc(r.last_known_address || 'Resolving…') + '</div></div>';
    S.info.setContent(html);
    if (marker.content) S.info.open({ map: S.map, anchor: marker }); else S.info.open(S.map, marker);
    if (!r.last_known_address && window.google && google.maps.Geocoder) {
      new google.maps.Geocoder().geocode({ location: { lat: Number(r.last_lat), lng: Number(r.last_lng) } }, function (results, status) {
        const el = document.getElementById('addr-' + r.id);
        if (!el) return;
        el.textContent = (status === 'OK' && results && results[0]) ? results[0].formatted_address : (Number(r.last_lat).toFixed(5) + ', ' + Number(r.last_lng).toFixed(5));
      });
    }
  }
  function focusRunner(r) {
    if (!S.map || r.last_lat == null) return;
    S.map.panTo({ lat: Number(r.last_lat), lng: Number(r.last_lng) });
    S.map.setZoom(Math.max(S.map.getZoom(), 15));
    if (S.markers[r.id]) openInfo(r, S.markers[r.id]);
  }
  function fitAll() {
    if (!S.map || !window.google) return;
    const b = new google.maps.LatLngBounds();
    let n = 0;
    S.runners.forEach(function (r) {
      if (r.last_lat == null) return;
      if (S.onlyViol && r.compliance !== 'IN_VIOLATION') return;
      b.extend({ lat: Number(r.last_lat), lng: Number(r.last_lng) });
      n++;
    });
    if (!n) return;
    if (n === 1) { S.map.setCenter(b.getCenter()); S.map.setZoom(14); } else S.map.fitBounds(b, 64);
  }
  function renderViolBadge(count, list) {
    S.openViolations = list || [];
    $('violCount').textContent = String(count);
    $('violBadge').setAttribute('data-count', String(count));
    const box = $('violList');
    if (!S.openViolations.length) { box.innerHTML = '<div class="muted">No open violations.</div>'; return; }
    box.innerHTML = S.openViolations.map(function (v) {
      return '<div class="vrow"><div style="font-weight:800">' + esc(v.name) + ' · ' + esc(v.designation) + '</div><div class="muted">' + esc(v.violation_type) + '</div><div>Since ' + esc(v.detected_at) + '</div><div class="muted">' + esc(v.last_known_address || ((v.last_known_lat || '') + ', ' + (v.last_known_lng || ''))) + '</div><button class="btn" type="button" data-resolve="' + esc(v.incident_id) + '" style="margin-top:6px">Mark resolved</button></div>';
    }).join('');
    box.querySelectorAll('[data-resolve]').forEach(function (btn) {
      btn.addEventListener('click', async function () {
        try { await api('resolve_violation', { method: 'POST', body: { incident_id: btn.getAttribute('data-resolve') } }); await refresh(); }
        catch (e) { err(e.message); }
      });
    });
  }
  async function refresh() {
    err('');
    try {
      const d = await api('get_runners');
      S.runners = d.runners || [];
      S.policy = d.policy || null;
      const c = d.counts || {};
      $('cTotal').textContent = c.total || 0;
      $('cMoving').textContent = c.moving || 0;
      $('cIdle').textContent = c.idle || 0;
      $('cOffline').textContent = c.offline || 0;
      $('cViol').textContent = c.violations || 0;
      if (S.policy) {
        $('masterEmail').textContent = (S.policy.logistics_admin_name ? (S.policy.logistics_admin_name + ' · ') : '') + (S.policy.master_tracking_email || '—');
        $('hoursLbl').textContent = (S.policy.working_hours.start || '') + '–' + (S.policy.working_hours.end || '') + (d.working_hours_active ? ' · ON DUTY' : ' · off duty');
      }
      renderViolBadge(d.open_violation_count || 0, d.open_violations || []);
      renderRoster();
      syncMarkers();
    } catch (e) { err(e.message || String(e)); }
  }
  function loadMaps() {
    if (!MAPS_KEY) return;
    if (window.google && google.maps) { initMap(); return; }
    const s = document.createElement('script');
    s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(MAPS_KEY) + '&v=weekly&libraries=marker';
    s.async = true;
    s.onload = initMap;
    document.head.appendChild(s);
  }
  function initMap() {
    S.map = new google.maps.Map($('map'), { center: { lat: 28.6139, lng: 77.2090 }, zoom: 11, mapId: 'HR_COMPLIANCE_MAP', streetViewControl: false, mapTypeControl: false });
    S.info = new google.maps.InfoWindow();
    syncMarkers();
  }
  async function loadPath() {
    const rid = $('histRunner').value, date = $('histDate').value;
    if (!rid) return;
    try {
      const d = await api('get_history', { params: { runner_id: rid, date: date } });
      S.points = d.points || [];
      S.playIdx = 0;
      stopPlay();
      drawPath();
      $('scrub').max = Math.max(0, S.points.length - 1);
      $('scrub').value = 0;
      $('scrubLabel').textContent = S.points[0] ? S.points[0].timestamp : '';
    } catch (e) { err(e.message); }
  }
  function drawPath() {
    if (!S.map || !window.google) return;
    if (S.path) { S.path.setMap(null); S.path = null; }
    if (S.playMarker) { if (S.playMarker.map !== undefined) S.playMarker.map = null; else if (S.playMarker.setMap) S.playMarker.setMap(null); S.playMarker = null; }
    if (!S.points.length) return;
    const path = S.points.map(function (p) { return { lat: p.lat, lng: p.lng }; });
    S.path = new google.maps.Polyline({ path: path, geodesic: true, strokeColor: '#60a5fa', strokeOpacity: 0.95, strokeWeight: 4, map: S.map });
    const b = new google.maps.LatLngBounds();
    path.forEach(function (p) { b.extend(p); });
    S.map.fitBounds(b, 48);
    if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
      const pin = document.createElement('div');
      pin.style.cssText = 'width:28px;height:28px;border-radius:999px;background:#2563eb;border:2px solid #fff';
      S.playMarker = new google.maps.marker.AdvancedMarkerElement({ map: S.map, position: path[0], content: pin });
    } else {
      S.playMarker = new google.maps.Marker({ map: S.map, position: path[0] });
    }
  }
  function movePlay(i) {
    if (!S.points.length || !S.playMarker) return;
    i = Math.max(0, Math.min(S.points.length - 1, i));
    S.playIdx = i;
    const p = S.points[i], pos = { lat: p.lat, lng: p.lng };
    if (S.playMarker.content) S.playMarker.position = pos; else if (S.playMarker.setPosition) S.playMarker.setPosition(pos);
    $('scrub').value = i;
    $('scrubLabel').textContent = p.timestamp || '';
  }
  function stopPlay() { S.playing = false; $('btnPlay').textContent = 'Play'; if (S.timer) { clearInterval(S.timer); S.timer = null; } }
  function togglePlay() {
    if (S.playing) { stopPlay(); return; }
    if (!S.points.length) return;
    S.playing = true; $('btnPlay').textContent = 'Pause';
    S.timer = setInterval(function () { if (S.playIdx >= S.points.length - 1) { stopPlay(); return; } movePlay(S.playIdx + 1); }, 400);
  }
  async function loadAudit() {
    try {
      const d = await api('audit', { params: { from: $('auditFrom').value, to: $('auditTo').value, runner_id: $('histRunner').value || '' } });
      const tb = $('auditBody'); tb.innerHTML = '';
      (d.rows || []).forEach(function (r) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td>' + esc(r.date) + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.start || '—') + '</td><td>' + esc(r.end || '—') + '</td><td>' + Number(r.distance_km).toFixed(2) + ' km</td><td>' + Number(r.active_hours).toFixed(2) + ' h</td><td>' + Number(r.idle_hours).toFixed(2) + ' h</td>';
        tb.appendChild(tr);
      });
      if (!(d.rows || []).length) tb.innerHTML = '<tr><td colspan="7" class="muted" style="text-align:center;padding:20px">No rows</td></tr>';
    } catch (e) { err(e.message); }
  }
    function fillPolicyForm(p) {
    if (!p) return;
    var sel = $('polAdmin');
    if (sel && sel.options.length <= 1) {
      (TEAM_CONTACTS || []).forEach(function (c) {
        var o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.name + (c.email ? (' · ' + c.email) : ' · (no email)') + (c.designation ? (' · ' + c.designation) : '');
        o.setAttribute('data-email', c.email || '');
        o.setAttribute('data-name', c.name || '');
        sel.appendChild(o);
      });
    }
    if (sel) {
      sel.value = p.logistics_admin_id || '';
      if (p.logistics_admin_id && !sel.value) {
        // stale id — keep email field from policy
        sel.value = '';
      }
    }
    $('polEmail').value = p.master_tracking_email || '';
    $('polStart').value = (p.working_hours && p.working_hours.start) || '09:00';
    $('polEnd').value = (p.working_hours && p.working_hours.end) || '19:00';
    $('polTimeout').value = p.heartbeat_timeout_minutes || 10;
    $('polDesig').value = (p.mandatory_designations || []).join(', ');
    $('polDept').value = (p.mandatory_departments || []).join(', ');
  }
  async function loadLog() {
    try {
      const d = await api('get_violations', { params: { status: $('logFilter').value } });
      const box = $('logList');
      if (!(d.violations || []).length) { box.innerHTML = '<div class="muted">No incidents.</div>'; return; }
      box.innerHTML = '<table><thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Detected</th><th>Resolved</th><th>Min</th><th>Status</th></tr></thead><tbody>' + d.violations.map(function (v) {
        return '<tr><td>' + esc(v.incident_id) + '</td><td>' + esc(v.name) + '</td><td>' + esc(v.violation_type) + '</td><td>' + esc(v.detected_at) + '</td><td>' + esc(v.resolved_at || '—') + '</td><td>' + esc(v.duration_minutes ?? '—') + '</td><td>' + esc(v.status) + '</td></tr>';
      }).join('') + '</tbody></table>';
    } catch (e) { err(e.message); }
  }
  function todayISO() { const d = new Date(), z = function (n) { return String(n).padStart(2, '0'); }; return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate()); }
  function daysAgo(n) { const d = new Date(Date.now() - n * 864e5), z = function (x) { return String(x).padStart(2, '0'); }; return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate()); }
  $('histDate').value = todayISO();
  $('auditFrom').value = daysAgo(14);
  $('auditTo').value = todayISO();
  $('btnRefresh').addEventListener('click', refresh);
  $('btnFit').addEventListener('click', fitAll);
  $('btnLoadPath').addEventListener('click', loadPath);
  $('btnPlay').addEventListener('click', togglePlay);
  $('scrub').addEventListener('input', function () { movePlay(Number(this.value)); });
  $('btnAudit').addEventListener('click', loadAudit);
  $('onlyViol').addEventListener('change', function () { S.onlyViol = this.checked; renderRoster(); syncMarkers(); });
  $('btnCsv').addEventListener('click', function () { window.open(API + '?action=export_csv&from=' + encodeURIComponent($('auditFrom').value) + '&to=' + encodeURIComponent($('auditTo').value), '_blank'); });
  $('btnViolCsv').addEventListener('click', function () { window.open(API + '?action=export_violations_csv&status=ALL', '_blank'); });
  $('btnViolPrint').addEventListener('click', function () { window.open(API + '?action=export_violations_print&status=ALL', '_blank'); });
  $('violBadge').addEventListener('click', function () { $('modalViol').classList.add('open'); });
  $('closeViol').addEventListener('click', function () { $('modalViol').classList.remove('open'); });
  $('btnPolicy').addEventListener('click', function () { fillPolicyForm(S.policy); $('modalPolicy').classList.add('open'); });
  if ($('polAdmin')) {
    $('polAdmin').addEventListener('change', function () {
      var opt = this.selectedOptions[0];
      if (!opt || !opt.value) return;
      var em = opt.getAttribute('data-email') || '';
      if (em) $('polEmail').value = em;
    });
  }
  $('closePolicy').addEventListener('click', function () { $('modalPolicy').classList.remove('open'); });
  $('btnViolLog').addEventListener('click', function () { $('modalLog').classList.add('open'); loadLog(); });
  $('closeLog').addEventListener('click', function () { $('modalLog').classList.remove('open'); });
  $('logFilter').addEventListener('change', loadLog);
  $('btnSavePolicy').addEventListener('click', async function () {
    try {
      const body = {
        master_tracking_email: $('polEmail').value.trim(),
        logistics_admin_id: $('polAdmin') ? $('polAdmin').value : '',
        logistics_admin_name: (function () {
          var s = $('polAdmin'); if (!s || !s.selectedOptions[0]) return '';
          return s.selectedOptions[0].getAttribute('data-name') || '';
        })(),
        working_hours: { start: $('polStart').value, end: $('polEnd').value, timezone: 'Asia/Kolkata' },
        heartbeat_timeout_minutes: parseInt($('polTimeout').value, 10) || 10,
        mandatory_designations: $('polDesig').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean),
        mandatory_departments: $('polDept').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean)
      };
      const d = await api('save_policy', { method: 'POST', body: body });
      S.policy = d.policy;
      $('modalPolicy').classList.remove('open');
      await refresh();
    } catch (e) { err(e.message); }
  });
  refresh();
  loadMaps();
  setInterval(refresh, 30000);
})();
</script>
</body>
</html>
