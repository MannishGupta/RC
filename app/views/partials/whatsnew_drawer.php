<?php
// Version: 3.1.0
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
if (!class_exists('WhatsNewStore') && is_file(BASE_PATH . '/app/WhatsNew/Store.php')) {
    require_once BASE_PATH . '/app/WhatsNew/Store.php';
}
$__wn = class_exists('WhatsNewStore') ? WhatsNewStore::all() : [];
?>
<aside id="rc-whatsnew-drawer" class="no-print" hidden
       style="position:fixed;top:0;right:0;width:min(420px,100%);height:100dvh;height:100vh;z-index:9000;background:var(--rc-card,#fff);color:var(--rc-ink,#201F1E);border-left:1px solid var(--rc-border,#E1DFDD);box-shadow:-8px 0 32px rgba(0,0,0,.12);display:flex;flex-direction:column;padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0) 0">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--rc-border,#E1DFDD)">
    <strong>What's New</strong>
    <button type="button" id="rc-wn-close" aria-label="Close" style="min-width:44px;min-height:44px;border:none;background:transparent;font-size:20px;cursor:pointer">×</button>
  </div>
  <div style="padding:8px 16px;display:flex;flex-wrap:wrap;gap:6px;border-bottom:1px solid var(--rc-border,#E1DFDD)">
    <button type="button" class="rc-wn-filter" data-filter="all" style="min-height:32px;padding:4px 10px;border-radius:999px;border:1px solid var(--rc-border);background:var(--rc-accent,#0078D4);color:#fff;cursor:pointer">All</button>
    <?php foreach (['Feature', 'Improvement', 'Fix', 'Security'] as $__f): ?>
    <button type="button" class="rc-wn-filter" data-filter="<?= htmlspecialchars($__f, ENT_QUOTES, 'UTF-8') ?>" style="min-height:32px;padding:4px 10px;border-radius:999px;border:1px solid var(--rc-border);background:transparent;cursor:pointer"><?= htmlspecialchars($__f, ENT_QUOTES, 'UTF-8') ?></button>
    <?php endforeach; ?>
  </div>
  <div style="padding:8px 16px;display:flex;flex-wrap:wrap;gap:6px">
    <button type="button" data-wn-speak="play" data-wn-lang="en-IN" data-wn-rate="1" style="min-height:44px;padding:6px 12px;border-radius:6px;border:1px solid var(--rc-border);cursor:pointer">Listen (EN)</button>
    <button type="button" data-wn-speak="play" data-wn-lang="hi-IN" data-wn-rate="1" style="min-height:44px;padding:6px 12px;border-radius:6px;border:1px solid var(--rc-border);cursor:pointer">सुनें (HI)</button>
    <button type="button" data-wn-speak="stop" style="min-height:44px;padding:6px 12px;border-radius:6px;border:1px solid var(--rc-border);cursor:pointer">Stop</button>
  </div>
  <div id="rc-whatsnew-panel" role="region" aria-live="polite" style="flex:1;overflow:auto;padding:12px 16px">
    <?php if ($__wn === []): ?>
      <p style="color:var(--rc-ink-muted)">No release notes yet.</p>
    <?php else: foreach ($__wn as $__item): ?>
      <article class="rc-wn-item" data-category="<?= htmlspecialchars($__item['category'], ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--rc-border,#E1DFDD)">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
          <span style="font-size:11px;font-weight:700;background:var(--rc-side-active-bg,#EFF6FC);color:var(--rc-side-active-ink,#005A9E);padding:2px 8px;border-radius:999px"><?= htmlspecialchars($__item['version'], ENT_QUOTES, 'UTF-8') ?></span>
          <span style="font-size:11px;color:var(--rc-ink-muted)"><?= htmlspecialchars($__item['category'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <h3 style="font-size:14px;margin:0 0 4px"><?= htmlspecialchars($__item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <pre style="white-space:pre-wrap;font:12px/1.5 system-ui,sans-serif;margin:0;color:var(--rc-ink-soft,#323130)"><?= htmlspecialchars($__item['body'], ENT_QUOTES, 'UTF-8') ?></pre>
      </article>
    <?php endforeach; endif; ?>
  </div>
</aside>
<script>
(function () {
  var drawer = document.getElementById('rc-whatsnew-drawer');
  var openers = document.querySelectorAll('[data-open-whatsnew]');
  var closeBtn = document.getElementById('rc-wn-close');
  function show(on) {
    if (!drawer) return;
    drawer.hidden = !on;
    drawer.style.display = on ? 'flex' : 'none';
  }
  openers.forEach(function (el) {
    el.addEventListener('click', function (e) { e.preventDefault(); show(true); });
  });
  if (closeBtn) closeBtn.addEventListener('click', function () { show(false); });
  document.querySelectorAll('.rc-wn-filter').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var f = btn.getAttribute('data-filter') || 'all';
      document.querySelectorAll('.rc-wn-item').forEach(function (item) {
        item.style.display = (f === 'all' || item.getAttribute('data-category') === f) ? '' : 'none';
      });
    });
  });
})();
</script>
<script src="/assets/js/whatsnew-speech.js?v=<?= rawurlencode(defined('APP_VERSION') ? (string) APP_VERSION : '1') ?>" defer></script>
