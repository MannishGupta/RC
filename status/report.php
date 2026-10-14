<?php

 
 
 
 
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
AppLocale::boot();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   isHttps() ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name(cfg('site.cookie_prefix', 'fusion') . '_public_session');
session_start();

 
$projects   = safeReadJson(PROJ_FILE);
$components = safeReadJson(COMP_FILE);
$updates    = safeReadJson(UPD_FILE);

[$projMap, $compMap] = buildMaps($projects, $components);
sortUpdates($updates, $compMap, $projMap);

 
$tree = [];
foreach ($projects  as $p) $tree[$p['id']] = ['info' => $p, 'types' => []];
foreach ($components as $c) {
    if (isset($tree[$c['project_id']])) $tree[$c['project_id']]['types'][$c['type']][] = $c;
}

 
$feedGroups    = [];
$updatesByComp = [];
foreach ($updates as $u) {
    if (($u['component_id'] ?? '') === 'legacy') continue;
    $updatesByComp[$u['component_id']][] = $u;
}
foreach ($tree as $projId => $pData) {
    $pName = $pData['info']['name'] ?? 'Unknown Project';
    foreach ($pData['types'] as $typeName => $jobs) {
        foreach ($jobs as $job) {
            $cid = (string)($job['id'] ?? '');
            if (isset($updatesByComp[$cid])) {
                foreach ($updatesByComp[$cid] as $u) { $feedGroups[$pName][$typeName][] = $u; }
            }
        }
    }
}

 
$projLogos = [];
foreach ($tree as $projId => $pData) {
    $pName = $pData['info']['name'] ?? 'Unknown Project';
    if (!empty($pData['info']['logo'])) {
        $projLogos[$pName] = sanitizeImagePath($pData['info']['logo']);
    }
}

$generatedOn  = AppLocale::now();
$companyLogo  = assetPath('assets/ag-image.png');
$projectLogo  = assetPath('assets/BnR.png');

 
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(AppLocale::lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Construction Progress Report — <?php echo htmlspecialchars((string)cfg('brand.company', ''), ENT_QUOTES, 'UTF-8'); ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?php echo @filemtime(__DIR__ . '/assets/app.css') ?: time(); ?>">
</head>
<body class="report-body">

<!-- Screen toolbar -->
<div class="print-toolbar no-print">
    <h1>&#128196; Construction Progress Report — <?php echo htmlspecialchars((string)cfg('brand.company', ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="btn-group">
        <a href="index.php" class="btn-back">&#8592; Back</a>
        <button class="btn-print" onclick="window.print()">&#128438; Save as PDF / Print</button>
    </div>
</div>

<!-- A4 page shell -->
<div class="page-shell">

    <!-- Report header -->
    <header class="report-header">
        <div class="logos">
            <img src="<?php echo htmlspecialchars($companyLogo, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="<?php echo htmlspecialchars((string)cfg('brand.company', ''), ENT_QUOTES, 'UTF-8'); ?>"
                 onerror="this.style.display='none'">
            <img src="<?php echo htmlspecialchars($projectLogo, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="Project Logo"
                 onerror="this.style.display='none'">
        </div>
        <div class="title-block">
            <h2><?php echo htmlspecialchars((string)cfg('brand.report_title', 'Official Progress Report'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="subtitle">Generated: <?php echo htmlspecialchars($generatedOn, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </header>

    <!-- Content -->
    <?php if (empty($feedGroups)): ?>
        <div class="empty">No construction updates available at this time.</div>
    <?php else: ?>
        <?php $cardIndex = 0; foreach ($feedGroups as $pName => $types): ?>
        <div class="project-group">
            <div class="project-heading">
                <h2><?php echo htmlspecialchars($pName, ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if (!empty($projLogos[$pName])): ?>
                <img src="<?php echo htmlspecialchars($projLogos[$pName], ENT_QUOTES, 'UTF-8'); ?>"
                     class="proj-logo"
                     alt="<?php echo htmlspecialchars($pName, ENT_QUOTES, 'UTF-8'); ?> logo"
                     onerror="this.style.display='none'">
                <?php endif; ?>
            </div>

            <?php foreach ($types as $tName => $typeUpdates): ?>
            <div class="type-group">
                <div class="type-heading">
                    <h3><?php echo htmlspecialchars($tName, ENT_QUOTES, 'UTF-8'); ?></h3>
                </div>

                <?php foreach ($typeUpdates as $u):
                    $cardIndex++;
                    $c          = $compMap[$u['component_id']] ?? null;
                    $jobLabel   = htmlspecialchars($c['job'] ?? '', ENT_QUOTES, 'UTF-8');
                    $dateLabel  = htmlspecialchars(AppLocale::date((string)($u['date'] ?? '')), ENT_QUOTES, 'UTF-8');
                    $hasImages  = !empty($u['images']);
                    $ledger     = ($cardIndex % 2 === 0) ? '' : 'ledger-odd';
                    $noImgClass = $hasImages ? '' : 'no-images';
                     
                     
                     
                    $desc       = sanitizeRichText($u['description'] ?? '');
                ?>
                <div class="update-card <?php echo $ledger; ?> <?php echo $noImgClass; ?>">

                    <!-- Text (left col) -->
                    <div class="card-text">
                        <?php if (!$hasImages): ?>
                        <span class="inline-date"><?php echo $dateLabel; ?></span>
                        <?php endif; ?>
                        <h4><?php echo $jobLabel; ?></h4>
                        <div class="desc"><?php echo $desc; ?></div>
                    </div>

                    <!-- Images (right col) — date sits above grid -->
                    <?php if ($hasImages): ?>
                    <div class="card-imgs">
                        <span class="date-badge"><?php echo $dateLabel; ?></span>
                        <div class="img-grid">
                            <?php foreach (array_slice($u['images'], 0, 4) as $img): ?>
                            <div class="img-cell">
                                <img src="<?php echo htmlspecialchars(sanitizeImagePath($img), ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="Site photograph — <?php echo $jobLabel; ?>"
                                     loading="eager">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- /update-card -->
                <?php endforeach; ?>

            </div><!-- /type-group -->
            <?php endforeach; ?>

        </div><!-- /project-group -->
        <?php endforeach; ?>
    <?php endif; ?>

</div><!-- /page-shell -->

<!-- Fixed print footer — appears on every page when printing -->
<div class="page-footer">
    <span><?php echo htmlspecialchars((string)cfg('brand.company', ''), ENT_QUOTES, 'UTF-8'); ?> &mdash; Official Construction Progress Report &mdash; <?php echo htmlspecialchars($generatedOn, ENT_QUOTES, 'UTF-8'); ?></span>
    <span>Confidential &mdash; For Authorised Recipients Only</span>
</div>

<script>
// Auto-trigger print dialog when opened with ?print=1
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', function () { setTimeout(window.print, 400); });
}
</script>
</body>
</html>
