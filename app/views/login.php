<?php
/**
 * login.php — Version: 260919.04
 * PageSpeed-oriented public surface (what PSI measures when unauthenticated).
 * Zero render-blocking third-party CSS/JS. System fonts. Inline SVG icons.
 * LCP: company logo with explicit dimensions + fetchpriority=high.
 */
if (!defined('BASE_PATH')) exit;

$companyData = class_exists('AppDB') ? AppDB::read('company') : [];
if (empty($companyData) && file_exists(BASE_PATH . '/data/company.json')) {
    $compJson = json_decode((string)file_get_contents(BASE_PATH . '/data/company.json'), true);
    $companyData = $compJson[0] ?? $compJson ?? [];
}
if (!is_array($companyData)) $companyData = [];

$compName    = (string)($companyData['name'] ?? 'Enterprise Portal');
$compLogo    = !empty($companyData['logo'])    ? 'images/' . $companyData['logo']    : null;
$compFavicon = !empty($companyData['favicon']) ? 'images/' . $companyData['favicon'] : null;
$compWebsite = (string)($companyData['website'] ?? '');
$websiteUrl  = $compWebsite;
if ($websiteUrl !== '' && $websiteUrl !== '#' && strpos($websiteUrl, 'http') !== 0) {
    $websiteUrl = 'https://' . $websiteUrl;
}

$seoTab = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)($_GET['tab'] ?? 'team')));
if ($seoTab === '') $seoTab = 'team';
if ($seoTab === 'stat') $seoTab = 'statutory';

$seoHtml = '';
if (class_exists('AppSEO')) {
    $seoHtml = AppSEO::generateTags(['tab' => $seoTab], true);
}

$h = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$csrf = (string)($_SESSION['csrf_token'] ?? '');
$logoW = 160;
$logoH = 48;
?>
<!DOCTYPE html>
<html lang="en-IN" data-theme="reserve">
<head>
    <meta charset="UTF-8">
    <?php if (is_file(__DIR__ . '/partials/rc_theme_head.php')) require __DIR__ . '/partials/rc_theme_head.php'; ?>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=<?= rawurlencode(defined('APP_VERSION') ? (string)APP_VERSION : '1') ?>">
    <link rel="apple-touch-icon" href="/apple-touch-icon-180.png">

    <meta name="format-detection" content="telephone=no,date=no,email=no,address=no">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=5">
    <meta name="theme-color" id="rc-theme-color" content="#FAF9F8">
    <meta name="color-scheme" content="light">
<?php if ($seoHtml !== ''): ?>
    <?= $seoHtml ?>
<?php else: ?>
    <title><?= $h($compName) ?> | Secure Access</title>
    <meta name="description" content="Official corporate directory and portal for <?= $h($compName) ?>.">
    <meta property="og:title" content="<?= $h($compName) ?> | Secure Access">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="en_IN">
<?php endif; ?>
<?php if ($compFavicon): ?>
    <link rel="icon" href="<?= $h($compFavicon) ?>" sizes="any">
    <link rel="apple-touch-icon" href="<?= $h($compFavicon) ?>">
<?php endif; ?>
<?php if ($compLogo): ?>
    <link rel="preload" as="image" href="<?= $h($compLogo) ?>" fetchpriority="high">
<?php endif; ?>
    <style>
        /* Critical-only CSS — no external stylesheets */
        *,*::before,*::after{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%}
        body{
            margin:0;
            min-height:100vh;min-height:100dvh;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:1.5rem;
            font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
            font-size:16px;line-height:1.5;color:#0f172a;
            background:#f8fafc;
            background-image:
                radial-gradient(ellipse at 70% 10%,rgba(219,234,254,.85) 0%,transparent 50%),
                radial-gradient(ellipse at 20% 90%,rgba(224,231,255,.55) 0%,transparent 45%);
        }
        .card{
            width:100%;max-width:400px;
            background:#fff;border:1px solid #e2e8f0;border-radius:1.25rem;
            padding:2.25rem 2rem;
            box-shadow:0 1px 3px rgba(0,0,0,.05),0 8px 28px rgba(15,23,42,.06);
        }
        .logo-wrap{display:flex;justify-content:center;margin-bottom:1.5rem;min-height:48px}
        .logo-img{height:48px;width:auto;max-width:160px;object-fit:contain;display:block}
        .logo-fallback{
            width:48px;height:48px;border-radius:.875rem;
            background:linear-gradient(135deg,#1e293b,#0f172a);
            display:grid;place-items:center;color:#fff
        }
        .heading{margin:0 0 .35rem;font-size:1.35rem;font-weight:800;letter-spacing:-.02em;text-align:center;color:#0f172a}
        .subheading{margin:0 0 1.5rem;font-size:.8125rem;color:#64748b;text-align:center;font-weight:500}
        .subheading strong{color:#334155;font-weight:700}
        .divider{height:1px;background:#e2e8f0;margin:0 0 1.25rem}
        .input-wrap{position:relative;margin-bottom:1rem}
        .input-icon{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);width:1rem;height:1rem;color:#94a3b8;pointer-events:none}
        .input-field{
            width:100%;height:2.75rem;padding:.65rem 1rem .65rem 2.5rem;
            border:1px solid #e2e8f0;border-radius:.75rem;background:#f8fafc;
            font:inherit;color:#0f172a;outline:none;transition:border-color .15s,box-shadow .15s
        }
        .input-field:focus{border-color:#3b82f6;background:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
        .btn{
            width:100%;height:2.85rem;margin-top:.25rem;border:0;border-radius:.75rem;
            background:#0f172a;color:#fff;font:inherit;font-weight:700;font-size:.9rem;
            display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
            cursor:pointer;transition:background .15s,transform .15s
        }
        .btn:hover{background:#1e293b}
        .btn:active{transform:translateY(1px)}
        .btn:disabled{opacity:.7;cursor:wait}
        .btn.success{background:#059669}
        .btn svg{width:1rem;height:1rem;flex-shrink:0}
        .msg-box{display:none;margin-top:1rem;padding:.65rem .85rem;border-radius:.65rem;font-size:.8rem;font-weight:600;text-align:center}
        .msg-box.ok{display:block;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
        .msg-box.error{display:block;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
        .links{margin-top:1.25rem;text-align:center;font-size:.75rem}
        .links a{color:#64748b;text-decoration:none;font-weight:600}
        .links a:hover{color:#2563eb}
        .powered{margin-top:1.75rem;text-align:center;font-size:.7rem;color:#94a3b8}
        .powered a{color:#64748b;text-decoration:none;font-weight:600}
        .spin{animation:spin .7s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media (prefers-reduced-motion:reduce){.spin{animation:none}}
    </style>
    <link rel="stylesheet" href="assets/a11y.css?v=260921.29">
<link rel="stylesheet" href="/assets/contrast-lock.css?v=20261009.9">
    <link rel="stylesheet" href="/assets/rc-compat.css?v=20261009.13">
    <script src="assets/a11y-tooltip.js?v=260921.29" defer></script>

<script>
(function(){
  var map = { light:'#FAF9F8', dark:'#1B1A19', reserve:'#F5F0E8' };
  function apply(){
    var th = (document.documentElement.getAttribute('data-theme')||'light');
    if (th === 'system') {
      th = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    }
    var el = document.getElementById('rc-theme-color');
    if (el) el.setAttribute('content', map[th] || map.light);
  }
  apply();
  document.addEventListener('DOMContentLoaded', apply);
  try {
    var _s = localStorage.getItem('rc-theme');
    if (_s) { /* theme already applied by head script */ }
  } catch(e) {}
  var obs = new MutationObserver(apply);
  obs.observe(document.documentElement, { attributes:true, attributeFilter:['data-theme','class'] });
})();
</script>

</head>
<body class="rc-login-portal">
<canvas id="rc-login-net" class="rc-login-net" aria-hidden="true"></canvas>
<div class="rc-login-orbs" aria-hidden="true"><i></i><i></i><i></i></div>
<div class="rc-login-scan" aria-hidden="true"></div>
<a class="rc-skip-link" href="#password">Skip to sign-in form</a>

    <main id="login-main" class="card">
        <div class="logo-wrap">
<?php if ($compLogo): ?>
            <?php $__logoBg = strtolower((string)($companyData['logo_bg'] ?? 'light')); if (!in_array($__logoBg, ['light','dark'], true)) $__logoBg = 'light'; ?>
            <span class="rc-logo-plate" data-bg="<?= $h($__logoBg) ?>">
            <img src="<?= $h($compLogo) ?>"
                 alt="<?= $h($compName) ?>"
                 class="logo-img"
                 width="<?= (int)$logoW ?>"
                 height="<?= (int)$logoH ?>"
                 decoding="async"
                 fetchpriority="high">
            </span>
<?php else: ?>
            <div class="logo-fallback" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
<?php endif; ?>
        </div>

        <h1 class="heading">Secure Access</h1>
        <p class="subheading">Authentication required for<br><strong><?= $h($compName) ?></strong></p>
        <div class="divider" role="presentation"></div>

        <p class="text-[11px] text-slate-500 mb-3" style="max-width:22rem;margin:0 auto 0.75rem;text-align:center">Enterprise access key required. Contact your administrator for credentials.</p>
        <form id="loginForm" method="post" action="index.php" autocomplete="current-password">
            <div class="input-wrap">
                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 1 0-8 0v4h8z"/></svg>
                <label for="password" class="visually-hidden" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0">Access key</label>
                <input type="password"
                       id="password"
                       name="password"
                       class="input-field"
                       placeholder="Enter access key"
                       autocomplete="current-password"
                       required
                       autofocus>
            </div>
            <button type="submit" class="btn" id="btn">
                <svg id="btnIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v1"/></svg>
                <span id="btnText">Authenticate</span>
            </button>
            <div id="msg" class="msg-box" role="status" aria-live="polite"></div>
        </form>

<?php if ($websiteUrl !== '' && $websiteUrl !== '#'): ?>
        <div class="links">
            <a href="<?= $h($websiteUrl) ?>" rel="noopener noreferrer">Visit website</a>
        </div>
<?php endif; ?>
    </main>

    <p class="powered">Powered by <a href="https://arthsathi.com" rel="noopener noreferrer">Arthsathi</a>
        · <a href="index.php?tab=terms&amp;policy=privacy">Privacy</a>
        · <a href="index.php?tab=terms">Terms</a></p>
    <p class="powered" style="margin-top:.35rem;font-size:.7rem;opacity:.85">Personal data is processed under the organisation’s policies and applicable Indian law (including DPDP Act, 2023).</p>

    <script>
    (function () {
        var form = document.getElementById('loginForm');
        if (!form) return;
        var iconLogin = '<path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v1"/>';
        var iconSpin  = '<path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>';
        var iconOk    = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>';
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('btn');
            var btnText = document.getElementById('btnText');
            var btnIcon = document.getElementById('btnIcon');
            var msg = document.getElementById('msg');
            var pass = document.getElementById('password').value;
            btn.disabled = true;
            btnText.textContent = 'Verifying…';
            btnIcon.innerHTML = iconSpin;
            btnIcon.classList.add('spin');
            msg.className = 'msg-box';
            msg.textContent = '';
            fetch('index.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Cache-Control': 'no-cache' },
                body: JSON.stringify({
                    action: 'login',
                    password: pass,
                    csrf_token: <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                })
            }).then(function (res) {
                return res.text().then(function (text) {
                    var data = null;
                    try { data = text ? JSON.parse(text) : null; } catch (e) {
                        throw new Error(
                            (text && text.replace(/<[^>]+>/g, ' ').trim().slice(0, 160))
                            || ('Login failed (HTTP ' + res.status + ', empty or non-JSON response)')
                        );
                    }
                    if (!data) throw new Error('Login failed (HTTP ' + res.status + ', empty response)');
                    return data;
                });
            }).then(function (data) {
                if (data.status === 'success') {
                    msg.className = 'msg-box ok';
                    msg.textContent = 'Access granted — redirecting…';
                    btn.classList.add('success');
                    btnText.textContent = 'Redirecting…';
                    btnIcon.classList.remove('spin');
                    btnIcon.innerHTML = iconOk;
                    // Hard navigation (not reload) so a stale SW cannot keep serving login HTML
                    setTimeout(function () {
                        window.location.replace(window.location.pathname + window.location.search + (window.location.search ? '&' : '?') + '_=' + Date.now());
                    }, 400);
                } else {
                    throw new Error(data.message || 'Invalid access key');
                }
            }).catch(function (err) {
                msg.className = 'msg-box error';
                msg.textContent = err.message || 'Login failed';
                btn.disabled = false;
                btn.classList.remove('success');
                btnText.textContent = 'Authenticate';
                btnIcon.classList.remove('spin');
                btnIcon.innerHTML = iconLogin;
                var pw = document.getElementById('password');
                pw.value = '';
                pw.focus();
            });
        });
    })();
    </script>
<script>
(function () {
  if (!('serviceWorker' in navigator)) return;
  navigator.serviceWorker.getRegistrations().then(function (regs) {
    regs.forEach(function (r) { r.unregister(); });
  }).catch(function () {});
})();
</script>

<style id="rc-login-portal-css">
html,body.rc-login-portal{min-height:100%;margin:0}
body.rc-login-portal{
  background:#0b1220!important;color:#e2e8f0;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  min-height:100vh;min-height:100dvh;padding:1.5rem;position:relative;overflow-x:hidden;
}
.rc-login-net{position:fixed;inset:0;width:100%;height:100%;z-index:0;pointer-events:none}
.rc-login-orbs{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.rc-login-orbs i{position:absolute;border-radius:50%;filter:blur(60px);opacity:.35}
.rc-login-orbs i:nth-child(1){width:40vw;height:40vw;left:-10%;top:-10%;background:#1d4ed8}
.rc-login-orbs i:nth-child(2){width:30vw;height:30vw;right:-5%;bottom:10%;background:#0e7490}
.rc-login-orbs i:nth-child(3){width:25vw;height:25vw;left:40%;bottom:-10%;background:#4c1d95}
.rc-login-scan{position:fixed;inset:0;z-index:0;pointer-events:none;
  background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(255,255,255,.02) 2px,rgba(255,255,255,.02) 4px)}
body.rc-login-portal main.card,#login-main.card{
  position:relative;z-index:2;
  background:rgba(15,23,42,.72)!important;backdrop-filter:blur(16px);
  border:1px solid rgba(148,163,184,.25)!important;
  box-shadow:0 25px 50px -12px rgba(0,0,0,.5)!important;
  color:#e2e8f0!important;
}
body.rc-login-portal .heading,body.rc-login-portal .subheading,body.rc-login-portal .subheading strong{color:#f1f5f9!important}
body.rc-login-portal .powered,body.rc-login-portal .powered a{color:#94a3b8!important}
body.rc-login-portal .input-field{background:#0f172a!important;color:#f1f5f9!important;border-color:#334155!important}
body.rc-login-portal .btn{background:#2563eb!important;color:#fff!important}
@media (prefers-reduced-motion: reduce){
  .rc-login-net,.rc-login-orbs,.rc-login-scan{display:none!important}
  body.rc-login-portal{background:#0f172a!important}
}
</style>
<script>
(function(){
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var c = document.getElementById('rc-login-net');
  if (!c || !c.getContext) return;
  var ctx = c.getContext('2d'), pts = [], N = 48, W, H;
  function resize(){ W=c.width=window.innerWidth; H=c.height=window.innerHeight; }
  function init(){
    pts=[]; for(var i=0;i<N;i++) pts.push({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.35,vy:(Math.random()-.5)*.35});
  }
  function tick(){
    ctx.clearRect(0,0,W,H);
    for(var i=0;i<pts.length;i++){
      var p=pts[i]; p.x+=p.vx; p.y+=p.vy;
      if(p.x<0||p.x>W)p.vx*=-1; if(p.y<0||p.y>H)p.vy*=-1;
      ctx.beginPath(); ctx.arc(p.x,p.y,1.4,0,6.28); ctx.fillStyle='rgba(148,163,184,.55)'; ctx.fill();
      for(var j=i+1;j<pts.length;j++){
        var q=pts[j], dx=p.x-q.x, dy=p.y-q.y, d=Math.sqrt(dx*dx+dy*dy);
        if(d<120){ ctx.strokeStyle='rgba(59,130,246,'+(0.18*(1-d/120))+')'; ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(q.x,q.y); ctx.stroke(); }
      }
    }
    requestAnimationFrame(tick);
  }
  resize(); init(); tick();
  window.addEventListener('resize', function(){ resize(); init(); });
})();
</script>

</body>
</html>
