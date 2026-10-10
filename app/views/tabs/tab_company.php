<?php
// Version: 260916.14 — HQ_LOCATION_FIX
// FIX: company.location_id was read from a single key; it may be stored under
//      location_id / location / hq_location / hq depending on which modal field name
//      was active at save time. All four variants are now checked with the first
//      truthy value winning — fixes the "HQ keeps showing Not Set / being asked again" bug.
// FIX: openModalEditor now receives a normalised copy with location_id always populated.
if (!defined('BASE_PATH')) exit; ?>
<div class="max-w-5xl mx-auto w-full space-y-8 pb-12">

    <?php
      $__co = is_array($viewData['company'] ?? null) ? $viewData['company'] : [];
      $__coName = trim((string)($__co['name'] ?? ''));
      $__coLogo = trim((string)($__co['logo'] ?? ''));
      $__teamN = is_array($viewData['jsData']['team'] ?? null) ? count($viewData['jsData']['team']) : 0;
      $__showSetup = ($__coName === '' && $__coLogo === '');
    ?>
    <?php if ($__showSetup): ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-5 shadow-sm mb-6">
      <h2 class="text-base font-black text-slate-800">Welcome — let's set up your organization</h2>
      <p class="text-xs text-slate-600 mt-1 mb-3">Complete these items using the existing screens. This checklist disappears once name and logo are set.</p>
      <ul class="space-y-2 text-sm text-slate-700">
        <li class="flex items-center gap-2">
          <span class="w-5 text-center"><?= $__coName !== '' ? '✓' : '○' ?></span>
          <button type="button" class="text-left font-semibold text-blue-700 hover:underline"
            onclick="(function(){ const c=Object.assign({}, window.__DASHBOARD_STATE__.company||{}); c.location_id=c.location_id||c.location||''; window.openModalEditor && window.openModalEditor(true,'company',c); })()">
            1) Company name &amp; website
          </button>
        </li>
        <li class="flex items-center gap-2">
          <span class="w-5 text-center"><?= $__coLogo !== '' ? '✓' : '○' ?></span>
          <button type="button" class="text-left font-semibold text-blue-700 hover:underline"
            onclick="(function(){ const c=Object.assign({}, window.__DASHBOARD_STATE__.company||{}); window.openModalEditor && window.openModalEditor(true,'company',c); })()">
            2) Logo &amp; brand colour
          </button>
        </li>
        <li class="flex items-center gap-2">
          <span class="w-5 text-center"><?= $__teamN > 0 ? '✓' : '○' ?></span>
          <a href="?tab=team" class="font-semibold text-blue-700 hover:underline">3) Add your first team member</a>
        </li>
        <li class="flex items-center gap-2">
          <span class="w-5 text-center">○</span>
          <a href="?tab=terms&amp;policy=privacy" class="font-semibold text-blue-700 hover:underline">4) Review Privacy / DPDP page</a>
        </li>
      </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($isAdmin)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
      <h2 class="text-sm font-black text-slate-800 mb-3">Brand assets</h2>
      <p class="text-xs text-slate-500 mb-4">Three assets, each for a different place. <strong class="font-semibold text-slate-700">Primary logo</strong> — app chrome (sidebar, login, cards, print). <strong class="font-semibold text-slate-700">Site icon</strong> — browser tab (favicon). <strong class="font-semibold text-slate-700">Social preview image</strong> — WhatsApp / LinkedIn link cards and the Organisation banner. Recommended size and formats appear under each control.</p>
      
<script>
window.rcRasterLogoToPng = window.rcRasterLogoToPng || function (file, maxW, maxH, plate) {
  maxW = maxW || 1120; maxH = maxH || 448; plate = plate || 'auto';
  return new Promise(function (resolve, reject) {
    if (!file) return reject(new Error('no file'));
    var url = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function () {
      try {
        var sw = img.naturalWidth || img.width || 512;
        var sh = img.naturalHeight || img.height || 512;
        var scale = Math.min(maxW / sw, maxH / sh, 4);
        var w = Math.max(64, Math.round(sw * scale));
        var h = Math.max(32, Math.round(sh * scale));
        var c = document.createElement('canvas');
        c.width = w; c.height = h;
        var ctx = c.getContext('2d');
        // Draw on transparent first to sample ink
        ctx.clearRect(0, 0, w, h);
        ctx.drawImage(img, 0, 0, w, h);
        var data = ctx.getImageData(0, 0, w, h).data;
        var sum = 0, n = 0;
        for (var i = 0; i < data.length; i += 16) {
          var a = data[i + 3];
          if (a < 40) continue;
          sum += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
          n++;
        }
        var mean = n ? (sum / n) : 128;
        var useDark = (plate === 'light') ? false : ((plate === 'dark') || mean > 90 || plate === 'auto');
        ctx.globalCompositeOperation = 'destination-over';
        ctx.fillStyle = useDark ? '#1B1A19' : '#FFFFFF';
        ctx.fillRect(0, 0, w, h);
        c.toBlob(function (blob) {
          URL.revokeObjectURL(url);
          if (blob) resolve({ blob: blob, plate: useDark ? 'dark' : 'light' });
          else reject(new Error('toBlob failed'));
        }, 'image/png', 0.95);
      } catch (e) {
        URL.revokeObjectURL(url);
        reject(e);
      }
    };
    img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('image load failed')); };
    // SVG/HTMLImageElement from blob URL
    img.crossOrigin = 'anonymous';
    img.src = url;
  });
};
</script>

      



<script>
window.__rcBrandFiles = window.__rcBrandFiles || { logo: null, favicon: null, cover: null };
window.rcBrandFilePick = window.rcBrandFilePick || function (field, fileOrInput) {
  try {
    var file = null;
    if (fileOrInput && fileOrInput.files) file = fileOrInput.files[0] || null;
    else if (fileOrInput && typeof fileOrInput.size === 'number') file = fileOrInput;
    if (field) window.__rcBrandFiles[field] = file;
  } catch (e) {}
};
window.rcSaveCompanyImages = window.rcSaveCompanyImages || async function (ev) {
  try {
    if (ev && ev.preventDefault) ev.preventDefault();
    var el = document.getElementById('rcCompanyImagesForm');
    if (!el) return false;
    var fd = new FormData();
    fd.append('action', 'save_company_brand');
    var st = window.__DASHBOARD_STATE__ || {};
    var lbgEl = el.querySelector('[name=logo_bg]');
    var lbg = (lbgEl && lbgEl.value) ? lbgEl.value : 'auto';
    fd.append('logo_bg', lbg);
    var csrf = (window.APP && window.APP.csrf) || window.CSRF_TOKEN || st.csrf || '';
    if (csrf) { fd.append('csrf_token', csrf); fd.append('csrf', csrf); }

    var nFiles = 0;
    var queued = [];
    ['logo', 'favicon', 'cover'].forEach(function (n) {
      var file = (window.__rcBrandFiles && window.__rcBrandFiles[n]) || null;
      if (!file) {
        var inp = el.querySelector('input[type=file][name="' + n + '"]')
               || el.querySelector('input[type=file][data-brand="' + n + '"]');
        if (inp && inp.files && inp.files[0]) file = inp.files[0];
      }
      if (file && file.size > 0) {
        fd.append(n, file, file.name || (n + '.bin'));
        nFiles++;
        queued.push(n + ': ' + (file.name || '?') + ' (' + file.size + ' bytes)');
      }
    });

    if (nFiles === 0) {
      alert('No image files are queued.\n\nUse Browse on Primary logo, Site icon, and/or Social preview — you should see “Selected: filename” under each — then click Save images.');
      return false;
    }

    try {
      var logoFile = window.__rcBrandFiles && window.__rcBrandFiles.logo;
      if (logoFile && window.rcRasterLogoToPng) {
        var r = await window.rcRasterLogoToPng(logoFile, 1120, 448, lbg);
        if (r && r.blob) {
          fd.append('logo_sig', r.blob, 'company-logo.sig.png');
          if (lbg === 'auto' && r.plate) { lbg = r.plate; fd.set('logo_bg', lbg); }
        }
      }
    } catch (e) { console.warn('logo_sig', e); }

    var btn = el.querySelector('button[type=submit]');
    if (btn) { btn.disabled = true; btn.dataset._t = btn.textContent; btn.textContent = 'Saving ' + nFiles + '…'; }

    var res = await fetch((document.querySelector('base') && document.querySelector('base').href) || 'index.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
    });
    var t = await res.text();
    var j = {};
    try { j = JSON.parse(t); } catch (e) {
      throw new Error((t || '').replace(/<[^>]+>/g, ' ').slice(0, 300) || ('HTTP ' + res.status));
    }
    if (j.status === 'success' && (!j.saved || !Object.keys(j.saved).length) && nFiles > 0) {
      throw new Error('Server stored no files. ' + (j.message || '') + (j.received ? (' Received: ' + j.received) : '') + (j.errors && j.errors.length ? (' — ' + j.errors.join('; ')) : ''));
    }
    if (j.status === 'success' && j.saved && Object.keys(j.saved).length) {
      // OK — files on disk
      window.__rcBrandFiles = { logo: null, favicon: null, cover: null };
      alert('Saved: ' + Object.keys(j.saved).join(', '));
      location.href = location.pathname + '?tab=company&_=' + Date.now();
    } else if (j.status === 'success') {
      // Server got no files — do not pretend success
      alert('Server received no image files.\nQueued client-side:\n' + queued.join('\n') +
            (j.received ? ('\nServer received: ' + j.received) : '') +
            '\n\nTry a smaller PNG, or check PHP upload_max_filesize / post_max_size.');
      if (btn) { btn.disabled = false; btn.textContent = btn.dataset._t || 'Save images'; }
    } else {
      alert((j.message || 'Save failed') + (j.received ? ('\nReceived: ' + j.received) : '') +
            (j.errors && j.errors.length ? ('\n' + j.errors.join('\n')) : ''));
      if (btn) { btn.disabled = false; btn.textContent = btn.dataset._t || 'Save images'; }
    }
  } catch (e) {
    alert(String(e.message || e));
    var b = document.querySelector('#rcCompanyImagesForm button[type=submit]');
    if (b) { b.disabled = false; if (b.dataset._t) b.textContent = b.dataset._t; }
  }
  return false;
};
</script>

<form id="rcCompanyImagesForm" class="grid grid-cols-1 md:grid-cols-2 gap-6" enctype="multipart/form-data" onsubmit="return window.rcSaveCompanyImages(event)">
        <div>
          <?php
            $iuField = 'logo';
            $iuCurrent = (string)(($viewData['company']['logo'] ?? '') ?: '');
            $iuLabel = 'Primary logo';
            $iuId = 'company_logo';
            require BASE_PATH . '/app/views/partials/image_upload.php';
          ?>
        </div>
        <div>
          <?php
            $iuField = 'favicon';
            $iuCurrent = (string)(($viewData['company']['favicon'] ?? '') ?: '');
            $iuLabel = 'Site icon';
            $iuId = 'company_favicon';
            require BASE_PATH . '/app/views/partials/image_upload.php';
          ?>
        </div>
        
        <div class="md:col-span-2">
          <?php
            $iuField = 'cover';
            $iuCurrent = (string)(($viewData['company']['cover'] ?? '') ?: '');
            $iuLabel = 'Social preview image';
            $iuId = 'company_cover';
            require BASE_PATH . '/app/views/partials/image_upload.php';
          ?>
        </div>
        <div class="md:col-span-2">
          <label class="block text-xs font-bold text-slate-600 mb-1">Primary logo background plate</label>
          <select name="logo_bg" class="h-9 px-2 border border-slate-200 rounded-lg text-sm bg-white">
            <?php $__lbg = strtolower((string)($viewData['company']['logo_bg'] ?? 'auto')); ?>
            <option value="auto" <?= ($__lbg === 'auto' || $__lbg === '') ? 'selected' : '' ?>>Auto (detect from logo)</option>
            <option value="light" <?= $__lbg === 'light' ? 'selected' : '' ?>>Light plate</option>
            <option value="dark" <?= $__lbg === 'dark' ? 'selected' : '' ?>>Dark plate</option>
          </select>
          <p class="text-[11px] text-slate-500 mt-1">Keeps white-ink logos readable. Auto samples the logo once on upload.</p>
        </div>

        <div class="md:col-span-2">
          <button type="submit" class="h-9 px-4 rounded-lg bg-blue-600 text-white text-xs font-bold">Save images</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">Organization Setup</h1>

    <?php
      $__logoFile = (string)($viewData['company']['logo'] ?? '');
      $__isSvgLogo = $__logoFile !== '' && preg_match('/\.svgz?$/i', $__logoFile);
      $__hasRaster = false;
      if ($__isSvgLogo && defined('IMG_PATH')) {
        foreach (['company-logo.sig.png','company-logo.png','company-logo.webp'] as $__r) {
          if (is_file(rtrim(IMG_PATH,'/\\') . '/' . $__r)) { $__hasRaster = true; break; }
        }
      }
      if ($__isSvgLogo && !$__hasRaster):
    ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-sm px-4 py-3 mb-4">
      SVG logos cannot be used in emails / app icon on this server without a PNG sibling — upload a PNG as well, or ensure Imagick is available so a raster copy is created on save.
    </div>
    <?php endif; ?>

            <p class="text-slate-500 font-medium text-sm mt-1">Manage your central brand identity, digital presence, and authority assets.</p>
        </div>
        
        <?php if($isAdmin): ?>
        <div class="shrink-0 w-full sm:w-auto flex flex-col sm:items-end gap-2">
            <button 
                type="button" 
                onclick="(function(){
                    const c = Object.assign({}, window.__DASHBOARD_STATE__.company);
                    // Normalise: ensure location_id is always populated regardless of which key was saved
                    c.location_id = c.location_id || c.location || c.hq_location || c.hq || '';
                    window.openModalEditor(true, 'company', c);
                })()" 
                class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold transition shadow-lg hover:shadow-blue-500/30 transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                <i class="fa-solid fa-sliders"></i> Edit Configuration
            </button>
            <div class="text-[11px] text-slate-500"
                 x-data="{ key: (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.company && (window.__DASHBOARD_STATE__.company.google_maps_api_key || window.__DASHBOARD_STATE__.company.maps_api_key)) || '' }">
                <span class="font-bold text-slate-600">Maps API:</span>
                <span x-show="key" class="text-emerald-600 font-semibold">configured</span>
                <span x-show="!key" class="text-amber-600 font-semibold">not set — add under Edit Configuration</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden relative group">
        
        <div class="h-32 bg-gradient-to-b from-slate-200 to-slate-100 w-full relative border-b border-slate-200">
            <template x-if="company.cover">
                <img :src="'/images/' + company.cover + '?v=' + ts" class="w-full h-full object-cover opacity-50 mix-blend-overlay">
            </template>
        </div>
        
        <div class="p-6 md:p-8 pt-0 relative z-10 flex flex-col md:flex-row gap-6 md:gap-8 items-center md:items-start text-center md:text-left">
            
            <div class="-mt-8 md:-mt-10 shrink-0 relative">
                <div class="max-w-[160px] max-h-[80px] bg-white rounded-2xl border border-slate-100 p-2 flex items-center justify-center shadow-sm">
                    <template x-if="company.logo">
                        <img :src="'/images/' + company.logo + '?v=' + ts" class="max-w-full max-h-full object-contain bg-transparent" alt="Primary logo">
                    </template>
                    <template x-if="!company.logo">
                        <span class="text-3xl font-black text-slate-300" x-text="company.name ? company.name.charAt(0).toUpperCase() : 'C'"></span>
                    </template>
                </div>
            </div>
            
            <div class="flex-1 w-full mt-2 md:mt-4">
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight break-words leading-tight" style="text-wrap: balance;" x-text="company.name || 'Setup Your Organization'"></h2>
                
                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-left">
                    <div class="flex items-center gap-3 text-sm text-slate-600 bg-slate-50 px-4 py-3 rounded-xl border border-slate-100">
                        <i class="fa-solid fa-globe text-blue-500 w-4 text-center text-lg opacity-80"></i>
                        <div class="min-w-0 flex-1">
                            <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1.5">Website</div>
                            <a x-show="company.website" :href="company.website" target="_blank" class="hover:text-blue-600 font-semibold truncate block leading-none" x-text="company.website"></a>
                            <span x-show="!company.website" class="italic opacity-50 block leading-none text-xs">Not Set</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 text-sm text-slate-600 bg-slate-50 px-4 py-3 rounded-xl border border-slate-100">
                        <i class="fa-solid fa-phone text-emerald-500 w-4 text-center text-lg opacity-80"></i>
                        <div class="min-w-0 flex-1">
                            <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1.5">Phone</div>
                            <span x-show="company.phone" class="font-semibold font-mono tracking-wide truncate block leading-none" x-text="company.phone"></span>
                            <span x-show="!company.phone" class="italic opacity-50 block leading-none text-xs">Not Set</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-sm text-slate-600 bg-slate-50 px-4 py-3 rounded-xl border border-slate-100">
                        <i class="fa-solid fa-envelope text-amber-500 w-4 text-center text-lg opacity-80"></i>
                        <div class="min-w-0 flex-1">
                            <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1.5">Email</div>
                            <a x-show="company.email" :href="'mailto:'+company.email" class="hover:text-amber-600 font-semibold truncate block leading-none" x-text="company.email"></a>
                            <span x-show="!company.email" class="italic opacity-50 block leading-none text-xs">Not Set</span>
                        </div>
                    </div>

                    <!-- HQ Location: resolve location_id | location | hq_location | hq -->
                    <div class="flex items-center gap-3 text-sm text-slate-600 bg-slate-50 px-4 py-3 rounded-xl border border-slate-100">
                        <i class="fa-solid fa-location-dot text-rose-500 w-4 text-center text-lg opacity-80"></i>
                        <div class="min-w-0 flex-1"
                             x-data="{ hqId: company.location_id || company.location || company.hq_location || company.hq || '' }">
                            <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1.5">HQ Location</div>
                            <span x-show="hqId"
                                  class="font-semibold truncate block leading-none"
                                  x-text="resolveName('locations', hqId)"></span>
                            <span x-show="!hqId"
                                  class="italic opacity-50 block leading-none text-xs">Not Set</span>
                            <!-- Debug hint: shown only when a value is stored but fails to resolve -->
                            <span x-show="hqId && resolveName('locations', hqId) === '-'"
                                  class="text-[10px] text-amber-500 font-semibold block leading-none mt-1">
                                <i class="fa-solid fa-triangle-exclamation text-[9px]"></i>
                                ID "<span x-text="hqId"></span>" not found — run Optimise Data
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 md:p-8 flex flex-col h-full">
            <div class="mb-6">
                <h3 class="text-lg font-bold text-slate-800 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-swatchbook text-blue-500"></i> Brand Assets
                </h3>
                <p class="text-xs font-medium text-slate-500 mt-1">Secondary visual elements across public cards and internal UI.</p>
            </div>
            
            <div class="grid grid-cols-2 gap-6 flex-1">
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-5 flex flex-col items-center justify-center text-center">
                    <div class="w-16 h-16 bg-white rounded-xl shadow-sm border border-slate-200 p-2.5 flex items-center justify-center mb-4">
                        <img :src="company.favicon ? '/images/' + company.favicon + '?v=' + ts : 'https://ui-avatars.com/api/?name=F&background=f1f5f9&color=94a3b8'" class="max-w-full max-h-full object-contain">
                    </div>
                    <div class="text-[11px] font-bold text-slate-700 uppercase tracking-widest">Site icon</div>
                    <div class="text-[10px] text-slate-400 mt-1 font-medium">Browser Tab Icon</div>
                </div>
                
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-5 flex flex-col items-center justify-center text-center">
                    <div class="w-full h-16 bg-white rounded-xl shadow-sm border border-slate-200 flex items-center justify-center mb-4 overflow-hidden">
                        <img :src="company.cover ? '/images/' + company.cover + '?v=' + ts : 'https://placehold.co/400x150/f8fafc/cbd5e1?text=No+Cover'" class="w-full h-full object-cover">
                    </div>
                    <div class="text-[11px] font-bold text-slate-700 uppercase tracking-widest">Social Cover</div>
                    <div class="text-[10px] text-slate-400 mt-1 font-medium">OpenGraph Banner</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col h-full">
            <div class="p-6 md:p-8 pb-4 bg-white z-10 relative">
                <h3 class="text-lg font-bold text-slate-800 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-share-nodes text-blue-500"></i> Digital Presence
                </h3>
                <p class="text-xs font-medium text-slate-500 mt-1">Official social channels linked on public assets.</p>
            </div>
            
            <details class="group bg-slate-50 border-t border-slate-100 flex-1 open:bg-white transition-colors" open>
                <summary class="cursor-pointer p-5 text-[10px] font-bold text-slate-500 uppercase tracking-widest hover:text-blue-600 hover:bg-blue-50/50 transition flex items-center justify-between select-none">
                    View Configured Links
                    <i class="fa-solid fa-chevron-down transition-transform duration-300 group-open:rotate-180"></i>
                </summary>
                <div class="p-6 pt-2 space-y-3">
                    
                    <div class="flex items-center gap-4 bg-white border border-slate-200 p-3 rounded-xl shadow-sm hover:border-blue-200 transition">
                        <div class="w-9 h-9 rounded-lg bg-[#0a66c2]/10 text-[#0a66c2] flex items-center justify-center shrink-0"><i class="fa-brands fa-linkedin-in text-lg"></i></div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1">LinkedIn</div>
                            <a x-show="company.social?.linkedin" :href="company.social?.linkedin" target="_blank" class="text-sm font-semibold text-slate-700 hover:text-blue-600 truncate block leading-none" x-text="company.social?.linkedin"></a>
                            <div x-show="!company.social?.linkedin" class="text-[11px] text-slate-400 italic leading-none mt-1">Not configured</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 bg-white border border-slate-200 p-3 rounded-xl shadow-sm hover:border-slate-300 transition">
                        <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center shrink-0"><i class="fa-brands fa-x-twitter text-lg"></i></div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1">X (Twitter)</div>
                            <a x-show="company.social?.twitter" :href="company.social?.twitter" target="_blank" class="text-sm font-semibold text-slate-700 hover:text-blue-600 truncate block leading-none" x-text="company.social?.twitter"></a>
                            <div x-show="!company.social?.twitter" class="text-[11px] text-slate-400 italic leading-none mt-1">Not configured</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 bg-white border border-slate-200 p-3 rounded-xl shadow-sm hover:border-pink-200 transition">
                        <div class="w-9 h-9 rounded-lg bg-[#E1306C]/10 text-[#E1306C] flex items-center justify-center shrink-0"><i class="fa-brands fa-instagram text-lg"></i></div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1">Instagram</div>
                            <a x-show="company.social?.instagram" :href="company.social?.instagram" target="_blank" class="text-sm font-semibold text-slate-700 hover:text-blue-600 truncate block leading-none" x-text="company.social?.instagram"></a>
                            <div x-show="!company.social?.instagram" class="text-[11px] text-slate-400 italic leading-none mt-1">Not configured</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 bg-white border border-slate-200 p-3 rounded-xl shadow-sm hover:border-blue-200 transition">
                        <div class="w-9 h-9 rounded-lg bg-[#1877F2]/10 text-[#1877F2] flex items-center justify-center shrink-0"><i class="fa-brands fa-facebook-f text-lg"></i></div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1">Facebook</div>
                            <a x-show="company.social?.facebook" :href="company.social?.facebook" target="_blank" class="text-sm font-semibold text-slate-700 hover:text-blue-600 truncate block leading-none" x-text="company.social?.facebook"></a>
                            <div x-show="!company.social?.facebook" class="text-[11px] text-slate-400 italic leading-none mt-1">Not configured</div>
                        </div>
                    </div>

                </div>
            </details>
        </div>
        
    </div>
</div>


<?php if (!empty($isAdmin)): ?>
<!-- Enterprise Integrations (moved from dedicated left-pane item) -->
<section class="mt-8 pt-6 border-t border-slate-200 max-w-3xl" id="enterprise-integrations">
  <div class="mb-4">
    <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">Integrations</div>
    <h2 class="text-lg font-extrabold text-slate-800">Enterprise Integrations</h2>
    <p class="text-xs text-slate-500 mt-1">Google Wallet, vehicle registry, WhatsApp, SMTP and related connectors. Configured here so Organizational Configuration stays the single admin hub.</p>
  </div>
  <?php
    $settingsFile = __DIR__ . '/tab_settings.php';
    if (is_file($settingsFile)) {
        require $settingsFile;
    }
  ?>
</section>
<?php endif; ?>

