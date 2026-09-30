<?php
/**
 * Admin console: live routes map + vehicle assignment + Hindi dispatch
 */
if (!defined('DISPATCH_ROOT')) {
    exit;
}
$settings = d_settings();
$mapsKey = (string) ($settings['google_maps_api_key'] ?? '');
$defLat = (float) ($settings['default_lat'] ?? 28.6139);
$defLng = (float) ($settings['default_lng'] ?? 77.2090);
$team = d_team();
$vehicles = d_vehicles();
$routes = d_read('routes');
$assignments = d_read('assignments');
$locations = d_locations();
$activeRoutes = array_values(array_filter($routes, static fn($r) => is_array($r) && ($r['status'] ?? '') === 'active'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dispatch Console · Live Routes</title>
  <link rel="stylesheet" href="/assets/app.css">
  <link rel="stylesheet" href="/assets/vendor/fontawesome.min.css">
  <style>
    html, body { height: 100%; }
    #map { height: 100%; min-height: 360px; border-radius: 0.75rem; }
    .glass { background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(10px); }
    .chip { font-size: 10px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
  <div class="max-w-[1400px] mx-auto p-3 md:p-5 space-y-4">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <div class="chip text-amber-400">Resource Center · Logistics</div>
        <h1 class="text-xl md:text-2xl font-black tracking-tight">Dispatch Console</h1>
        <p class="text-slate-400 text-sm">Live routes · vehicle mapping · Roman Hindi dispatch</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="?view=mobile" class="px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-xs font-bold hover:bg-slate-700">
          <i class="fa-solid fa-mobile-screen mr-1"></i> Staff mobile view
        </a>
        <button type="button" id="btn-refresh" class="px-3 py-2 rounded-lg bg-blue-600 text-xs font-bold hover:bg-blue-500">
          <i class="fa-solid fa-rotate mr-1"></i> Refresh
        </button>
      </div>
    </header>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
      <div class="rounded-xl border border-slate-800 bg-slate-900 p-3">
        <div class="chip text-slate-500">Active routes</div>
        <div class="text-2xl font-black mt-1" id="stat-routes"><?= count($activeRoutes) ?></div>
      </div>
      <div class="rounded-xl border border-slate-800 bg-slate-900 p-3">
        <div class="chip text-slate-500">Vehicles mapped</div>
        <div class="text-2xl font-black mt-1" id="stat-asn"><?= count(array_filter($assignments, static fn($a) => is_array($a) && ($a['status'] ?? '') === 'active')) ?></div>
      </div>
      <div class="rounded-xl border border-slate-800 bg-slate-900 p-3">
        <div class="chip text-slate-500">Field staff</div>
        <div class="text-2xl font-black mt-1"><?= count($team) ?></div>
      </div>
      <div class="rounded-xl border border-slate-800 bg-slate-900 p-3">
        <div class="chip text-slate-500">Fleet tags</div>
        <div class="text-2xl font-black mt-1"><?= count($vehicles) ?></div>
      </div>
    </div>

    <div class="grid lg:grid-cols-5 gap-4">
      <!-- Map -->
      <section class="lg:col-span-3 rounded-xl border border-slate-800 bg-slate-900 p-3 flex flex-col" style="min-height:420px">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-sm font-bold"><i class="fa-solid fa-route text-amber-400 mr-1"></i> Live routes</h2>
          <span class="text-[10px] text-slate-500" id="map-hint"><?= $mapsKey !== '' ? 'Google Maps ready' : 'Add Maps API key in settings below' ?></span>
        </div>
        <div id="map" class="flex-1 bg-slate-950"></div>
        <?php if ($mapsKey === ''): ?>
        <p class="text-xs text-amber-300/90 mt-2">Without a Google Maps key, a simple list of coordinates is shown. Paste your key under Settings.</p>
        <?php endif; ?>
      </section>

      <!-- Side panels -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Create route -->
        <section class="rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-3">
          <h2 class="text-sm font-bold"><i class="fa-solid fa-plus text-emerald-400 mr-1"></i> New delivery route</h2>
          <div class="space-y-2 text-sm">
            <input id="r-title" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Title e.g. Bank documents drop">
            <select id="r-runner" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2">
              <option value="">— Field staff (live-tracking roles only) —</option>
              <?php foreach ($team as $t): ?>
              <option value="<?= d_h($t['id']) ?>"><?= d_h($t['name'] . ($t['designation'] !== '' ? ' · ' . $t['designation'] : '')) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Pickup (standard location or type custom)</label>
            <select id="r-origin-sel" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm">
              <option value="">— Standard location (optional) —</option>
              <?php if (!empty($locations)): ?>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= d_h($loc['label']) ?>"
                      data-lat="<?= d_h((string)$loc['lat']) ?>"
                      data-lng="<?= d_h((string)$loc['lng']) ?>"
                      data-name="<?= d_h($loc['name']) ?>"><?= d_h($loc['label']) ?></option>
              <?php endforeach; ?>
              <?php endif; ?>
              <option value="__custom__">Other / type below…</option>
            </select>
            <input id="r-origin" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Pickup address (auto-filled or type)">
            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mt-1 block">Drop-off *</label>
            <select id="r-dest-sel" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm">
              <option value="">— Standard location —</option>
              <?php if (!empty($locations)): ?>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= d_h($loc['label']) ?>"
                      data-lat="<?= d_h((string)$loc['lat']) ?>"
                      data-lng="<?= d_h((string)$loc['lng']) ?>"
                      data-name="<?= d_h($loc['name']) ?>"><?= d_h($loc['label']) ?></option>
              <?php endforeach; ?>
              <?php endif; ?>
              <option value="__custom__">Other / type below…</option>
            </select>
            <input id="r-dest" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Delivery address *" required>
            <div class="grid grid-cols-2 gap-2">
              <input id="r-olat" type="number" step="any" class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Pickup lat" value="<?= d_h((string)$defLat) ?>">
              <input id="r-olng" type="number" step="any" class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Pickup lng" value="<?= d_h((string)$defLng) ?>">
              <input id="r-dlat" type="number" step="any" class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Dest lat" value="<?= d_h((string)$defLat) ?>">
              <input id="r-dlng" type="number" step="any" class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Dest lng" value="<?= d_h((string)$defLng) ?>">
            </div>
            <p class="text-[10px] text-slate-500">Staff list shows only designations with <strong class="text-slate-300">Mandatory live tracking</strong>. Locations come from <strong class="text-slate-300">Standard Locations</strong> (Premises Registry).</p>
            <input id="r-item" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="What to carry e.g. envelope, parcel">
            <input id="r-contact" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Receiver name">
            <input id="r-phone" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Receiver phone">
            <textarea id="r-notes" rows="2" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2" placeholder="Extra instructions"></textarea>
            <button type="button" id="btn-create-route" class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 font-bold text-sm">
              Create route on map
            </button>
          </div>
        </section>

        <!-- Vehicle mapping -->
        <section class="rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-3">
          <h2 class="text-sm font-bold"><i class="fa-solid fa-car text-sky-400 mr-1"></i> Map vehicle → staff</h2>
          <p class="text-[11px] text-slate-400">Link a fleet tag / registration to a runner or driver so dispatches name the right gaadi.</p>
          <select id="a-runner" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm">
            <option value="">— Staff —</option>
            <?php foreach ($team as $t): ?>
            <option value="<?= d_h($t['id']) ?>"><?= d_h($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="a-vehicle" class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm">
            <option value="">— Vehicle / tag —</option>
            <?php foreach ($vehicles as $v): ?>
            <option value="<?= d_h($v['id']) ?>"><?= d_h(trim($v['registration'] . ' · ' . $v['make_model'] . ' · ' . $v['tag_id'])) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="btn-assign" class="w-full py-2 rounded-lg bg-sky-600 hover:bg-sky-500 font-bold text-sm">
            Assign vehicle
          </button>
          <ul id="asn-list" class="text-xs space-y-1 max-h-36 overflow-y-auto">
            <?php foreach ($assignments as $a):
              if (!is_array($a) || ($a['status'] ?? '') !== 'active') continue;
            ?>
            <li class="flex justify-between gap-2 rounded-lg bg-slate-950 border border-slate-800 px-2 py-1.5">
              <span><?= d_h($a['runner_name'] ?? '') ?> → <?= d_h(($a['registration'] ?? '') . ' ' . ($a['make_model'] ?? '')) ?></span>
              <button type="button" class="text-rose-400 unassign" data-id="<?= d_h($a['id'] ?? '') ?>">×</button>
            </li>
            <?php endforeach; ?>
          </ul>
        </section>
      </div>
    </div>

    <!-- Active jobs + dispatch -->
    <section class="rounded-xl border border-slate-800 bg-slate-900 p-4">
      <h2 class="text-sm font-bold mb-3"><i class="fa-solid fa-paper-plane text-violet-400 mr-1"></i> Active jobs · send Roman Hindi dispatch</h2>
      <div id="jobs" class="space-y-2">
        <?php if ($activeRoutes === []): ?>
        <p class="text-sm text-slate-500">No active routes yet. Create one above.</p>
        <?php endif; ?>
        <?php foreach ($activeRoutes as $r): ?>
        <div class="job-row flex flex-wrap items-center gap-2 rounded-xl border border-slate-800 bg-slate-950 px-3 py-2.5" data-id="<?= d_h($r['id']) ?>">
          <div class="min-w-0 flex-1">
            <div class="font-bold text-sm truncate"><?= d_h($r['title'] ?? 'Route') ?></div>
            <div class="text-[11px] text-slate-400 truncate">
              <?= d_h($r['runner_name'] ?: 'Unassigned') ?>
              · <?= d_h($r['destination'] ?? '') ?>
              <?php if (!empty($r['item'])): ?> · <?= d_h($r['item']) ?><?php endif; ?>
            </div>
          </div>
          <button type="button" class="btn-dispatch px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-xs font-bold" data-id="<?= d_h($r['id']) ?>">
            <i class="fa-brands fa-whatsapp mr-1"></i> Dispatch (Hindi)
          </button>
          <button type="button" class="btn-done px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-xs font-bold" data-id="<?= d_h($r['id']) ?>">Done</button>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Settings -->
    <section class="rounded-xl border border-slate-800 bg-slate-900 p-4 space-y-2 max-w-xl">
      <h2 class="text-sm font-bold"><i class="fa-solid fa-key text-amber-400 mr-1"></i> Maps API key</h2>
      <p class="text-[11px] text-slate-400">Paste Google Maps JavaScript API key (same as company settings). Required for the live map.</p>
      <div class="flex gap-2">
        <input id="maps-key" type="text" class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm" placeholder="AIza…" value="<?= d_h($mapsKey) ?>">
        <button type="button" id="btn-save-key" class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-500 text-sm font-bold">Save</button>
      </div>
    </section>

    <!-- Dispatch preview modal -->
    <div id="modal" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 p-3">
      <div class="glass w-full max-w-lg rounded-2xl border border-slate-700 p-4 shadow-2xl">
        <div class="flex justify-between items-center mb-2">
          <h3 class="font-bold">Dispatch message (Roman Hindi)</h3>
          <button type="button" id="modal-close" class="text-slate-400 hover:text-white">×</button>
        </div>
        <pre id="modal-msg" class="text-sm whitespace-pre-wrap bg-slate-950 rounded-xl p-3 border border-slate-800 max-h-64 overflow-y-auto text-slate-200"></pre>
        <div class="flex flex-wrap gap-2 mt-3">
          <button type="button" id="modal-copy" class="px-3 py-2 rounded-lg bg-slate-700 text-xs font-bold">Copy text</button>
          <a id="modal-wa" href="#" target="_blank" rel="noopener" class="px-3 py-2 rounded-lg bg-emerald-600 text-xs font-bold inline-flex items-center gap-1">
            <i class="fa-brands fa-whatsapp"></i> Open WhatsApp
          </a>
        </div>
      </div>
    </div>
  </div>

  <script>
  const API = 'dispatch.php';
  const DEF = { lat: <?= json_encode($defLat) ?>, lng: <?= json_encode($defLng) ?> };
  const MAPS_KEY = <?= json_encode($mapsKey, JSON_UNESCAPED_SLASHES) ?>;
  let map, directionsService, directionsRenderer, markers = [];

  async function api(action, body = {}) {
    const res = await fetch(API + '?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(Object.assign({ action }, body))
    });
    return res.json();
  }

  function initMap() {
    const el = document.getElementById('map');
    if (!MAPS_KEY || typeof google === 'undefined') {
      el.innerHTML = '<div class="p-6 text-sm text-slate-400">Map waits for Google Maps API key. Active routes still list below.</div>';
      return;
    }
    map = new google.maps.Map(el, {
      center: DEF,
      zoom: 12,
      mapId: 'DISPATCH_LIVE',
      disableDefaultUI: false,
      styles: [
        { elementType: 'geometry', stylers: [{ color: '#1e293b' }] },
        { elementType: 'labels.text.stroke', stylers: [{ color: '#0f172a' }] },
        { elementType: 'labels.text.fill', stylers: [{ color: '#94a3b8' }] },
        { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#334155' }] },
        { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0f172a' }] }
      ]
    });
    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
      map,
      suppressMarkers: false,
      polylineOptions: { strokeColor: '#fbbf24', strokeWeight: 5, strokeOpacity: 0.9 }
    });
    drawRoutes();
  }

  async function drawRoutes() {
    const data = await api('list_all');
    const routes = (data.routes || []).filter(r => r.status === 'active');
    document.getElementById('stat-routes').textContent = routes.length;
    const asn = (data.assignments || []).filter(a => a.status === 'active');
    document.getElementById('stat-asn').textContent = asn.length;

    if (!map) return;
    markers.forEach(m => m.setMap(null));
    markers = [];
    if (routes.length === 0) return;

    // Draw first route as directions; others as markers
    const first = routes[0];
    if (first.origin_lat && first.dest_lat && directionsService) {
      directionsService.route({
        origin: { lat: +first.origin_lat, lng: +first.origin_lng },
        destination: { lat: +first.dest_lat, lng: +first.dest_lng },
        travelMode: google.maps.TravelMode.DRIVING
      }, (result, status) => {
        if (status === 'OK') directionsRenderer.setDirections(result);
      });
    }
    const bounds = new google.maps.LatLngBounds();
    routes.forEach((r, i) => {
      if (!r.dest_lat || !r.dest_lng) return;
      const pos = { lat: +r.dest_lat, lng: +r.dest_lng };
      const m = new google.maps.Marker({
        map,
        position: pos,
        title: (r.title || 'Stop') + ' · ' + (r.runner_name || ''),
        label: { text: String(i + 1), color: '#0f172a', fontWeight: '700' }
      });
      const info = new google.maps.InfoWindow({
        content: '<div style="color:#0f172a;font:12px/1.4 system-ui"><b>' +
          (r.title || 'Route') + '</b><br>' + (r.destination || '') +
          '<br><span style="color:#64748b">' + (r.runner_name || '') + '</span></div>'
      });
      m.addListener('click', () => info.open({ map, anchor: m }));
      markers.push(m);
      bounds.extend(pos);
      if (r.origin_lat && r.origin_lng) {
        bounds.extend({ lat: +r.origin_lat, lng: +r.origin_lng });
      }
    });
    if (!bounds.isEmpty()) map.fitBounds(bounds, 48);
  }

  function bindLocSelect(selId, textId, latId, lngId) {
    const sel = document.getElementById(selId);
    if (!sel) return;
    sel.addEventListener('change', () => {
      const opt = sel.options[sel.selectedIndex];
      if (!opt || !opt.value || opt.value === '__custom__') return;
      document.getElementById(textId).value = opt.value;
      const lat = parseFloat(opt.getAttribute('data-lat') || '0');
      const lng = parseFloat(opt.getAttribute('data-lng') || '0');
      if (lat && lng) {
        document.getElementById(latId).value = lat;
        document.getElementById(lngId).value = lng;
      }
    });
  }
  bindLocSelect('r-origin-sel', 'r-origin', 'r-olat', 'r-olng');
  bindLocSelect('r-dest-sel', 'r-dest', 'r-dlat', 'r-dlng');

  document.getElementById('btn-create-route').addEventListener('click', async () => {
    const body = {
      title: document.getElementById('r-title').value,
      runner_id: document.getElementById('r-runner').value,
      origin: document.getElementById('r-origin').value,
      destination: document.getElementById('r-dest').value,
      dest_lat: document.getElementById('r-dlat').value,
      dest_lng: document.getElementById('r-dlng').value,
      origin_lat: document.getElementById('r-olat').value || DEF.lat,
      origin_lng: document.getElementById('r-olng').value || DEF.lng,
      item: document.getElementById('r-item').value,
      contact_name: document.getElementById('r-contact').value,
      contact_phone: document.getElementById('r-phone').value,
      notes: document.getElementById('r-notes').value
    };
    if (!body.destination) { alert('Delivery address is required'); return; }
    const res = await api('create_route', body);
    if (!res.ok) { alert(res.error || 'Failed'); return; }
    location.reload();
  });

  document.getElementById('btn-assign').addEventListener('click', async () => {
    const res = await api('assign_vehicle', {
      runner_id: document.getElementById('a-runner').value,
      vehicle_id: document.getElementById('a-vehicle').value
    });
    if (!res.ok) { alert(res.error || 'Failed'); return; }
    location.reload();
  });

  document.querySelectorAll('.unassign').forEach(btn => {
    btn.addEventListener('click', async () => {
      await api('unassign_vehicle', { id: btn.dataset.id });
      location.reload();
    });
  });

  document.getElementById('jobs').addEventListener('click', async (e) => {
    const d = e.target.closest('.btn-dispatch');
    const done = e.target.closest('.btn-done');
    if (d) {
      const res = await api('dispatch', { route_id: d.dataset.id });
      if (!res.ok) { alert(res.error || 'Failed'); return; }
      document.getElementById('modal-msg').textContent = res.message || '';
      document.getElementById('modal-wa').href = res.whatsapp_url || '#';
      document.getElementById('modal').classList.remove('hidden');
    }
    if (done) {
      await api('update_route_status', { id: done.dataset.id, status: 'completed' });
      location.reload();
    }
  });

  document.getElementById('modal-close').addEventListener('click', () => {
    document.getElementById('modal').classList.add('hidden');
  });
  document.getElementById('modal-copy').addEventListener('click', async () => {
    const t = document.getElementById('modal-msg').textContent;
    try { await navigator.clipboard.writeText(t); alert('Copied'); } catch (e) { alert('Copy manually'); }
  });

  document.getElementById('btn-save-key').addEventListener('click', async () => {
    const res = await api('save_settings', { google_maps_api_key: document.getElementById('maps-key').value });
    if (res.ok) location.reload();
  });
  document.getElementById('btn-refresh').addEventListener('click', () => location.reload());

  // Load Maps JS if key present
  if (MAPS_KEY) {
    const s = document.createElement('script');
    s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(MAPS_KEY) + '&callback=initMap';
    s.async = true;
    window.initMap = initMap;
    document.head.appendChild(s);
  } else {
    initMap();
  }
  </script>
</body>
</html>
