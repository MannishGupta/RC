<?php
// tab_locations.php — Version: 20260928.09
if (!defined('BASE_PATH')) {
    exit;
}
$isAdminLoc = !empty($isAdmin);
?>
<style>
/* Location logos: always sit on light plate so dark/reserve themes stay readable */
.rc-loc-logo-shell {
  background: #ffffff !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 1px 2px rgba(15,23,42,.06);
}
html[data-theme="dark"] .rc-loc-logo-shell,
html[data-theme="reserve"] .rc-loc-logo-shell {
  background: #ffffff !important;
  border-color: #cbd5e1 !important;
}
.rc-loc-logo { max-height: 100%; max-width: 100%; object-fit: contain; }
</style>
<script>
window.rcMediaUrl = window.rcMediaUrl || function (name) {
  if (!name) return '';
  var n = String(name).trim();
  if (/^https?:\/\//i.test(n) || n.indexOf('data:') === 0) return n;
  // strip query and path prefixes (images/, media/, tenants/...)
  n = n.split('?')[0].replace(/^.*[\\/]/, '');
  if (!n) return '';
  var base = (typeof window.RC_BASE === 'string' && window.RC_BASE) ? window.RC_BASE.replace(/\/$/, '') : '';
  // Prefer media gateway (tenant-safe); images/ also rewritten on Apache/IIS
  return base + '/media_serve.php?f=' + encodeURIComponent(n);
};
window.rcMediaOnError = window.rcMediaOnError || function (el) {
  if (!el || el.dataset.rcTried) return;
  el.dataset.rcTried = '1';
  var n = (el.getAttribute('data-src-name') || '').replace(/^.*[\\/]/, '');
  if (!n) { el.style.display = 'none'; return; }
  el.src = '/images/' + encodeURIComponent(n);
};
</script>
<div class="w-full flex flex-col gap-4" x-data="{ q: '' }">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3 min-w-0">
      <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
        <i class="fa-solid fa-map-location-dot"></i>
      </div>
      <div class="min-w-0">
        <h2 class="text-lg font-bold text-slate-900 m-0">Shared Locations</h2>
        <p class="text-xs text-slate-500 m-0">Premises registry · logos via media gateway</p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <input type="search" x-model="q" placeholder="Filter locations…"
        class="h-9 px-3 rounded-lg border border-slate-200 text-sm bg-white text-slate-900">
      <?php if ($isAdminLoc): ?>
      <button type="button" @click="window.openModalEditor(false, 'locations')"
        class="h-9 px-3 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-blue-600">+ Add</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="flex flex-wrap items-center gap-2" x-show="(filteredList||[]).some(i => i.logo || i.photo)">
    <template x-for="i in (filteredList||[]).filter(i => i.logo || i.photo)" :key="'lg-'+(i.id||i.slug||i.name)">
      <div class="rc-loc-logo-shell h-12 w-12 rounded-lg p-1.5 flex items-center justify-center overflow-hidden" :title="i.name">
        <img :src="rcMediaUrl(i.logo || i.photo)" :data-src-name="i.logo || i.photo" @error="rcMediaOnError($event.target)" alt="" class="max-h-full max-w-full object-contain rc-loc-logo" loading="lazy"
             @error="$el.style.display='none'">
      </div>
    </template>
  </div>

  <template x-if="!Array.isArray(filteredList) || filteredList.filter(i => !q || JSON.stringify(i).toLowerCase().includes(q.toLowerCase())).length === 0">
    <div class="flex flex-col items-center justify-center h-48 text-slate-500 border-2 border-dashed border-slate-200 rounded-xl bg-white">
      <i class="fa-solid fa-map text-3xl mb-3 opacity-50"></i>
      <p class="text-sm font-semibold text-slate-700">No locations found.</p>
      <p class="text-xs text-slate-400 mt-1" x-text="'Server locations: ' + (window.__RC_LOC_COUNT__ || 0)"></p>
    </div>
  </template>

  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <template x-for="i in (filteredList || []).filter(i => !q || JSON.stringify(i).toLowerCase().includes(q.toLowerCase()))" :key="i.id || i.slug || i.name">
      <article class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow flex flex-col">
        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50 flex items-center gap-3 min-h-[3.75rem]">
          <div class="w-14 h-14 rounded-lg bg-white border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
            <template x-if="i.logo || i.photo">
              <img :src="rcMediaUrl(i.logo || i.photo)" :data-src-name="i.logo || i.photo" @error="rcMediaOnError($event.target)" alt="" class="max-h-full max-w-full object-contain rc-loc-logo" loading="lazy"
                   @error="$el.replaceWith(Object.assign(document.createElement('i'),{className:'fa-solid fa-building text-slate-300 text-lg'}))">
            </template>
            <template x-if="!(i.logo || i.photo)">
              <i class="fa-solid fa-building text-slate-300 text-lg"></i>
            </template>
          </div>
          <div class="min-w-0 flex-1">
            <h3 class="text-sm font-bold text-slate-900 truncate m-0" x-text="i.name || 'Unnamed facility'"></h3>
            <p class="text-[11px] text-slate-500 truncate m-0" x-text="[i.city, i.state].filter(Boolean).join(' · ') || 'Location'"></p>
          </div>
        </div>
        <div class="p-4 flex-1 space-y-2 text-sm text-slate-700">
          <p class="m-0 leading-relaxed" x-show="i.address" x-text="i.address"></p>
          <p class="m-0 text-xs text-slate-500" x-show="i.pincode" x-text="'PIN ' + i.pincode"></p>
          <a x-show="i.map_url" :href="i.map_url" target="_blank" rel="noopener"
            class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline">
            <i class="fa-solid fa-map"></i> Map
          </a>
        </div>
        <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-end gap-2">
          <button type="button" @click.stop="shareItem('locations', i)"
            class="h-8 px-2.5 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-100 border border-slate-200">Share</button>
          <?php if ($isAdminLoc): ?>
          <button type="button" @click.stop="window.openModalEditor(true, 'locations', i)"
            class="h-8 px-2.5 rounded-lg text-xs font-bold text-blue-700 hover:bg-blue-50 border border-blue-200">Edit</button>
          <button type="button" @click.stop="deleteItem(i.id, 'locations')"
            class="h-8 px-2.5 rounded-lg text-xs font-bold text-rose-700 hover:bg-rose-50 border border-rose-200">Delete</button>
          <?php endif; ?>
        </div>
      </article>
    </template>
  </div>
</div>
