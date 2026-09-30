<?php
// Version: 260916.14 — HQ_LOCATION_FIX
// FIX: company.location_id was read from a single key; it may be stored under
//      location_id / location / hq_location / hq depending on which modal field name
//      was active at save time. All four variants are now checked with the first
//      truthy value winning — fixes the "HQ keeps showing Not Set / being asked again" bug.
// FIX: openModalEditor now receives a normalised copy with location_id always populated.
if (!defined('BASE_PATH')) exit; ?>
<div class="max-w-5xl mx-auto w-full space-y-8 pb-12">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight">Organization Setup</h1>
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
                        <img :src="'/images/' + company.logo + '?v=' + ts" class="max-w-full max-h-full object-contain bg-transparent" alt="Company Logo">
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

                    <!-- HQ Location
                         BUG FIX: company.location_id may be stored under any of:
                           location_id (canonical), location, hq_location, hq.
                         Read all variants; the first truthy value wins.
                         This fixes the "asked again" symptom where the field shows
                         Not Set even after saving, because the modal saved under a
                         different key than the one being read here.
                    -->
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
                    <div class="text-[11px] font-bold text-slate-700 uppercase tracking-widest">Favicon</div>
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

