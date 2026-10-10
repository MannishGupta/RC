<?php // Version: 260916.14
declare(strict_types=1);
/**
 * offline.php — Shown by the service worker when a page is requested with no
 * connection and no cached copy.
 *
 * Precached at install, so it must not depend on anything that could be
 * unavailable offline: no CDN fonts, no Font Awesome, no external CSS. Styles
 * are inline and the icon is a plain glyph. A fallback page that itself fails
 * to render offline is worse than none.
 */

$rootPath = __DIR__;
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH')) define('IMG_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/images');
if (!defined('DOC_PATH')) define('DOC_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/documents');
require_once BASE_PATH . '/app/bootstrap.php';

$company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$orgName = (string)($company['name'] ?? 'Directory');
?>
<!DOCTYPE html>
<html lang="en" data-theme="reserve">
<head>
<?php if (defined('BASE_PATH') && is_file(BASE_PATH . '/app/views/partials/rc_theme_head.php')) { require BASE_PATH . '/app/views/partials/rc_theme_head.php'; } ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title>Offline — <?= htmlspecialchars($orgName) ?></title>
<meta name="description" content="You are offline. <?= htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8') ?> Resource Centre will reconnect when your network is available.">
<meta name="robots" content="noindex">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter','Aptos',system-ui,-apple-system,'Segoe UI',sans-serif;
       background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;
       align-items:center;justify-content:center;padding:2rem;
       padding-bottom:calc(2rem + env(safe-area-inset-bottom));-webkit-font-smoothing:antialiased}
  .box{max-width:24rem;text-align:center}
  .icon{font-size:3rem;line-height:1;margin-bottom:1.25rem;opacity:.5}
  h1{font-size:1.25rem;font-weight:700;margin-bottom:.6rem;color:#f8fafc}
  p{font-size:.875rem;line-height:1.65;color:#94a3b8;margin-bottom:.75rem}
  .hint{font-size:.75rem;color:#64748b;margin-top:1.5rem;line-height:1.6}
  button{margin-top:1.5rem;padding:.8rem 1.75rem;border:0;border-radius:.6rem;
         background:#f8fafc;color:#0f172a;font:600 .875rem inherit;cursor:pointer}
  button:active{transform:scale(.98)}
  .status{margin-top:1rem;font-size:.75rem;font-weight:600}
</style>
</head>
<body>
  <div class="box">
    <div class="icon">&#9888;</div>
    <h1>You're offline</h1>
    <p>
      This page hasn't been opened on this device yet, so there's no saved copy
      to show.
    </p>
    <p>
      Pages you have already visited &mdash; including the team directory
      &mdash; stay available offline.
    </p>

    <button type="button" id="btnRetry">Try again</button>
  <button type="button" id="btnClear" style="margin-top:.5rem;background:transparent;color:#94a3b8;border:1px solid #334155">Clear offline cache</button>
    <div class="status" id="status"></div>

    <p class="hint">
      <?= htmlspecialchars($orgName) ?> &middot;
      build <?= htmlspecialchars(defined('APP_VERSION') ? APP_VERSION : '—') ?>
    </p>
  </div>

<script>
  var el = document.getElementById('status');
  function online() {
    el.textContent = 'Back online \u2014 reloading\u2026';
    el.style.color = '#86efac';
    setTimeout(function () { location.href = '/'; }, 400);
  }
  window.addEventListener('online', online);
  if (navigator.onLine) {
    el.textContent = 'Connection detected — tap Try again.';
    el.style.color = '#93c5fd';
  }
  document.getElementById('btnRetry').addEventListener('click', function () {
    location.href = '/?_=' + Date.now();
  });
  document.getElementById('btnClear').addEventListener('click', function () {
    var done = function () { location.href = '/?_=' + Date.now(); };
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(function (regs) {
        return Promise.all(regs.map(function (r) { return r.unregister(); }));
      }).then(function () {
        return caches.keys();
      }).then(function (keys) {
        return Promise.all(keys.map(function (k) { return caches.delete(k); }));
      }).then(done).catch(done);
    } else {
      done();
    }
  });
</script>
</body>
</html>