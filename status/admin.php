<?php
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | Release v14.1.0 (2026-10-11)
   Single release version across all files. See CHANGELOG.md for changes.
   ───────────────────────────────────────────────────────────────────── */
declare(strict_types=1);

require_once __DIR__ . '/admin_backend.php';

$bsCssHref = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css';
$bsCssInt  = 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB';
$bsJsHref  = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js';
$bsJsInt   = 'sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI';
$faHref    = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css';
$faInt     = 'sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg==';
$xlsxSrc   = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';
$quillCss  = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css';
$quillJs   = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js';

// CSRF token is derived via HMAC — no session read required
$token = makeCsrfToken();
function signedUrl(string $base): string {
    global $token;
    $sep = str_contains($base, '?') ? '&' : '?';
    return $base . $sep . '_t=' . urlencode($token);
}

if (!isset($_SESSION['logged_in'])):
    $locked     = !empty($loginLockedMinutes);
    $errorMsg   = $locked
        ? 'Too many failed attempts. Try again in about ' . (int)$loginLockedMinutes . ' minute(s).'
        : (isset($_POST['login']) ? 'Incorrect Access Key.' : '');
    $sessionMsg = isset($_GET['msg']) ? (string)$_GET['msg'] : '';
    $brandCo    = (string)cfg('brand.company', 'Resource Centre');
    $brandPr    = (string)cfg('brand.project', 'Construction Progress');
    $verLabel   = defined('STATUS_RELEASE') ? (string)STATUS_RELEASE : (defined('STATUS_VERSION') ? (string)STATUS_VERSION : '');
    $e = static fn(?string $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $e(AppLocale::lang()); ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Admin Login · <?php echo $e($brandCo); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#0B1220">
    <link rel="icon" href="<?php echo $e(sanitizeImagePath($favicon ?? 'assets/favicon.ico')); ?>" type="image/x-icon">
    <style>
    html,body{margin:0;min-height:100%;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}
    body.st-admin-login{background:#0B1220;color:#E8EEF7;min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
    .st-net{position:fixed;inset:0;width:100%;height:100%;z-index:0;pointer-events:none}
    .st-orbs{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
    .st-orbs i{position:absolute;border-radius:50%;filter:blur(60px);opacity:.35}
    .st-orbs i:nth-child(1){width:42vw;height:42vw;left:-12%;top:-12%;background:#1d4ed8}
    .st-orbs i:nth-child(2){width:32vw;height:32vw;right:-8%;bottom:8%;background:#0e7490}
    .st-orbs i:nth-child(3){width:28vw;height:28vw;left:38%;bottom:-12%;background:#4c1d95}
    .st-scan{position:fixed;inset:0;z-index:1;pointer-events:none;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(255,255,255,.015) 3px);opacity:.5}
    .st-grid{position:fixed;inset:0;z-index:1;pointer-events:none;background-image:linear-gradient(rgba(56,189,248,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(56,189,248,.06) 1px,transparent 1px);background-size:48px 48px;mask-image:radial-gradient(ellipse at center,black 20%,transparent 70%)}
    .st-card{position:relative;z-index:2;width:min(420px,92vw);padding:1.75rem 1.5rem 1.35rem;border-radius:16px;background:rgba(15,23,42,.72);border:1px solid rgba(148,163,184,.28);box-shadow:0 24px 64px rgba(0,0,0,.45);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}
    .st-card h1{margin:0 0 .25rem;font-size:1.15rem;font-weight:800;letter-spacing:.02em;color:#F8FAFC}
    .st-card .sub{margin:0 0 1rem;font-size:.8rem;color:#94A3B8}
    .st-card label{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;margin-bottom:.35rem}
    .st-card input[type=password]{width:100%;box-sizing:border-box;padding:.7rem .85rem;border-radius:10px;border:1px solid rgba(148,163,184,.35);background:rgba(2,6,23,.65);color:#F1F5F9;font-size:.95rem;outline:none}
    .st-card input:focus{border-color:#38BDF8;box-shadow:0 0 0 3px rgba(56,189,248,.2)}
    .st-card button[type=submit]{width:100%;margin-top:.85rem;padding:.75rem 1rem;border:0;border-radius:10px;font-weight:800;font-size:.85rem;letter-spacing:.04em;cursor:pointer;background:linear-gradient(135deg,#0284C7,#2563EB);color:#fff}
    .st-card button:hover{filter:brightness(1.08)}
    .st-alert{margin:0 0 .75rem;padding:.55rem .75rem;border-radius:8px;font-size:.8rem;font-weight:600}
    .st-alert.err{background:rgba(127,29,29,.45);border:1px solid rgba(248,113,113,.4);color:#FECACA}
    .st-alert.warn{background:rgba(120,53,15,.4);border:1px solid rgba(251,191,36,.35);color:#FDE68A}
    .st-strip{display:flex;flex-wrap:wrap;gap:.5rem .85rem;justify-content:center;margin-top:1rem;font-size:.65rem;font-weight:600;color:#64748B;letter-spacing:.04em;text-transform:uppercase}
    .st-strip span{display:inline-flex;align-items:center;gap:.3rem}
    .st-strip .ok{color:#34D399}
    .st-foot{margin-top:1rem;text-align:center;font-size:.7rem;color:#64748B;line-height:1.45}
    .st-foot a{color:#7DD3FC;text-decoration:none}
    @media (prefers-reduced-motion:reduce){.st-orbs i,.st-scan{opacity:.2}}
    </style>
<style>
.project-logo-plate,.st-logo-plate{display:inline-flex;align-items:center;justify-content:center;min-width:48px;min-height:40px;padding:6px 10px;border-radius:10px;background:radial-gradient(ellipse at 30% 20%,rgba(255,248,230,.55),transparent 55%),linear-gradient(165deg,#c9a66b 0%,#e8d4a8 38%,#d4b07a 72%,#b8956a 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 1px 2px rgba(0,0,0,.12)}
.project-logo-plate img,.st-logo-plate img{max-width:80px;max-height:44px;object-fit:contain;display:block}
</style>
</head>
<body class="st-admin-login">
<canvas id="st-net" class="st-net" aria-hidden="true"></canvas>
<div class="st-orbs" aria-hidden="true"><i></i><i></i><i></i></div>
<div class="st-grid" aria-hidden="true"></div>
<div class="st-scan" aria-hidden="true"></div>
<main class="st-card" id="main" tabindex="-1">
    <h1>Management Console</h1>
    <p class="sub"><?php echo $e($brandCo); ?> · <?php echo $e($brandPr); ?></p>
    <?php if ($errorMsg !== ''): ?><div class="st-alert err" role="alert"><?php echo $e($errorMsg); ?></div><?php endif; ?>
    <?php if ($sessionMsg !== ''): ?><div class="st-alert warn"><?php echo $e($sessionMsg); ?></div><?php endif; ?>
    <form method="post" action="" autocomplete="on">
        <input type="hidden" name="_t" value="<?php echo $e($token); ?>">
        <label for="admin-pass">Access key</label>
        <input type="password" id="admin-pass" name="password" placeholder="Access key" spellcheck="false" autocomplete="current-password" required <?php echo $locked ? 'disabled' : ''; ?>>
        <button type="submit" name="login" value="1" <?php echo $locked ? 'disabled' : ''; ?>>Authorize</button>
    </form>
    <div class="st-strip" aria-hidden="true">
        <span class="ok">● System operational</span>
        <?php if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'): ?><span>TLS encrypted</span><?php endif; ?>
        <?php if ($verLabel !== ''): ?><span><?php echo $e($verLabel); ?></span><?php endif; ?>
        <span id="st-clock"></span>
    </div>
    <p class="st-foot">Authorized access only · activity is logged<br>
        <a href="./">← Public dashboard</a>
    </p>
</main>
<script>
(function(){
  var el=document.getElementById('st-clock');
  function tick(){ if(!el)return; var d=new Date(); el.textContent=d.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); }
  tick(); setInterval(tick,1000);
  var c=document.getElementById('st-net'); if(!c||!c.getContext) return;
  if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var ctx=c.getContext('2d'), pts=[], W,H,n=48;
  function resize(){ W=c.width=window.innerWidth; H=c.height=window.innerHeight; }
  function init(){ pts=[]; for(var i=0;i<n;i++) pts.push({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.35,vy:(Math.random()-.5)*.35}); }
  function frame(){
    ctx.clearRect(0,0,W,H);
    for(var i=0;i<pts.length;i++){
      var p=pts[i]; p.x+=p.vx; p.y+=p.vy; if(p.x<0||p.x>W)p.vx*=-1; if(p.y<0||p.y>H)p.vy*=-1;
      ctx.fillStyle='rgba(125,211,252,.55)'; ctx.beginPath(); ctx.arc(p.x,p.y,1.4,0,Math.PI*2); ctx.fill();
      for(var j=i+1;j<pts.length;j++){ var q=pts[j], dx=p.x-q.x, dy=p.y-q.y, d=Math.sqrt(dx*dx+dy*dy);
        if(d<120){ ctx.strokeStyle='rgba(56,189,248,'+(0.12*(1-d/120))+')'; ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(q.x,q.y); ctx.stroke(); }
      }
    }
    requestAnimationFrame(frame);
  }
  resize(); init(); frame();
  window.addEventListener('resize', function(){ resize(); init(); });
})();
</script>
</body>
</html>
<?php
    exit;
endif;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(AppLocale::lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <title>Management Console | <?php echo htmlspecialchars((string)cfg('brand.company', ''), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <link rel="icon" href="<?php echo sanitizeImagePath($favicon); ?>" type="image/x-icon">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $bsCssHref; ?>" integrity="<?php echo $bsCssInt; ?>" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo $faHref; ?>" integrity="<?php echo $faInt; ?>" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo $quillCss; ?>" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/app.css?v=<?php echo @filemtime(__DIR__ . '/assets/app.css') ?: time(); ?>">
    <script src="<?php echo $xlsxSrc; ?>" crossorigin="anonymous" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<nav class="fixed-top site-nav d-flex flex-nowrap align-items-center justify-content-between px-3 px-md-4" aria-label="Admin navigation">
    <div style="width:120px; flex-shrink:0;">
        <img src="<?php echo sanitizeImagePath($companyLogo); ?>" width="120" height="44" alt="<?php echo htmlspecialchars((string)cfg('brand.company', ''), ENT_QUOTES, 'UTF-8'); ?>" onerror="this.style.display='none'">
    </div>
    <h1 class="m-0 fw-bold text-dark flex-grow-1 text-center px-2" style="font-family: var(--font-display); font-size: clamp(0.85rem,1.8vw,1.15rem); font-weight: 700;">Management Console</h1>
    <div style="width:120px; flex-shrink:0; text-align:right;">
        <a href="?logout=1" class="logout-link d-inline-flex" aria-label="Log out">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i> <span class="d-none d-md-inline">Logout</span>
        </a>
    </div>
</nav>

<main class="container py-4 flex-grow-1" id="main" tabindex="-1" aria-label="Admin dashboard">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm border-0 sys-alert mb-4" role="alert">
            <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
            <strong><?php echo htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
        </div>
    <?php endif; ?>

    <nav aria-label="Admin sections" class="mb-4">
        <div class="admin-nav">
            <a class="nav-link <?php echo $activeTab==='updates' ? 'active' : ''; ?>" href="?tab=updates"><i class="fas fa-edit"></i> Post Updates</a>
            <a class="nav-link <?php echo $activeTab==='components' ? 'active' : ''; ?>" href="?tab=components"><i class="fas fa-cubes"></i> Directory Master</a>
            <a class="nav-link <?php echo $activeTab==='projects' ? 'active' : ''; ?>" href="?tab=projects"><i class="fas fa-building"></i> Project Master</a>
            <div class="nav-divider"></div>
            <form method="POST" class="d-inline" onsubmit="return confirm('Optimise Storage: purge orphaned uploads and compact data. Proceed?');">
                <input type="hidden" name="action" value="optimize_system">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token,ENT_QUOTES,'UTF-8'); ?>">
                <button type="submit" class="nav-link border" title="Remove orphaned files and compact data"><i class="fas fa-magic"></i> Optimise</button>
            </form>
            <div class="nav-divider"></div>
            <a class="nav-link <?php echo $activeTab==='analytics' ? 'active' : ''; ?>" href="?tab=analytics"><i class="fas fa-chart-pie"></i> Analytics</a>
            <a class="nav-link border" href="backup.php" target="_blank"><i class="fas fa-download"></i> Backup</a>
            <a class="nav-link border" href="/" target="_blank"><i class="fas fa-external-link-alt"></i> Public View</a>
        </div>
    </nav>

    <div class="row g-4">
    <?php if ($activeTab === 'analytics'): ?>
        <div class="col-12">
            <div class="row g-3 mb-3">
                <?php
                $stats = [
                    [$a_totalVisits, 'Total Page Views', 'fas fa-eye', 'text-primary'],
                    [$a_uniqueVisitors, 'Unique Sessions', 'fas fa-users', 'text-success'],
                    [$a_avgTimeFormatted, 'Avg Engagement', 'far fa-clock', 'text-warning'],
                    [$a_interactions, 'Interactions', 'fas fa-mouse-pointer', 'text-info'],
                ];
                foreach ($stats as [$val, $label, $icon, $cls]):
                ?>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="stat-label"><i class="<?php echo $icon; ?> <?php echo $cls; ?> me-1" aria-hidden="true"></i> <?php echo $label; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card" title="Most filtered project">
                        <div class="stat-value" style="font-size:1rem;word-break:break-word"><?php echo htmlspecialchars($a_topProject, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="stat-label"><i class="fas fa-building text-secondary me-1"></i> Top Project</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" title="Most filtered tower/component">
                        <div class="stat-value" style="font-size:1rem;word-break:break-word"><?php echo htmlspecialchars($a_topTower, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="stat-label"><i class="fas fa-layer-group text-secondary me-1"></i> Top Tower</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" title="Top traffic source domain">
                        <div class="stat-value" style="font-size:0.9rem;word-break:break-all"><?php echo htmlspecialchars($a_topReferrer, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="stat-label"><i class="fas fa-external-link-alt text-secondary me-1"></i> Top Referrer</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card" title="Device breakdown">
                        <div class="stat-value" style="font-size:0.8rem;line-height:1.6">
                            <?php foreach ($a_deviceBreakdown as $dtype => $dcount): ?>
                                <span class="me-2"><?php echo htmlspecialchars($dtype, ENT_QUOTES, 'UTF-8'); ?>: <strong><?php echo $dcount; ?></strong></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="stat-label"><i class="fas fa-mobile-alt text-secondary me-1"></i> Devices</div>
                    </div>
                </div>
            </div>

            <div class="card card-lux p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom flex-wrap gap-2">
                    <h2 class="fw-bold mb-0 text-dark fs-5"><i class="fas fa-broom text-muted me-2" aria-hidden="true"></i> Housekeeping</h2>
                    <span class="hk-note">Files nothing in the app refers to. Nothing is deleted until you choose it.</span>
                </div>

                <?php $hkAll = array_merge($housekeeping['code'], $housekeeping['media']); ?>
                <?php if (empty($hkAll)): ?>
                    <p class="mb-0 text-muted small"><i class="fas fa-check-circle text-success me-1"></i> Nothing unused found — every file is referenced by the code or the database.</p>
                <?php else: ?>
                    <form method="POST" onsubmit="return confirm('Permanently delete the selected files? This cannot be undone.');">
                        <input type="hidden" name="action" value="housekeeping_delete">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="table-responsive hk-scroll">
                            <table class="table table-hover table-custom mb-0" aria-label="Unused files">
                                <thead>
                                    <tr>
                                        <th scope="col" class="hk-check"><input type="checkbox" class="form-check-input" id="hkAll" aria-label="Select all"></th>
                                        <th scope="col">File</th>
                                        <th scope="col">Size</th>
                                        <th scope="col">Why it is listed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($hkAll as $hk): ?>
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input hk-row" name="unused_files[]" value="<?php echo htmlspecialchars($hk['path'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select file"></td>
                                        <td class="hk-path"><?php echo htmlspecialchars($hk['path'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-nowrap text-muted small"><?php echo number_format($hk['bytes'] / 1024, 1); ?> KB</td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($hk['why'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                            <span class="hk-note"><?php echo count($hkAll); ?> file(s) listed. Each one is re-checked at delete time, so a stale page cannot remove something now in use.</span>
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt me-1" aria-hidden="true"></i> Delete Selected</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card card-lux p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-2">
                    <h2 class="fw-bold mb-0 text-dark fs-5"><i class="fas fa-list-alt text-muted me-2" aria-hidden="true"></i> Visitor Event Ledger</h2>
                    <form method="POST" onsubmit="return confirm('Permanently delete all analytics history. This cannot be undone. Proceed?');">
                        <input type="hidden" name="action" value="clear_analytics">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt me-1" aria-hidden="true"></i> Clear Logs</button>
                    </form>
                </div>
                <div class="table-responsive" style="max-height: 560px; overflow-y: auto;">
                    <table class="table table-hover table-custom mb-0" aria-label="Visitor event log">
                        <thead class="sticky-top bg-light">
                            <tr><th scope="col">Timestamp</th><th scope="col">IP (Hashed)</th><th scope="col">Action</th><th scope="col">Details</th></tr>
                        </thead>
                        <tbody>
                        <?php
                        // The ledger is capped at 10,000 rows. Rendering them all built a
                        // single enormous table on every page load; it is paged now.
                        $allLogs   = array_reverse($analytics);
                        $lpSize    = 100;
                        $lpTotal   = max(1, (int)ceil(count($allLogs) / $lpSize));
                        $lpCurrent = max(1, min($lpTotal, (int)($_GET['lp'] ?? 1)));
                        $sortedLogs = array_slice($allLogs, ($lpCurrent - 1) * $lpSize, $lpSize);
                        if (empty($allLogs)):
                        ?>
                            <tr><td colspan="4" class="text-center text-muted py-5">No tracking data recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($sortedLogs as $log):
                                $actionBadges = ['page_load' => 'bg-info text-dark', 'time_spent' => 'bg-warning text-dark', 'button_click' => 'bg-success', 'directory_filter' => 'bg-primary', 'image_viewed' => 'bg-secondary'];
                                $badgeCls = $actionBadges[$log['action'] ?? ''] ?? 'bg-dark';
                                $ipDisplay = isset($log['ip_hash']) ? substr($log['ip_hash'], 0, 16) . '…' : htmlspecialchars($log['ip_address'] ?? '—', ENT_QUOTES, 'UTF-8');
                                $merged = array_merge((array)($log['details'] ?? []), (array)($log['context'] ?? []));
                                $detailParts = [];
                                foreach ($merged as $k => $v) {
                                    if (is_array($v)) $v = json_encode($v);
                                    $detailParts[] = '<strong>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars(mb_substr((string)$v, 0, 60), ENT_QUOTES, 'UTF-8');
                                }
                                $detailStr = implode(' &nbsp;|&nbsp; ', $detailParts);
                            ?>
                            <tr>
                                <td class="small text-muted text-nowrap"><i class="far fa-clock me-1" aria-hidden="true"></i> <?php echo htmlspecialchars(date('M d, H:i', strtotime($log['timestamp'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="small font-monospace text-secondary"><?php echo $ipDisplay; ?></td>
                                <td><span class="badge <?php echo $badgeCls; ?> text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.8px;"><?php echo htmlspecialchars(str_replace('_', ' ', $log['action'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td class="small" style="word-break: break-all; color: #555;"><?php echo $detailStr; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($lpTotal > 1): ?>
                <nav class="pager mt-3" aria-label="Visitor event ledger pages">
                    <span class="hk-note">
                        Showing <?php echo number_format(($lpCurrent - 1) * $lpSize + 1); ?>–<?php
                        echo number_format(min($lpCurrent * $lpSize, count($allLogs))); ?>
                        of <?php echo number_format(count($allLogs)); ?> events
                    </span>
                    <span class="ms-auto d-inline-flex gap-1 align-items-center">
                        <?php if ($lpCurrent > 1): ?>
                            <a class="page-btn" href="?tab=analytics&amp;lp=<?php echo $lpCurrent - 1; ?>" rel="prev">&laquo; Previous</a>
                        <?php else: ?>
                            <span class="page-btn disabled" aria-disabled="true">&laquo; Previous</span>
                        <?php endif; ?>
                        <span class="hk-note px-2">Page <?php echo $lpCurrent; ?> of <?php echo $lpTotal; ?></span>
                        <?php if ($lpCurrent < $lpTotal): ?>
                            <a class="page-btn" href="?tab=analytics&amp;lp=<?php echo $lpCurrent + 1; ?>" rel="next">Next &raquo;</a>
                        <?php else: ?>
                            <span class="page-btn disabled" aria-disabled="true">Next &raquo;</span>
                        <?php endif; ?>
                    </span>
                </nav>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($activeTab === 'projects'): ?>
        <div class="col-lg-4" id="leftSidebar">
            <div class="card card-lux p-4 sidebar-sticky">
                <h2 class="fw-bold mb-4 fs-5" style="color: var(--brand-magenta);">
                    <i class="fas <?php echo isset($_GET['edit_proj']) ? 'fa-edit' : 'fa-plus-circle'; ?> me-2" aria-hidden="true"></i>
                    <?php echo isset($_GET['edit_proj']) ? 'Edit Project' : 'Add New Project'; ?>
                </h2>
                <form method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="action" value="save_project">
                    <input type="hidden" name="_fp" value="<?php echo htmlspecialchars(storeFingerprint(PROJ_FILE), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($editProjData['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="existing_logo" value="<?php echo htmlspecialchars($editProjData['logo'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="mb-3">
                        <label for="proj-name" class="form-label small fw-bold text-muted text-uppercase">Project Name + Phase</label>
                        <input type="text" id="proj-name" name="project_name" class="form-control" value="<?php echo htmlspecialchars($editProjData['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="proj-rera" class="form-label small fw-bold text-muted text-uppercase">RERA Number</label>
                        <input type="text" id="proj-rera" name="rera_number" class="form-control" value="<?php echo htmlspecialchars($editProjData['rera'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="proj-logo" class="form-label small fw-bold text-muted text-uppercase">Project Logo</label>
                        <?php if (!empty($editProjData['logo'])): ?>
                            <div class="mb-2 p-2 border rounded bg-light text-center">
                                <?php echo statusLogoImg((string)$editProjData['logo'], 'Current project logo'); ?>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="proj-logo" name="project_logo" class="form-control" accept="image/*,.svg,image/svg+xml">
                    </div>
                    <button type="submit" class="btn btn-magenta w-100">Save Project</button>
                    <?php if (isset($_GET['edit_proj'])): ?>
                        <div class="text-center mt-3"><a href="admin.php?tab=projects" class="text-muted small text-decoration-none"><i class="fas fa-times me-1" aria-hidden="true"></i> Cancel</a></div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-lg-8" id="mainContent">
            <div class="card card-lux p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold mb-0 fs-5"><i class="fas fa-list text-muted me-2" aria-hidden="true"></i> Project Database</h2>
                </div>
                <table class="table table-custom" aria-label="Projects list">
                    <thead><tr><th scope="col">Project / Phase</th><th scope="col">RERA</th><th scope="col">Logo</th><th scope="col" class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($projects)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No projects defined yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($projects as $p): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($p['rera'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if (!empty($p['logo'])): ?>
                                    <span class="project-logo-plate st-logo-plate"><?php echo statusLogoImg((string)$p['logo'], (string)($p['name'] ?? '')); ?> logo"></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">No Logo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="admin.php?tab=projects&amp;edit_proj=<?php echo urlencode((string)$p['id']); ?>" class="btn btn-sm btn-light border text-primary me-1"><i class="fas fa-pencil-alt" aria-hidden="true"></i><span class="visually-hidden">Edit project <?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                                <a href="<?php echo htmlspecialchars(signedUrl('admin.php?tab=projects&delete_project=' . urlencode((string)$p['id'])), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-light text-danger border" onclick="return confirm('Delete project? This cannot be undone.')"><i class="fas fa-trash-alt" aria-hidden="true"></i><span class="visually-hidden">Delete project <?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($activeTab === 'components'):
        $compGroups = [];
        foreach ($components as $c) {
            $pName = $projMap[$c['project_id']]['name'] ?? 'Unknown Project';
            $compGroups[$pName][$c['type']][] = $c;
        }
    ?>
        <div class="col-lg-4" id="leftSidebar">
            <div class="card card-lux p-4 sidebar-sticky">
                <h2 class="fw-bold mb-4 fs-5" style="color: var(--brand-magenta);">
                    <i class="fas <?php echo isset($_GET['edit_comp']) ? 'fa-edit' : 'fa-plus-circle'; ?> me-2" aria-hidden="true"></i>
                    <?php echo isset($_GET['edit_comp']) ? 'Edit Directory Node' : 'Define Directory Node'; ?>
                </h2>
                <form method="POST" novalidate>
                    <input type="hidden" name="action" value="save_component">
                    <input type="hidden" name="_fp" value="<?php echo htmlspecialchars(storeFingerprint(COMP_FILE), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="component_id" value="<?php echo htmlspecialchars($editCompData['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="mb-3">
                        <label for="comp-proj" class="form-label small fw-bold text-muted text-uppercase">Project</label>
                        <select id="comp-proj" name="project_id" class="form-select" required>
                            <option value="">— Choose Project —</option>
                            <?php foreach ($projects as $p): $sel = ($p['id'] === $editCompData['project_id']) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="comp-type" class="form-label small fw-bold text-muted text-uppercase">Component Category</label>
                        <input list="typeList" id="comp-type" name="type" class="form-control" value="<?php echo htmlspecialchars($editCompData['type'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        <datalist id="typeList">
                            <option value="Daily Convenience Retail 🛒">
                            <option value="Luxury Residences 🏙️">
                            <option value="Club & Social Spaces 🥂">
                        </datalist>
                    </div>
                    <div class="mb-4">
                        <label for="comp-job" class="form-label small fw-bold text-muted text-uppercase">Job / Tower Name</label>
                        <input type="text" id="comp-job" name="job" class="form-control" value="<?php echo htmlspecialchars($editCompData['job'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-magenta w-100">Save Directory Node</button>
                    <?php if (isset($_GET['edit_comp'])): ?>
                        <div class="text-center mt-3"><a href="admin.php?tab=components" class="text-muted small text-decoration-none"><i class="fas fa-times me-1" aria-hidden="true"></i> Cancel</a></div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-lg-8" id="mainContent">
            <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded border shadow-sm">
                <h2 class="fw-bold mb-0 fs-5"><i class="fas fa-network-wired text-muted me-2" aria-hidden="true"></i> Structured Directory Manager</h2>
            </div>
            <?php if (empty($components)): ?>
                <div class="text-center p-5 border rounded bg-white text-muted shadow-sm"><i class="fas fa-network-wired fa-2x mb-2 opacity-50" aria-hidden="true"></i><br>No Directory Nodes Defined</div>
            <?php endif; ?>
            <?php foreach ($compGroups as $pName => $types): ?>
                <div class="mb-4 border rounded overflow-hidden shadow-sm bg-white">
                    <div class="p-3 border-bottom d-flex align-items-center" style="background: var(--brand-magenta);">
                        <h3 class="m-0 fw-bold text-white fs-6"><i class="far fa-building me-2" aria-hidden="true"></i><?php echo htmlspecialchars($pName, ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                    <?php foreach ($types as $tName => $jobs): ?>
                        <div class="bg-light p-2 px-3 border-bottom fw-bold text-uppercase d-flex align-items-center" style="color: var(--brand-dark); font-size: 0.8rem; letter-spacing: 1px;">
                            <i class="fas fa-layer-group me-2 opacity-50" aria-hidden="true"></i><?php echo htmlspecialchars($tName, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($jobs as $job): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                    <span class="fw-bold" style="font-size: 0.97rem;"><i class="fas fa-angle-right text-muted me-2 small" aria-hidden="true"></i><?php echo htmlspecialchars($job['job'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <div class="text-nowrap">
                                        <a href="admin.php?tab=components&amp;edit_comp=<?php echo urlencode((string)$job['id']); ?>" class="btn btn-sm btn-light border text-primary me-1"><i class="fas fa-pencil-alt" aria-hidden="true"></i><span class="visually-hidden">Edit <?php echo htmlspecialchars($job['job'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                                        <a href="<?php echo htmlspecialchars(signedUrl('admin.php?tab=components&delete_comp=' . urlencode((string)$job['id'])), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-light text-danger border" onclick="return confirm('Delete node? (Ensure no updates rely on this)')"><i class="fas fa-trash-alt" aria-hidden="true"></i><span class="visually-hidden">Delete <?php echo htmlspecialchars($job['job'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div class="col-lg-5" id="leftSidebar">
            <div class="card card-lux p-4 sidebar-sticky">
                <h2 class="fw-bold mb-4 fs-5" style="color: var(--brand-magenta);">
                    <i class="fas <?php echo isset($_GET['edit']) ? 'fa-edit' : 'fa-pen-nib'; ?> me-2" aria-hidden="true"></i>
                    <?php echo isset($_GET['edit']) ? 'Edit Update Record' : 'Post Site Update'; ?>
                </h2>
                <form method="POST" enctype="multipart/form-data" id="updateForm" novalidate>
                    <input type="hidden" name="action" value="save_update">
                    <input type="hidden" name="_fp" value="<?php echo htmlspecialchars(storeFingerprint(UPD_FILE), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="update_id" value="<?php echo htmlspecialchars($editUpdateData['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="existing_images" value="<?php echo htmlspecialchars(json_encode($editUpdateData['images']), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="legacy_title" value="<?php echo htmlspecialchars($editUpdateData['legacy_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="mb-3">
                        <label for="upd-project" class="form-label small fw-bold text-muted text-uppercase">1. Select Project</label>
                        <select id="upd-project" class="form-select" required>
                            <option value="">— Choose Project —</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="upd-component" class="form-label small fw-bold text-muted text-uppercase">2. Select Target Node</label>
                        <select id="upd-component" name="component_id" class="form-select" required>
                            <option value="">— Select Project First —</option>
                            <option value="legacy" style="color: #c0392b;">Legacy Updates (Archive)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="upd-date" class="form-label small fw-bold text-muted text-uppercase">Status Date</label>
                        <input type="date" id="upd-date" name="date" class="form-control" value="<?php echo htmlspecialchars($dateForInput, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Progress Details</label>
                        <div id="quill-toolbar">
                            <span class="ql-formats">
                                <button class="ql-bold" title="Bold"></button>
                                <button class="ql-italic" title="Italic"></button>
                                <button class="ql-underline" title="Underline"></button>
                                <button class="ql-strike" title="Strikethrough"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered" title="Numbered List"></button>
                                <button class="ql-list" value="bullet" title="Bullet List"></button>
                            </span>
                            <span class="ql-formats"><button class="ql-clean" title="Clear Formatting"></button></span>
                        </div>
                        <div id="quill-editor"><?php echo sanitizeRichText($editUpdateData['description'] ?? ''); ?></div>
                        <input type="hidden" name="description" id="hidden_description">
                    </div>
                    <div class="mb-4">
                        <label for="upd-images" class="form-label small fw-bold text-muted text-uppercase">Attach Photos</label>
                        <?php if (!empty($editUpdateData['images'])): ?>
                            <div class="p-3 mb-2 bg-light border rounded">
                                <div id="existingImagesSortable" class="d-flex flex-wrap gap-2">
                                    <?php foreach ($editUpdateData['images'] as $imgIdx => $img): ?>
                                        <div class="sortable-img-item photo-tile" data-path="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>">
                                            <img src="<?php echo sanitizeImagePath($img); ?>" class="w-100 h-100 photo-tile-img" loading="lazy" alt="Attached photograph <?php echo (int)$imgIdx + 1; ?>">
                                            <label class="img-delete-toggle" title="Mark this photograph for deletion">
                                                <i class="fas fa-times" aria-hidden="true"></i>
                                                <span class="visually-hidden">Mark photograph <?php echo (int)$imgIdx + 1; ?> for deletion</span>
                                                <input type="checkbox" class="visually-hidden js-del-img" name="delete_images[]" value="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>">
                                            </label>
                                            <!-- Keyboard/click alternative to dragging (WCAG 2.2 · 2.5.7). -->
                                            <span class="img-order-controls">
                                                <button type="button" class="img-order-btn js-img-move" data-dir="-1" title="Move earlier">
                                                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                                                    <span class="visually-hidden">Move photograph <?php echo (int)$imgIdx + 1; ?> earlier</span>
                                                </button>
                                                <button type="button" class="img-order-btn js-img-move" data-dir="1" title="Move later">
                                                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                                    <span class="visually-hidden">Move photograph <?php echo (int)$imgIdx + 1; ?> later</span>
                                                </button>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="existing_images" id="existingImagesOrdered" value="<?php echo htmlspecialchars(json_encode($editUpdateData['images']), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        <?php endif; ?>
                        <input type="file" id="upd-images" name="images[]" class="form-control" multiple accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-magenta w-100 py-2">PUBLISH REPORT</button>
                    <?php if (isset($_GET['edit'])): ?>
                        <div class="text-center mt-3"><a href="admin.php" class="text-muted small text-decoration-none"><i class="fas fa-times me-1" aria-hidden="true"></i> Cancel</a></div>
                    <?php endif; ?>
                </form>
                <hr class="my-4">
                <div class="d-flex gap-2 flex-wrap">
                    <button onclick="exportToExcel()" class="btn btn-sm btn-outline-success flex-grow-1"><i class="fas fa-file-excel me-1" aria-hidden="true"></i> Export Excel</button>
                    <label class="btn btn-sm btn-outline-secondary flex-grow-1 mb-0" style="cursor: pointer;"><i class="fas fa-file-import me-1" aria-hidden="true"></i> Import Excel<input type="file" accept=".xlsx" style="display: none;" onchange="importFromExcel(event)"></label>
                </div>
            </div>
        </div>

        <div class="col-lg-7" id="mainContent">
            <form method="POST" id="bulkForm">
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="bg-white p-3 rounded border shadow-sm mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h2 class="fw-bold mb-0 fs-5"><i class="fas fa-history text-muted me-2" aria-hidden="true"></i> Report Repository</h2>
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete ALL selected updates? This cannot be undone.')"><i class="fas fa-trash-alt me-1" aria-hidden="true"></i> Delete Selected</button>
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="updateSearch" class="form-control border-start-0" placeholder="Search by project, tower, keyword..." aria-label="Search updates" autocomplete="off">
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="document.getElementById('updateSearch').value='';filterUpdates()"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Clear search</span></button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 small text-muted" id="paginationBar" style="display:none!important">
                        <span id="paginationInfo"></span>
                        <div class="d-flex gap-1">
                            <button class="btn btn-outline-secondary btn-sm py-0 px-2" id="prevPage" onclick="changePage(-1)">&laquo; Prev</button>
                            <button class="btn btn-outline-secondary btn-sm py-0 px-2" id="nextPage" onclick="changePage(1)">Next &raquo;</button>
                        </div>
                    </div>
                </div>

                <div id="updatesGrid">
                    <?php foreach ($feedGroups as $pName => $types): ?>
                        <div class="project-group-container">
                            <header class="project-group-header mt-2">
                                <i class="far fa-building" style="color: var(--brand-magenta);" aria-hidden="true"></i>
                                <h2><?php echo htmlspecialchars($pName, ENT_QUOTES, 'UTF-8'); ?></h2>
                            </header>
                            <?php foreach ($types as $tName => $typeUpdates): ?>
                                <div class="type-group-container ms-0 ms-md-3 mb-5">
                                    <div class="type-group-header mb-4"><h3><?php echo htmlspecialchars($tName, ENT_QUOTES, 'UTF-8'); ?></h3></div>
                                    <div class="updates-list ms-0 ms-md-3">
                                        <?php $ci = 0; foreach ($typeUpdates as $u): $ci++;
                                            $c = $compMap[$u['component_id']] ?? null;
                                            $displayJob = htmlspecialchars($c['job'] ?? '', ENT_QUOTES, 'UTF-8');
                                            $ledger = $ci % 2 === 0 ? 'ledger-even' : 'ledger-odd';
                                        ?>
                                        <article class="update-card <?php echo $ledger; ?> position-relative" data-comp-id="<?php echo htmlspecialchars($u['component_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="position-absolute top-0 end-0 p-3 d-flex align-items-center gap-1">
                                                <input type="checkbox" name="selected_updates[]" value="<?php echo htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8'); ?>" class="form-check-input row-checkbox" aria-label="Select the <?php echo $displayJob; ?> update of <?php echo htmlspecialchars(AppLocale::date((string)($u['date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> for bulk deletion">
                                                <a href="admin.php?tab=updates&amp;edit=<?php echo urlencode((string)$u['id']); ?>" class="btn btn-sm btn-light border text-primary"><i class="fas fa-pencil-alt" aria-hidden="true"></i><span class="visually-hidden">Edit the <?php echo $displayJob; ?> update of <?php echo htmlspecialchars(AppLocale::date((string)($u['date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></a>
                                                <a href="<?php echo htmlspecialchars(signedUrl('admin.php?tab=updates&delete_update=' . urlencode((string)$u['id'])), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-light text-danger border" onclick="return confirm('Delete this update?')"><i class="fas fa-trash-alt" aria-hidden="true"></i><span class="visually-hidden">Delete the <?php echo $displayJob; ?> update of <?php echo htmlspecialchars(AppLocale::date((string)($u['date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></a>
                                            </div>
                                            <?php $hasImages = !empty($u['images']); ?>
                                            <div class="row g-4 pt-1">
                                                <div class="<?php echo $hasImages ? 'col-lg-7' : 'col-12'; ?>">
                                                    <h3 class="fw-bold text-dark mb-3" style="font-size:1.05rem;"><?php echo $displayJob; ?></h3>
                                                    <div class="progress-description text-dark" style="font-size:0.88rem;line-height:1.65;">
                                                        <?php echo sanitizeRichText($u['description'] ?? ''); ?>
                                                    </div>
                                                    <?php if (!$hasImages): ?>
                                                        <div class="mt-2"><time class="badge bg-secondary opacity-75 fw-normal"><?php echo htmlspecialchars(AppLocale::date((string)($u['date'] ?? '')),ENT_QUOTES,'UTF-8'); ?></time></div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($hasImages): ?>
                                                    <div class="col-lg-5">
                                                        <div class="mb-2"><time class="badge bg-secondary opacity-75 fw-normal"><?php echo htmlspecialchars(AppLocale::date((string)($u['date'] ?? '')),ENT_QUOTES,'UTF-8'); ?></time></div>
                                                        <div class="row g-2">
                                                            <?php foreach ($u['images'] as $img): ?>
                                                                <div class="col-6"><div class="thumb-wrapper"><img src="<?php echo sanitizeImagePath($img); ?>" class="update-thumb" loading="lazy" decoding="async" alt="<?php echo $displayJob; ?>"></div></div>
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
                </div>
            </form>

            <div class="card card-lux p-4 mt-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div style="background:var(--brand-magenta);border-radius:7px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-video text-white" style="font-size:0.78rem;"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold" style="font-size:0.92rem;">Featured Media</h3>
                        <p class="mb-0 text-muted" style="font-size:0.75rem;">Video block shown at the bottom of the public update feed</p>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="save_site_config">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label class="form-label fw-semibold small" for="video-url-input">Video URL or file</label>
                            <?php $vids = listVideoFiles(); ?>
                            <input type="text" id="video-url-input" name="video_url" list="videoFileList"
                                   class="form-control form-control-sm font-monospace"
                                   placeholder="assets/wip.mp4  — or a full https:// address"
                                   value="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8'); ?>">
                            <datalist id="videoFileList">
                                <?php foreach ($vids as $v): ?>
                                    <option value="<?php echo htmlspecialchars($v['path'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo number_format($v['bytes'] / 1048576, 1); ?> MB
                                    </option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if ($vids): ?>
                                <div class="vid-hint">
                                    <?php echo count($vids); ?> video file<?php echo count($vids) === 1 ? '' : 's'; ?>
                                    found on the server — click the field to pick one:
                                    <?php foreach (array_slice($vids, 0, 4) as $v): ?>
                                        <button type="button" class="vid-pick" data-path="<?php echo htmlspecialchars($v['path'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(basename($v['path']), ENT_QUOTES, 'UTF-8'); ?></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="vid-hint vid-hint-warn">
                                    No video files found in <code>assets\</code> or <code>uploads\</code>.
                                    Upload one (for example <code>assets\wip.mp4</code>) via FTP, then reload this page.
                                </div>
                            <?php endif; ?>
                            <div class="vid-hint">A path relative to the portal, or a full <code>https://</code> address. Leave empty to hide the section.</div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label fw-semibold small" for="video-title-input">Section Title</label>
                            <input type="text" id="video-title-input" name="video_title" class="form-control form-control-sm" placeholder="Site Aerial Overview" value="<?php echo htmlspecialchars($videoTitle, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-sm fw-bold" style="background:var(--brand-magenta);color:#fff;border-radius:7px;padding:7px 20px;"><i class="fas fa-save me-1"></i> Save Media</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    </div>
</main>

<script src="<?php echo $bsJsHref; ?>" integrity="<?php echo $bsJsInt; ?>" crossorigin="anonymous"></script>
<script src="<?php echo $quillJs; ?>" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js" integrity="sha384-DgmC6Xe2bSN2WjTDXzWYbUbxyhNP+NNkGDR/g78pCXV7E7rcVTGxVg0uIVCUUcBc" crossorigin="anonymous" defer></script>
<script>
(function () {
    'use strict';
    const editorEl = document.getElementById('quill-editor');
    const toolbarEl = document.getElementById('quill-toolbar');

    if (editorEl && toolbarEl) {
        window.quill = new Quill(editorEl, { theme: 'snow', modules: { toolbar: { container: toolbarEl } }, placeholder: 'Type progress updates here.' });
        const hiddenDesc = document.getElementById('hidden_description');
        function sync() { let html = window.quill.root.innerHTML; hiddenDesc.value = (html === '<p><br></p>') ? '' : html; }
        window.quill.on('text-change', sync);
        sync();
        const form = document.getElementById('updateForm');
        if (form) form.addEventListener('submit', sync);
    }

    const projectSel = document.getElementById('upd-project');
    const componentSel = document.getElementById('upd-component');
    const rawComponents = <?php echo json_encode($components, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const editProjId = <?php echo json_encode($editProjectId); ?>;
    const editCompId = <?php echo json_encode($editUpdateData['component_id'] ?? ''); ?>;

    function populateComponents(pid, selectedId) {
        if (!componentSel) return;
        componentSel.innerHTML = '<option value="">— Choose Target Node —</option><option value="legacy" style="color:#c0392b">Legacy Updates (Archive)</option>';
        rawComponents.filter(c => c.project_id === pid).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = '[' + c.type + '] ' + c.job;
            if (c.id === selectedId) opt.selected = true;
            componentSel.appendChild(opt);
        });
        if (selectedId === 'legacy') componentSel.value = 'legacy';
    }

    if (projectSel && componentSel) {
        projectSel.addEventListener('change', function () { populateComponents(this.value, ''); });
        if (editProjId) { projectSel.value = editProjId; populateComponents(editProjId, editCompId); }
        else if (editCompId === 'legacy') { populateComponents('', 'legacy'); }
    }

    window.exportToExcel = function () {
        if (!window.XLSX) { alert('Library loading, please wait.'); return; }
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(<?php echo json_encode($projects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>), 'Projects');
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(<?php echo json_encode($components, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>), 'Components');
        const dbUpdates = <?php echo json_encode($updates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(dbUpdates.map(u => ({ ...u, images: Array.isArray(u.images) ? u.images.join(',') : (u.images || '') }))), 'Updates');
        XLSX.writeFile(wb, 'Fusion_Data_Backup.xlsx');
    };

    window.importFromExcel = function (e) {
        const file = e.target.files[0];
        if (!file) return;
        if (!confirm('WARNING: This OVERWRITES the live database.\nProceed?')) { e.target.value = ''; return; }
        const reader = new FileReader();
        reader.onload = function (ev) {
            try {
                const wb = XLSX.read(new Uint8Array(ev.target.result), { type: 'array' });
                const imported = {};
                if (wb.Sheets['Projects']) imported.projects = XLSX.utils.sheet_to_json(wb.Sheets['Projects'], { defval: '' });
                if (wb.Sheets['Components']) imported.components = XLSX.utils.sheet_to_json(wb.Sheets['Components'], { defval: '' });
                if (wb.Sheets['Updates']) imported.updates = XLSX.utils.sheet_to_json(wb.Sheets['Updates'], { defval: '' }).map(u => ({ ...u, images: u.images ? String(u.images).split(',').map(s => s.trim()).filter(Boolean) : [] }));
                
                const frm = document.createElement('form');
                frm.method = 'POST';
                const fields = { action: 'import_xlsx', csrf_token: <?php echo json_encode($token); ?>, xlsx_data: JSON.stringify(imported) };
                Object.entries(fields).forEach(([k, v]) => { const inp = document.createElement('input'); inp.type = 'hidden'; inp.name = k; inp.value = v; frm.appendChild(inp); });
                document.body.appendChild(frm);
                frm.submit();
            } catch (err) { alert('Error parsing Excel file.'); }
        };
        reader.readAsArrayBuffer(file);
        e.target.value = '';
    };

    const ITEMS_PER_PAGE = 50;
    let currentPage = 1, allCards = [], visibleCards = [];
    
    function filterUpdates() {
        const q = (document.getElementById('updateSearch')?.value || '').toLowerCase().trim();
        allCards = Array.from(document.querySelectorAll('#updatesGrid article.update-card'));
        visibleCards = allCards.filter(card => !q || card.textContent.toLowerCase().includes(q));
        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        const total = visibleCards.length;
        const start = (currentPage - 1) * ITEMS_PER_PAGE;
        const end = Math.min(start + ITEMS_PER_PAGE, total);
        allCards.forEach(c => c.style.display = 'none');
        visibleCards.slice(start, end).forEach(c => c.style.display = '');
        const bar = document.getElementById('paginationBar');
        if (bar && total > ITEMS_PER_PAGE) {
            bar.style.removeProperty('display');
            document.getElementById('paginationInfo').textContent = `Showing ${start + 1}–${end} of ${total} updates`;
            document.getElementById('prevPage').disabled = currentPage <= 1;
            document.getElementById('nextPage').disabled = currentPage >= Math.ceil(total / ITEMS_PER_PAGE);
        } else if (bar) bar.style.display = 'none';
    }

    window.filterUpdates = filterUpdates;
    window.changePage = function (delta) {
        currentPage = Math.max(1, Math.min(Math.ceil(visibleCards.length / ITEMS_PER_PAGE) || 1, currentPage + delta));
        renderPage();
    };

    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('updateSearch');
        if (searchInput) {
            let debounce;
            searchInput.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(filterUpdates, 150); });
        }
        filterUpdates();

        document.querySelectorAll('.vid-pick').forEach(function (b) {
            b.addEventListener('click', function () {
                const f = document.getElementById('video-url-input');
                if (f) { f.value = b.dataset.path; f.focus(); }
            });
        });

        const hkAll = document.getElementById('hkAll');
        if (hkAll) {
            hkAll.addEventListener('change', function () {
                document.querySelectorAll('.hk-row').forEach(cb => { cb.checked = hkAll.checked; });
            });
        }

        const sortableEl = document.getElementById('existingImagesSortable');
        const orderedInput = document.getElementById('existingImagesOrdered');

        function syncPhotoOrder() {
            if (!sortableEl || !orderedInput) { return; }
            orderedInput.value = JSON.stringify(
                Array.from(sortableEl.querySelectorAll('.sortable-img-item')).map(el => el.dataset.path)
            );
        }

        if (sortableEl && typeof Sortable !== 'undefined' && orderedInput) {
            Sortable.create(sortableEl, {
                animation: 150, handle: '.sortable-img-item', ghostClass: 'sortable-ghost',
                onEnd: syncPhotoOrder
            });
        }

        // Reordering without a dragging movement (WCAG 2.2 · 2.5.7). Runs
        // entirely in the page, so unsaved edits are never lost to a round trip.
        if (sortableEl) {
            sortableEl.addEventListener('click', function (ev) {
                const btn = ev.target.closest('.js-img-move');
                if (!btn) { return; }
                const tile = btn.closest('.sortable-img-item');
                const dir  = parseInt(btn.dataset.dir, 10);
                if (!tile) { return; }
                const sibling = dir < 0 ? tile.previousElementSibling : tile.nextElementSibling;
                if (!sibling) { return; }
                if (dir < 0) { sortableEl.insertBefore(tile, sibling); }
                else         { sortableEl.insertBefore(sibling, tile); }
                syncPhotoOrder();
                btn.focus();   // keep the keyboard user where they were
            });

            // Deletion marking, moved out of an inline onchange handler so the
            // page carries no inline JavaScript for a CSP to block.
            sortableEl.addEventListener('change', function (ev) {
                const cb = ev.target.closest('.js-del-img');
                if (!cb) { return; }
                const tile = cb.closest('.sortable-img-item');
                if (tile) { tile.classList.toggle('is-deleted', cb.checked); }
            });
        }
    });
})();
</script>
</body>
</html>