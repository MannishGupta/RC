<?php
  if (!defined('BASE_PATH')) exit;
  $__photoSpec = class_exists('AppMedia') ? AppMedia::imageSpec('photo') : ['hint' => 'Recommended 400×400 · PNG, JPG, WebP · ≤450 KB', 'accept' => 'image/jpeg,image/png,image/webp', 'maxKB' => 450];
  $__locPhotoSpec = class_exists('AppMedia') ? AppMedia::imageSpec('location_photo') : $__photoSpec;
?>
<?php
// Version: 1.5 — Executive record editor + locations image restore: photo preview/upload, social links, polished layout
if (!defined('BASE_PATH')) {
    exit;
}
?>
<style id="rc-record-editor-css">
#enterprise-editor-root {
  position: fixed; inset: 0; z-index: 200;
  display: flex; /* Alpine x-show toggles visibility; do NOT use display:none here */
  align-items: flex-start; justify-content: center; padding: 0.75rem;
  background: rgba(15, 23, 42, 0.58); backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px); overflow-y: auto;
  -webkit-overflow-scrolling: touch; box-sizing: border-box;
}
#enterprise-editor-root[x-cloak] { display: none !important; }
#enterprise-editor-root .rc-ed-foot { position: relative; z-index: 5; pointer-events: auto; }
#enterprise-editor-root .rc-ed-btn, #enterprise-editor-root .rc-ed-btn-primary, #enterprise-editor-root .rc-ed-close {
  position: relative; z-index: 6; pointer-events: auto; -webkit-tap-highlight-color: transparent;
}
#enterprise-editor-root .rc-ed-panel {
  position: relative; width: 100%; max-width: 44rem; margin: 0.5rem auto;
  background: #fff; border-radius: 1.125rem;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,.4);
  display: flex; flex-direction: column;
  max-height: min(92vh, 900px); overflow: hidden; box-sizing: border-box;
  border: 1px solid rgba(226,232,240,.9);
}
#enterprise-editor-root .rc-ed-head {
  flex: 0 0 auto; min-height: 3.5rem; display: flex; align-items: center;
  justify-content: space-between; gap: 0.75rem; padding: 0.75rem 1.15rem;
  border-bottom: 1px solid #eef2f7;
  background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
}
#enterprise-editor-root .rc-ed-body {
  flex: 1 1 auto; min-height: 0; overflow-x: hidden; overflow-y: auto;
  -webkit-overflow-scrolling: touch; padding: 1rem 1.15rem 1.25rem; box-sizing: border-box;
  background: #fafbfc;
}
#enterprise-editor-root .rc-ed-foot {
  flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between;
  gap: 0.5rem; padding: 0.85rem 1.15rem; border-top: 1px solid #eef2f7;
  background: #fff; box-shadow: 0 -6px 16px rgba(15,23,42,.04);
}
#enterprise-editor-root .rc-ed-section {
  background: #fff; border: 1px solid #e8eef5; border-radius: 0.875rem;
  padding: 0.95rem 1rem; margin-bottom: 0.85rem;
  box-shadow: 0 1px 2px rgba(15,23,42,.03);
}
#enterprise-editor-root .rc-ed-section-title {
  display: flex; align-items: center; gap: 0.45rem;
  font-size: 11px; font-weight: 800; letter-spacing: 0.07em;
  text-transform: uppercase; color: #64748b; margin: 0 0 0.85rem;
}
#enterprise-editor-root .rc-ed-section-title i { color: #3b82f6; font-size: 12px; }
#enterprise-editor-root .rc-ed-grid {
  display: grid; grid-template-columns: 1fr; gap: 0.75rem;
  width: 100%; max-width: 100%; box-sizing: border-box;
}
@media (min-width: 640px) {
  #enterprise-editor-root .rc-ed-grid { grid-template-columns: 1fr 1fr; }
  #enterprise-editor-root { align-items: center; padding: 1rem; }
}
#enterprise-editor-root .rc-ed-grid .span-2 { grid-column: 1 / -1; }
#enterprise-editor-root label { display: block; min-width: 0; max-width: 100%; }
#enterprise-editor-root .rc-ed-lbl {
  display: block; font-size: 10px; font-weight: 700; letter-spacing: 0.05em;
  text-transform: uppercase; color: #94a3b8; margin-bottom: 0.3rem;
}
#enterprise-editor-root .rc-ed-input,
#enterprise-editor-root select.rc-ed-input {
  display: block; width: 100%; max-width: 100%; height: 2.55rem;
  padding: 0 0.8rem; border: 1px solid #e2e8f0; border-radius: 0.6rem;
  font-size: 0.875rem; color: #0f172a; background: #fff; outline: none; box-sizing: border-box;
  transition: border-color .15s, box-shadow .15s;
}
#enterprise-editor-root .rc-ed-input:focus,
#enterprise-editor-root select.rc-ed-input:focus {
  border-color: #60a5fa; box-shadow: 0 0 0 3px rgba(96,165,250,.22);
}
#enterprise-editor-root .rc-ed-input.rank {
  font-weight: 700; color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe;
}
#enterprise-editor-root .rc-ed-hint {
  display: block; margin-top: 0.3rem; font-size: 10px; color: #94a3b8;
  line-height: 1.4; word-break: break-word;
}
#enterprise-editor-root .rc-ed-err {
  margin: 0.5rem 0 0; font-size: 0.75rem; color: #e11d48;
  background: #fff1f2; border: 1px solid #fecdd3; border-radius: 0.5rem; padding: 0.5rem 0.7rem;
}
#enterprise-editor-root .rc-ed-btn {
  height: 2.4rem; padding: 0 1.1rem; border-radius: 0.65rem; font-size: 0.78rem;
  font-weight: 700; border: 1px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer;
}
#enterprise-editor-root .rc-ed-btn-primary {
  background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
  border-color: #2563eb; color: #fff;
  box-shadow: 0 4px 12px rgba(37,99,235,.28);
}
#enterprise-editor-root .rc-ed-btn-primary:disabled { opacity: 0.55; cursor: not-allowed; box-shadow: none; }
#enterprise-editor-root .rc-ed-close {
  width: 2.1rem; height: 2.1rem; border-radius: 999px; border: 0; background: #e2e8f0;
  color: #64748b; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
}
#enterprise-editor-root .rc-ed-close:hover { background: #ffe4e6; color: #e11d48; }
#enterprise-editor-root .rc-ed-title {
  font-size: 0.95rem; font-weight: 800; color: #0f172a; min-width: 0;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
#enterprise-editor-root .rc-ed-sub {
  font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 1px;
}
/* Photo block */
#enterprise-editor-root .rc-ed-photo {
  display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap;
}
#enterprise-editor-root .rc-ed-avatar {
  width: 88px; height: 88px; border-radius: 12px; overflow: hidden;
  background: linear-gradient(145deg, #e2e8f0, #f8fafc);
  border: 2px solid #e2e8f0; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  color: #94a3b8; font-size: 1.75rem; font-weight: 800;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
}
#enterprise-editor-root .rc-ed-avatar img {
  width: 100%; height: 100%; object-fit: cover; object-position: center 18%; display: block; border-radius: 0;
  transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
#enterprise-editor-root .rc-ed-avatar:hover img { transform: scale(1.1); }
#enterprise-editor-root .rc-ed-photo-meta { flex: 1; min-width: 12rem; }
#enterprise-editor-root .rc-ed-file {
  display: inline-flex; align-items: center; gap: 0.4rem;
  height: 2.35rem; padding: 0 0.9rem; border-radius: 0.6rem;
  border: 1px dashed #cbd5e1; background: #f8fafc; color: #334155;
  font-size: 0.78rem; font-weight: 700; cursor: pointer;
}
#enterprise-editor-root .rc-ed-file:hover { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
#enterprise-editor-root .rc-ed-file input { display: none; }
#enterprise-editor-root .rc-ed-soc-grid {
  display: grid; grid-template-columns: 1fr; gap: 0.65rem;
}
@media (min-width: 640px) {
  #enterprise-editor-root .rc-ed-soc-grid { grid-template-columns: 1fr 1fr; }
}
#enterprise-editor-root .rc-ed-soc-row {
  display: flex; align-items: center; gap: 0.5rem;
}
#enterprise-editor-root .rc-ed-soc-ico {
  width: 2.2rem; height: 2.2rem; border-radius: 0.55rem;
  display: inline-flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 0.85rem; color: #fff;
}
#enterprise-editor-root .rc-ed-soc-ico.li { background: #0a66c2; }
#enterprise-editor-root .rc-ed-soc-ico.tw { background: #0f172a; }
#enterprise-editor-root .rc-ed-soc-ico.ig { background: linear-gradient(135deg,#f58529,#dd2a7b,#8134af); }
#enterprise-editor-root .rc-ed-soc-ico.fb { background: #1877f2; }
#enterprise-editor-root .rc-ed-soc-ico.yt { background: #ff0000; }
#enterprise-editor-root .rc-ed-soc-ico.web { background: #0ea5e9; }
#enterprise-editor-root .rc-ed-badge {
  display: inline-flex; align-items: center; gap: 0.3rem;
  font-size: 10px; font-weight: 700; padding: 0.2rem 0.5rem;
  border-radius: 999px; background: #eff6ff; color: #1d4ed8;
}
</style>

<div id="enterprise-editor-root"
     x-data="enterpriseEditor"
     x-show="open"
     x-cloak
     x-init="initA11y()"
     @keydown.escape.window="if (open) close()"
     @open-editor.window="openEditor($event.detail)"
     @click.self="close()">
  <div class="rc-ed-panel" @click.stop role="dialog" aria-modal="true" data-rc-focus-trap="1"
       aria-labelledby="rc-ed-dialog-title" aria-describedby="rc-ed-dialog-desc"
       x-ref="panel" tabindex="-1">
    <p id="rc-ed-dialog-desc" class="sr-only">Edit the fields below. Press Escape to discard and close. Commit Transaction saves changes.</p>
    <div class="rc-ed-head">
      <div style="min-width:0">
        <div class="rc-ed-title" id="rc-ed-dialog-title">
          <span x-text="isEdit ? 'Modify Record' : 'Provision Entity'"></span>
          <span x-text="typeLabel"></span>
        </div>
        <div class="rc-ed-sub" x-show="type === 'team' && form.name" x-text="form.name"></div>
      </div>
      <button type="button" class="rc-ed-close" @click.prevent="close()" onclick="window.__rcEditorClose && window.__rcEditorClose()" aria-label="Close dialog">
        <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
      </button>
    </div>

    <div class="rc-ed-body">
      <!-- TEAM -->
      <template x-if="type === 'team'">
        <div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-id-badge"></i> Profile photo</div>
            <div class="rc-ed-photo">
              <div class="rc-ed-avatar">
                <template x-if="photoPreview">
                  <img :src="photoPreview" alt="Preview" @error="$el.style.display='none'">
                </template>
                <template x-if="!photoPreview">
                  <span x-text="(form.name || '?').charAt(0).toUpperCase()"></span>
                </template>
              </div>
              <div class="rc-ed-photo-meta">
                <label class="rc-ed-file">
                  <i class="fa-solid fa-upload"></i>
                  <span x-text="photoFileName || 'Upload image'"></span>
                  <input type="file" accept="<?= htmlspecialchars((string)($__photoSpec['accept'] ?? 'image/jpeg,image/png,image/webp'), ENT_QUOTES, 'UTF-8') ?>" @change="onPhotoPick($event)">
                </label>
                <button type="button" class="rc-ed-btn" style="margin-left:0.4rem;height:2.35rem"
                        x-show="photoPreview || form.photo" @click="clearPhoto()">Clear Image</button>
                <span class="rc-ed-hint"><?= htmlspecialchars((string)($__photoSpec['hint'] ?? 'PNG, JPG, WebP · ≤450 KB'), ENT_QUOTES, 'UTF-8') ?></span>
                <label style="margin-top:0.65rem">
                  <span class="rc-ed-lbl">Or filename in /images</span>
                  <input type="text" class="rc-ed-input" x-model="form.photo" @input="syncPhotoFromName()" placeholder="e.g. personnel-id.jpg">
                </label>
              </div>
            </div>
          </div>

          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-user"></i> Identity</div>
            <div class="rc-ed-grid">
              <label class="span-2">
                <span class="rc-ed-lbl">Full name</span>
                <input type="text" class="rc-ed-input" x-model="form.name" autocomplete="name">
              </label>
              <label>
                <span class="rc-ed-lbl">Profile ID (slug)</span>
                <input type="text" class="rc-ed-input" x-model="form.slug" autocomplete="off">
              </label>
              <label>
                <span class="rc-ed-lbl">Phone</span>
                <input type="text" class="rc-ed-input" x-model="form.phone" autocomplete="tel">
              </label>
              <label>
                <span class="rc-ed-lbl">Email</span>
                <input type="email" class="rc-ed-input" x-model="form.email" autocomplete="email">
              </label>
              <label>
                <span class="rc-ed-lbl">Date of birth</span>
                <input type="date" class="rc-ed-input" x-model="form.dob" title="Used for numerology and Janam Patri">
                <span class="block text-[10px] text-slate-500 mt-0.5">For Vedic Numero / Janam Patri</span>
              </label>
              <label>
                <span class="rc-ed-lbl">Time of birth</span>
                <input type="time" class="rc-ed-input" x-model="form.time_of_birth" step="60" title="Local time of birth">
                <span class="block text-[10px] text-slate-500 mt-0.5">Optional · improves Kundli accuracy</span>
              </label>
              <label class="sm:col-span-2">
                <span class="rc-ed-lbl">Place of birth</span>
                <input type="text" class="rc-ed-input" x-model="form.place_of_birth" placeholder="Delhi, IN" title="City of birth">
                <span class="block text-[10px] text-slate-500 mt-0.5">City / country for Janam Patri</span>
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Birth coordinates (lat, lng)</span>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:stretch">
                  <input type="text" class="rc-ed-input" style="flex:1;min-width:12rem" x-model="form.birth_geo"
                    @blur="parseBirthGeo()"
                    placeholder="28.610000, 77.230000"
                    inputmode="decimal"
                    autocomplete="off"
                    title="Paste Google Maps coordinates as lat, lng — or use Use my location">
                  <button type="button" class="rc-ed-btn" style="white-space:nowrap;padding:0.5rem 0.75rem;font-size:0.75rem;font-weight:700"
                    @click="useMyLocation()" title="Browser geolocation">
                    <i class="fa-solid fa-location-crosshairs"></i> Use my location
                  </button>
                </div>
                <span class="block text-[10px] text-slate-500 mt-0.5">Paste from Google Maps (lat, lng). Manual override anytime. Stored to ~6 decimal places.</span>
              </label>
              <div class="sm:col-span-2 flex flex-wrap gap-2 items-center" x-show="form.dob">
                <a class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-indigo-600 text-white text-[11px] font-bold hover:bg-indigo-700 no-underline"
                   :href="'janam_patri.php?name=' + encodeURIComponent(form.name || '') + '&dob=' + encodeURIComponent(form.dob || '') + '&tob=' + encodeURIComponent(form.time_of_birth || '') + '&pob=' + encodeURIComponent(form.place_of_birth || '')"
                   target="_blank" rel="noopener">
                  <i class="fa-solid fa-om" aria-hidden="true"></i> Open Janam Patri
                </a>
                <a class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-indigo-200 text-indigo-800 text-[11px] font-bold bg-indigo-50 hover:bg-indigo-100 no-underline"
                   href="janam_patri.php?view=match" target="_blank" rel="noopener">
                  <i class="fa-solid fa-heart" aria-hidden="true"></i> Kundli Milan
                </a>
              </div>
              <label>
                <span class="rc-ed-lbl">Gender</span>
                <select class="rc-ed-input" x-model="form.gender">
                  <option value="">—</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </label>
              <label>
                <span class="rc-ed-lbl">Blood group</span>
                <input type="text" class="rc-ed-input" x-model="form.blood_group" placeholder="e.g. B+">
              </label>
              <label class="sm:col-span-2">
                <span class="rc-ed-lbl">Gotra</span>
                <input type="text" class="rc-ed-input" x-model="form.gotra" placeholder="e.g. Kashyap, Bharadwaj, Garg" title="Family gotra for Milan / directory filter">
                <span class="block text-[10px] text-slate-500 mt-0.5">Used for Kundli Milan and directory filters</span>
              </label>
            </div>
          </div>

          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-sitemap"></i> Role &amp; placement</div>
            <div class="rc-ed-grid">
              <label>
                <span class="rc-ed-lbl">Designation</span>
                <select class="rc-ed-input" x-model="form.designation_id" @change="onDesignationChange()">
                  <option value="">— Select —</option>
                  <template x-for="d in designationOptions" :key="String(d.id || d.code || d.name)">
                    <option :value="d.id || d.code" x-text="desigLabel(d)"></option>
                  </template>
                </select>
              </label>
              <label>
                <span class="rc-ed-lbl">Directory order</span>
                <input type="text" class="rc-ed-input rank" readonly
                       :value="form.rank && form.rank < 900 ? ('#' + form.rank + ' from designation') : 'Set designation rank in taxonomy'"
                       title="Auto-derived from designation — not edited per person">
                <span class="rc-ed-hint" x-show="rankHint" x-text="rankHint"></span>
              </label>
              <label>
                <span class="rc-ed-lbl">Department</span>
                <select class="rc-ed-input" x-model="form.department_id">
                  <option value="">— Select —</option>
                  <template x-for="d in departmentOptions" :key="String(d.id || d.code)">
                    <option :value="d.id || d.code" x-text="d.name || d.code"></option>
                  </template>
                </select>
              </label>
              <label>
                <span class="rc-ed-lbl">Location</span>
                <select class="rc-ed-input" x-model="form.location_id">
                  <option value="">— Select —</option>
                  <template x-for="d in locationOptions" :key="String(d.id || d.slug)">
                    <option :value="d.id || d.slug" x-text="d.name || d.slug"></option>
                  </template>
                </select>
              </label>
            </div>
          </div>

          <div class="rc-ed-section">
            <div class="rc-ed-section-title">
              <i class="fa-solid fa-share-nodes"></i> Social links
              <span class="rc-ed-badge" style="margin-left:auto;text-transform:none;letter-spacing:0">optional</span>
            </div>
            <div class="rc-ed-soc-grid">
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico li"><i class="fa-brands fa-linkedin-in"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.linkedin" placeholder="https://linkedin.com/in/…">
              </div>
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico tw"><i class="fa-brands fa-x-twitter"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.twitter" placeholder="https://x.com/…">
              </div>
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico ig"><i class="fa-brands fa-instagram"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.instagram" placeholder="https://instagram.com/…">
              </div>
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico fb"><i class="fa-brands fa-facebook-f"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.facebook" placeholder="https://facebook.com/…">
              </div>
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico yt"><i class="fa-brands fa-youtube"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.youtube" placeholder="https://youtube.com/…">
              </div>
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico web"><i class="fa-solid fa-globe"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.website" placeholder="https://…">
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- DESIGNATIONS -->
      <template x-if="type === 'designations'">
        <div class="rc-ed-section">
          <div class="rc-ed-section-title"><i class="fa-solid fa-list-check"></i> Designation</div>
          <div class="rc-ed-grid">
            <label>
              <span class="rc-ed-lbl">Code</span>
              <input type="text" class="rc-ed-input" x-model="form.code">
            </label>
            <label>
              <span class="rc-ed-lbl">Rank (1–899)</span>
              <input type="number" class="rc-ed-input rank" min="1" max="899" x-model.number="form.rank">
            </label>
            <label class="span-2">
              <span class="rc-ed-lbl">Name</span>
              <input type="text" class="rc-ed-input" x-model="form.name">
            </label>
            <label class="span-2" style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.85rem 0.9rem;border:1px solid #bfdbfe;border-radius:0.75rem;background:#eff6ff;cursor:pointer">
              <input type="checkbox" x-model="form.mandatory_live_tracking" style="width:1.15rem;height:1.15rem;margin-top:0.15rem;accent-color:#2563eb;flex-shrink:0">
              <span>
                <span style="display:block;font-size:0.875rem;font-weight:700;color:#1e3a5f">Mandatory live tracking</span>
                <span class="rc-ed-hint" style="margin:0.25rem 0 0">When ticked, every team member with this designation must share GPS during working hours. Name can be anything (not only Driver / Office Runner).</span>
              </span>
            </label>
          </div>
        </div>
      </template>

      <!-- COMPANY -->
      <template x-if="type === 'company'">
        <div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-building"></i> Organization</div>
            <div class="rc-ed-grid">
              <label class="span-2">
                <span class="rc-ed-lbl">Name</span>
                <input type="text" class="rc-ed-input" x-model="form.name">
              </label>
              <label>
                <span class="rc-ed-lbl">Website</span>
                <input type="text" class="rc-ed-input" x-model="form.website">
              </label>
              <label>
                <span class="rc-ed-lbl">Phone</span>
                <input type="text" class="rc-ed-input" x-model="form.phone">
              </label>
              <label>
                <span class="rc-ed-lbl">Email</span>
                <input type="email" class="rc-ed-input" x-model="form.email">
              </label>
              <label>
                <span class="rc-ed-lbl">Brand color</span>
                <input type="text" class="rc-ed-input" x-model="form.brand_color">
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Google Maps API key</span>
                <input type="text" class="rc-ed-input" x-model="form.google_maps_api_key" placeholder="AIza…" autocomplete="off">
                <span class="rc-ed-hint">Stored in data/company.json · Live Tracking map</span>
              </label>
              <label>
                <span class="rc-ed-lbl">Logo file</span>
                <input type="text" class="rc-ed-input" x-model="form.logo">
              </label>
              <label>
                <span class="rc-ed-lbl">Favicon file</span>
                <input type="text" class="rc-ed-input" x-model="form.favicon">
              </label>
            </div>
          </div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-share-nodes"></i> Company social</div>
            <div class="rc-ed-soc-grid">
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico li"><i class="fa-brands fa-linkedin-in"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.linkedin" placeholder="LinkedIn URL">
              </div>
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico tw"><i class="fa-brands fa-x-twitter"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.twitter" placeholder="X / Twitter URL">
              </div>
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico ig"><i class="fa-brands fa-instagram"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.instagram" placeholder="Instagram URL">
              </div>
              <div class="rc-ed-soc-row">
                <span class="rc-ed-soc-ico fb"><i class="fa-brands fa-facebook-f"></i></span>
                <input type="url" class="rc-ed-input" x-model="form.social.facebook" placeholder="Facebook URL">
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- BANK / TREASURY LEDGER -->
      <template x-if="type === 'bank'">
        <div>
          <div class="rc-ed-section" style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-color:#1e3a5f;color:#e2e8f0;margin-bottom:0.75rem">
            <div class="rc-ed-section-title" style="color:#93c5fd;border:0;margin:0 0 0.5rem"><i class="fa-solid fa-building-columns"></i> Live ledger card</div>
            <div style="font-family:ui-monospace,Menlo,monospace;font-size:0.8rem;line-height:1.55">
              <div style="font-size:0.7rem;letter-spacing:0.08em;text-transform:uppercase;opacity:0.7;margin-bottom:0.25rem" x-text="(form.bank_name || 'Financial institution').toUpperCase()"></div>
              <div style="font-size:1.05rem;font-weight:800;letter-spacing:0.04em;margin-bottom:0.35rem" x-text="maskAcc(form.acc_no) || '•••• •••• ••••'"></div>
              <div style="display:flex;justify-content:space-between;gap:0.75rem;flex-wrap:wrap">
                <span x-text="form.holder_name || 'Account principal'"></span>
                <span style="opacity:0.85" x-text="form.ifsc ? ('IFSC ' + form.ifsc) : 'IFSC —'"></span>
              </div>
              <div style="margin-top:0.35rem;opacity:0.8;font-size:0.75rem" x-show="form.branch || form.upi_id">
                <span x-show="form.branch" x-text="form.branch"></span>
                <span x-show="form.branch && form.upi_id"> · </span>
                <span x-show="form.upi_id" x-text="'UPI ' + form.upi_id"></span>
              </div>
            </div>
          </div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-landmark"></i> Treasury instrument</div>
            <div class="rc-ed-grid">
              <label class="span-2">
                <span class="rc-ed-lbl">Financial institution</span>
                <?php $masterKind = 'banks'; $masterModel = 'form.bank_name'; $masterInputClass = 'rc-ed-input';
            $md = __DIR__ . '/master_dropdown.php';
            if (is_file($md)) { include $md; }
            else { ?>
            <input type="text" class="rc-ed-input" x-model="form.bank_name" placeholder="e.g. HDFC Bank" autocomplete="organization">
            <?php } ?>
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Account principal (beneficiary)</span>
                <input type="text" class="rc-ed-input" x-model="form.holder_name" placeholder="Legal name on the mandate" autocomplete="name">
              </label>
              <label>
                <span class="rc-ed-lbl">Account number</span>
                <input type="text" class="rc-ed-input" x-model="form.acc_no" placeholder="Core banking account no." inputmode="numeric" autocomplete="off">
              </label>
              <label>
                <span class="rc-ed-lbl">IFSC</span>
                <input type="text" class="rc-ed-input" x-model="form.ifsc" placeholder="11-character IFSC" style="text-transform:uppercase" maxlength="11" autocomplete="off">
              </label>
              <label>
                <span class="rc-ed-lbl">Branch / service outlet</span>
                <input type="text" class="rc-ed-input" x-model="form.branch" placeholder="Branch locality or code">
              </label>
              <label>
                <span class="rc-ed-lbl">UPI virtual payment address</span>
                <input type="text" class="rc-ed-input" x-model="form.upi_id" placeholder="name@upi">
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Registry slug <span style="font-weight:500;opacity:0.7">(optional)</span></span>
                <input type="text" class="rc-ed-input" x-model="form.slug" placeholder="Auto-derived if left blank">
                <span class="rc-ed-hint">Stable key for integrations and exports</span>
              </label>
            </div>
          </div>
        </div>
      </template>

      <!-- DOCS / DOCUMENT VAULT -->
      <template x-if="type === 'docs'">
        <div>
          <div class="rc-ed-section" style="background:#f8fafc;border-style:dashed">
            <div class="rc-ed-section-title"><i class="fa-solid fa-eye"></i> Vault preview</div>
            <div style="display:flex;gap:0.85rem;align-items:flex-start">
              <div style="width:3rem;height:3rem;border-radius:0.65rem;background:#eff6ff;color:#1d4ed8;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.15rem">
                <i class="fa-solid" :class="docIconClass()"></i>
              </div>
              <div style="min-width:0;flex:1">
                <div style="font-weight:800;color:#0f172a;font-size:0.95rem" x-text="form.name || form.title || 'Untitled corporate artefact'"></div>
                <div style="font-size:0.75rem;color:#64748b;margin-top:0.2rem" x-text="docPreviewMeta()"></div>
                <template x-if="docPreviewHref()">
                  <a :href="docPreviewHref()" target="_blank" rel="noopener" class="rc-ed-hint" style="display:inline-flex;align-items:center;gap:0.35rem;margin-top:0.45rem;color:#2563eb;font-weight:600;text-decoration:none">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open linked resource
                  </a>
                </template>
                <template x-if="isYouTube(form.external_url)">
                  <div style="margin-top:0.65rem;border-radius:0.65rem;overflow:hidden;border:1px solid #e2e8f0;aspect-ratio:16/9;background:#0f172a">
                    <iframe :src="youTubeEmbed(form.external_url)" title="Document media preview" style="width:100%;height:100%;border:0" allowfullscreen loading="lazy"></iframe>
                  </div>
                </template>
              </div>
            </div>
          </div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-folder-open"></i> Document identity</div>
            <div class="rc-ed-grid">
              <label class="span-2">
                <span class="rc-ed-lbl">Display title</span>
                <input type="text" class="rc-ed-input" x-model="form.name" placeholder="Board pack · Q3 compliance summary" @input="if (!form.title) form.title = form.name">
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Secondary title <span style="font-weight:500;opacity:0.7">(optional)</span></span>
                <input type="text" class="rc-ed-input" x-model="form.title" placeholder="Internal reference name">
              </label>
              <label>
                <span class="rc-ed-lbl">Classification / file type</span>
                <input type="text" class="rc-ed-input" x-model="form.file_type" placeholder="pdf · docx · link · youtube">
              </label>
              <label>
                <span class="rc-ed-lbl">Version label</span>
                <input type="text" class="rc-ed-input" x-model="form.version" placeholder="v1.0 · Final · Draft">
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Vault file name</span>
                <input type="text" class="rc-ed-input" x-model="form.doc_file" placeholder="filename.pdf (under /docs)">
                <span class="rc-ed-hint">Stored artefact path relative to the document vault</span>
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">External resource URL</span>
                <input type="url" class="rc-ed-input" x-model="form.external_url" placeholder="https://… or YouTube share link">
                <span class="rc-ed-hint">Prefer a durable link when the artefact lives outside this platform</span>
              </label>
              <label>
                <span class="rc-ed-lbl">Declared size</span>
                <input type="text" class="rc-ed-input" x-model="form.size" placeholder="e.g. 2.4 MB">
              </label>
              <label>
                <span class="rc-ed-lbl">Last revised</span>
                <input type="text" class="rc-ed-input" x-model="form.updated_at" placeholder="YYYY-MM-DD or free text">
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Registry slug <span style="font-weight:500;opacity:0.7">(optional)</span></span>
                <input type="text" class="rc-ed-input" x-model="form.slug" placeholder="Auto-derived if left blank">
              </label>
            </div>
          </div>
        </div>
      </template>

      <!-- LOCATIONS / PREMISES -->
      <template x-if="type === 'locations'">
        <div>
          <div class="rc-ed-section" style="background:#f8fafc;border-style:dashed">
            <div class="rc-ed-section-title"><i class="fa-solid fa-map-location-dot"></i> Premises card</div>
            <div style="font-weight:800;color:#0f172a" x-text="form.name || 'Unnamed facility'"></div>
            <div style="font-size:0.8rem;color:#64748b;margin-top:0.25rem;line-height:1.45" x-text="[form.address, form.city, form.state, form.pincode].filter(Boolean).join(', ') || 'Complete the address block below'"></div>
            <a x-show="form.map_url" :href="form.map_url" target="_blank" rel="noopener" style="display:inline-flex;margin-top:0.5rem;font-size:0.75rem;font-weight:700;color:#2563eb;text-decoration:none"><i class="fa-solid fa-arrow-up-right-from-square" style="margin-right:0.35rem"></i>Open cartography</a>
          </div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-building"></i> Facility identity</div>
            <div class="rc-ed-grid">
              <label class="span-2"><span class="rc-ed-lbl">Facility name</span><input type="text" class="rc-ed-input" x-model="form.name" placeholder="Head office · Regional hub"></label>
                            <label class="span-2"><span class="rc-ed-lbl">Site logo / image</span>
                <div class="rc-ed-photo" style="margin-top:0.35rem">
                  <div class="rc-ed-avatar" style="width:72px;height:72px;border-radius:12px;overflow:hidden;background:#f1f5f9;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <template x-if="form._logoPreview || form.logo || form.photo">
                      <img :src="form._logoPreview || (form.logo || form.photo ? ('media_serve.php?f=' + encodeURIComponent(String(form.logo || form.photo).replace(/^.*[\\/]/,''))) : '')" alt="" style="width:100%;height:100%;object-fit:contain;background:#fff" @error="$el.style.display='none'">
                    </template>
                    <template x-if="!(form._logoPreview || form.logo || form.photo)">
                      <i class="fa-solid fa-image" style="color:#94a3b8;font-size:1.25rem"></i>
                    </template>
                  </div>
                  <div class="rc-ed-photo-meta">
                    <label class="rc-ed-file" style="display:inline-flex;align-items:center;gap:0.4rem;cursor:pointer;padding:0.45rem 0.85rem;border-radius:0.5rem;background:#0f172a;color:#fff;font-size:0.8rem;font-weight:700">
                      <i class="fa-solid fa-upload"></i>
                      <span x-text="photoFileName || (form.logo || form.photo ? 'Replace image' : 'Upload image')"></span>
                      <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/*" @change="onPhotoPick($event, 'logo')">
                    </label>
                    <button type="button" class="rc-ed-btn-ghost" style="margin-left:0.35rem"
                      x-show="form._logoPreview || form.logo || form.photo || photoFile"
                      @click="clearLocationImage()">Clear image</button>
                    <span class="rc-ed-hint" style="display:block;margin-top:0.4rem">Shown on Shared Locations cards · JPG / PNG / WebP · saved with Commit</span>
                    <input type="text" class="rc-ed-input" style="margin-top:0.4rem" x-model="form.logo"
                      @input="form.photo = form.logo; form._logoPreview = ''"
                      placeholder="Or type existing filename e.g. head-office.webp">
                  </div>
                </div>
              </label>
              <label class="span-2"><span class="rc-ed-lbl">Street address</span><input type="text" class="rc-ed-input" x-model="form.address" placeholder="Plot / street / landmark"></label>
              <label><span class="rc-ed-lbl">City</span><input type="text" class="rc-ed-input" x-model="form.city" placeholder="City"></label>
              <label><span class="rc-ed-lbl">State / UT</span><input type="text" class="rc-ed-input" x-model="form.state" placeholder="State"></label>
              <label><span class="rc-ed-lbl">PIN code</span><input type="text" class="rc-ed-input" x-model="form.pincode" placeholder="6-digit PIN" inputmode="numeric" maxlength="10"></label>
              <label><span class="rc-ed-lbl">Registry slug</span><input type="text" class="rc-ed-input" x-model="form.slug" placeholder="Optional stable key"></label>
              <label class="span-2"><span class="rc-ed-lbl">Maps URL</span><input type="url" class="rc-ed-input" x-model="form.map_url" placeholder="https://maps.google.com/…"></label>
            </div>
          </div>
        </div>
      </template>

      <!-- EVENTS / CALENDAR -->
      <template x-if="type === 'events'">
        <div>
          <div class="rc-ed-section" style="background:linear-gradient(135deg,#eff6ff,#f8fafc);border-color:#bfdbfe">
            <div class="rc-ed-section-title"><i class="fa-solid fa-calendar-day"></i> Observance preview</div>
            <div style="font-weight:800;color:#1e3a5f" x-text="form.name || 'Untitled observance'"></div>
            <div style="font-size:0.8rem;color:#475569;margin-top:0.3rem" x-text="[form.date, form.location, form.is_virtual ? 'Virtual' : ''].filter(Boolean).join(' · ') || 'Schedule details pending'"></div>
          </div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-calendar"></i> Calendar entry</div>
            <div class="rc-ed-grid">
              <label class="span-2"><span class="rc-ed-lbl">Event title</span><input type="text" class="rc-ed-input" x-model="form.name" placeholder="Annual town-hall · Founders day"></label>
              <label><span class="rc-ed-lbl">Date</span><input type="date" class="rc-ed-input" x-model="form.date"></label>
              <label><span class="rc-ed-lbl">Venue / channel</span><input type="text" class="rc-ed-input" x-model="form.location" placeholder="Auditorium · Zoom · City"></label>
              <label class="span-2"><span class="rc-ed-lbl">Brief</span><input type="text" class="rc-ed-input" x-model="form.description" placeholder="One-line executive summary"></label>
              <label class="span-2"><span class="rc-ed-lbl">Resource URL</span><input type="url" class="rc-ed-input" x-model="form.url" placeholder="https://…"></label>
              <label class="span-2" style="display:flex;align-items:center;gap:0.6rem;padding:0.65rem 0.75rem;border:1px solid #e2e8f0;border-radius:0.65rem;background:#f8fafc;cursor:pointer">
                <input type="checkbox" x-model="form.is_virtual" style="width:1.1rem;height:1.1rem;accent-color:#2563eb">
                <span style="font-size:0.85rem;font-weight:600;color:#334155">Virtual attendance</span>
              </label>
            </div>
          </div>
        </div>
      </template>

      <!-- DEPARTMENTS / ORG UNITS -->
      <template x-if="type === 'departments'">
        <div class="rc-ed-section">
          <div class="rc-ed-section-title"><i class="fa-solid fa-sitemap"></i> Organizational unit</div>
          <div class="rc-ed-grid">
            <label><span class="rc-ed-lbl">Unit code</span><input type="text" class="rc-ed-input" x-model="form.code" placeholder="OPS · FIN · HR"></label>
            <label><span class="rc-ed-lbl">Registry slug</span><input type="text" class="rc-ed-input" x-model="form.slug" placeholder="Optional"></label>
            <label class="span-2"><span class="rc-ed-lbl">Unit name</span><input type="text" class="rc-ed-input" x-model="form.name" placeholder="Operations · Finance · People"></label>
          </div>
        </div>
      </template>

      <!-- STATUTORY / COMPLIANCE REGISTER -->
      <template x-if="type === 'statutory'">
        <div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-scale-balanced"></i> Corporate identity</div>
            <div class="rc-ed-grid">
              <label class="span-2"><span class="rc-ed-lbl">Legal entity name</span><input type="text" class="rc-ed-input" x-model="form.company_name" placeholder="As registered with MCA / ROC"></label>
              <label><span class="rc-ed-lbl">CIN</span><input type="text" class="rc-ed-input" x-model="form.cin" placeholder="21-character CIN" style="text-transform:uppercase"></label>
              <label><span class="rc-ed-lbl">Date of incorporation</span><input type="date" class="rc-ed-input" x-model="form.date_of_incorporation"></label>
              <label><span class="rc-ed-lbl">PAN</span><input type="text" class="rc-ed-input" x-model="form.pan" placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase"></label>
              <label><span class="rc-ed-lbl">TAN</span><input type="text" class="rc-ed-input" x-model="form.tan" placeholder="TAN" style="text-transform:uppercase"></label>
              <label><span class="rc-ed-lbl">GSTIN</span><input type="text" class="rc-ed-input" x-model="form.gst" placeholder="15-character GSTIN" style="text-transform:uppercase"></label>
              <label><span class="rc-ed-lbl">LEI</span><input type="text" class="rc-ed-input" x-model="form.lei" placeholder="Legal Entity Identifier"></label>
              <label><span class="rc-ed-lbl">ROC code</span><input type="text" class="rc-ed-input" x-model="form.roc_code" placeholder="ROC jurisdiction"></label>
              <label><span class="rc-ed-lbl">MSME / Udyam</span><input type="text" class="rc-ed-input" x-model="form.msme" placeholder="Udyam registration"></label>
              <label><span class="rc-ed-lbl">DPIIT startup</span><input type="text" class="rc-ed-input" x-model="form.dpiit_startup" placeholder="Recognition number"></label>
              <label><span class="rc-ed-lbl">Bank account (statutory)</span><input type="text" class="rc-ed-input" x-model="form.bank_account" placeholder="Primary compliance account"></label>
              <label><span class="rc-ed-lbl">ESIC</span><input type="text" class="rc-ed-input" x-model="form.esic" placeholder="ESIC code"></label>
              <label><span class="rc-ed-lbl">PF / EPFO</span><input type="text" class="rc-ed-input" x-model="form.pf_code" placeholder="Establishment code"></label>
              <label><span class="rc-ed-lbl">ISIN</span><input type="text" class="rc-ed-input" x-model="form.isin" placeholder="Security ISIN"></label>
              <label><span class="rc-ed-lbl">Demat ID</span><input type="text" class="rc-ed-input" x-model="form.demat_id" placeholder="Demat client ID"></label>
            </div>
          </div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-address-book"></i> Registered contact</div>
            <div class="rc-ed-grid">
              <label><span class="rc-ed-lbl">Phone</span><input type="tel" class="rc-ed-input" x-model="form.phone" placeholder="+91 …"></label>
              <label><span class="rc-ed-lbl">Email</span><input type="email" class="rc-ed-input" x-model="form.email" placeholder="compliance@…"></label>
              <label class="span-2"><span class="rc-ed-lbl">Registered address</span><input type="text" class="rc-ed-input" x-model="form.address" placeholder="Registered office address"></label>
              <label class="span-2"><span class="rc-ed-lbl">Development office</span><input type="text" class="rc-ed-input" x-model="form.development_office" placeholder="Optional"></label>
              <label class="span-2"><span class="rc-ed-lbl">GST sales office</span><input type="text" class="rc-ed-input" x-model="form.gst_sales_office" placeholder="Optional"></label>
            </div>
          </div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-house"></i> RERA (if applicable)</div>
            <div class="rc-ed-grid">
              <label><span class="rc-ed-lbl">RERA ID</span><input type="text" class="rc-ed-input" x-model="form.rera" placeholder="RERA registration"></label>
              <label><span class="rc-ed-lbl">Project name</span><input type="text" class="rc-ed-input" x-model="form.rera_project_name" placeholder="Project"></label>
              <label><span class="rc-ed-lbl">Phase 1</span><input type="text" class="rc-ed-input" x-model="form.rera_phase_1"></label>
              <label><span class="rc-ed-lbl">Phase 2</span><input type="text" class="rc-ed-input" x-model="form.rera_phase_2"></label>
              <label class="span-2"><span class="rc-ed-lbl">Phase 3</span><input type="text" class="rc-ed-input" x-model="form.rera_phase_3"></label>
            </div>
          </div>
        </div>
      </template>

      <!-- LEADS / OPPORTUNITY PIPELINE -->
      <template x-if="type === 'leads'">
        <div class="rc-ed-section">
          <div class="rc-ed-section-title"><i class="fa-solid fa-address-card"></i> Opportunity record</div>
          <div class="rc-ed-grid">
            <label class="span-2"><span class="rc-ed-lbl">Prospect name</span><input type="text" class="rc-ed-input" x-model="form.name" placeholder="Full name"></label>
            <label><span class="rc-ed-lbl">Email</span><input type="email" class="rc-ed-input" x-model="form.email" placeholder="name@domain"></label>
            <label><span class="rc-ed-lbl">Phone</span><input type="tel" class="rc-ed-input" x-model="form.phone" placeholder="+91 …"></label>
            <label><span class="rc-ed-lbl">Pipeline status</span>
              <select class="rc-ed-input" x-model="form.status">
                <option value="new">New</option>
                <option value="contacted">Contacted</option>
                <option value="qualified">Qualified</option>
                <option value="closed">Closed</option>
              </select>
            </label>
            <label><span class="rc-ed-lbl">Source / channel</span><input type="text" class="rc-ed-input" x-model="form.source" placeholder="Card · Web · Referral"></label>
            <label class="span-2"><span class="rc-ed-lbl">Notes</span><input type="text" class="rc-ed-input" x-model="form.notes" placeholder="Executive note"></label>
          </div>
        </div>
      </template>

      
      <!-- MEDIA KIT ASSET -->
      <template x-if="type === 'mediakit'">
        <div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-photo-film"></i> Media asset</div>
            <div class="rc-ed-grid">
              <label class="span-2">
                <span class="rc-ed-lbl">Display name</span>
                <input type="text" class="rc-ed-input" x-model="form.name" placeholder="e.g. Fusion Homes hero · Brand logo" autocomplete="off">
              </label>
              <label>
                <span class="rc-ed-lbl">Category</span>
                <select class="rc-ed-input" x-model="form.category">
                  <option value="promo">Promotional creatives</option>
                  <option value="logo">Logos (SVG)</option>
                  <option value="print">Print (high-res)</option>
                  <option value="whatsapp">WhatsApp status</option>
                  <option value="social">Social posts</option>
                  <option value="video">Video / Reels</option>
                  <option value="other">Other</option>
                </select>
              </label>
              <label>
                <span class="rc-ed-lbl">Platform</span>
                <input type="text" class="rc-ed-input" x-model="form.platform" placeholder="Instagram · LinkedIn · Print · All">
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Caption / notes</span>
                <textarea class="rc-ed-input" rows="3" x-model="form.caption" placeholder="Share text · WhatsApp-style *bold* _italic_"></textarea>
              </label>
              <label class="span-2">
                <span class="rc-ed-lbl">Notes (internal)</span>
                <input type="text" class="rc-ed-input" x-model="form.notes" placeholder="Optional internal note">
              </label>
            </div>
          </div>
          <div class="rc-ed-section">
            <div class="rc-ed-section-title"><i class="fa-solid fa-cloud-arrow-up"></i> File</div>
            <div class="rc-ed-photo">
              <div class="rc-ed-avatar" style="width:5.5rem;height:5.5rem;border-radius:0.75rem;overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;border:1px solid #e2e8f0">
                <template x-if="photoPreview || form.photo || form.file">
                  <img :src="photoPreview || (form.photo || form.file ? ('/media_serve.php?f=' + encodeURIComponent(String(form.photo || form.file).replace(/^.*[\\/]/,''))) : '')" alt="" style="width:100%;height:100%;object-fit:contain" @error="$el.style.display='none'">
                </template>
                <template x-if="!(photoPreview || form.photo || form.file)">
                  <i class="fa-solid fa-file-image" style="font-size:1.5rem;color:#94a3b8"></i>
                </template>
              </div>
              <div class="rc-ed-photo-meta">
                <label class="rc-ed-btn-ghost" style="display:inline-flex;align-items:center;gap:0.35rem;cursor:pointer">
                  <i class="fa-solid fa-upload"></i>
                  <span x-text="photoFileName || ((form.photo || form.file) ? 'Replace file' : 'Upload file')"></span>
                  <input type="file" class="hidden" accept="image/*,.svg,.pdf,.mp4,.webm,.mov,image/svg+xml,image/webp,application/pdf"
                         @change="onPhotoPick($event, 'photo')">
                </label>
                <button type="button" class="rc-ed-btn-ghost" x-show="photoPreview || form.photo || form.file || photoFile" @click="clearPhoto(); form.file=''; form.photo=''">Clear</button>
                <span class="rc-ed-hint">PNG, JPG, WebP, SVG, PDF, MP4 · up to 12 MB · server optimises images</span>
                <div class="rc-ed-hint" x-show="form.file || form.photo" style="margin-top:0.25rem">
                  Stored as: <code x-text="form.file || form.photo"></code>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>


      <!-- CAR TAGS (fallback if global modal used) -->
      <template x-if="type === 'cartags'">
        <div class="rc-ed-section">
          <div class="rc-ed-section-title"><i class="fa-solid fa-car"></i> Fleet asset</div>
          <div class="rc-ed-grid">
            <label><span class="rc-ed-lbl">Tag ID</span><input type="text" class="rc-ed-input" x-model="form.tag_id" placeholder="Internal tag"></label>
            <label><span class="rc-ed-lbl">Registration</span><input type="text" class="rc-ed-input" x-model="form.registration_number" placeholder="MH12AB1234" style="text-transform:uppercase"></label>
            <label><span class="rc-ed-lbl">Plate / alias</span><input type="text" class="rc-ed-input" x-model="form.plate" placeholder="Display plate"></label>
            <label><span class="rc-ed-lbl">Class</span><input type="text" class="rc-ed-input" x-model="form.vehicle_class" placeholder="SUV · Sedan · Two-wheeler"></label>
            <label class="span-2"><span class="rc-ed-lbl">Make / model</span><input type="text" class="rc-ed-input" x-model="form.make_model" placeholder="Maruti Swift · Toyota Innova"></label>
            <label><span class="rc-ed-lbl">Colour</span><input type="text" class="rc-ed-input" x-model="form.colour" placeholder="Colour"></label>
            <label><span class="rc-ed-lbl">Owner / custodian</span><input type="text" class="rc-ed-input" x-model="form.owner_name" placeholder="Name"></label>
            <label class="span-2"><span class="rc-ed-lbl">Linked member ID</span><input type="text" class="rc-ed-input" x-model="form.member_id" placeholder="Optional HCI personnel id"></label>
          </div>
        </div>
      </template>

      <!-- GENERIC residual -->
      <template x-if="!['team','designations','company','bank','docs','locations','events','departments','statutory','leads','cartags','mediakit'].includes(type)">
        <div class="rc-ed-section">
          <div class="rc-ed-section-title"><i class="fa-solid fa-pen-to-square"></i> Entity attributes</div>
          <div class="rc-ed-grid">
            <template x-for="key in genericKeys" :key="key">
              <label :class="key.length > 12 ? 'span-2' : ''">
                <span class="rc-ed-lbl" x-text="prettyKey(key)"></span>
                <input type="text" class="rc-ed-input" x-model="form[key]" :placeholder="'Enter ' + prettyKey(key).toLowerCase()">
              </label>
            </template>
            <p class="span-2 rc-ed-hint" x-show="!genericKeys.length" style="margin:0">No attribute schema for this entity. Use Data Ingestion or contact platform administration.</p>
          </div>
        </div>
      </template>

      <p class="rc-ed-err text-sm font-bold text-rose-900 bg-rose-50 border-2 border-dashed border-rose-600 rounded-lg px-3 py-2" role="alert" x-show="error" x-text="error"></p>
    </div>

    <div class="rc-ed-foot">
      <span class="rc-ed-hint" style="margin:0" x-show="type === 'team'">Photo + social saved with the personnel record</span>
      <span class="rc-ed-hint" style="margin:0" x-show="type === 'bank'">Treasury instruments appear on the Banking Ledger after commit</span>
      <span class="rc-ed-hint" style="margin:0" x-show="type === 'docs'">Vault artefacts are queryable from the Document Vault index</span>
      <span class="rc-ed-hint" style="margin:0" x-show="type === 'locations'">Premises feed Human Capital location assignments</span>
      <span class="rc-ed-hint" style="margin:0" x-show="type === 'events'">Observances surface on the Corporate Calendar</span>
      <span class="rc-ed-hint" style="margin:0" x-show="type === 'statutory'">Identifiers should match MCA / GST / EPFO source documents</span>
      <span x-show="!['team','bank','docs','locations','events','statutory'].includes(type)"></span>
      <div style="display:flex;gap:0.5rem">
        <button type="button" class="rc-ed-btn" @click.prevent="close()" onclick="window.__rcEditorClose && window.__rcEditorClose()">Discard Changes</button>
        <button type="button" class="rc-ed-btn rc-ed-btn-primary" @click.prevent="save()" onclick="window.__rcEditorSave && window.__rcEditorSave()" :disabled="saving">
          <span x-text="saving ? 'Committing…' : 'Commit Transaction'"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  function emptySocial() {
    return { linkedin: '', twitter: '', instagram: '', facebook: '', youtube: '', website: '' };
  }
  function normalizeSocial(s) {
    var o = emptySocial();
    if (!s) return o;
    if (typeof s === 'string') {
      try { s = JSON.parse(s); } catch (e) { return o; }
    }
    if (typeof s !== 'object') return o;
    ['linkedin','twitter','instagram','facebook','youtube','website'].forEach(function (k) {
      o[k] = (s[k] != null && String(s[k]).trim() !== '') ? String(s[k]).trim() : '';
    });
    return o;
  }
  function photoUrl(name) {
    if (!name) return '';
    var n = String(name).trim();
    if (/^https?:/i.test(n) || n.indexOf('data:') === 0) return n;
    n = n.replace(/^.*[\\\/]/, '');
    return 'media_serve.php?f=' + encodeURIComponent(n) + '&v=' + Date.now();
  }

  function factory() {
    return {
      open: false, isEdit: false, type: 'team', form: {}, saving: false, error: '', rankHint: '',
      _focusTrap: null, _returnFocus: null,
      photoFile: null, photoFileName: '', photoPreview: '',

      parseBirthGeo() {
        var raw = String(this.form.birth_geo || '').trim();
        if (!raw) {
          if (this.form.birth_lat != null && this.form.birth_lng != null && this.form.birth_lat !== '' && this.form.birth_lng !== '') {
            var la = Number(this.form.birth_lat), ln = Number(this.form.birth_lng);
            if (isFinite(la) && isFinite(ln)) {
              la = Math.round(la * 1e6) / 1e6;
              ln = Math.round(ln * 1e6) / 1e6;
              this.form.birth_lat = la;
              this.form.birth_lng = ln;
              this.form.birth_geo = la + ', ' + ln;
            }
          }
          return;
        }
        var m = raw.match(/(-?\d+(?:\.\d+)?)\s*[,\s]+\s*(-?\d+(?:\.\d+)?)/);
        if (!m) return;
        var lat = Number(m[1]), lng = Number(m[2]);
        if (!isFinite(lat) || !isFinite(lng)) return;
        if (lat < -90) lat = -90; if (lat > 90) lat = 90;
        if (lng < -180) lng = -180; if (lng > 180) lng = 180;
        lat = Math.round(lat * 1e6) / 1e6;
        lng = Math.round(lng * 1e6) / 1e6;
        this.form.birth_lat = lat;
        this.form.birth_lng = lng;
        this.form.birth_geo = lat + ', ' + lng;
      },
      useMyLocation() {
        var self = this;
        if (!navigator.geolocation) {
          alert('Geolocation is not available in this browser.');
          return;
        }
        navigator.geolocation.getCurrentPosition(function (pos) {
          var lat = Math.round(pos.coords.latitude * 1e6) / 1e6;
          var lng = Math.round(pos.coords.longitude * 1e6) / 1e6;
          self.form.birth_lat = lat;
          self.form.birth_lng = lng;
          self.form.birth_geo = lat + ', ' + lng;
        }, function (err) {
          alert('Could not get location: ' + (err && err.message ? err.message : 'permission denied'));
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
      },

      get typeLabel() {
        var m = { team: 'Personnel Record', designations: 'Role Taxonomy Entry', departments: 'Organizational Unit', bank: 'Treasury Instrument', locations: 'Premises Record', events: 'Calendar Observance', docs: 'Vault Artefact', cartags: 'Fleet Asset', company: 'Organizational Configuration', statutory: 'Compliance Register Entry', leads: 'Opportunity Record', mediakit: 'Media Kit Asset' };
        return m[this.type] || this.type;
      },
      get designationOptions() {
        var d = this.dashData(), rows = (d && d.designations) ? d.designations.slice() : [], self = this;
        rows.sort(function (a, b) {
          var ra = self.desigRank(a), rb = self.desigRank(b);
          if (ra !== rb) return ra - rb;
          return String(a.name || '').localeCompare(String(b.name || ''));
        });
        return rows;
      },
      get departmentOptions() { var d = this.dashData(); return Array.isArray(d.departments) ? d.departments : []; },
      get locationOptions() { var d = this.dashData(); return Array.isArray(d.locations) ? d.locations : []; },
      get genericKeys() {
        var skip = { id: 1, social: 1 };
        return Object.keys(this.form || {}).filter(function (k) { return !skip[k] && typeof this.form[k] !== 'object'; }, this);
      },
      dashData() {
        try {
          var el = document.querySelector('[x-data="dashboardApp"]');
          if (el && window.Alpine) { var data = Alpine.$data(el); if (data && data.data) return data.data; }
        } catch (e) {}
        return (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.data) || {};
      },
      desigRank(d) {
        if (!d) return 9999;
        var r = Number(d.rank); if (!isNaN(r) && r > 0 && r < 900) return r;
        var h = Number(d.hierarchy_rank); if (!isNaN(h) && h > 0 && h < 900) return h;
        if (/^\d+$/.test(String(d.code || '').trim())) return Number(d.code);
        return 9999;
      },
      desigLabel(d) {
        var name = d.name || d.code || '—', r = this.desigRank(d);
        return r < 900 ? (name + ' · rank ' + r) : name;
      },
      normalizeRank(raw, item) {
        var n = Number(raw); if (!isNaN(n) && n > 0 && n < 900) return n;
        if (item && item.hierarchy_rank) { var h = Number(item.hierarchy_rank); if (!isNaN(h) && h > 0 && h < 900) return h; }
        return '';
      },

      onPhotoPick(ev, field) {
        var f = ev.target && ev.target.files && ev.target.files[0];
        if (!f) return;
        if (this.type !== 'mediakit' && !/^image\//.test(f.type) && f.type !== 'image/svg+xml') {
          this.error = 'Please choose an image file'; return;
        }
        if (this.type === 'mediakit' && f.size > 12 * 1024 * 1024) {
          this.error = 'File must be ≤ 12 MB'; return;
        }
        this.photoFile = f;
        this.photoFileName = f.name;
        this._photoField = field || 'photo';
        var self = this;
        var reader = new FileReader();
        reader.onload = function (e) {
          if (self._photoField === 'logo') {
            self.form._logoPreview = e.target.result;
          } else {
            self.photoPreview = e.target.result;
          }
        };
        reader.readAsDataURL(f);
      },
      clearPhoto() {
        this.photoFile = null;
        this.photoFileName = '';
        this.photoPreview = '';
        this.form.photo = '';
      },
      syncPhotoFromName() {
        if (this.photoFile) return;
        this.photoPreview = photoUrl(this.form.photo);
      },


      initA11y() {
        var self = this;
        this.$watch('open', function (val) {
          if (val) {
            self.$nextTick(function () { self.activateFocusTrap(); });
          } else {
            self.deactivateFocusTrap();
          }
        });
      },
      activateFocusTrap() {
        this.deactivateFocusTrap();
        if (!window.RcFocusTrap) return;
        var panel = this.$refs.panel || document.querySelector('#enterprise-editor-root .rc-ed-panel');
        this._focusTrap = window.RcFocusTrap.activate(panel, { returnFocus: this._returnFocus });
      },
      deactivateFocusTrap() {
        if (this._focusTrap && typeof this._focusTrap.deactivate === 'function') {
          this._focusTrap.deactivate();
        }
        this._focusTrap = null;
      },

      openEditor(detail) {
        this._returnFocus = document.activeElement;
        this.error = ''; this.rankHint = '';
        this.photoFile = null; this.photoFileName = ''; this.photoPreview = '';
        this.isEdit = !!(detail && detail.isEdit);
        this.type = (detail && detail.type) ? detail.type : 'team';
        var item = {};
        try { item = (detail && detail.item) ? JSON.parse(JSON.stringify(detail.item)) : {}; }
        catch (e) { item = (detail && detail.item) || {}; }

        if (this.type === 'team') {
          this.form = {
            id: item.id || '', name: item.name || '', slug: item.slug || '',
            phone: item.phone || item.mobile || '', email: item.email || '',
            designation_id: item.designation_id || item.designation_code || '',
            department_id: item.department_id || item.department_code || '',
            location_id: item.location_id || '', dob: item.dob || '', time_of_birth: item.time_of_birth || '', place_of_birth: item.place_of_birth || (item.id || item.slug ? '' : 'Delhi, IN'), gotra: item.gotra || '', gender: item.gender || '', birth_lat: item.birth_lat || item.lat || (item.id || item.slug ? '' : '28.610000'), birth_lng: item.birth_lng || item.lng || (item.id || item.slug ? '' : '77.230000'), birth_geo: item.birth_geo || ((item.birth_lat || item.lat) && (item.birth_lng || item.lng) ? String(item.birth_lat || item.lat) + ', ' + String(item.birth_lng || item.lng) : (item.id || item.slug ? '' : '28.610000, 77.230000')),
            blood_group: item.blood_group || '', photo: item.photo || '',
            rank: this.normalizeRank(item.rank, item),
            social: normalizeSocial(item.social)
          };
          this.photoPreview = photoUrl(this.form.photo);
          if (!this.form.rank || this.form.rank >= 900) this.onDesignationChange(true);
        } else if (this.type === 'designations') {
          var r = Number(item.rank); if (!r || r >= 900) r = Number(item.hierarchy_rank) || ''; if (r >= 900) r = '';
          var track = item.mandatory_live_tracking ?? item.require_live_tracking ?? item.live_tracking ?? false;
          if (track === 1 || track === '1' || track === 'true' || track === 'yes' || track === 'on') track = true;
          track = !!track;
          this.form = {
            id: item.id || '', code: item.code || '', name: item.name || '',
            rank: r || '', slug: item.slug || '',
            mandatory_live_tracking: track
          };
        } else if (this.type === 'company') {
          this.form = {
            id: item.id || item.company_id || '', name: item.name || '', website: item.website || '',
            phone: item.phone || '', email: item.email || '', logo: item.logo || '', favicon: item.favicon || '',
            cover: item.cover || '', brand_color: item.brand_color || '#1e3a5f',
            google_maps_api_key: item.google_maps_api_key || item.maps_api_key || '',
            maps_api_key: item.maps_api_key || item.google_maps_api_key || '',
            social: normalizeSocial(item.social)
          };
        } else if (this.type === 'bank') {
          this.form = {
            id: item.id || '',
            slug: item.slug || '',
            bank_name: item.bank_name || '',
            holder_name: item.holder_name || '',
            acc_no: item.acc_no || item.account_no || item.account_number || '',
            ifsc: item.ifsc || '',
            branch: item.branch || '',
            upi_id: item.upi_id || item.upi || ''
          };
        } else if (this.type === 'mediakit') {
          item = item || {};
          this.form = {
            id: item.id || '',
            name: item.name || item.title || '',
            title: item.title || item.name || '',
            category: item.category || 'promo',
            platform: item.platform || '',
            caption: item.caption || '',
            notes: item.notes || '',
            file: item.file || item.photo || item.filename || '',
            photo: item.photo || item.file || item.filename || '',
            file_type: item.file_type || '',
            width: item.width || '',
            height: item.height || '',
            bytes: item.bytes || ''
          };
          this.photoPreview = photoUrl(this.form.photo || this.form.file);
          this.photoFile = null;
          this.photoFileName = '';
        } else if (this.type === 'docs') {
          this.form = {
            id: item.id || '',
            name: item.name || item.title || '',
            title: item.title || item.name || '',
            slug: item.slug || '',
            doc_file: item.doc_file || item.file || item.filename || '',
            file_type: item.file_type || item.type || '',
            size: item.size || '',
            version: item.version || '',
            external_url: item.external_url || item.url || '',
            updated_at: item.updated_at || item.date || ''
          };
        } else if (this.type === 'locations') {
          this.form = {
            id: item.id || '', slug: item.slug || '', name: item.name || '',
            address: item.address || '', city: item.city || '', state: item.state || '',
            pincode: item.pincode || '', map_url: item.map_url || ''
          };
        } else if (this.type === 'events') {
          this.form = {
            id: item.id || '', name: item.name || '', date: item.date || '',
            location: item.location || '', description: item.description || '',
            url: item.url || '', is_virtual: !!(item.is_virtual === true || item.is_virtual === 1 || item.is_virtual === '1')
          };
        } else if (this.type === 'departments') {
          this.form = {
            id: item.id || '', code: item.code || '', name: item.name || '', slug: item.slug || ''
          };
        } else if (this.type === 'statutory') {
          this.form = {
            id: item.id || '',
            company_name: item.company_name || item.name || '',
            cin: item.cin || '', pan: item.pan || '', tan: item.tan || '',
            gst: item.gst || item.gstin || '', lei: item.lei || '', roc_code: item.roc_code || '',
            date_of_incorporation: item.date_of_incorporation || '',
            msme: item.msme || '', dpiit_startup: item.dpiit_startup || '',
            bank_account: item.bank_account || '', esic: item.esic || '', pf_code: item.pf_code || '',
            isin: item.isin || '', demat_id: item.demat_id || '',
            phone: item.phone || '', email: item.email || '', address: item.address || '',
            development_office: item.development_office || '', gst_sales_office: item.gst_sales_office || '',
            rera: item.rera || '', rera_project_name: item.rera_project_name || '',
            rera_phase_1: item.rera_phase_1 || '', rera_phase_2: item.rera_phase_2 || '', rera_phase_3: item.rera_phase_3 || ''
          };
        } else if (this.type === 'leads') {
          this.form = {
            id: item.id || '', name: item.name || '', email: item.email || '', phone: item.phone || '',
            status: item.status || 'new', source: item.source || '', notes: item.notes || item.message || '',
            member_id: item.member_id || '', card_slug: item.card_slug || ''
          };
        } else if (this.type === 'cartags') {
          this.form = {
            id: item.id || '', tag_id: item.tag_id || '',
            registration_number: item.registration_number || item.plate || '',
            plate: item.plate || '', make_model: item.make_model || '',
            colour: item.colour || item.color || '', vehicle_class: item.vehicle_class || '',
            owner_name: item.owner_name || '', member_id: item.member_id || '', photo: item.photo || ''
          };
        } else {
          var defaults = {
            locations: { id: '', slug: '', name: '', address: '', city: '', state: '', pincode: '', map_url: '', logo: '', photo: '' },
            events: { id: '', name: '', date: '', location: '', description: '', url: '', is_virtual: false },
            departments: { id: '', code: '', name: '', slug: '' },
            cartags: { id: '', tag_id: '', registration_number: '', plate: '', make_model: '', colour: '', vehicle_class: '', owner_name: '', member_id: '' },
            statutory: { id: '', company_name: '', cin: '', pan: '', tan: '', gst: '' },
            leads: { id: '', name: '', email: '', phone: '', status: 'new' },
            mediakit: { id: '', name: '', title: '', category: 'promo', platform: '', caption: '', notes: '', file: '', photo: '', file_type: '', width: '', height: '', bytes: '' }
          };
          var base = defaults[this.type] ? Object.assign({}, defaults[this.type]) : { id: '', name: '' };
          this.form = Object.assign(base, item || {});
        }

        this.open = true;
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
      },

      onDesignationChange(silent) {
        var id = String(this.form.designation_id || '').trim();
        if (!id) { if (!silent) this.rankHint = 'Select a designation to load rank'; return; }
        var d = this.designationOptions.find(function (x) {
          return String(x.id || '') === id || String(x.code || '') === id || String(x.slug || '') === id;
        });
        if (!d) { this.rankHint = 'Designation not found in master list'; return; }
        var r = this.desigRank(d);
        if (r < 900) { this.form.rank = r; this.rankHint = 'Linked from designation «' + (d.name || d.code) + '»'; }
        else { this.rankHint = 'Designation has no rank set — edit Designations master'; }
      },

      prettyKey(key) {
        var map = {
          bank_name: 'Financial institution', holder_name: 'Account principal', acc_no: 'Account number',
          ifsc: 'IFSC', branch: 'Branch', upi_id: 'UPI address', doc_file: 'Vault file',
          file_type: 'Classification', external_url: 'External URL', updated_at: 'Last revised',
          map_url: 'Map link', pincode: 'PIN code', category: 'Category', caption: 'Caption', platform: 'Platform', file: 'File', file_type: 'Type'
        };
        if (map[key]) return map[key];
        return String(key || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
      },
      maskAcc(n) {
        var s = String(n || '').replace(/\s+/g, '');
        if (!s) return '';
        if (s.length <= 4) return s;
        return '•••• ' + s.slice(-4);
      },
      docIconClass() {
        var t = String(this.form.file_type || this.form.doc_file || this.form.external_url || '').toLowerCase();
        if (t.indexOf('pdf') >= 0) return 'fa-file-pdf';
        if (t.indexOf('doc') >= 0) return 'fa-file-word';
        if (t.indexOf('xls') >= 0 || t.indexOf('sheet') >= 0) return 'fa-file-excel';
        if (t.indexOf('ppt') >= 0) return 'fa-file-powerpoint';
        if (t.indexOf('youtu') >= 0 || t.indexOf('video') >= 0) return 'fa-brands fa-youtube';
        if (t.indexOf('http') >= 0 || t.indexOf('link') >= 0) return 'fa-link';
        if (t.indexOf('zip') >= 0) return 'fa-file-zipper';
        if (t.indexOf('png') >= 0 || t.indexOf('jpg') >= 0 || t.indexOf('image') >= 0) return 'fa-file-image';
        return 'fa-file-lines';
      },
      docPreviewMeta() {
        var parts = [];
        if (this.form.file_type) parts.push(String(this.form.file_type).toUpperCase());
        if (this.form.version) parts.push('Rev ' + this.form.version);
        if (this.form.size) parts.push(this.form.size);
        if (this.form.updated_at) parts.push('Revised ' + this.form.updated_at);
        if (this.form.doc_file) parts.push(this.form.doc_file);
        return parts.length ? parts.join(' · ') : 'Awaiting classification — complete the fields below';
      },
      docPreviewHref() {
        var ext = String(this.form.external_url || '').trim();
        if (ext) return ext;
        var f = String(this.form.doc_file || '').trim();
        if (f) return 'docs/' + f.replace(/^\/+/, '');
        return '';
      },
      isYouTube(url) {
        return /youtu(\.be|be\.com)/i.test(String(url || ''));
      },
      youTubeEmbed(url) {
        var m = String(url || '').match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
        return m ? ('https://www.youtube-nocookie.com/embed/' + m[1]) : '';
      },

      formatBytes(n) {
        var b = Number(n) || 0;
        if (b <= 0) return '';
        var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0;
        while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
        var r = (b < 10 && i > 0) ? Math.round(b * 10) / 10 : Math.round(b);
        return r + ' ' + u[i];
      },
      applySizeBytes(bytes, source) {
        var b = Number(bytes) || 0;
        this.sizeBytes = b;
        this.form.size_bytes = b > 0 ? b : '';
        this.form.size = b > 0 ? this.formatBytes(b) : (source === 'remote-unknown' ? '' : (this.form.size || ''));
        if (b > 0) this.sizeStatus = 'ok';
        else if (source === 'probing') this.sizeStatus = 'probing';
        else if (source === 'remote-unknown') this.sizeStatus = 'unknown';
        else this.sizeStatus = '';
      },
      onDocFilePick(ev) {
        var f = ev.target && ev.target.files && ev.target.files[0];
        if (!f) return;
        this.docFile = f;
        this.docFileName = f.name;
        this.form.doc_file = f.name;
        if (!this.form.name) this.form.name = f.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
        if (!this.form.title) this.form.title = this.form.name;
        var ext = (f.name.split('.').pop() || '').toLowerCase();
        if (ext) this.form.file_type = ext;
        this.applySizeBytes(f.size, 'upload');
        if (!this.form.updated_at) {
          try { this.form.updated_at = new Date().toISOString().slice(0, 10); } catch (e) {}
        }
        this.error = '';
      },
      clearDocFile() {
        this.docFile = null;
        this.docFileName = '';
        // keep form.doc_file if user only clears pending upload
        this.applySizeBytes(0, '');
      },
      async probeLocalDocSize() {
        var name = String(this.form.doc_file || '').trim();
        if (!name || this.docFile) return;
        this.sizeStatus = 'probing';
        try {
          var csrf = (window.APP && window.APP.csrf) || window.CSRF_TOKEN || '';
          var res = await fetch('index.php?action=probe_doc_size&file=' + encodeURIComponent(name), {
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
            credentials: 'same-origin'
          });
          var data = await res.json().catch(function () { return {}; });
          if (data && data.ok && data.bytes > 0) {
            this.applySizeBytes(data.bytes, 'local');
            if (data.formatted) this.form.size = data.formatted;
          } else {
            this.sizeStatus = this.form.size ? 'ok' : 'unknown';
          }
        } catch (e) {
          this.sizeStatus = this.form.size ? 'ok' : 'unknown';
        }
      },
      async probeExternalSize() {
        var url = String(this.form.external_url || '').trim();
        if (!url || this.docFile) return;
        if (this.isYouTube(url)) {
          this.form.size = this.form.size || '';
          this.sizeStatus = 'unknown';
          return;
        }
        this.sizeStatus = 'probing';
        try {
          var csrf = (window.APP && window.APP.csrf) || window.CSRF_TOKEN || '';
          var res = await fetch('index.php?action=probe_url_size&url=' + encodeURIComponent(url), {
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
            credentials: 'same-origin'
          });
          var data = await res.json().catch(function () { return {}; });
          if (data && data.ok && data.bytes > 0) {
            this.applySizeBytes(data.bytes, 'remote');
            if (data.formatted) this.form.size = data.formatted;
          } else {
            this.applySizeBytes(0, 'remote-unknown');
          }
        } catch (e) {
          this.applySizeBytes(0, 'remote-unknown');
        }
      },

      close() {
        this.deactivateFocusTrap();
        this.open = false; this.error = '';
        this.photoFile = null; this.photoFileName = '';
        this.docFile = null; this.docFileName = ''; this.sizeStatus = ''; this.sizeBytes = 0;
        this.photoPreview = this.photoPreview || '';
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
        document.body.classList.remove('rc-modal-open');
        // Clear any inline display forced by openModalEditor retry path
        try {
          var root = document.getElementById('enterprise-editor-root');
          if (root) { root.style.display = ''; root.classList.remove('is-open'); }
        } catch (e) {}
      },

      async save() {
        this.saving = true; this.error = '';
        try {
          if (this.type === 'team') {
            if (!String(this.form.name || '').trim()) throw new Error('Name is required');
            if (!this.form.rank || Number(this.form.rank) >= 900) this.onDesignationChange(true);
            if (this.form.rank && Number(this.form.rank) >= 900) this.form.rank = '';
            this.parseBirthGeo();
            if (this.form.birth_lat !== '' && this.form.birth_lat != null) this.form.lat = this.form.birth_lat;
            if (this.form.birth_lng !== '' && this.form.birth_lng != null) this.form.lng = this.form.birth_lng;
            var soc = this.form.social || {};
            var cleaned = {};
            Object.keys(soc).forEach(function (k) {
              if (soc[k] && String(soc[k]).trim()) cleaned[k] = String(soc[k]).trim();
            });
            this.form.social = cleaned;
          }
          if (this.type === 'designations') {
            if (!String(this.form.name || '').trim() && !String(this.form.code || '').trim()) throw new Error('Name or code is required');
            if (this.form.rank && Number(this.form.rank) >= 900) throw new Error('Rank must be 1–899 (not 999)');
            this.form.mandatory_live_tracking = !!this.form.mandatory_live_tracking;
          }
          if (this.type === 'bank') {
            if (!String(this.form.bank_name || '').trim()) throw new Error('Financial institution is required');
            if (!String(this.form.holder_name || '').trim()) throw new Error('Account principal is required');
            if (!String(this.form.acc_no || '').trim()) throw new Error('Account number is required');
            if (this.form.ifsc) this.form.ifsc = String(this.form.ifsc).toUpperCase().replace(/\s+/g, '');
          }
          if (this.type === 'mediakit') {
            if (!String(this.form.name || this.form.title || '').trim()) throw new Error('Display name is required');
            if (!this.photoFile && !String(this.form.file || this.form.photo || '').trim()) {
              throw new Error('Upload a file or keep the existing stored filename');
            }
            if (!this.form.name && this.form.title) this.form.name = this.form.title;
            if (this.form.file && !this.form.photo) this.form.photo = this.form.file;
            if (this.form.photo && !this.form.file) this.form.file = this.form.photo;
          }
          if (this.type === 'docs') {
            if (!String(this.form.name || this.form.title || '').trim()) throw new Error('Display title is required');
            if (!this.docFile && !String(this.form.doc_file || '').trim() && !String(this.form.external_url || '').trim()) {
              throw new Error('Ingest a vault file or supply an external resource URL');
            }
            if (!this.form.name && this.form.title) this.form.name = this.form.title;
            if (this.sizeBytes > 0) {
              this.form.size_bytes = this.sizeBytes;
              this.form.size = this.formatBytes(this.sizeBytes);
            }
            if (!this.form.updated_at) {
              try { this.form.updated_at = new Date().toISOString().slice(0, 10); } catch (e) {}
            }
          }
          if (this.type === 'locations') {
            if (!String(this.form.name || '').trim()) throw new Error('Facility name is required');
          }
          if (this.type === 'events') {
            if (!String(this.form.name || '').trim()) throw new Error('Event title is required');
            this.form.is_virtual = !!this.form.is_virtual;
          }
          if (this.type === 'departments') {
            if (!String(this.form.name || '').trim() && !String(this.form.code || '').trim()) throw new Error('Unit name or code is required');
          }
          if (this.type === 'statutory') {
            if (!String(this.form.company_name || '').trim()) throw new Error('Legal entity name is required');
            ['cin','pan','tan','gst'].forEach(function (k) {
              if (this.form[k]) this.form[k] = String(this.form[k]).toUpperCase().replace(/\s+/g, '');
            }.bind(this));
          }
          if (this.type === 'leads') {
            if (!String(this.form.name || '').trim()) throw new Error('Prospect name is required');
          }
          if (this.type === 'cartags') {
            if (!String(this.form.registration_number || this.form.plate || '').trim()) throw new Error('Registration number is required');
          }
          if (this.type === 'company') {
            if (this.form.google_maps_api_key) this.form.maps_api_key = this.form.google_maps_api_key;
            var cs = this.form.social || {};
            var cc = {};
            Object.keys(cs).forEach(function (k) {
              if (cs[k] && String(cs[k]).trim()) cc[k] = String(cs[k]).trim();
            });
            this.form.social = cc;
          }

          var csrf = (window.APP && window.APP.csrf) || (window.CSRF_TOKEN) || '';
          var id = String(this.form.id || this.form.company_id || '');
          // Server API: action=save, ns, id, __edit_mode, payload={...}
          var payloadObj = Object.assign({}, this.form);
          // Ensure id is inside payload for updates
          if (id && !payloadObj.id) payloadObj.id = id;

          var res, data;
          // Never persist client-only preview blobs into JSON
          ['_logoPreview', '_photoPreview', 'photoPreview'].forEach(function (k) { delete payloadObj[k]; });
          if (payloadObj.logo && String(payloadObj.logo).indexOf('data:') === 0) delete payloadObj.logo;
          if (payloadObj.photo && String(payloadObj.photo).indexOf('data:') === 0) delete payloadObj.photo;
          if (payloadObj.favicon && String(payloadObj.favicon).indexOf('data:') === 0) delete payloadObj.favicon;
          if (payloadObj.cover && String(payloadObj.cover).indexOf('data:') === 0) delete payloadObj.cover;

          if (this.photoFile && (this.type === 'team' || this.type === 'locations' || this.type === 'company' || this.type === 'mediakit')) {
            var fd = new FormData();
            fd.append('action', 'save');
            fd.append('ns', this.type);
            fd.append('id', id);
            fd.append('__edit_mode', this.isEdit ? '1' : '0');
            fd.append('payload', JSON.stringify(payloadObj));
            fd.append('csrf_token', csrf);
            if (this.type === 'company') {
              fd.append('company_id', id || payloadObj.id || 'company');
              // Company logo picker stores file in photoFile; field name is logo
              fd.append('logo', this.photoFile, this.photoFile.name);
            } else if (this.type === 'mediakit') {
              fd.append('photo', this.photoFile, this.photoFile.name);
              fd.append('file', this.photoFile, this.photoFile.name);
            } else {
              fd.append('photo', this.photoFile, this.photoFile.name);
              if (this.type === 'locations') {
                fd.append('logo', this.photoFile, this.photoFile.name);
              }
            }

            res = await fetch('index.php', {
              method: 'POST',
              headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
              credentials: 'same-origin',
              body: fd
            });
          } else if (this.docFile && this.type === 'docs') {
            var fdDoc = new FormData();
            fdDoc.append('action', 'save');
            fdDoc.append('ns', 'docs');
            fdDoc.append('id', id);
            fdDoc.append('__edit_mode', this.isEdit ? '1' : '0');
            fdDoc.append('payload', JSON.stringify(payloadObj));
            fdDoc.append('csrf_token', csrf);
            fdDoc.append('doc_file', this.docFile, this.docFile.name);
            res = await fetch('index.php', {
              method: 'POST',
              headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
              credentials: 'same-origin',
              body: fdDoc
            });
          } else {
            var body = {
              action: 'save',
              ns: this.type,
              id: id,
              __edit_mode: this.isEdit,
              payload: payloadObj,
              csrf_token: csrf
            };
            if (this.type === 'company') {
              body.company_id = id || payloadObj.id || 'company';
            }
            res = await fetch('index.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
                'Accept': 'application/json'
              },
              credentials: 'same-origin',
              body: JSON.stringify(body)
            });
          }

          data = await res.json().catch(function () { return {}; });
          if (!res.ok || data.status === 'error' || data.ok === false) {
            throw new Error(data.message || data.error || ('HTTP ' + res.status));
          }
          this.close();
          // Stay on the same tab after save (preserve ?tab=… and other query params)
          try {
            var u = new URL(window.location.href);
            var tab = u.searchParams.get('tab') || (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.context && window.__DASHBOARD_STATE__.context.tab) || '';
            if (tab) u.searchParams.set('tab', tab);
            u.searchParams.set('_ts', String(Date.now())); // bust cache
            window.location.href = u.pathname + u.search + u.hash;
          } catch (e) {
            var q = window.location.search || '';
            if (q.indexOf('tab=') === -1) {
              var t = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.context && window.__DASHBOARD_STATE__.context.tab) || 'team';
              q = (q ? q + '&' : '?') + 'tab=' + encodeURIComponent(t);
            }
            window.location.href = window.location.pathname + q;
          }
        } catch (e) {
          this.error = e.message || String(e);
        } finally {
          this.saving = false;
        }
      }

    };
  }

  function register() {
    if (!window.Alpine || typeof Alpine.data !== 'function') return false;
    try { Alpine.data('enterpriseEditor', factory); return true; } catch (e) { return false; }
  }
  document.addEventListener('alpine:init', register);
  if (window.Alpine) register();
  window.__enterpriseEditorFactory = factory;

  function editorApi() {
    try {
      var root = document.getElementById('enterprise-editor-root');
      if (root && window.Alpine) return Alpine.$data(root);
    } catch (e) {}
    return null;
  }
  window.__rcEditorClose = function () {
    var ed = editorApi();
    if (ed && typeof ed.close === 'function') { ed.close(); return; }
    var root = document.getElementById('enterprise-editor-root');
    if (root) {
      root.style.display = 'none';
      document.documentElement.style.overflow = '';
      document.body.style.overflow = '';
    }
  };
  window.__rcEditorSave = function () {
    var ed = editorApi();
    if (ed && typeof ed.save === 'function') { ed.save(); return; }
    alert('Editor is still loading. Wait a second and try Save again.');
  };
})();
</script>
