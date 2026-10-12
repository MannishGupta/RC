<?php
// Version: 14.1.3
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | Release v14.1.3 (2026-10-12)
   Single release version across all files. See CHANGELOG.md for changes.
   ───────────────────────────────────────────────────────────────────── */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/version.php';
AppLocale::boot();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isHttps() ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name(cfg('site.cookie_prefix', 'fusion') . '_public_session');
session_start();

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}
if (empty($_SESSION['analytics_token'])) {
    $_SESSION['analytics_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['_consent_nonce'])) {
    $_SESSION['_consent_nonce'] = bin2hex(random_bytes(16));
}

// DPDP storage limitation: expire analytics past its retention period.
analyticsRetentionTick();

$consentGiven    = analyticsConsentGiven();
$consentDecided  = consentDecided();

header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; media-src 'self';");
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

$projects   = function_exists('statusLoadJson') ? statusLoadJson('projects.json') : cachedJson(PROJ_FILE);
$components = function_exists('statusLoadJson') ? statusLoadJson('components.json') : cachedJson(COMP_FILE);
$updates    = function_exists('statusLoadJson') ? statusLoadJson('updates.json') : cachedJson(UPD_FILE);

[$projMap, $compMap] = buildMaps($projects, $components);
sortUpdates($updates, $compMap, $projMap);

$perComp  = max(1, (int)cfg('feed.per_component', 12));
$maxTotal = max($perComp, (int)cfg('feed.max_total', 400));
$seen     = [];
$capped   = [];
foreach ($updates as $u) {
    $key = (string)($u['component_id'] ?? '');
    $seen[$key] = ($seen[$key] ?? 0) + 1;
    if ($seen[$key] > $perComp) { continue; }
    $capped[] = $u;
    if (count($capped) >= $maxTotal) { break; }
}
$updates = $capped;

$tree = [];
foreach ($projects as $p) $tree[$p['id']] = ['info' => $p, 'types' => []];
foreach ($components as $c) {
    if (isset($tree[$c['project_id']])) {
        $tree[$c['project_id']]['types'][$c['type']][] = $c;
    }
}

$feedGroups    = [];
$legacyUpdates = [];
$updatesByComp = [];
foreach ($updates as $u) {
    if ($u['component_id'] === 'legacy') { $legacyUpdates[] = $u; continue; }
    $updatesByComp[$u['component_id']][] = $u;
}
foreach ($tree as $projId => $pData) {
    $pName = $pData['info']['name'] ?? 'Unknown Project';
    foreach ($pData['types'] as $typeName => $jobs) {
        foreach ($jobs as $job) {
            $cid = (string)($job['id'] ?? '');
            if (isset($updatesByComp[$cid])) {
                foreach ($updatesByComp[$cid] as $u) {
                    $feedGroups[$pName][$typeName][] = $u;
                }
            }
        }
    }
}

$projLogos = [];
foreach ($tree as $projId => $pData) {
    $pName = $pData['info']['name'] ?? 'Unknown Project';
    if (!empty($pData['info']['logo'])) {
        $projLogos[$pName] = $pData['info']['logo'];
    }
}

$siteConfig = cachedJson(DATA_DIR . '/site_config.json');
$videoRaw   = trim((string)($siteConfig['video_url'] ?? ''));

if ($videoRaw !== '' && !preg_match('#^https?://#i', $videoRaw)) {
    $resolved = assetPath($videoRaw);
    if ($resolved !== '' && !str_contains($resolved, 'serve.php') && !str_starts_with($resolved, '/status/') && !preg_match('#^https?://#i', $resolved)) {
        $videoRaw = $resolved;
    }
}
[$videoUrl, $videoExists] = statusResolveVideo($videoRaw);
$videoTitle = $siteConfig['video_title'] ?? 'Site Aerial Overview';

$currentMonthYear = date('F Y');
$company          = (string)cfg('brand.company', 'Fusion Limited');
$projectName      = (string)cfg('brand.project', 'Brook & Rivulet');

$companyLogo = '';
$projectLogo = '';
if (class_exists('AppDB') && method_exists('AppDB', 'read')) {
    try {
        $__co = AppDB::read('company') ?: [];
        if (isset($__co[0]) && is_array($__co[0])) {
            $__co = $__co[0];
        }
        if (is_array($__co)) {
            $__logo = trim((string)($__co['logo'] ?? ''));
            if ($__logo === '') {
                $__logo = trim((string)($__co['favicon'] ?? ''));
            }
            if ($__logo !== '') {
                if (preg_match('#^(https?:)?//#i', $__logo) || str_starts_with($__logo, '/')) {
                    $companyLogo = $__logo;
                } else {
                    $companyLogo = '/media_serve.php?f=' . rawurlencode(ltrim($__logo, '/'));
                }
            }
            $__cn = trim((string)($__co['name'] ?? ''));
            if ($__cn !== '') {
                $company = $__cn;
            }
        }
    } catch (Throwable $e) {
        // keep defaults
    }
}
if ($companyLogo === '') {
    foreach (['assets/ag-image.png', 'assets/ag-image.svg', 'assets/logo.svg', 'assets/logo.png'] as $__try) {
        if (function_exists('statusMediaFileExists') && statusMediaFileExists($__try)) {
            $companyLogo = statusMediaUrl($__try);
            break;
        }
        if (is_file(__DIR__ . '/' . $__try)) {
            $companyLogo = '/status/' . $__try;
            break;
        }
    }
}

foreach ($projects as $__p) {
    $__pl = trim((string)($__p['logo'] ?? ''));
    if ($__pl !== '') {
        $projectLogo = $__pl;
        break;
    }
}
if ($projectLogo === '') {
    foreach (['assets/BnR.png', 'assets/BnR.svg', 'assets/project-logo.png'] as $__try) {
        if (is_file(__DIR__ . '/' . $__try)) {
            $projectLogo = $__try;
            break;
        }
    }
}
$favicon          = assetPath('assets/favicon.ico');
$locality         = (string)cfg('brand.locality', '');
$siteUrl          = appBaseUrl();
$ogImage = '';
foreach (['assets/og.jpg', 'assets/og.png', 'assets/og.jpeg', 'assets/og.webp'] as $__ogTry) {
    $__ogAbs = __DIR__ . '/' . $__ogTry;
    if (is_file($__ogAbs)) {
        $ogImage = rtrim(appBaseUrl(), '/') . '/status/' . $__ogTry . '?v=' . (string)@filemtime($__ogAbs);
        break;
    }
}
if ($ogImage === '') {
    // Fallback: first project logo via media URL, or brand colour card not available → site URL
    $ogImage = rtrim(appBaseUrl(), '/') . '/status/assets/og.jpg';
}
$pageTitle        = "Construction Progress Update ({$currentMonthYear}) | {$company}";
$pageDesc         = "View the official {$currentMonthYear} construction progress, tower milestones, and live site photographs for {$projectName} by {$company}.";

$bsCssHref = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css';
$bsCssInt  = 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB';
$bsJsHref  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js';
$bsJsInt   = 'sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI';
$faHref    = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(AppLocale::lang(), ENT_QUOTES, 'UTF-8'); ?>"<?php
    $themeMode = (string)cfg('theme.mode', 'auto');
    if (!in_array($themeMode, ['light', 'dark', 'reserve'], true)) {
        $themeMode = 'auto';
    }
    echo in_array($themeMode, ['light', 'dark', 'reserve'], true) ? ' data-theme="' . htmlspecialchars($themeMode, ENT_QUOTES, 'UTF-8') . '"' : '';
?>>
<head>

<script>
(function () {
  try {
    var cfg = document.documentElement.getAttribute('data-theme');
    if (cfg === 'light' || cfg === 'dark' || cfg === 'reserve') return;
    var t = localStorage.getItem('rc-theme') || localStorage.getItem('status-theme');
    if (t !== 'light' && t !== 'dark' && t !== 'reserve') {
      t = 'reserve';
    }
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
})();
window.statusSetTheme = function (mode) {
  if (mode !== 'light' && mode !== 'dark' && mode !== 'reserve') mode = 'light';
  try {
    localStorage.setItem('status-theme', mode);
    localStorage.setItem('rc-theme', mode);
  } catch (e) {}
  document.documentElement.setAttribute('data-theme', mode);
  document.querySelectorAll('[data-st-theme-opt]').forEach(function (b) {
    var on = b.getAttribute('data-st-theme-opt') === mode;
    b.setAttribute('aria-pressed', on ? 'true' : 'false');
  });
};
</script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#910048">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php echo seoHead([
        'title'       => $pageTitle,
        'description' => $pageDesc,
        'path'        => '',
        'type'        => 'website',
    ]); ?>

    <!-- Open Graph Metadata (WhatsApp, Facebook, LinkedIn) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($projectName . ' Construction Progress', ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Twitter Card Metadata -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">

    <meta name="keywords" content="<?php echo htmlspecialchars($company . ', ' . $projectName . ', construction update' . ($locality ? ', ' . $locality : '') . ', RERA, progress report, site photographs', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="article:modified_time" content="<?php echo date('c'); ?>">
    <link rel="manifest" href="manifest.php">
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($company . ' — construction updates', ENT_QUOTES, 'UTF-8'); ?>" href="feed.php">

    <script type="application/ld+json"><?php echo seoJsonLd($projects, $components, $updates, $compMap, $projMap); ?></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preload" as="image" href="<?php echo $ogImage; ?>">
    <link rel="stylesheet" href="<?php echo $bsCssHref; ?>" integrity="<?php echo $bsCssInt; ?>" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo $faHref; ?>" crossorigin="anonymous">

    <link rel="stylesheet" href="/status/assets/app.css?v=<?php echo @filemtime(__DIR__ . '/assets/app.css') ?: time(); ?>">

<style id="st-lb-css">
#stLightbox{display:none;position:fixed;inset:0;z-index:10000;background:rgba(15,15,15,.92);
  align-items:center;justify-content:center;flex-direction:column;padding:24px;cursor:zoom-out}
#stLightbox.is-open{display:flex}
#stLightbox .st-lb-img{max-width:min(96vw,1200px);max-height:82vh;object-fit:contain;border-radius:8px;
  box-shadow:0 8px 32px rgba(0,0,0,.45);cursor:default}
#stLightbox .st-lb-cap{color:#eee;margin-top:12px;font:600 13px/1.3 system-ui,sans-serif;text-align:center}
#stLightbox .st-lb-close{position:absolute;top:12px;right:16px;border:0;background:transparent;color:#fff;
  font-size:32px;line-height:1;cursor:pointer;opacity:.85}
.thumb-wrapper{cursor:zoom-in;position:relative}
.thumb-wrapper:focus{outline:2px solid var(--rc-accent,#0078D4);outline-offset:2px}
.thumb-wrapper img{pointer-events:none}
</style>
</head>
<?php
$__stEmbed = !empty($_GET['embed']);
$__bodyClass = $__stEmbed ? 'st-embed' : '';
$__bodyEmbed = $__stEmbed ? '1' : '0';
?>
<body data-embed="<?php echo $__bodyEmbed; ?>" class="<?php echo $__bodyClass; ?>">

<script>
(function () {
  function ensureLb() {
    var o = document.getElementById('stLightbox');
    if (o) return o;
    o = document.createElement('div');
    o.id = 'stLightbox';
    o.setAttribute('role', 'dialog');
    o.setAttribute('aria-modal', 'true');
    o.innerHTML = '<button type="button" class="st-lb-close" aria-label="Close">&times;</button>'
      + '<img class="st-lb-img" alt="">'
      + '<div class="st-lb-cap"></div>';
    (document.body || document.documentElement).appendChild(o);
    o.addEventListener('click', function (e) {
      if (e.target === o || (e.target && e.target.classList && e.target.classList.contains('st-lb-close'))) {
        o.classList.remove('is-open');
        try { document.body.style.overflow = ''; } catch (x) {}
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        o.classList.remove('is-open');
        try { document.body.style.overflow = ''; } catch (x) {}
      }
    });
    return o;
  }
  window.openLightbox = function (src, filename) {
    if (!src) return false;
    filename = (filename || 'Site photo').replace(/\.+$/, '');
    var o = ensureLb();
    var img = o.querySelector('.st-lb-img');
    var cap = o.querySelector('.st-lb-cap');
    img.src = src;
    img.alt = filename;
    cap.textContent = filename.replace(/_/g, ' · ');
    o.classList.add('is-open');
    try { document.body.style.overflow = 'hidden'; } catch (x) {}
    return false;
  };
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    var wrap = t.closest('.thumb-wrapper, [data-full-src]');
    if (!wrap) return;
    var src = wrap.getAttribute('data-full-src') || wrap.getAttribute('data-src');
    if (!src) {
      var im = wrap.querySelector('img');
      if (im) src = im.currentSrc || im.src;
    }
    if (!src) return;
    e.preventDefault();
    e.stopPropagation();
    var cap = wrap.getAttribute('data-caption') || '';
    if (!cap) {
      var im2 = wrap.querySelector('img');
      if (im2 && im2.alt) cap = im2.alt;
    }
    window.openLightbox(src, cap || 'Site photo');
  }, true);
})();
</script>

<a class="skip-link" href="#main">Skip to main content</a>

<header class="fixed-top site-nav d-flex flex-nowrap align-items-center justify-content-between px-3 px-md-4">
    <div style="width: 120px; flex-shrink: 0;">
        <?php echo $companyLogo !== '' ? statusLogoImg((string)$companyLogo, (string)$company) : ''; ?>
    </div>
    <h1 class="site-nav-title flex-grow-1 text-center px-2"><?php echo htmlspecialchars((string)cfg('brand.dashboard', 'Official Construction Progress Dashboard'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div style="width: 120px; flex-shrink: 0; text-align: right;">
        <?php if (!empty($projectLogo)) echo statusLogoImg((string)$projectLogo, (string)$projectName); ?>
    </div>
</header>

<main class="container-fluid py-4 flex-grow-1" style="max-width: 1600px;" id="main" tabindex="-1">
        <?php if (empty($projects)): ?>
        <div class="alert alert-secondary border my-4" role="status" style="color:var(--colorNeutralForeground1);background:var(--colorNeutralBackground2)">
            <strong>No construction projects published yet for this site.</strong>
            <span class="d-block small mt-1">Open the <a href="admin.php">Management Console</a> to add towers and updates.</span>
            <?php
            $__diag = function_exists('statusDataDiagnostics') ? statusDataDiagnostics() : [];
            $__tp = (string)($__diag['files']['projects.json']['tenant_path'] ?? (defined('DATA_DIR') ? DATA_DIR . '/projects.json' : ''));
            $__lp = (string)($__diag['files']['projects.json']['legacy_path'] ?? (__DIR__ . '/data/projects.json'));
            $__tb = (int)($__diag['files']['projects.json']['tenant_bytes'] ?? 0);
            $__lb = (int)($__diag['files']['projects.json']['legacy_bytes'] ?? 0);
            ?>
            <div class="small mt-2 p-2 rounded" style="background:rgba(0,0,0,.04);font-family:ui-monospace,monospace;font-size:11px;line-height:1.45">
                <div><strong>Looking for projects in:</strong></div>
                <div>1. <code><?php echo htmlspecialchars($__tp, ENT_QUOTES, 'UTF-8'); ?></code> (<?php echo $__tb; ?> bytes)</div>
                <div>2. <code><?php echo htmlspecialchars($__lp, ENT_QUOTES, 'UTF-8'); ?></code> (<?php echo $__lb; ?> bytes)</div>
                <div class="mt-1">Upload non-empty <code>projects.json</code>, <code>components.json</code>, <code>updates.json</code> into folder <strong>1</strong>
                (or into <code>status/data/</code> for auto-import). Photos/logos → <code>…/status/uploads/</code>.</div>
            </div>
        </div>
        <?php endif; ?>

    <div class="row g-4">
        <nav class="col-lg-3" id="leftSidebar" aria-label="Project directory">
            <div class="offcanvas-lg offcanvas-start bg-white" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarLabel">
                <div class="offcanvas-header border-bottom bg-light d-lg-none">
                    <h2 class="offcanvas-title fw-bold fs-6" id="sidebarLabel" style="color: var(--brand-magenta);">Project Directory</h2>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close navigation"></button>
                </div>
                <div class="offcanvas-body p-0 d-block">
                    <button class="tree-master-btn tree-selector active w-100 border-0" id="btn-master-overview" onclick="filterUpdates('all', 'Project Master Overview', this, false)" aria-pressed="true">
                        <i class="fas fa-chart-line" aria-hidden="true"></i> Master Overview
                    </button>
                    <?php foreach ($tree as $projId => $pData): ?>
                        <div class="border-bottom border-2">
                            <div class="bg-light p-3 border-bottom d-flex align-items-center justify-content-between gap-2">
                                <div>
                                    <div class="fw-black text-dark" style="font-size: 1rem; font-weight: 900;"><?php echo htmlspecialchars($pData['info']['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php if (!empty($pData['info']['rera'])): ?>
                                        <div class="text-muted" style="font-size: 0.72rem; font-weight: 600;">RERA: <?php echo htmlspecialchars($pData['info']['rera'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($pData['info']['logo'])): ?>
                                    <?php echo statusLogoImg((string)$pData['info']['logo'], (string)($pData['info']['name'] ?? '')); ?>
                                <?php endif; ?>
                            </div>
                            <?php foreach ($pData['types'] as $typeName => $jobs): $groupId = 'grp_' . md5($projId . $typeName); ?>
                                <button class="tree-category collapsed w-100 border-0 text-start" onclick="toggleTree('<?php echo $groupId; ?>', this)" aria-expanded="false" aria-controls="<?php echo $groupId; ?>">
                                    <span><?php echo htmlspecialchars($typeName, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <i class="fas fa-chevron-down chevron" aria-hidden="true"></i>
                                </button>
                                <div id="<?php echo $groupId; ?>" hidden class="tree-tower-list">
                                    <?php $ri = 0; foreach ($jobs as $job): $ri++; ?>
                                        <button class="tree-tower tree-selector w-100 border-0 text-start <?php echo $ri % 2 === 0 ? 'bg-white' : 'bg-light'; ?>"
                                            data-comp-id="<?php echo htmlspecialchars($job['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-title="<?php echo htmlspecialchars($pData['info']['name'] . ' — ' . $job['job'], ENT_QUOTES, 'UTF-8'); ?>"
                                            onclick="filterUpdates(this.dataset.compId, this.dataset.title, this, true)"
                                            data-target-id="<?php echo htmlspecialchars($job['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($job['job'], ENT_QUOTES, 'UTF-8'); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($legacyUpdates)): ?>
                        <button class="tree-master-btn text-danger border-top tree-selector w-100 border-0 text-start" onclick="filterUpdates('legacy', 'Legacy Archives', this, true)" data-target-id="legacy">
                            <i class="fas fa-archive" aria-hidden="true"></i> Legacy Archives
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <section class="col-lg-9" id="mainContent" aria-label="Update feed">
            <div id="printable-report">
                <div id="print-header" class="d-none justify-content-between align-items-center mb-4 pb-3" style="border-bottom: 2px solid var(--brand-magenta);">
                    <?php echo $companyLogo !== '' ? statusLogoImg((string)$companyLogo, (string)$company) : ''; ?>
                    <div class="text-end">
                        <h2 style="color: var(--brand-magenta); margin: 0; font-size: 1.5rem; font-weight: 800;"><?php echo htmlspecialchars((string)cfg('brand.report_title', 'Official Progress Report'), ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p style="margin: 0; color: #666; font-weight: 600;">Generated: <?php echo htmlspecialchars(date((string)cfg('locale.date_display', 'd M Y')), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>

                <div class="title-bar web-title-row d-flex flex-column flex-md-row align-items-md-center gap-3 mb-4">
                    <div class="d-flex align-items-center flex-grow-1 gap-2">
                        <button id="btn-sidebar-toggle" onclick="toggleSidebar()" class="btn-sidebar-toggle d-none d-lg-inline-flex" title="Toggle navigation pane" aria-label="Toggle navigation pane">
                            <i class="fas fa-columns" aria-hidden="true"></i>
                        </button>
                        <button class="btn-sidebar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Open navigation">
                            <i class="fas fa-bars" aria-hidden="true"></i>
                        </button>
                        <div class="search-wrap d-flex align-items-center flex-grow-1 px-3 py-1">
                            <i class="fas fa-search search-icon text-muted me-2 small" aria-hidden="true"></i>
                            <input type="search" id="searchInput" class="form-control border-0 shadow-none p-0 fw-bold" style="color: var(--brand-magenta);" placeholder="Search Master Overview…" spellcheck="false" autocomplete="off" aria-label="Search updates">
                        </div>
                    </div>
                    <?php include __DIR__ . '/actions.php'; ?>
                </div>

                <div id="updatesGrid" aria-live="polite">
                    <?php foreach ($feedGroups as $pName => $types): ?>
                        <div class="project-group-container">
                            <header class="project-group-header">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="far fa-building" style="color: var(--brand-magenta); font-size: 1.2rem;" aria-hidden="true"></i>
                                    <h2><?php echo htmlspecialchars($pName, ENT_QUOTES, 'UTF-8'); ?></h2>
                                </div>
                                <?php if (!empty($projLogos[$pName])): ?>
                                    <?php echo statusLogoImg((string)$projLogos[$pName], (string)$pName); ?>
                                <?php endif; ?>
                            </header>
                            <?php foreach ($types as $tName => $typeUpdates): ?>
                                <div class="type-group-container ms-0 ms-md-3 mb-5">
                                    <div class="type-group-header mb-4">
                                        <h3><?php echo htmlspecialchars($tName, ENT_QUOTES, 'UTF-8'); ?></h3>
                                    </div>
                                    <div class="updates-list ms-0 ms-md-3">
                                        <?php $ci = 0; foreach ($typeUpdates as $u): $ci++;
                                            $c = $compMap[$u['component_id']] ?? null;
                                            $displayJob = htmlspecialchars($c['job'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $ledger = $ci % 2 === 0 ? 'ledger-even' : 'ledger-odd';
                                            $hasImages = !empty($u['images']);
                                            $lb_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $pName.'_'.($c['name'] ?? $tName).'_'.($c['job'] ?? '').'_'.($u['date'] ?? ''));
                                        ?>
                                        <article class="update-card <?php echo $ledger; ?>" data-comp-id="<?php echo htmlspecialchars($u['component_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="row g-4 print-flex-row">
                                                <div class="print-text-col <?php echo $hasImages ? 'col-lg-7' : 'col-12'; ?>">
                                                    <div class="d-flex justify-content-end mb-2">
                                                        <time class="date-badge" datetime="<?php $d = AppLocale::parse((string)($u['date'] ?? '')); echo htmlspecialchars($d ? $d->format('Y-m-d') : '', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <i class="far fa-calendar-alt" aria-hidden="true"></i>
                                                            <?php echo htmlspecialchars(AppLocale::date((string)($u['date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                                                        </time>
                                                    </div>
                                                    <h3 class="mb-3"><?php echo $displayJob; ?></h3>
                                                    <div class="progress-description">
                                                        <?php echo sanitizeRichText($u['description'] ?? ''); ?>
                                                    </div>
                                                </div>
                                                <?php if ($hasImages): ?>
                                                    <div class="col-lg-5 print-img-col">
                                                        <div class="row g-2 print-img-grid">
                                                            <?php foreach ($u['images'] as $img): ?>
                                                                <div class="col-6 print-img-item">
                                                                    <div class="thumb-wrapper" role="button" tabindex="0" aria-label="View full-size image" data-full-src="<?php echo sanitizeImagePath($img); ?>" data-caption="<?php echo htmlspecialchars((string)($lb_name ?? 'Site photo'), ENT_QUOTES, 'UTF-8'); ?>">
                                                                        <?php echo statusPhotoImg((string)$img, 'Site photograph — ' . (string)$displayJob); ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($legacyUpdates)): ?>
                        <div class="project-group-container">
                            <header class="project-group-header" style="border-color: #6c757d !important;">
                                <i class="fas fa-archive text-secondary" aria-hidden="true"></i>
                                <h2 class="text-secondary" style="color: #6c757d !important;">Legacy Archives</h2>
                            </header>
                            <div class="type-group-container ms-0 ms-md-3 mb-5">
                                <div class="updates-list ms-0 ms-md-3">
                                    <?php $ci = 0; foreach ($legacyUpdates as $u): $ci++;
                                        $displayJob = htmlspecialchars($u['legacy_title'] ?? 'Update', ENT_QUOTES, 'UTF-8');
                                        $ledger = $ci % 2 === 0 ? 'ledger-even' : 'ledger-odd';
                                        $hasImages = !empty($u['images']);
                                        $lb_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', 'Legacy_'.($u['legacy_title'] ?? 'Archive').'_'.($u['date'] ?? ''));
                                    ?>
                                    <article class="update-card <?php echo $ledger; ?>" data-comp-id="legacy">
                                        <div class="row g-4 print-flex-row">
                                            <div class="print-text-col <?php echo $hasImages ? 'col-lg-7' : 'col-12'; ?>">
                                                <div class="d-flex justify-content-end mb-2">
                                                    <time class="date-badge" datetime="<?php $d = AppLocale::parse((string)($u['date'] ?? '')); echo htmlspecialchars($d ? $d->format('Y-m-d') : '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <i class="far fa-calendar-alt" aria-hidden="true"></i>
                                                        <?php echo htmlspecialchars($u['date'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </time>
                                                </div>
                                                <h3 class="mb-3"><?php echo $displayJob; ?></h3>
                                                <div class="progress-description">
                                                    <?php echo sanitizeRichText($u['description'] ?? ''); ?>
                                                </div>
                                            </div>
                                            <?php if ($hasImages): ?>
                                                <div class="col-lg-5 print-img-col">
                                                    <div class="row g-2 print-img-grid">
                                                        <?php foreach ($u['images'] as $img): ?>
                                                            <div class="col-6 print-img-item">
                                                                <div class="thumb-wrapper" role="button" tabindex="0" aria-label="View full-size image" data-full-src="<?php echo sanitizeImagePath($img); ?>" data-caption="<?php echo htmlspecialchars((string)($lb_name ?? 'Site photo'), ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <?php echo statusPhotoImg((string)$img, 'Legacy archive photograph'); ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wip-video-card mt-4" id="wipVideoBlock">
                <div class="wip-video-header">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 1.5px; color: var(--text-muted);"><i class="fas fa-video me-1" aria-hidden="true"></i> Featured Media</span>
                        <span class="live-badge">Work in Progress</span>
                    </div>
                    <h2 style="font-family: var(--font-display); font-size: 1.2rem; font-weight: 700; color: var(--brand-dark); margin: 0;"><?php echo htmlspecialchars($videoTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                </div>
                <?php if ($videoExists): ?>
                <video id="wipVideo" controls loop playsinline poster="<?php echo sanitizeImagePath($projectLogo); ?>" preload="metadata" aria-label="<?php echo htmlspecialchars($videoTitle, ENT_QUOTES, 'UTF-8'); ?>">
                    <source src="<?php echo sanitizeImagePath($videoUrl); ?>" type="<?php echo htmlspecialchars(videoMimeFor($videoUrl), ENT_QUOTES, 'UTF-8'); ?>">
                    Your browser cannot play this video.
                </video>
                <?php else: ?>
                <div class="wip-video-empty">
                    <i class="fas fa-film" aria-hidden="true"></i>
                    <p>No featured video is set at the moment.</p>
                </div>
                <?php endif; ?>
                <div class="p-4 pt-3">
                    <p class="mb-0 text-secondary" style="font-size: 0.95rem; line-height: 1.65;">Latest comprehensive visual documentation of ongoing structural and developmental activities across the project site.</p>
                </div>
            </div>

            <div id="emptyState" class="empty-state d-none" aria-live="polite">
                <div class="empty-state-icon"><i class="fas fa-folder-open" aria-hidden="true"></i></div>
                <h3 class="fw-bold">No Updates Found</h3>
                <p class="small" style="color: var(--text-muted);">Try adjusting your search or selecting a different category.</p>
            </div>
        </section>
    </div>
</main>

<footer role="contentinfo">
    <div class="container text-center">
        <div class="footer-shield mb-2"><i class="fas fa-shield-alt" aria-hidden="true"></i></div>
        <p class="footer-links">
            <a href="privacy.php">Privacy Notice</a>
            <a href="report.php" rel="nofollow">Printable report</a>
            <a href="feed.php">Updates feed</a>
        </p>
        <p>The timelines, images, and progress details provided in this Official Construction Dashboard are for informational transparency and stakeholder engagement. While every effort is made to present real-time and accurate site conditions, this digital report is compiled from field observations and is subject to human error, omissions, or minor temporal delays. This document does not constitute a legally binding representation of completion percentages, structural status, or regulatory certifications. <?php echo htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?> reserves the right to alter, correct, or append this data without formal notice.</p>
        <?php echo developerFooter(); ?>
    </div>
</footer>

<div class="modal fade" id="imageLightbox" tabindex="-1" aria-labelledby="lbTitle" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0" style="background:rgba(10,10,10,0.96);border-radius:12px;overflow:hidden;">
            <div class="modal-header border-0 px-4 py-3 gap-3 align-items-center" style="background:rgba(255,255,255,0.07);">
                <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                    <span id="lbTitle" class="text-white fw-semibold" style="font-size:0.83rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
                    <span id="lbFilename" class="text-white-50" style="font-size:0.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></span>
                </div>
                <a id="lbDownload" href="#" download="" target="_blank" rel="noopener noreferrer" class="btn btn-sm fw-semibold flex-shrink-0 d-flex align-items-center gap-2" style="background:var(--brand-magenta);color:#fff;border-radius:8px;font-size:0.8rem;padding:7px 16px;text-decoration:none;">
                    <i class="fas fa-download" aria-hidden="true"></i> <span class="d-none d-sm-inline">Save</span>
                </a>
                <button type="button" id="lbClose" class="btn-close btn-close-white shadow-none flex-shrink-0" aria-label="Close image viewer" style="opacity:0.7;margin:0;"></button>
            </div>
            <div class="modal-body text-center p-3 p-md-4">
                <img id="lightboxImage" src="" style="max-width:100%;max-height:78vh;object-fit:contain;border-radius:6px;display:block;margin:0 auto;" alt="Full-size site photograph">
            </div>
        </div>
    </div>
</div>

<?php if (!$consentDecided): ?>
<div class="modal fade" id="complianceModal" tabindex="-1" data-bs-backdrop="static"
     data-bs-keyboard="false" aria-labelledby="complianceTitle" aria-describedby="complianceBody" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-center text-center pt-4">
                <?php echo $companyLogo !== '' ? statusLogoImg((string)$companyLogo, (string)$company) : ''; ?>
                <h2 class="modal-title" id="complianceTitle">Transparency, and your data</h2>
            </div>
            <div class="modal-body px-4 py-3" id="complianceBody">
                <p>At <?php echo htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?>, we treat transparency as
                   the foundation of what we build. Beyond the quarterly updates we are required to file under the
                   <strong>Real Estate (Regulation and Development) Act, 2016</strong>, this dashboard gives you
                   continuous visibility of physical milestones and site realities for
                   <?php echo htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'); ?>.</p>
                <p class="mb-2"><strong>Before you continue, a word about data.</strong> This dashboard can record
                   anonymous usage — which towers and photographs are viewed — to help us improve what we publish.
                   That involves a random visitor ID and a hashed version of your IP address. It is never sold or
                   used for advertising, and it is deleted automatically after
                   <?php echo (int)cfg('dpdp.retention_days', 180); ?> days.</p>
                <p class="mb-0 hk-note">You can change your mind at any time from the
                   <a href="privacy.php">Privacy Notice</a>. Choosing “Essential only” does not limit anything
                   you can see here.</p>
            </div>
            <div class="modal-footer flex-column flex-sm-row gap-2 px-4 pb-4">
                <form method="POST" action="consent.php" class="w-100 m-0 flex-fill">
                    <input type="hidden" name="_nonce" value="<?php echo htmlspecialchars((string)$_SESSION['_consent_nonce'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="essential">
                    <button type="submit" class="btn w-100 py-2">Essential only</button>
                </form>
                <form method="POST" action="consent.php" class="w-100 m-0 flex-fill">
                    <input type="hidden" name="_nonce" value="<?php echo htmlspecialchars((string)$_SESSION['_consent_nonce'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="grant">
                    <button type="submit" class="btn btn-primary w-100 py-2">Allow anonymous analytics</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="<?php echo $bsJsHref; ?>" integrity="<?php echo $bsJsInt; ?>" crossorigin="anonymous" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const csrfToken = <?php echo json_encode($_SESSION['analytics_token'] ?? '', JSON_HEX_TAG); ?>;
    const sessionStart = Date.now();

    let visitorId = '';
    let sessionId = '';
    const analyticsOn = <?php echo (cfg('analytics.enabled', true) && $consentGiven) ? 'true' : 'false'; ?>;
    if (analyticsOn) {
        try {
            visitorId = localStorage.getItem('br_vid') || ('v_' + Math.random().toString(36).slice(2, 11));
            localStorage.setItem('br_vid', visitorId);
            sessionId = sessionStorage.getItem('br_sid') || ('s_' + Math.random().toString(36).slice(2, 11));
            sessionStorage.setItem('br_sid', sessionId);
        } catch (e) { }
    }

    window.logAnalytics = function (action, details = {}) {
        if (!analyticsOn) { return; }
        fetch('analytics.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: csrfToken, visitor_id: visitorId, session_id: sessionId, action, url: location.pathname, details })
        }).catch(() => {});
    };

    const consentDialog = document.getElementById('complianceModal');
    if (consentDialog && typeof bootstrap !== 'undefined') {
        new bootstrap.Modal(consentDialog).show();
    }

    localStorage.setItem('lastFilterCompId', 'all');
    const si = document.getElementById('searchInput');
    if (si) { si.value = ''; si.placeholder = 'Search Master Overview…'; }
    const defBtn = document.getElementById('btn-master-overview');
    if (defBtn) filterUpdates('all', 'Project Master Overview', defBtn, false);

    logAnalytics('page_load', { referrer: document.referrer, screen: screen.width + 'x' + screen.height });

    window.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            logAnalytics('time_spent', { seconds: Math.round((Date.now() - sessionStart) / 1000) });
        }
    });

    const wipVideo = document.getElementById('wipVideo');
    if (wipVideo) {
        wipVideo.addEventListener('play', function () {
            logAnalytics('video_play', { title: '<?php echo htmlspecialchars(addslashes($videoTitle), ENT_QUOTES, 'UTF-8'); ?>' });
        }, { once: true });
    }

    function updateGroupVisibility() {
        document.querySelectorAll('.type-group-container').forEach(g => {
            const visible = Array.from(g.querySelectorAll('.update-card')).some(c => !c.classList.contains('d-none'));
            g.classList.toggle('d-none', !visible);
        });
        document.querySelectorAll('.project-group-container').forEach(g => {
            const visible = Array.from(g.querySelectorAll('.type-group-container')).some(t => !t.classList.contains('d-none'));
            g.classList.toggle('d-none', !visible);
        });
    }

    if (si) {
        let debounceTimer;
        si.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                const term = si.value.toLowerCase().trim();
                const filter = localStorage.getItem('lastFilterCompId') || 'all';
                let count = 0;
                document.querySelectorAll('.update-card').forEach(card => {
                    const matchSearch = !term || card.textContent.toLowerCase().includes(term);
                    const matchFilter = filter === 'all' || card.dataset.compId === filter;
                    const show = matchSearch && matchFilter;
                    card.classList.toggle('d-none', !show);
                    if (show) count++;
                });
                updateGroupVisibility();
                document.getElementById('emptyState').classList.toggle('d-none', count > 0);
                const wip = document.getElementById('wipVideoBlock');
                if (wip) wip.classList.toggle('d-none', term.length > 0 || filter !== 'all');
            }, 150);
        });
    }

    window.filterUpdates = function (compId, title, el, saveState) {
        document.querySelectorAll('.tree-selector').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        if (el) { el.classList.add('active'); el.setAttribute('aria-pressed', 'true'); }
        if (si) { si.value = ''; si.placeholder = 'Search ' + title + '…'; }

        if (saveState) {
            localStorage.setItem('lastFilterCompId', compId);
            localStorage.setItem('lastFilterTitle', title);
        }

        const wip = document.getElementById('wipVideoBlock');
        if (wip) wip.classList.toggle('d-none', compId !== 'all');

        let count = 0;
        document.querySelectorAll('.update-card').forEach(card => {
            const show = compId === 'all' || card.dataset.compId === compId;
            card.classList.toggle('d-none', !show);
            if (show) count++;
        });

        updateGroupVisibility();
        document.getElementById('emptyState').classList.toggle('d-none', count > 0);

        if (window.innerWidth < 992) {
            const oc = document.getElementById('sidebarMenu');
            if (oc) (bootstrap.Offcanvas.getInstance(oc) || new bootstrap.Offcanvas(oc)).hide();
        }
        if (saveState) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            logAnalytics('directory_filter', { comp_id: compId, title });
        }
    };

    window.toggleSidebar = function () {
        const sb = document.getElementById('leftSidebar');
        const mc = document.getElementById('mainContent');
        if (!sb || !mc) return;
        const hidden = sb.classList.contains('d-none');
        sb.classList.toggle('d-none', !hidden);
        mc.classList.toggle('col-lg-12', !hidden);
        mc.classList.toggle('col-lg-9', hidden);
        localStorage.setItem('sidebarHidden', (!hidden) ? '1' : '0');
    };

    if (localStorage.getItem('sidebarHidden') === '1') {
        const sb = document.getElementById('leftSidebar');
        const mc = document.getElementById('mainContent');
        if (sb) sb.classList.add('d-none');
        if (mc) { mc.classList.add('col-lg-12'); mc.classList.remove('col-lg-9'); }
    }

    window.toggleTree = function (id, btn) {
        const el = document.getElementById(id);
        if (!el) return;
        const isHidden = el.hidden;
        el.hidden = !isHidden;
        btn.classList.toggle('collapsed', !isHidden);
        btn.setAttribute('aria-expanded', String(isHidden));
    };

    let _lbModal = null;
    function getLbModal() {
        const el = document.getElementById('imageLightbox');
        if (!_lbModal) _lbModal = new bootstrap.Modal(el, { keyboard: true, backdrop: true });
        return _lbModal;
    }
    document.getElementById('lbClose')?.addEventListener('click', () => getLbModal().hide());

    window.openLightbox = function (src, filename) {
        if (!src) return;
        filename = (filename || 'Site_Image').replace(/\.+$/, '');
        var overlay = document.getElementById('stLightbox');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'stLightbox';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.innerHTML = '<button type="button" class="st-lb-close" aria-label="Close">&times;</button>'
                + '<img class="st-lb-img" alt="">'
                + '<div class="st-lb-cap"></div>';
            document.body.appendChild(overlay);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay || e.target.classList.contains('st-lb-close')) {
                    overlay.classList.remove('is-open');
                    document.body.style.overflow = '';
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    overlay.classList.remove('is-open');
                    document.body.style.overflow = '';
                }
            });
        }
        var img = overlay.querySelector('.st-lb-img');
        var cap = overlay.querySelector('.st-lb-cap');
        img.src = src;
        img.alt = filename.replace(/_/g, ' ');
        cap.textContent = filename.replace(/_/g, ' · ');
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        try {
            var imgEl = document.getElementById('lightboxImage');
            if (imgEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                imgEl.src = src;
                var t = document.getElementById('lbTitle');
                if (t) t.textContent = filename.replace(/_/g, ' · ');
            }
        } catch (e) {}
    };
});
</script>

<div class="st-theme-seg" role="group" aria-label="Colour theme" style="position:fixed;bottom:16px;right:16px;z-index:40">
  <button type="button" data-st-theme-opt="light" aria-pressed="false" onclick="statusSetTheme('light')">Light</button>
  <button type="button" data-st-theme-opt="dark" aria-pressed="false" onclick="statusSetTheme('dark')">Dark</button>
  <button type="button" data-st-theme-opt="reserve" aria-pressed="false" onclick="statusSetTheme('reserve')">Reserve</button>
</div>
<script>
(function(){ var t=document.documentElement.getAttribute('data-theme')||'reserve';
  document.querySelectorAll('[data-st-theme-opt]').forEach(function(b){
    b.setAttribute('aria-pressed', b.getAttribute('data-st-theme-opt')===t?'true':'false');
  });
})();
</script>

</body>
</html>