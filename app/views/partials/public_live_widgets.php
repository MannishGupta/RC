<?php
/**
 * Public interface — wall clock (IST), weather, AQI (Open-Meteo, no API key).
 * Default coordinates: Delhi NCR (can override via company lat/lng if present).
 */
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$lat = 28.6595;
$lng = 77.3170;
if (class_exists('AppDB')) {
    $co = AppDB::read('company');
    if (is_array($co)) {
        $row = isset($co['name']) ? $co : ($co[0] ?? []);
        if (is_array($row)) {
            if (isset($row['lat']) && is_numeric($row['lat'])) {
                $lat = (float)$row['lat'];
            }
            if (isset($row['lng']) && is_numeric($row['lng'])) {
                $lng = (float)$row['lng'];
            }
        }
    }
}
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div id="rc-public-widgets" class="contents" role="region" aria-label="Live local information">
  <!-- Wall clock IST -->
  <div class="rc-live-card rounded-xl border shadow-sm p-2.5 sm:p-3 flex flex-col justify-between min-h-0 min-w-0 overflow-hidden">
    <div class="flex items-center justify-between gap-2">
      <span class="text-[10px] font-bold uppercase tracking-[0.14em] rc-live-kicker">Wall clock</span>
      <span class="text-[9px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-full px-1.5 py-0.5 shrink-0">IST</span>
    </div>
    <div id="rc-wall-clock" class="mt-1 font-mono text-base sm:text-xl md:text-2xl font-black tabular-nums rc-live-value tracking-tight leading-none" aria-live="polite">--:--:--</div>
    <div id="rc-wall-date" class="text-xs font-semibold rc-live-meta mt-1">—</div>
  </div>
  <!-- Weather -->
  <div class="rc-live-card rounded-xl border shadow-sm p-2.5 sm:p-3 flex flex-col justify-between min-h-0 min-w-0 overflow-hidden">
    <div class="flex items-center justify-between gap-2">
      <span class="text-[10px] font-bold uppercase tracking-[0.14em] rc-live-kicker">Weather</span>
      <span class="text-[9px] rc-live-kicker shrink-0 hidden sm:inline">Open-Meteo</span>
    </div>
    <div class="mt-2 flex items-end gap-2">
      <span id="rc-wx-temp" class="text-base sm:text-xl md:text-2xl font-black rc-live-value tabular-nums leading-none">—°</span>
      <span id="rc-wx-icon" class="text-2xl" aria-hidden="true">🌤️</span>
    </div>
    <div id="rc-wx-desc" class="text-xs font-semibold rc-live-meta mt-1">Loading…</div>
  </div>
  <!-- AQI -->
  <div class="rc-live-card rounded-xl border shadow-sm p-2.5 sm:p-3 flex flex-col justify-between min-h-0 min-w-0 overflow-hidden">
    <div class="flex items-center justify-between gap-2">
      <span class="text-[10px] font-bold uppercase tracking-[0.14em] rc-live-kicker">Air quality</span>
      <span id="rc-aqi-badge" class="text-[10px] font-bold rounded-full px-2 py-0.5 bg-slate-100 rc-live-meta border border-slate-200">US AQI</span>
    </div>
    <div class="mt-2 flex items-end gap-2">
      <span id="rc-aqi-val" class="text-base sm:text-xl md:text-2xl font-black rc-live-value tabular-nums leading-none">—</span>
      <span id="rc-aqi-label" class="text-sm font-bold rc-live-meta">—</span>
    </div>
    <div id="rc-aqi-detail" class="text-xs font-semibold rc-live-meta mt-1">PM2.5 · PM10 loading…</div>
  </div>
</div>
<script>
(function () {
  var lat = <?= json_encode(round($lat, 5)) ?>;
  var lng = <?= json_encode(round($lng, 5)) ?>;
  var tz = 'Asia/Kolkata';

  function pad(n) { return String(n).padStart(2, '0'); }
  function tickClock() {
    try {
      var now = new Date();
      var fmt = new Intl.DateTimeFormat('en-IN', {
        timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
      });
      var df = new Intl.DateTimeFormat('en-IN', {
        timeZone: tz, weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
      });
      var el = document.getElementById('rc-wall-clock');
      var ed = document.getElementById('rc-wall-date');
      if (el) el.textContent = fmt.format(now);
      if (ed) ed.textContent = df.format(now);
    } catch (e) {
      var d = new Date();
      var el2 = document.getElementById('rc-wall-clock');
      if (el2) el2.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }
  }
  tickClock();
  setInterval(tickClock, 1000);

  var WMO = {
    0: ['Clear sky', '☀️'], 1: ['Mainly clear', '🌤️'], 2: ['Partly cloudy', '⛅'], 3: ['Overcast', '☁️'],
    45: ['Fog', '🌫️'], 48: ['Rime fog', '🌫️'], 51: ['Light drizzle', '🌦️'], 61: ['Rain', '🌧️'],
    71: ['Snow', '🌨️'], 80: ['Rain showers', '🌦️'], 95: ['Thunderstorm', '⛈️']
  };
  function wxLabel(code) {
    if (WMO[code]) return WMO[code];
    if (code >= 50 && code < 60) return ['Drizzle', '🌦️'];
    if (code >= 60 && code < 70) return ['Rain', '🌧️'];
    if (code >= 70 && code < 80) return ['Snow', '🌨️'];
    if (code >= 80 && code < 90) return ['Showers', '🌦️'];
    if (code >= 90) return ['Storm', '⛈️'];
    return ['—', '🌡️'];
  }
  function aqiBand(v) {
    if (v == null || isNaN(v)) return { label: '—', cls: 'bg-slate-100 rc-live-meta border-slate-200', color: '#64748b' };
    if (v <= 50) return { label: 'Good', cls: 'bg-teal-50 text-teal-800 border-teal-200', color: '#0f766e' };
    if (v <= 100) return { label: 'Moderate', cls: 'bg-amber-50 text-amber-900 border-amber-200', color: '#b45309' };
    if (v <= 150) return { label: 'Unhealthy*', cls: 'bg-orange-50 text-orange-900 border-orange-300', color: '#c2410c' };
    if (v <= 200) return { label: 'Unhealthy', cls: 'bg-rose-50 text-rose-900 border-rose-300', color: '#9f1239' };
    if (v <= 300) return { label: 'Very Unhealthy', cls: 'bg-purple-50 text-purple-900 border-purple-300', color: '#6b21a8' };
    return { label: 'Hazardous', cls: 'bg-stone-800 text-white border-stone-900', color: '#1c1917' };
  }

  fetch('https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lng
    + '&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&timezone=' + encodeURIComponent(tz))
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var c = data.current || {};
      var pair = wxLabel(c.weather_code);
      var tEl = document.getElementById('rc-wx-temp');
      var iEl = document.getElementById('rc-wx-icon');
      var dEl = document.getElementById('rc-wx-desc');
      if (tEl && c.temperature_2m != null) tEl.textContent = Math.round(c.temperature_2m) + '°';
      if (iEl) iEl.textContent = pair[1];
      if (dEl) {
        var bits = [pair[0]];
        if (c.relative_humidity_2m != null) bits.push(c.relative_humidity_2m + '% RH');
        if (c.wind_speed_10m != null) bits.push(Math.round(c.wind_speed_10m) + ' km/h wind');
        dEl.textContent = bits.join(' · ');
      }
    })
    .catch(function () {
      var dEl = document.getElementById('rc-wx-desc');
      if (dEl) dEl.textContent = 'Weather unavailable';
    });

  fetch('https://air-quality-api.open-meteo.com/v1/air-quality?latitude=' + lat + '&longitude=' + lng
    + '&current=us_aqi,pm2_5,pm10&timezone=' + encodeURIComponent(tz))
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var c = data.current || {};
      var aqi = c.us_aqi;
      var band = aqiBand(aqi);
      var vEl = document.getElementById('rc-aqi-val');
      var lEl = document.getElementById('rc-aqi-label');
      var bEl = document.getElementById('rc-aqi-badge');
      var dEl = document.getElementById('rc-aqi-detail');
      if (vEl && aqi != null) {
        vEl.textContent = String(Math.round(aqi));
        vEl.style.color = band.color;
      }
      if (lEl) lEl.textContent = band.label;
      if (bEl) {
        bEl.className = 'text-[10px] font-bold rounded-full px-2 py-0.5 border ' + band.cls;
        bEl.textContent = 'US AQI';
      }
      if (dEl) {
        var pm = [];
        if (c.pm2_5 != null) pm.push('PM2.5 ' + Math.round(c.pm2_5));
        if (c.pm10 != null) pm.push('PM10 ' + Math.round(c.pm10));
        dEl.textContent = pm.length ? pm.join(' · ') + ' µg/m³' : '—';
      }
    })
    .catch(function () {
      var dEl = document.getElementById('rc-aqi-detail');
      if (dEl) dEl.textContent = 'AQI unavailable';
    });
})();
</script>
