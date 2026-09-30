<?php
$GLOBALS['__status_placeholder'] = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22400%22%20height%3D%22300%22%20viewBox%3D%220%200%20400%20300%22%3E%3Crect%20fill%3D%22%23f1f5f9%22%20width%3D%22400%22%20height%3D%22300%22/%3E%3Crect%20x%3D%2224%22%20y%3D%2224%22%20width%3D%22352%22%20height%3D%22252%22%20rx%3D%2216%22%20fill%3D%22%23e2e8f0%22/%3E%3Ctext%20x%3D%22200%22%20y%3D%22145%22%20text-anchor%3D%22middle%22%20font-family%3D%22Segoe%20UI%2Csystem-ui%2Csans-serif%22%20font-size%3D%2220%22%20font-weight%3D%22700%22%20fill%3D%22%2364748b%22%3EComing%20Soon%3C/text%3E%3Ctext%20x%3D%22200%22%20y%3D%22175%22%20text-anchor%3D%22middle%22%20font-family%3D%22Segoe%20UI%2Csystem-ui%2Csans-serif%22%20font-size%3D%2213%22%20fill%3D%22%2394a3b8%22%3EWork%20in%20Progress%3C/text%3E%3C/svg%3E';
// app/views/tabs/tab_status.php — Version: 260917.01
//
// Surfaces the standalone Construction Progress Portal (/status/) inside the
// Resource Center.
//
// WHY THIS IS A READER, NOT A MERGE
// The portal is ~3,600 lines and fully self-contained: its own session name
// (fusion_public_session), its own admin login, its own CSP headers, its own
// data layer, and its own release line (v12.2.1). It is also LIVE and
// public-facing at fusionlimited.in/status/, where customers check
// construction progress.
//
// Merging it would mean rewriting all of that against this app's session,
// auth and AppDB — weeks of work, and every hour of it carrying the risk of
// breaking a customer-facing site. Iframing is not an option either: the
// portal sends `default-src 'self'`, which blocks framing, and it maintains a
// separate login anyway.
//
// So this tab reads the portal's JSON files directly (read-only, never
// written) and deep-links into it for anything that changes data. The portal
// keeps its own release cycle; this tab simply reflects whatever it holds.
//
// If the portal is not installed, the tab says so plainly rather than
// erroring — this is an optional module, not a dependency.
if (!defined('BASE_PATH')) exit;

$statusDir  = BASE_PATH . '/status';
$statusData = $statusDir . '/data';
$statusUp   = 'status/uploads/';
// Host-aware public portal URL (works on rc.arthsathi.com and rc.fusionlimited.in)
$_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
       || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$_host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
$_origin = $_host !== '' ? (($_https ? 'https://' : 'http://') . $_host) : '';
$stInstalledLocal = is_dir($statusDir);
$portalUrl  = $stInstalledLocal ? 'status/' : ($_origin ? $_origin . '/status/' : 'https://fusionlimited.in/status/');
$adminUrl   = $stInstalledLocal ? 'status/admin.php' : ($portalUrl);
$publicPortalUrl = $stInstalledLocal
    ? (($_origin !== '' ? $_origin : '') . '/status/')
    : 'https://fusionlimited.in/status/';
if ($publicPortalUrl === '/status/') {
    $publicPortalUrl = 'status/';
}

/** Read one of the portal's stores. Read-only; never writes back. */
$stRead = function (string $file) use ($statusData): array {
    $p = $statusData . '/' . $file;
    if (!is_readable($p)) return [];
    $d = json_decode((string)@file_get_contents($p), true);
    return is_array($d) ? $d : [];
};

$stInstalled = is_dir($statusDir) && is_dir($statusData);
$projects    = $stInstalled ? $stRead('projects.json')   : [];
$components  = $stInstalled ? $stRead('components.json') : [];
$updates     = $stInstalled ? $stRead('updates.json')    : [];

// ── Index components by project, and updates by component ────────────────
$compByProject = [];
$compById      = [];
foreach ($components as $c) {
    if (!is_array($c)) continue;
    $cid = (string)($c['id'] ?? '');
    if ($cid !== '') $compById[$cid] = $c;
    $pid = (string)($c['project_id'] ?? '');
    if ($pid !== '') $compByProject[$pid][] = $c;
}

$updByComponent = [];
foreach ($updates as $u) {
    if (!is_array($u)) continue;
    $k = (string)($u['component_id'] ?? '');
    $updByComponent[$k][] = $u;   // '' groups the project-level updates
}

/**
 * The portal stores dates as "01-Jan-2024". Parse to a timestamp for sorting,
 * falling back to 0 so an unparseable date sinks rather than throwing.
 */
$stTs = function (string $d): int {
    $d = trim($d);
    if ($d === '') return 0;
    $t = strtotime(str_replace('-', ' ', $d));
    return $t ?: 0;
};

// Most recent updates across every project.
$recent = array_values(array_filter($updates, 'is_array'));
usort($recent, fn($a, $b) => $stTs((string)($b['date'] ?? '')) <=> $stTs((string)($a['date'] ?? '')));
$recent = array_slice($recent, 0, 12);

$totalImages = 0;
foreach ($updates as $u) {
    if (is_array($u) && !empty($u['images']) && is_array($u['images'])) $totalImages += count($u['images']);
}

// The portal keeps its own version line — show it rather than this app's, so
// it is obvious the two are versioned independently.
$stVersion = is_readable($statusDir . '/VERSION')
    ? trim((string)@file_get_contents($statusDir . '/VERSION'))
    : '';
?>

<div class="w-full flex flex-col space-y-4" x-data="{ open: '' }">

<?php if (!$stInstalled): ?>

    <div class="flex flex-col items-center justify-center h-64 text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl w-full bg-slate-50/50 gap-4">
        <i class="fa-solid fa-helmet-safety text-4xl text-slate-300"></i>
        <div class="text-center">
            <p class="text-sm font-semibold text-slate-600">Progress Portal not installed on this host.</p>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                Upload the portal to a <span class="font-mono">status/</span> folder for an in-dashboard summary,
                or open the live public portal below.
            </p>
        </div>
        <a href="<?= htmlspecialchars($publicPortalUrl) ?>" target="_blank" rel="noopener noreferrer"
           class="px-5 py-2.5 bg-slate-900 hover:bg-blue-600 text-white text-xs font-bold rounded-lg transition flex items-center gap-2">
            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
            Open Construction Progress Portal
        </a>
    </div>

<?php else: ?>

    <!-- Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex flex-col md:flex-row md:items-center gap-4">
        <div class="w-11 h-11 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center border border-amber-100 shrink-0">
            <i class="fa-solid fa-helmet-safety"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-black text-slate-800">Construction Progress Portal</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Live view of the public portal's data.
                <?php if ($stVersion): ?>
                    Portal release <span class="font-mono font-bold text-slate-600"><?= htmlspecialchars($stVersion) ?></span>
                    &mdash; versioned separately from this dashboard.
                <?php endif; ?>
            </p>
        </div>
        <div class="flex gap-2 shrink-0 flex-wrap">
            <a href="<?= htmlspecialchars($portalUrl) ?>" target="_blank" rel="noopener noreferrer"
               class="px-4 py-2 bg-slate-900 hover:bg-blue-600 text-white text-xs font-bold rounded-lg transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> Open Portal
            </a>
            <a href="<?= htmlspecialchars($publicPortalUrl) ?>" target="_blank" rel="noopener noreferrer"
               class="px-4 py-2 bg-white hover:bg-amber-50 border border-slate-300 text-slate-700 text-xs font-bold rounded-lg transition flex items-center gap-2"
               title="Public site at fusionlimited.in/status/">
                <i class="fa-solid fa-globe text-[10px]"></i> Public Site
            </a>
            <?php if ($isAdmin): ?>
            <a href="<?= htmlspecialchars($adminUrl) ?>" target="_blank" rel="noopener noreferrer"
               class="px-4 py-2 bg-white hover:bg-slate-100 border border-slate-300 text-slate-700 text-xs font-bold rounded-lg transition flex items-center gap-2">
                <i class="fa-solid fa-pen text-[10px]"></i> Manage
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Counts -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php foreach ([
            ['Projects',   count($projects),   'fa-building',      'blue'],
            ['Components', count($components), 'fa-layer-group',   'indigo'],
            ['Updates',    count($updates),    'fa-clock-rotate-left', 'emerald'],
            ['Photos',     $totalImages,       'fa-image',         'violet'],
        ] as [$label, $n, $icon, $tone]): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="flex items-center gap-2 mb-1">
                <i class="fa-solid <?= $icon ?> text-<?= $tone ?>-500 text-xs"></i>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?= $label ?></span>
            </div>
            <div class="text-2xl font-black text-slate-800"><?= (int)$n ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Projects -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <?php foreach ($projects as $p):
            if (!is_array($p)) continue;
            $pid   = (string)($p['id'] ?? '');
            $comps = $compByProject[$pid] ?? [];
            $logo  = trim((string)($p['logo'] ?? ''));

            // Newest update across this project's components.
            $latest = 0;
            foreach ($comps as $c) {
                foreach ($updByComponent[(string)($c['id'] ?? '')] ?? [] as $u) {
                    $latest = max($latest, $stTs((string)($u['date'] ?? '')));
                }
            }
        ?>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
            <div class="p-5 flex items-start gap-4 border-b border-slate-100">
                <?php
                    $ph = $GLOBALS['__status_placeholder'] ?? '';
                    if ($logo): ?>
                    <img src="<?= htmlspecialchars('status/' . ltrim($logo, '/')) ?>"
                         alt="" class="w-12 h-12 rounded-lg object-contain bg-slate-50 border border-slate-100 shrink-0"
                         onerror="this.onerror=null;this.src='<?= htmlspecialchars($ph, ENT_QUOTES) ?>';">
                <?php else: ?>
                    <img src="<?= htmlspecialchars($ph, ENT_QUOTES) ?>"
                         alt="Coming Soon — Work in Progress"
                         class="w-12 h-12 rounded-lg object-cover bg-slate-50 border border-slate-100 shrink-0">
                <?php endif; ?>
                <div class="min-w-0 flex-1">
                    <h3 class="font-black text-slate-800 leading-tight"><?= htmlspecialchars((string)($p['name'] ?? 'Untitled Project')) ?></h3>
                    <?php if (!empty($p['rera'])): ?>
                        <div class="text-[10px] font-mono font-bold text-slate-400 mt-1">
                            RERA <?= htmlspecialchars((string)$p['rera']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex items-center gap-3 mt-2 text-[11px] text-slate-500">
                        <span><i class="fa-solid fa-layer-group mr-1 text-slate-300"></i><?= count($comps) ?> component(s)</span>
                        <?php if ($latest): ?>
                            <span><i class="fa-regular fa-clock mr-1 text-slate-300"></i>Updated <?= date('d M Y', $latest) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($comps): ?>
            <div class="px-5 py-3">
                <button @click="open = (open === '<?= htmlspecialchars($pid) ?>' ? '' : '<?= htmlspecialchars($pid) ?>')"
                        class="text-[11px] font-bold text-slate-500 hover:text-blue-600 transition flex items-center gap-1.5">
                    <i class="fa-solid text-[9px]" :class="open === '<?= htmlspecialchars($pid) ?>' ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    <span x-text="open === '<?= htmlspecialchars($pid) ?>' ? 'Hide components' : 'Show components'"></span>
                </button>

                <div x-show="open === '<?= htmlspecialchars($pid) ?>'" x-cloak class="mt-3 space-y-1.5">
                    <?php foreach ($comps as $c):
                        $cid  = (string)($c['id'] ?? '');
                        $cUps = $updByComponent[$cid] ?? [];
                    ?>
                    <div class="flex items-center gap-2 text-xs py-1.5 border-b border-slate-50 last:border-0">
                        <span class="font-semibold text-slate-700 truncate flex-1"><?= htmlspecialchars((string)($c['job'] ?? '—')) ?></span>
                        <span class="text-[10px] text-slate-400 truncate max-w-[45%]"><?= htmlspecialchars((string)($c['type'] ?? '')) ?></span>
                        <span class="text-[10px] font-bold text-slate-500 shrink-0"><?= count($cUps) ?> upd</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent updates -->
    <?php if ($recent): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
            <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider">Recent Site Updates</h3>
        </div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($recent as $u):
                $cid   = (string)($u['component_id'] ?? '');
                $comp  = $compById[$cid] ?? null;
                $imgs  = (is_array($u['images'] ?? null)) ? $u['images'] : [];
                $ts    = $stTs((string)($u['date'] ?? ''));
                $desc  = trim(strip_tags((string)($u['description'] ?? '')));
            ?>
            <div class="px-5 py-3 flex items-start gap-3 hover:bg-slate-50/60 transition">
                <?php
                    $ph = $GLOBALS['__status_placeholder'] ?? '';
                    if ($imgs): ?>
                    <img src="<?= htmlspecialchars('status/' . ltrim((string)$imgs[0], '/')) ?>"
                         alt="" loading="lazy"
                         class="w-14 h-14 rounded-lg object-cover bg-slate-100 border border-slate-200 shrink-0"
                         onerror="this.onerror=null;this.src='<?= htmlspecialchars($ph, ENT_QUOTES) ?>';">
                <?php else: ?>
                    <img src="<?= htmlspecialchars($ph, ENT_QUOTES) ?>"
                         alt="Coming Soon — Work in Progress" loading="lazy"
                         class="w-14 h-14 rounded-lg object-cover bg-slate-50 border border-slate-200 shrink-0">
                <?php endif; ?>
                <div class="min-w-0 flex-1">
                    <div class="text-xs font-bold text-slate-700 truncate">
                        <?= htmlspecialchars($comp ? (string)($comp['job'] ?? 'Update') : 'Project update') ?>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                        <?= $ts ? htmlspecialchars(date('d M Y', $ts)) : htmlspecialchars((string)($u['date'] ?? '')) ?>
                        <?php if (count($imgs) > 1): ?>
                            &middot; <?= count($imgs) ?> photos
                        <?php endif; ?>
                    </div>
                    <?php if ($desc !== ''): ?>
                        <p class="text-[11px] text-slate-500 mt-1 line-clamp-2"><?= htmlspecialchars(mb_substr($desc, 0, 160)) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <p class="text-[11px] text-slate-400 text-center leading-relaxed px-4">
        This tab reads the portal's data directly and never writes to it.
        Use <span class="font-semibold">Manage</span> to add projects, components or site updates.
    </p>

<?php endif; ?>

</div>
