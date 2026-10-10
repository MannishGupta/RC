<?php
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
$__pwaName = trim((string)($viewData['company']['name'] ?? '')) ?: 'Resource Centre';
$__pwaVer  = defined('APP_VERSION') ? (string)APP_VERSION : '';
?>
<div id="rc-pwa-install" class="rc-pwa-install no-print" hidden role="region" aria-label="Install app">
  <div class="rc-pwa-install__inner">
    <div class="rc-pwa-install__icon" aria-hidden="true">
      <img src="/tools/pwa_icon.php?size=96" width="40" height="40" alt="" loading="lazy"
           onerror="this.style.display='none'">
    </div>
    <div class="rc-pwa-install__copy min-w-0">
      <p class="rc-pwa-install__title">App Available. Install Resource Centre.</p>
      <p class="rc-pwa-install__sub">
        <span><?= htmlspecialchars($__pwaName, ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($__pwaVer !== ''): ?>
          · v<?= htmlspecialchars($__pwaVer, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
        · Standalone window · Home screen / taskbar
      </p>
      <p class="rc-pwa-install__ios" id="rc-pwa-ios-hint" hidden>
        iPhone/iPad: Safari → Share → <strong>Add to Home Screen</strong>
      </p>
    </div>
    <div class="rc-pwa-install__actions">
      <button type="button" id="rc-pwa-install-btn" class="rc-pwa-install__btn">Install</button>
      <button type="button" id="rc-pwa-install-dismiss" class="rc-pwa-install__dismiss">Not now</button>
    </div>
  </div>
</div>
<style>
.rc-pwa-install{
  position:fixed;left:0;right:0;bottom:0;z-index:80;
  padding:10px 12px calc(10px + env(safe-area-inset-bottom,0px));
  background:var(--rc-card,#fff);border-top:1px solid var(--rc-border,#e2e8f0);
  box-shadow:0 -8px 24px rgba(15,23,42,.12);color:var(--rc-ink,#0f172a);
}
.rc-pwa-install__inner{max-width:960px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.rc-pwa-install__title{margin:0;font-size:14px;font-weight:800;color:var(--rc-ink)!important}
.rc-pwa-install__sub,.rc-pwa-install__ios{margin:2px 0 0;font-size:11px;color:var(--rc-ink-muted,#64748b)!important}
.rc-pwa-install__actions{display:flex;gap:8px;margin-left:auto}
.rc-pwa-install__btn{height:36px;padding:0 16px;border-radius:999px;border:0;background:var(--rc-accent,#0078D4);color:#fff!important;font-weight:700;font-size:13px;cursor:pointer}
.rc-pwa-install__dismiss{height:36px;padding:0 12px;border-radius:999px;border:1px solid var(--rc-border);background:transparent;color:var(--rc-ink-muted)!important;font-weight:600;font-size:12px;cursor:pointer}
html[data-theme="dark"] .rc-pwa-install{background:#1e293b;border-color:#334155}
@media (display-mode: standalone), (display-mode: window-controls-overlay){
  .rc-pwa-install{display:none!important}
}
</style>
<script>
(function () {
  var bar = document.getElementById('rc-pwa-install');
  if (!bar) return;
  // Bump key so prior "Not now" does not hide the bar after this fix
  var KEY = 'rc_pwa_install_dismissed_v3';
  try { if (localStorage.getItem(KEY) === '1') return; } catch (e) {}
  try {
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (window.navigator.standalone === true) return;
  } catch (e) {}

  var deferred = null;
  var btn = document.getElementById('rc-pwa-install-btn');
  var dismiss = document.getElementById('rc-pwa-install-dismiss');
  var iosHint = document.getElementById('rc-pwa-ios-hint');
  var helpMode = false;

  function show() { bar.hidden = false; }

  function bindNativeInstall() {
    if (!btn) return;
    helpMode = false;
    btn.textContent = 'Install';
    btn.onclick = function () {
      if (!deferred) return;
      deferred.prompt();
      deferred.userChoice.then(function (c) {
        deferred = null;
        if (c && c.outcome === 'accepted') bar.hidden = true;
      }).catch(function () {});
    };
  }

  function bindHelpInstall() {
    if (!btn || deferred) return;
    helpMode = true;
    btn.textContent = 'How to install';
    btn.onclick = function () {
      alert(
        'Install Resource Centre\n\n' +
        'Microsoft Edge / Google Chrome on Windows:\n' +
        '1. Click the install icon in the address bar, or\n' +
        '2. Menu (⋯) → Apps → Install this site as an app\n\n' +
        'Use HTTPS. Stay on the site a few seconds so the browser enables Install.\n\n' +
        'If Install is greyed out: clear site data once, hard-refresh, open the dashboard again.'
      );
    };
  }

  // Capture native event ASAP (same path that works on Linux)
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    bindNativeInstall();
    show();
  });

  var ua = navigator.userAgent || '';
  var isIOS = /iPad|iPhone|iPod/.test(ua) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

  if (isIOS && iosHint) {
    iosHint.hidden = false;
    if (btn) btn.style.display = 'none';
    show();
  }

  // Windows/Edge: wait longer for beforeinstallprompt before falling back
  // (engagement criteria often fire after SW + manifest settle)
  if (!isIOS) {
    setTimeout(function () {
      if (!deferred) {
        show();
        bindHelpInstall();
      }
    }, 4500);
  }

  if (dismiss) {
    dismiss.addEventListener('click', function () {
      bar.hidden = true;
      try { localStorage.setItem(KEY, '1'); } catch (e) {}
    });
  }
  window.addEventListener('appinstalled', function () {
    bar.hidden = true;
    deferred = null;
  });
})();
</script>
