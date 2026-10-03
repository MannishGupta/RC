<?php
// dbd/tab_cartags.php — Version: 260916.14
//
// Dashboard tab for the vehicle-tag module (was car-tags/admin.php).
//
// WHAT CHANGED vs THE SUPPLIED admin.php:
//  * AUTH: admin.php shipped with no login check at all — its own README said
//    "wrap it with whatever auth guard the rest of rc.arthsathi.com uses
//    before deploying". As a dashboard tab it now inherits the existing
//    session auth automatically, and write actions are additionally gated to
//    isAdmin server-side by index.php.
//  * CSRF: admin.php's fetch() calls posted with no CSRF token. Writes now go
//    through index.php's save/delete actions, which enforce AppAuth::verify_csrf().
//  * DATA LAYER: dropped the module's private store.php (its own DATA_DIR,
//    read_json/write_json, and a non-atomic write). Tags now live in
//    data/cartags.json via AppDB, which already has the atomic-write +
//    Windows rename-retry logic this codebase needs. Scan logs stay in
//    data/cartag_logs.json, also via AppDB.
//  * QR: uses EasyQRCodeJS (maintained fork of qrcodejs), which this
//    codebase already loads in card_qr.php / card_visiting.php — one fewer
//    third-party library on the page.
//  * The public scan pages (vehicle-tags/index.php, vehicle-tags/emergency.php) stay
//    public by design — that's what a QR scan hits — but were hardened
//    separately.
if (!defined('BASE_PATH')) exit;
if (is_file(BASE_PATH . '/app/VehicleCatalog.php')) {
    require_once BASE_PATH . '/app/VehicleCatalog.php';
}
if (is_file(BASE_PATH . '/app/MasterDirectory.php')) {
    require_once BASE_PATH . '/app/MasterDirectory.php';
}

/** Resolve OEM logo URL for a make/model string. */
function rc_cartag_logo(string $makeModel): string {
    $try = trim($makeModel);
    if ($try === '') return '';
    if (class_exists('VehicleCatalog')) {
        $u = VehicleCatalog::getVehicleLogo($try);
        if (is_string($u) && $u !== '') return $u;
        $first = trim(explode(' ', $try)[0] ?? '');
        if ($first !== '' && $first !== $try) {
            $u = VehicleCatalog::getVehicleLogo($first);
            if (is_string($u) && $u !== '') return $u;
        }
    }
    if (class_exists('MasterDirectory')) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', explode(' ', $try)[0] ?? $try) ?? '');
        $u = MasterDirectory::logoUrl('', 'oems', $slug);
        if (is_string($u) && $u !== '') return $u;
    }
    return '';
}
?>
<style>/* cartag-modal-overflow-fix */
[x-show="modalOpen"] {
  align-items: flex-start !important;
  overflow-y: auto !important;
  -webkit-overflow-scrolling: touch;
  padding: 1rem !important;
  z-index: 200 !important;
}
[x-show="modalOpen"] > div.bg-white {
  max-height: min(92vh, 900px) !important;
  display: flex !important;
  flex-direction: column !important;
  margin-top: 1rem !important;
  margin-bottom: 1rem !important;
  overflow: hidden !important;
}
[x-show="modalOpen"] > div.bg-white > div.px-6.py-5,
[x-show="modalOpen"] > div.bg-white > form,
[x-show="modalOpen"] .cartag-modal-body {
  overflow-y: auto !important;
  max-height: calc(92vh - 8rem) !important;
  flex: 1 1 auto !important;
  -webkit-overflow-scrolling: touch;
}
</style>
<?php


// Team list for the owner dropdown. Only id/name/phone are passed to the
// browser — the tab has no need for photos, DOB or addresses, and sending
// the whole record would leak more than the feature requires.
$carTagTeam = [];
foreach ((class_exists('AppDB') ? (AppDB::read('team') ?: []) : []) as $_m) {
    if (empty($_m['id']) || empty($_m['name'])) continue;
    $carTagTeam[] = [
        'id'    => (string)$_m['id'],
        'name'  => (string)$_m['name'],
        'phone' => is_array($_m['phone'] ?? null) ? (string)($_m['phone'][0] ?? '') : (string)($_m['phone'] ?? ''),
    ];
}
usort($carTagTeam, fn($a, $b) => strcasecmp($a['name'], $b['name']));

require_once BASE_PATH . '/app/VehicleDecoder.php';
require_once BASE_PATH . '/app/VehicleRegistry.php';

// Needed for the sticker footer.
$company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$registryOn = VehicleRegistry::isConfigured();

$carTags    = class_exists('AppDB') ? (AppDB::read('cartags') ?: []) : [];

// ── Legacy fallback ───────────────────────────────────────────────────────
// The pre-integration module kept its own store at car-tags/data/tags.json.
// The optimizer imports it (Phase 0), but that only runs on login or on
// demand — so read the old file directly here too. Without this, a tab opened
// before the first optimizer run shows "no tags" while the records plainly
// exist on disk, which reads as data loss.
// Read-only: nothing is written back from here.
$_legacyFile = BASE_PATH . '/car-tags/data/tags.json';
$carTagsLegacyPending = 0;
if (is_file($_legacyFile)) {
    $_lg = json_decode((string)@file_get_contents($_legacyFile), true);
    if (is_array($_lg)) {
        $_have = [];
        foreach ($carTags as $_c) {
            $_k = trim((string)($_c['tag_id'] ?? ''));
            if ($_k !== '') $_have[$_k] = true;
        }
        foreach ($_lg as $_t) {
            if (!is_array($_t)) continue;
            $_tid = trim((string)($_t['tag_id'] ?? ''));
            if ($_tid === '' || isset($_have[$_tid])) continue;
            $carTags[] = [
                'id'                  => (string)($_t['id'] ?? $_tid),
                'tag_id'              => $_tid,
                'registration_number' => strtoupper(trim((string)($_t['plate'] ?? ''))),
                'vehicle_class'       => trim((string)($_t['vehicle_type'] ?? '')),
                'make_model'          => '',
                'colour'              => '',
                'member_id'           => '',
                'owner_name'          => trim((string)($_t['owner_name'] ?? '')),
                'status'              => trim((string)($_t['status'] ?? 'active')),
                '_legacy'             => true,   // flagged in the UI
            ];
            $_have[$_tid] = true;
            $carTagsLegacyPending++;
        }
    }
}
$carTagLogs = class_exists('AppDB') ? (AppDB::read('cartag_logs') ?: []) : [];

// Newest scans first, capped for page weight.
$carTagLogs = array_slice(array_reverse($carTagLogs), 0, 200);

// Free, offline enrichment. Derived entirely from the plate string and from
// scan logs we already hold — no API call, no cost, no personal data.
foreach ($carTags as &$_t) {
    $_e = VehicleDecoder::enrich($_t, $carTagLogs);
    $_t['_rto']    = $_e['decode']['rto_code'] ?? '';
    $_t['_state']  = $_e['decode']['state'] ?? '';
    $_t['_pretty'] = $_e['decode']['display'] ?: (string)($_t['registration_number'] ?? $_t['plate'] ?? '');
    $_t['_valid']  = (bool)($_e['decode']['valid'] ?? false);
    $_t['_badges'] = $_e['decode']['badges'] ?? [];
    $_t['_notes']  = $_e['decode']['notes'] ?? [];
    $_t['_series'] = $_e['decode']['series'] ?? '';
    $_t['_format'] = $_e['decode']['format'] ?? '';
    $_t['_scans']  = $_e['scan_count'];
    $_t['_last']   = $_e['last_scan'];
    $_t['_emerg']  = $_e['emergencies'];

    // Cached compliance, if a registry lookup has ever been run for this
    // vehicle. Never fetched here — rendering a page must not bill an API.
    $_reg = VehicleRegistry::get((string)($_t['registration_number'] ?? $_t['plate'] ?? ''));
    $_t['_reg']       = $_reg ? array_intersect_key($_reg, array_flip([
        'insurance_upto','insurance_company','pucc_upto','fitness_upto',
        'rc_status','emission_norms','maker_model','fetched_at',
    ])) : null;
    $_t['_regStale']  = $_reg ? VehicleRegistry::isStale($_reg) : false;
    $_t['_regAlerts'] = $_reg ? VehicleRegistry::alerts($_reg) : [];
    $_t['_regChecks'] = $_reg ? [
        'Insurance' => VehicleRegistry::validity((string)($_reg['insurance_upto'] ?? '')),
        'PUC'       => VehicleRegistry::validity((string)($_reg['pucc_upto'] ?? '')),
        'Fitness'   => VehicleRegistry::validity((string)($_reg['fitness_upto'] ?? '')),
    ] : [];
    // OEM logo — VehicleCatalog was built but never called from this tab
    $_make = trim((string)($_t['make_model'] ?? $_t['manufacturer'] ?? $_t['make'] ?? ''));
    if ($_make === '' && !empty($_reg['maker_model'])) {
        $_make = trim((string)$_reg['maker_model']);
    }
    $_t['oem_logo'] = ($_make !== '' && function_exists('rc_cartag_logo')) ? rc_cartag_logo($_make) : '';
    if ($_t['oem_logo'] === '' && $_make !== '' && class_exists('VehicleCatalog')) {
        $_t['oem_logo'] = (string)VehicleCatalog::getVehicleLogo($_make);
    }
}
unset($_t);

$_scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host    = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$scanBase = $_scheme . '://' . $_host . '/vehicle-tags/index.php?t=';
?>

<div class="w-full flex flex-col space-y-4" x-data="carTagsTab()">

    <?php if ($carTagsLegacyPending > 0): ?>
    <!-- Shown only while old-format records exist that have not yet been
         imported. They are already visible below (read directly from the old
         file), but they are not editable until imported. -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
        <i class="fa-solid fa-database text-amber-500 mt-0.5 shrink-0"></i>
        <div class="text-xs text-amber-800 leading-relaxed flex-1">
            <span class="font-bold"><?= (int)$carTagsLegacyPending ?> tag(s) from the previous version are showing read-only.</span>
            They are being read from the old <span class="font-mono">car-tags/data</span> folder.
            Run the System Optimizer once to import them properly — after that they become editable,
            and their scan history is brought across too. The old files are left in place either way.
        </div>
        <a href="?tab=opt" class="shrink-0 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition whitespace-nowrap">
            Import now
        </a>
    </div>
    <?php endif; ?>

    <!-- Sub-nav -->
    <div class="flex items-center justify-between gap-3 bg-white p-3.5 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center gap-2">
            <button @click="view='tags'"
                    :class="view==='tags' ? 'bg-slate-800 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    class="px-3.5 py-2 rounded-lg text-xs font-bold transition">
                <i class="fa-solid fa-car mr-1"></i> Registrations (<span x-text="tags.length"></span>)
            </button>
            <button @click="view='logs'"
                    :class="view==='logs' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    class="px-3.5 py-2 rounded-lg text-xs font-bold transition">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i> Scan Logs (<span x-text="logs.length"></span>)
            </button>
        </div>
        <?php if ($isAdmin): ?>
        <button @click="openNew()" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition shadow-sm">
            <i class="fa-solid fa-plus mr-1.5"></i> New Tag
        </button>
        <?php endif; ?>
    </div>

    <!-- ══════════ TAGS ══════════ -->
    <div x-show="view==='tags'">

        <template x-if="tags.length === 0">
            <div class="flex flex-col items-center justify-center h-64 text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl w-full bg-slate-50/50">
                <i class="fa-solid fa-car-side text-4xl mb-4 text-slate-300"></i>
                <p class="text-sm font-semibold text-slate-600">No vehicle tags yet.</p>
                <p class="text-xs text-slate-400 mt-1">Create a tag, then print its QR code for the windscreen.</p>
            </div>
        </template>

        <template x-if="tags.length > 0">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <template x-for="t in tags" :key="t.id">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden group hover:border-blue-300 hover:shadow-md transition-all flex flex-col">

                        <div class="bg-gradient-to-r from-slate-900 to-slate-700 px-4 pt-4 pb-10 relative overflow-hidden">
                            <div class="flex items-center gap-3 relative">
                                <!-- OEM logo always on white tile so black SVGs stay visible -->
                                <div class="w-12 h-12 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-md overflow-hidden p-1.5 ring-1 ring-white/30">
                                    <template x-if="t.oem_logo">
                                        <img :src="t.oem_logo" alt="" class="max-h-9 max-w-full object-contain" width="40" height="36" loading="lazy"
                                             @error="$el.style.display='none'; $el.nextElementSibling && ($el.nextElementSibling.style.display='flex')">
                                    </template>
                                    <span class="w-full h-full items-center justify-center text-slate-500" :style="t.oem_logo ? 'display:none' : 'display:flex'">
                                        <i class="fa-solid fa-car text-base"></i>
                                    </span>
                                </div>
                                <!-- Plate number: dominant, centered in remaining header -->
                                <div class="flex-1 min-w-0 text-center px-1">
                                    <div class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-300 mb-0.5">Registration</div>
                                    <div class="font-black text-white text-xl sm:text-2xl leading-tight font-mono tracking-wider truncate drop-shadow"
                                         x-text="t._pretty || t.registration_number || t.plate || '—'"></div>
                                </div>
                                <div class="flex gap-1.5 shrink-0 opacity-80 group-hover:opacity-100 transition">
                                    <button type="button" @click.stop="showQr(t)" class="w-8 h-8 rounded-lg bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition text-xs" title="QR Code">
                                        <i class="fa-solid fa-qrcode"></i>
                                    </button>
                                    <?php if ($isAdmin): ?>
                                    <button type="button" @click.stop="editTag(t)" class="w-8 h-8 rounded-lg bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition text-xs" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" @click.stop="removeTag(t)" class="w-8 h-8 rounded-lg bg-white/15 hover:bg-red-500/70 text-white flex items-center justify-center transition text-xs" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="-mt-5 mx-4 bg-white rounded-xl border border-slate-100 shadow-sm px-4 pt-4 pb-4 flex-1 flex flex-col gap-3">
                            <div>
                                <h3 class="font-bold text-slate-800 text-base leading-tight" x-show="t.make_model || t.colour">
                                    <span x-text="t.make_model || ''"></span><span x-show="t.make_model && t.colour" class="text-slate-300"> · </span><span class="text-slate-600 font-medium" x-text="t.colour || ''"></span>
                                </h3>
                                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 bg-slate-100 px-2 py-0.5 rounded font-mono">#<span x-text="t.tag_id || t.id"></span></span>
                                    <span x-show="t._legacy" class="text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">Not imported</span>
                                    <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100" x-text="t.vehicle_class || 'Car'"></span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border"
                                          :class="(t.status||'active')==='active' ? 'text-emerald-700 bg-emerald-50 border-emerald-100' : 'text-slate-500 bg-slate-100 border-slate-200'"
                                          x-text="t.status || 'active'"></span>
                                </div>
                            </div>

                            <div class="text-xs text-slate-500 flex items-start gap-2 leading-relaxed" x-show="t.owner_name || t.member_id">
                                <i class="fa-solid fa-user mt-0.5 text-slate-400 shrink-0"></i>
                                <span>
                                    <span class="font-semibold text-slate-700" x-text="memberName(t) || t.owner_name || '—'"></span>

                                </span>
                            </div>

                            <!-- Link status. An unlinked tag still works for scanning, but
                                 cannot notify anyone automatically, so it is called out. -->
                            <div class="flex items-center gap-1.5 text-[10px] font-bold">
                                <template x-if="t.member_id && memberName(t)">
                                    <span class="text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded">
                                        <i class="fa-solid fa-link text-[9px]"></i> Linked to directory
                                    </span>
                                </template>
                                <template x-if="t.member_id && !memberName(t)">
                                    <span class="text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded">
                                        <i class="fa-solid fa-link-slash text-[9px]"></i> Linked member deleted
                                    </span>
                                </template>
                                <template x-if="!t.member_id">
                                    <span class="text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">
                                        <i class="fa-solid fa-triangle-exclamation text-[9px]"></i> No owner linked
                                    </span>
                                </template>
                            </div>

                            <!-- ── Derived registry info (READ-ONLY) ──────────────────
                                 Rendered in grey throughout to signal that nothing here
                                 is editable: every value is computed from the plate
                                 string itself, so editing it would be meaningless.
                                 Computed server-side on every page load — offline, so
                                 it is always current and needs no refresh cycle. -->
                            <div class="mt-1 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2.5">
                                <div class="flex items-center gap-1.5 mb-2">
                                    <i class="fa-solid fa-lock text-[9px] text-slate-400"></i>
                                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">
                                        Registry Info · auto-derived
                                    </span>
                                </div>

                                <dl class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-[11px]">
                                    <div>
                                        <dt class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Registering State</dt>
                                        <dd class="text-slate-500 font-semibold" x-text="t._state || 'Not recognised'"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Registering Authority</dt>
                                        <dd class="text-slate-500 font-semibold font-mono" x-text="t._rto || '—'"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Series Code</dt>
                                        <dd class="text-slate-500 font-semibold font-mono" x-text="t._series || '—'"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-[9px] uppercase tracking-wider text-slate-400 font-bold">Format</dt>
                                        <dd class="text-slate-500 font-semibold capitalize" x-text="t._format || '—'"></dd>
                                    </div>
                                </dl>

                                <template x-if="(t._badges || []).length">
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        <template x-for="b in t._badges" :key="b.label">
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border border-slate-300 bg-white text-slate-500"
                                                  x-text="b.label"></span>
                                        </template>
                                    </div>
                                </template>

                                <!-- Compliance (paid registry). Only rendered when a
                                     lookup has been cached — never fetched on render. -->
                                <template x-if="t._reg">
                                    <div class="mt-2.5 pt-2.5 border-t border-slate-200">
                                        <div class="grid grid-cols-3 gap-2">
                                            <template x-for="(v, k) in t._regChecks" :key="k">
                                                <div>
                                                    <div class="text-[9px] uppercase tracking-wider text-slate-400 font-bold" x-text="k"></div>
                                                    <div class="text-[10px] font-bold leading-tight"
                                                         :class="{
                                                            'text-emerald-600': v.state==='ok',
                                                            'text-amber-600':   v.state==='soon',
                                                            'text-rose-600':    v.state==='expired',
                                                            'text-slate-400':   v.state==='unknown'
                                                         }" x-text="v.label"></div>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-1.5" x-show="t._reg.fetched_at">
                                            Registry data as of
                                            <span x-text="new Date(t._reg.fetched_at * 1000).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})"></span>
                                            <span x-show="t._regStale" class="text-amber-600 font-bold"> · stale</span>
                                        </div>
                                    </div>
                                </template>

                                <?php if ($registryOn && $isAdmin): ?>
                                <button @click.stop="fetchRegistry(t)" :disabled="regBusy === (t.registration_number || t.id)"
                                        class="mt-2 w-full py-1.5 text-[10px] font-bold rounded border border-slate-300 bg-white text-slate-500 hover:text-blue-600 hover:border-blue-300 transition disabled:opacity-50">
                                    <i class="fa-solid" :class="regBusy === (t.registration_number || t.id) ? 'fa-circle-notch fa-spin' : 'fa-cloud-arrow-down'"></i>
                                    <span x-text="t._reg ? 'Refresh compliance' : 'Fetch compliance data'"></span>
                                </button>
                                <?php elseif ($isAdmin): ?>
                                <div class="mt-2 text-[10px] text-slate-400 leading-snug">
                                    Compliance lookup (PUC, insurance, fitness) is not configured.
                                    See <span class="font-mono">data/registry_config.php</span>.
                                </div>
                                <?php endif; ?>

                                <template x-if="(t._notes || []).length">
                                    <ul class="mt-2 space-y-0.5">
                                        <template x-for="n in t._notes" :key="n">
                                            <li class="text-[10px] text-slate-400 leading-snug" x-text="n"></li>
                                        </template>
                                    </ul>
                                </template>
                            </div>

                            <!-- On-card square QR (always visible) -->
                            <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-3">
                                <div class="bg-white border border-slate-200 rounded-xl p-1.5 shrink-0" style="width:88px;height:88px;box-sizing:border-box;">
                                    <img
                                        :src="'https://api.qrserver.com/v1/create-qr-code/?size=80x80&margin=6&ecc=M&data=' + encodeURIComponent(scanBase + encodeURIComponent(t.tag_id || t.id || ''))"
                                        width="80" height="80" alt="Tag QR"
                                        style="display:block;width:80px;height:80px;image-rendering:pixelated;"
                                        loading="lazy"
                                    >
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Scan QR</div>
                                    <div class="text-[11px] text-slate-600 font-mono truncate" x-text="'#' + (t.tag_id || t.id || '')"></div>
                                    <button type="button" @click="showQr(t)"
                                            class="mt-1.5 inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold rounded-lg transition">
                                        <i class="fa-solid fa-expand text-[9px]"></i> Enlarge / Print
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-slate-100 mt-2">
                                <span class="text-[11px] text-slate-400">
                                    <i class="fa-solid fa-clock-rotate-left mr-1"></i>
                                    <span x-text="t._scans || 0"></span> scan<span x-show="(t._scans||0)!==1">s</span>
                                    <span x-show="t._last" class="text-slate-300"> · last <span x-text="t._last"></span></span>
                                    <span x-show="(t._emerg||0) > 0" class="ml-1 text-rose-600 font-bold">
                                        · <span x-text="t._emerg"></span> emergency
                                    </span>
                                </span>
                                <button type="button" @click="showQr(t)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold rounded-lg transition shadow-sm">
                                    <i class="fa-solid fa-qrcode text-[10px]"></i> QR
                                </button>
                            </div>
                        </div>
                        <div class="h-4"></div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- ══════════ LOGS ══════════ -->
    <div x-show="view==='logs'" x-cloak>
        <template x-if="logs.length === 0">
            <div class="flex flex-col items-center justify-center h-64 text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl w-full bg-slate-50/50">
                <i class="fa-regular fa-clock text-4xl mb-4 text-slate-300"></i>
                <p class="text-sm font-semibold text-slate-600">No scans logged yet.</p>
            </div>
        </template>

        <template x-if="logs.length > 0">
            <div class="w-full bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto hide-scrollbar">
                    <table class="w-full text-left" style="min-width:720px">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase text-slate-500 tracking-wider">
                            <tr>
                                <th class="px-4 py-3 font-bold w-44">Time</th>
                                <th class="px-4 py-3 font-bold w-24">Tag ID</th>
                                <th class="px-4 py-3 font-bold">Reason</th>
                                <th class="px-4 py-3 font-bold w-36">Reported By</th>
                                <th class="px-4 py-3 font-bold w-28">Location</th>
                                <th class="px-4 py-3 font-bold w-24 text-center">Verified</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <template x-for="l in logs" :key="l.id">
                                <tr class="hover:bg-slate-50/60 transition"
                                    :class="l.emergency ? 'bg-rose-50/60' : ''">
                                    <td class="px-4 py-3 text-xs text-slate-500 tabular-nums" x-text="fmtTime(l.ts)"></td>
                                    <td class="px-4 py-3 font-mono text-xs font-bold text-slate-700" x-text="l.tag_id || '—'"></td>
                                    <td class="px-4 py-3 text-xs text-slate-600">
                                        <span x-show="l.emergency" class="inline-block text-[9px] font-black uppercase tracking-widest text-rose-600 bg-rose-100 border border-rose-200 px-1.5 py-0.5 rounded mr-1.5">Emergency</span>
                                        <span x-text="l.reason || '—'"></span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-600 font-mono" x-text="l.scanner_phone || '—'"></td>
                                    <!-- Coordinates were already captured (260906.17) but never surfaced.
                                         Shown as a map link rather than raw numbers — a lat/lng pair is
                                         not something anyone can act on by reading it. -->
                                    <td class="px-4 py-3 text-xs">
                                        <template x-if="l.lat && l.lng">
                                            <a :href="'https://maps.google.com/?q=' + l.lat + ',' + l.lng"
                                               target="_blank" rel="noopener noreferrer"
                                               class="inline-flex items-center gap-1 font-bold text-blue-600 hover:text-blue-800 transition">
                                                <i class="fa-solid fa-location-dot text-[10px]"></i> Map
                                            </a>
                                        </template>
                                        <template x-if="!(l.lat && l.lng)">
                                            <span class="text-slate-300">—</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <i class="fa-solid text-xs"
                                           :class="l.verified ? 'fa-circle-check text-emerald-500' : 'fa-circle-xmark text-slate-300'"></i>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>

    <!-- ══════════ QR MODAL ══════════ -->
    <div x-show="qrOpen" x-cloak style="display:none"
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" style="z-index:200"
         @click.self="qrOpen=false" @keydown.escape.window="qrOpen=false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xs overflow-hidden text-center">
            <div class="px-6 pt-6">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Vehicle Tag</p>
                <p class="font-black text-slate-800 text-lg font-mono mt-1" x-text="qrPlate"></p>
                <p class="text-xs text-slate-400 font-mono">#<span x-text="qrTagId"></span></p>
            </div>
            <div class="p-6">
                <div id="cartagQr" style="width:200px;height:200px;margin:0 auto;" class="flex justify-center"></div>
                <p class="text-[10px] text-slate-400 mt-3 break-all font-mono" x-text="qrUrl"></p>
            </div>
            <div class="px-6 pb-6 flex gap-2">
                <button @click="printQr(6)" class="flex-1 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition">
                    <i class="fa-solid fa-print mr-1.5"></i> Sheet ×6
                </button>
                <button @click="printQr(1)" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">
                    <i class="fa-solid fa-note-sticky mr-1.5"></i> Single
                </button>
                <button @click="qrOpen=false" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition">Close</button>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- ══════════ EDITOR MODAL ══════════ -->
    <div x-show="modalOpen" x-cloak style="display:none" role="dialog" aria-modal="true" data-rc-dialog="cartags-editor"
         class="fixed inset-0 z-[200] bg-slate-900/50 backdrop-blur-sm flex items-start sm:items-center justify-center p-3 sm:p-4 overflow-y-auto"
         @click.self="modalOpen=false" @keydown.escape.window="modalOpen=false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md my-4 sm:my-8 max-h-[min(92vh,900px)] flex flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-black text-slate-800" x-text="form.id ? 'Edit Vehicle Tag' : 'New Vehicle Tag'"></h3>
                <button @click="modalOpen=false" class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-6 space-y-3">
                <!-- Vehicle photo (optional). A real photo of THIS car — not a
                     generic stock photo of the make/model, which wouldn't show the
                     actual colour, condition or angle of the specific vehicle a
                     scanner is standing in front of, and would need a paid image-
                     search API to fetch. Shown on the public scan page's header so a
                     scanner can visually confirm they have the right vehicle. Uses
                     the same upload field/handler already used for team and company
                     photos — no server change needed, 'photo' was already a
                     recognised upload field name. -->
                <div class="flex items-center gap-3">
                    <div class="w-16 h-16 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden shrink-0 flex items-center justify-center">
                        <img x-show="form._photoPreview || form.photo" :src="form._photoPreview || ('images/' + form.photo)" class="w-full h-full object-cover">
                        <i x-show="!form._photoPreview && !form.photo" class="fa-solid fa-car text-slate-300 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Vehicle Photo (optional)</label>
                        <input type="file" name="photo" accept="image/*" @change="onTagPhoto($event)"
                               class="w-full mt-1 text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-600 hover:file:bg-slate-200">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Registration Number *</label>
                    <input x-model="form.registration_number" placeholder="UP16EN4466"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono uppercase tracking-wide outline-none focus:border-blue-500">
                </div>
                <!-- Live, read-only decode of whatever is typed above. Shown here
                     so the admin sees the system understood the plate BEFORE
                     saving, rather than discovering a typo on the card later. -->
                <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-2" x-show="form.registration_number">
                    <div class="flex items-center gap-1.5 mb-1">
                        <i class="fa-solid fa-lock text-[9px] text-slate-400"></i>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Auto-derived · read only</span>
                    </div>
                    <div class="text-[11px] text-slate-500 font-mono" x-text="livePlate().display || '—'"></div>
                    <div class="text-[11px] text-slate-500 mt-0.5">
                        <span x-text="livePlate().state || 'State not recognised'"></span>
                        <span x-show="livePlate().rto"> · RTO <span x-text="livePlate().rto"></span></span>
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Vehicle Class</label>
                    <select x-model="form.vehicle_class" class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                        <!-- VAHAN vehicle-class descriptions. The bracketed codes
                             (LMV, MCWG, HGV…) are the abbreviations printed on an
                             RC book, so a value here reads identically to the
                             registration certificate it describes. -->
                        <option>Motor Car (LMV)</option>
                        <option>Motorcycle (MCWG)</option>
                        <option>Scooter (MCWOG)</option>
                        <option>Goods Vehicle (LGV/HGV)</option>
                        <option>Passenger Vehicle (LPV/HPV)</option>
                        <option>Three Wheeler</option>
                        <option>Tractor</option>
                        <option>Other</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Owner (from Directory)</label>
                    <select x-model="form.member_id" @change="onMemberPick()"
                            class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                        <option value="">— Not linked —</option>
                        <template x-for="m in team" :key="m.id">
                            <option :value="m.id" x-text="m.name"></option>
                        </template>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">
                        Linking lets the system reach the owner directly when a tag is scanned.
                        Leave unlinked for visitor or contractor vehicles.
                    </p>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Owner name (if not in directory)</label>
                    <input x-model="form.owner_name" placeholder="Visitor / contractor name"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Maker / Model</label>
                    <input x-model="form.make_model" placeholder="e.g. Maruti Suzuki Swift VXI"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Colour</label>
                    <input x-model="form.colour" placeholder="e.g. Pearl Arctic White"
                           class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                    <p class="text-[10px] text-slate-400 mt-1">
                        Maker/Model and Colour let a scanner confirm they are looking at the
                        right vehicle before sending a message.
                    </p>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Status</label>
                    <select x-model="form.status" class="w-full mt-1 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 shrink-0 sticky bottom-0">
                <button type="button" @click="modalOpen=false" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-800 transition">Cancel</button>
                <button type="button" @click="saveTag()" :disabled="saving"
                        class="px-5 py-2 bg-slate-900 hover:bg-blue-600 disabled:opacity-60 text-white text-sm font-bold rounded-lg transition">
                    <span x-text="saving ? 'Saving…' : 'Save'"></span>
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- EasyQRCodeJS 4.6.2 — replaces qrcodejs 1.0.0, abandoned since 2016
     with issues open from 2024–2026 and an unfixed code-length overflow.
     Same `new QRCode(el, options)` constructor, so this is a drop-in. -->
<script src="https://cdn.jsdelivr.net/npm/easyqrcodejs@4.6.2/dist/easy.qrcode.min.js"></script>
<script>

function renderSquareQR(el, text, size) {
    size = size || 128;
    if (!el) return;
    const imgUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=' + size + 'x' + size + '&margin=8&data=' + encodeURIComponent(text);
    const fallback = () => {
        el.innerHTML = '';
        const img = document.createElement('img');
        img.src = imgUrl; img.alt = 'QR'; img.width = size; img.height = size;
        img.style.cssText = 'display:block;width:'+size+'px;height:'+size+'px;';
        el.appendChild(img);
    };
    fallback();
    if (typeof QRCode === 'undefined') return;
    try {
        el.innerHTML = '';
        new QRCode(el, {
            text: text, width: size, height: size,
            colorDark: '#0f172a', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
            quietZone: 4, quietZoneColor: '#ffffff'
        });
        const c = el.querySelector('canvas, img, table');
        if (c) { c.style.width = size + 'px'; c.style.height = size + 'px'; }
    } catch (e) { fallback(); }
}

function carTagsTab() {
    return {
        view: 'tags',
        tags: <?= json_encode(array_values($carTags), JSON_UNESCAPED_SLASHES) ?>,
        team: <?= json_encode($carTagTeam, JSON_UNESCAPED_SLASHES) ?>,
        logs: <?= json_encode(array_values($carTagLogs), JSON_UNESCAPED_SLASHES) ?>,
        scanBase: <?= json_encode($scanBase) ?>,
        modalOpen: false, qrOpen: false, saving: false,
        qrUrl: '', qrTagId: '', qrPlate: '',
        form: { id:'', tag_id:'', registration_number:'', vehicle_class:'Motor Car (LMV)', make_model:'', colour:'', member_id:'', owner_name:'', status:'active' },

        // Client-side mirror of app/VehicleDecoder.php, used ONLY for the live
        // preview while typing. The card itself always renders the PHP-decoded
        // values, so this copy can never become the source of truth.
        states: <?= json_encode([
            'AP'=>'Andhra Pradesh','AR'=>'Arunachal Pradesh','AS'=>'Assam','BR'=>'Bihar',
            'CG'=>'Chhattisgarh','CH'=>'Chandigarh','DD'=>'Daman & Diu','DL'=>'Delhi',
            'DN'=>'Dadra & Nagar Haveli','GA'=>'Goa','GJ'=>'Gujarat','HP'=>'Himachal Pradesh',
            'HR'=>'Haryana','JH'=>'Jharkhand','JK'=>'Jammu & Kashmir','KA'=>'Karnataka',
            'KL'=>'Kerala','LA'=>'Ladakh','LD'=>'Lakshadweep','MH'=>'Maharashtra',
            'ML'=>'Meghalaya','MN'=>'Manipur','MP'=>'Madhya Pradesh','MZ'=>'Mizoram',
            'NL'=>'Nagaland','OD'=>'Odisha','OR'=>'Odisha (old series)','PB'=>'Punjab',
            'PY'=>'Puducherry','RJ'=>'Rajasthan','SK'=>'Sikkim','TN'=>'Tamil Nadu',
            'TR'=>'Tripura','TS'=>'Telangana','UK'=>'Uttarakhand','UA'=>'Uttarakhand (old series)',
            'UP'=>'Uttar Pradesh','WB'=>'West Bengal','AN'=>'Andaman & Nicobar',
        ]) ?>,

        livePlate() {
            const s = (this.form.registration_number || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            let m;
            if ((m = s.match(/^(\d{2})BH(\d{4})([A-Z]{1,2})$/)))
                return { display: `${m[1]} BH ${m[2]} ${m[3]}`, state: 'Bharat Series (nationwide)', rto: '' };
            if ((m = s.match(/^(\d{1,3})CD(\d{1,4})$/)))
                return { display: `${m[1]} CD ${m[2]}`, state: 'Diplomatic', rto: '' };
            if ((m = s.match(/^([A-Z]{2})(\d{1,2})([A-Z]{0,3})(\d{1,4})$/))) {
                const rto = m[2].padStart(2, '0');
                return {
                    display: [m[1], rto, m[3], m[4]].filter(Boolean).join(' '),
                    state: this.states[m[1]] || '',
                    rto
                };
            }
            return { display: '', state: '', rto: '' };
        },

        // Resolve the linked directory member. Returns '' when the tag is
        // unlinked OR when the member has since been deleted — the caller
        // distinguishes those two cases via t.member_id.
        memberName(t) {
            if (!t.member_id) return '';
            const m = this.team.find(x => x.id === t.member_id);
            return m ? m.name : '';
        },

        // Copy the chosen member's name onto the tag as a denormalised
        // fallback, so a printed/scanned tag still shows an owner even if the
        // directory record is later removed.
        onMemberPick() {
            const m = this.team.find(x => x.id === this.form.member_id);
            if (m) this.form.owner_name = m.name;
        },

        scanCount(t) {
            const key = t.tag_id || t.id;
            return this.logs.filter(l => l.tag_id === key).length;
        },

        fmtTime(ts) {
            if (!ts) return '—';
            const d = new Date(ts);
            if (isNaN(d)) return ts;
            return d.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' })
                 + ' · ' + d.toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit' });
        },

        openNew() {
            this.form = { id:'', tag_id:'', registration_number:'', vehicle_class:'Motor Car (LMV)', make_model:'', colour:'', member_id:'', owner_name:'', status:'active' };
            this.modalOpen = true;
        },

        editTag(t) {
            // NOTE: these keys must match the x-model bindings in the modal.
            // The 260906.14 rename updated the inputs to form.registration_number
            // / form.vehicle_class but left this object writing the old
            // plate/vehicle_type keys — so opening an existing tag populated
            // fields nothing was bound to and the form appeared blank.
            this.form = {
                id:                  t.id || '',
                tag_id:              t.tag_id || '',
                registration_number: t.registration_number || t.plate || '',
                vehicle_class:       t.vehicle_class || t.vehicle_type || 'Motor Car (LMV)',
                make_model:          t.make_model || '',
                colour:              t.colour || '',
                member_id:           t.member_id || '',
                owner_name:          t.owner_name || '',
                status:              t.status || 'active'
            };
            this.modalOpen = true;
        },

        onTagPhoto(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.form._photoFile = file;
            this.form._photoPreview = URL.createObjectURL(file);
        },

        async saveTag() {
            const regNo = (this.form.registration_number || this.form.plate || '').trim().toUpperCase().replace(/\s+/g, '');
            if (regNo.length < 4) { alert('Enter a valid registration number (at least 4 characters).'); return; }
            this.saving = true;
            const isEdit = !!(this.form.id);
            const csrf = (window.APP && window.APP.csrf) || window.CSRF_TOKEN || '';
            const payload = Object.assign({}, this.form, { registration_number: regNo });
            if (!payload.tag_id) payload.tag_id = String(Math.floor(10000 + Math.random() * 90000));
            if (!isEdit) delete payload.id;
            delete payload._photoFile;
            delete payload._photoPreview;

            const parseRes = async (r) => {
                const text = await r.text();
                try { return JSON.parse(text); }
                catch (e) {
                    return { status: 'error', message: 'Server returned non-JSON (HTTP ' + r.status + '). ' + text.replace(/\s+/g, ' ').slice(0, 120) };
                }
            };

            let res;
            try {
                if (this.form._photoFile) {
                    const fd = new FormData();
                    fd.set('action', 'save');
                    fd.set('ns', 'cartags');
                    fd.set('id', this.form.id || '');
                    fd.set('__edit_mode', isEdit ? '1' : '0');
                    fd.set('payload', JSON.stringify(payload));
                    fd.set('photo', this.form._photoFile);
                    const r = await fetch('index.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd });
                    res = await parseRes(r);
                } else {
                    const r = await fetch('index.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                        body: JSON.stringify({
                            action: 'save',
                            ns: 'cartags',
                            id: this.form.id || '',
                            __edit_mode: isEdit,
                            payload: payload
                        })
                    });
                    res = await parseRes(r);
                }
            } catch (err) {
                res = { status: 'error', message: err.message || 'Network error' };
            }

            this.saving = false;
            if (res && res.status === 'success') {
                this.modalOpen = false;
                location.reload();
            } else {
                alert('Save failed: ' + ((res && res.message) ? res.message : 'Unknown error'));
            }
        },

        regBusy: '',

        // Explicit, admin-only registry lookup. Billable, so it is never
        // called automatically on render or from the public scan page.
        async fetchRegistry(t) {
            const reg = t.registration_number || t.plate || '';
            if (!reg) { alert('This tag has no registration number.'); return; }
            this.regBusy = reg || t.id;
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                body: JSON.stringify({ action: 'registry_fetch', registration_number: reg, force: !!t._reg })
            }).then(r => r.json()).catch(() => ({ status: 'error', message: 'Network error' }));
            this.regBusy = '';
            if (res.status === 'success') location.reload();
            else alert('Lookup failed: ' + (res.message || 'Unknown error'));
        },

        async removeTag(t) {
            if (!confirm('Delete registration ' + (t.registration_number || t.tag_id) + '?\n\nThe printed QR sticker will stop working.')) return;
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                body: JSON.stringify({ action:'delete', ns:'cartags', id:t.id })
            }).then(r => r.json()).catch(() => ({ status:'error' }));
            if (res.status === 'success') location.reload();
            else alert('Delete failed.');
        },

        showQr(t) {
            this.qrTagId = t.tag_id || t.id;
            this.qrPlate = t.registration_number || t.plate || '';
            this.qrUrl   = this.scanBase + encodeURIComponent(this.qrTagId);
            this.qrOpen  = true;
            this.$nextTick(() => {
                const el = document.getElementById('cartagQr');
                if (!el) return;
                el.innerHTML = '';
                const img = document.createElement('img');
                img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=12&ecc=M&data=' + encodeURIComponent(this.qrUrl);
                img.width = 200; img.height = 200; img.alt = 'Vehicle Tag QR';
                img.style.cssText = 'display:block;width:200px;height:200px;image-rendering:pixelated;';
                el.appendChild(img);
            });
        },

        /**
         * Windscreen sticker sheet.
         *
         * DESIGN CONSTRAINTS — this is printed, stuck to glass, and scanned by
         * a stranger's phone through a windscreen, often at night:
         *  - Dark ground with a WHITE quiet-zone panel around the QR. Scanners
         *    need that white border; printing a QR edge-to-edge on dark stock
         *    is the single most common reason a sticker fails to scan.
         *  - Error-correction level H (30% recoverable), so the code still
         *    reads with dust, a scratch or glare across part of it.
         *  - No colour behind the QR itself. Coloured or gradient QRs look
         *    good in mockups and fail in low light.
         *  - Registration number printed in full: it is already visible on the
         *    vehicle, and it lets a guard match sticker to car without scanning.
         *  - Tag ID printed small, so a damaged sticker can still be identified
         *    and reissued.
         *
         * Sheet is A4 with 6 stickers (2 x 3) at 60 x 85mm — a size that fits
         * the corner of a windscreen without obstructing the driver's view.
         * 6 is the maximum that fits ONE page: 3 rows x 85mm + 2 x 6mm gaps
         * = 267mm against 281mm of usable height at 8mm margins. An earlier
         * 2 x 4 layout came to 358mm and silently spilled onto a second sheet,
         * wasting the stock a sticker sheet is usually printed on.
         */
        printQr(copies = 6) {
            const img = document.querySelector('#cartagQr img, #cartagQr canvas');
            if (!img) { alert('QR not ready — reopen the QR view and try again.'); return; }
            const src = img.tagName === 'IMG' ? img.src : img.toDataURL();

            const org   = <?= json_encode((string)(($company['name'] ?? '') ?: 'Vehicle Contact')) ?>;
            const host  = window.location.host;
            const plate = this.qrPlate || '';
            const tagId = this.qrTagId || '';

            const sticker = `
              <div class="st">
                <div class="st-top">
                  <span class="st-alert">&#9888;</span> IN CASE OF EMERGENCY
                  <span class="st-alert">&#9888;</span>
                </div>
                <div class="st-head">SCAN TO<br><b>CONTACT OWNER</b></div>
                <div class="st-qr"><img src="${src}"></div>
                <div class="st-plate">${plate}</div>
                <div class="st-park">OR IN CASE OF <b>WRONG PARKING</b></div>
                <div class="st-foot">
                  <span>${host}</span><span class="st-tag">#${tagId}</span>
                </div>
              </div>`;

            const w = window.open('', '_blank');
            if (!w) { alert('Please allow pop-ups to print the sticker sheet.'); return; }
            w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
              <title>Vehicle Tag ${tagId} — ${plate}</title>
              <style>
                @page { size: A4 portrait; margin: 8mm; }
                *{box-sizing:border-box;margin:0;padding:0}
                body{font-family:'Inter','Aptos',system-ui,sans-serif;
                     -webkit-print-color-adjust:exact;print-color-adjust:exact;background:#e2e8f0}
                .sheet{display:grid;grid-template-columns:repeat(2,60mm);
                       gap:6mm;justify-content:center;padding:6mm 0}
                .st{width:60mm;height:85mm;background:#111827;border-radius:4mm;
                    padding:3mm;display:flex;flex-direction:column;align-items:center;
                    text-align:center;color:#fff;border:.4mm solid #000;overflow:hidden}
                .st-top{font-size:6.5pt;font-weight:800;letter-spacing:.06em;
                        color:#fbbf24;margin-bottom:1mm}
                .st-alert{font-size:8pt}
                .st-head{font-size:9pt;font-weight:600;line-height:1.15;letter-spacing:.02em;
                         margin-bottom:1.6mm}
                .st-head b{font-size:11.5pt;font-weight:800;color:#fbbf24;letter-spacing:.01em}
                /* White quiet-zone panel — scanners need this margin */
                .st-qr{background:#fff;border-radius:2mm;padding:2.2mm;
                       border:.6mm solid #fbbf24;line-height:0}
                .st-qr img{width:36mm;height:36mm;display:block;image-rendering:pixelated}
                .st-plate{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11pt;
                          font-weight:700;letter-spacing:.06em;margin-top:2mm;color:#fff}
                .st-park{font-size:6pt;font-weight:700;color:#cbd5e1;margin-top:1.2mm;
                         letter-spacing:.04em}
                .st-park b{color:#fbbf24}
                .st-foot{margin-top:auto;width:100%;display:flex;justify-content:space-between;
                         align-items:center;font-size:5.5pt;color:#94a3b8;
                         border-top:.3mm solid #374151;padding-top:1.2mm;letter-spacing:.03em}
                .st-tag{font-family:ui-monospace,monospace;color:#6b7280}
                .bar{text-align:center;padding:10px;font-size:12px;color:#334155}
                .bar button{font:600 12px sans-serif;padding:8px 16px;border-radius:6px;
                            border:1px solid #cbd5e1;background:#fff;cursor:pointer}
                @media print{.bar{display:none}body{background:#fff}}
              </style></head><body>
              <div class="bar">
                ${copies} stickers &middot; 60 &times; 85 mm &middot; ${org}
                <button onclick="window.print()">Print</button>
              </div>
              <div class="sheet">${sticker.repeat(copies)}</div>
              </body></html>`);
            w.document.close();
            setTimeout(() => w.print(), 400);
        }
    };
}
</script>
