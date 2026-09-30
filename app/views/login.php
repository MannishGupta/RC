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
<html lang="en-IN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
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
            min-height:100vh;
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
<link rel="stylesheet" href="/assets/contrast-lock.css?v=20260928.07">
    <script src="assets/a11y-tooltip.js?v=260921.29" defer></script>
</head>
<body>
<a class="rc-skip-link" href="#password">Skip to sign-in form</a>

    <main id="login-main" class="card">
        <div class="logo-wrap">
<?php if ($compLogo): ?>
            <img src="<?= $h($compLogo) ?>"
                 alt="<?= $h($compName) ?>"
                 class="logo-img"
                 width="<?= (int)$logoW ?>"
                 height="<?= (int)$logoH ?>"
                 decoding="async"
                 fetchpriority="high">
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
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    action: 'login',
                    password: pass,
                    csrf_token: <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                })
            }).then(function (res) { return res.json(); }).then(function (data) {
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
</body>
</html>
