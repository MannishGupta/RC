<?php
/* ─────────────────────────────────────────────────────────────────────────
   status-tab.php — Construction Progress as a resource-centre module.

   Written against github.com/MannishGupta/RC as it stands on main:

     · emits NO <html>, <head>, <body> or headers — the host app owns the shell,
       and card views there are included with `require $viewFile` inside an
       output buffer, so this file must be safe in that position;
     · starts no session and sets no cookies — the host app's session is already
       open, and calling session_start() again would raise a notice;
     · all markup sits inside .rc-status and loads assets/app-embed.css, a build
       of app.css in which every one of 273 selectors is prefixed, so nothing
       can reach the resource centre's own styles;
     · honours ModuleRegistry::isEnabled('status') when that class is loaded, so
       the per-tenant modules.json switch works. ModuleRegistry::isEnabled()
       returns true for ids it does not know, so the tab works before anyone
       edits catalogue();
     · read-only. No POST handler, no CSRF surface, no admin action. Editing
       stays in the standalone console at status/admin.php.

   HOW TO MOUNT IT  (three lines in the resource centre)

     1. In the tab dispatch:
            if ($tab === 'status') { require __DIR__ . '/status/status-tab.php'; }

     2. In app/ModuleRegistry.php → catalogue(), to make it configurable:
            'status' => [
                'label'    => 'Construction Status',
                'group'    => 'Projects',
                'features' => ['status_feed' => 'Tower-wise progress and photographs'],
            ],

     3. Add the tab to the navigation however the shell builds it, pointing at
        ?tab=status

   Nothing else in the resource centre needs to change. The portal keeps its own
   data/ directory, so it is tenant-independent; see INTEGRATION.md if you want
   per-tenant status data instead.
   ───────────────────────────────────────────────────────────────────────── */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/version.php';
AppLocale::boot();

/* Respect the host's per-tenant module switch when it is available. */
if (cfg('integration.respect_registry', true) && class_exists('ModuleRegistry')) {
    $moduleId = (string)cfg('integration.module_id', 'status');
    if (method_exists('ModuleRegistry', 'isEnabled') && !ModuleRegistry::isEnabled($moduleId)) {
        return;
    }
}

$rcProjects   = function_exists('statusLoadJson') ? statusLoadJson('projects.json') : cachedJson(PROJ_FILE);
$rcComponents = function_exists('statusLoadJson') ? statusLoadJson('components.json') : cachedJson(COMP_FILE);
$rcUpdates    = function_exists('statusLoadJson') ? statusLoadJson('updates.json') : cachedJson(UPD_FILE);
[$rcProjMap, $rcCompMap] = buildMaps($rcProjects, $rcComponents);
sortUpdates($rcUpdates, $rcCompMap, $rcProjMap);

/* Same per-tower cap as the public page, so the embed cannot become enormous. */
$rcPerComp = max(1, (int)cfg('feed.per_component', 12));
$rcSeen    = [];
$rcFeed    = [];
foreach ($rcUpdates as $u) {
    $k = (string)($u['component_id'] ?? '');
    $rcSeen[$k] = ($rcSeen[$k] ?? 0) + 1;
    if ($rcSeen[$k] > $rcPerComp) { continue; }
    $rcFeed[] = $u;
}

/* Group by project then component type, mirroring the standalone layout. */
$rcTree = [];
foreach ($rcProjects as $p) { $rcTree[(string)$p['id']] = ['info' => $p, 'types' => []]; }
foreach ($rcComponents as $c) {
    if (isset($rcTree[(string)$c['project_id']])) {
        $rcTree[(string)$c['project_id']]['types'][(string)$c['type']][] = $c;
    }
}
$rcByComp = [];
foreach ($rcFeed as $u) { $rcByComp[(string)($u['component_id'] ?? '')][] = $u; }

$rcGroups = [];
foreach ($rcTree as $pData) {
    $pName = (string)($pData['info']['name'] ?? 'Unknown Project');
    foreach ($pData['types'] as $typeName => $jobs) {
        foreach ($jobs as $job) {
            $cid = (string)($job['id'] ?? '');
            foreach ($rcByComp[$cid] ?? [] as $u) {
                $rcGroups[$pName][(string)$typeName][] = $u;
            }
        }
    }
}

$rcTotal   = count($rcFeed);
$rcCompany = (string)cfg('brand.company', '');
$rcStandalone = canonicalUrl('');
$e = static fn(?string $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/status/assets/app-embed.css?v=<?php echo @filemtime(__DIR__ . '/assets/app-embed.css') ?: time(); ?>">

<div class="rc-status" id="rc-status">
    <div class="rc-status-head">
        <div>
            <h2 class="rc-status-title">Construction Progress</h2>
            <p class="rc-status-sub">
                <?php echo $e((string)cfg('brand.project', '')); ?> ·
                <?php echo (int)$rcTotal; ?> update<?php echo $rcTotal === 1 ? '' : 's'; ?> ·
                tower-wise milestones and site photographs
            </p>
        </div>
        <div class="rc-status-actions">
            <a class="btn btn-primary btn-sm" href="<?php echo $e($rcStandalone); ?>" target="_blank" rel="noopener">
                Open full dashboard
                <span class="visually-hidden">(opens in a new tab)</span>
            </a>
            <a class="btn btn-sm" href="<?php echo $e(canonicalUrl('report.php')); ?>" target="_blank" rel="noopener">
                Printable report
                <span class="visually-hidden">(opens in a new tab)</span>
            </a>
        </div>
    </div>

    <?php if (empty($rcGroups)): ?>
        <div class="empty-state">
            <div class="empty-state-icon" aria-hidden="true">&#9744;</div>
            <h3>No construction updates published yet</h3>
            <p class="hk-note">Publish from the <a href="/status/admin.php" target="_blank" rel="noopener">Management Console</a>
            (access key). Data is stored per tenant in <code>tenants/…/data/status/</code>.
            If you had content on the old shared <code>status/data/</code> folder, copy those JSON files into the tenant path.</p>
        </div>
    <?php else: ?>
        <?php foreach ($rcGroups as $pName => $types): ?>
            <section class="rc-status-project" aria-labelledby="rcp-<?php echo $e(md5((string)$pName)); ?>">
                <header class="project-group-header">
                    <h3 id="rcp-<?php echo $e(md5((string)$pName)); ?>"><?php echo $e((string)$pName); ?></h3>
                    <?php
                    $rera = '';
                    foreach ($rcProjects as $p) {
                        if ((string)($p['name'] ?? '') === (string)$pName && !empty($p['rera'])) {
                            $rera = (string)$p['rera'];
                            break;
                        }
                    }
                    ?>
                    <?php if ($rera !== ''): ?>
                        <span class="date-badge">RERA <?php echo $e($rera); ?></span>
                    <?php endif; ?>
                </header>

                <?php foreach ($types as $tName => $rows): ?>
                    <div class="type-group-header"><h4><?php echo $e((string)$tName); ?></h4></div>
                    <?php foreach ($rows as $u): ?>
                        <?php
                        $c   = $rcCompMap[$u['component_id'] ?? ''] ?? null;
                        $job = $c ? (string)($c['job'] ?? '') : (string)($u['legacy_title'] ?? 'Update');
                        $imgs = array_slice((array)($u['images'] ?? []), 0, 4);
                        $dIso = AppLocale::parse((string)($u['date'] ?? ''));
                        ?>
                        <article class="update-card">
                            <div class="rc-status-card">
                                <div class="rc-status-card-text">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
                                        <h5 class="mb-0"><?php echo $e($job); ?></h5>
                                        <time class="date-badge" datetime="<?php echo $e($dIso ? $dIso->format('Y-m-d') : ''); ?>">
                                            <?php echo $e(AppLocale::date((string)($u['date'] ?? ''))); ?>
                                        </time>
                                    </div>
                                    <div class="progress-description">
                                        <?php echo sanitizeRichText((string)($u['description'] ?? '')); ?>
                                    </div>
                                </div>
                                <?php if ($imgs): ?>
                                    <div class="rc-status-card-imgs">
                                        <?php foreach ($imgs as $img): ?>
                                            <a class="thumb-wrapper" href="<?php echo $e(canonicalUrl(assetPath((string)$img))); ?>"
                                               target="_blank" rel="noopener">
                                                <img class="update-thumb" loading="lazy" decoding="async"
                                                     src="<?php echo $e(canonicalUrl(assetPath((string)$img))); ?>"
                                                     alt="Site photograph — <?php echo $e($job); ?>, <?php echo $e(AppLocale::date((string)($u['date'] ?? ''))); ?>">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="rc-status-foot">
        Construction Progress module
        <?php if (cfg('footer.show_version', true)): ?>
            · <?php echo $e(statusVersionLabel()); ?>
            <?php if (statusVersionIsInherited()): ?>
                <span class="hk-note">(build from host application)</span>
            <?php endif; ?>
        <?php endif; ?>
        · <a href="<?php echo $e(canonicalUrl('privacy.php')); ?>" target="_blank" rel="noopener">Privacy</a>
    </p>
</div>
