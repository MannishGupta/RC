<?php
// Version: 3.1.0
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}

if (!class_exists('EighthSchedule') && is_file(BASE_PATH . '/core/I18n/EighthSchedule.php')) {
    require_once BASE_PATH . '/core/I18n/EighthSchedule.php';
}
if (!class_exists('NoticeStore') && is_file(BASE_PATH . '/core/Compliance/NoticeStore.php')) {
    require_once BASE_PATH . '/core/Compliance/NoticeStore.php';
} elseif (!class_exists('NoticeStore') && is_file(BASE_PATH . '/app/Compliance/NoticeStore.php')) {
    require_once BASE_PATH . '/app/Compliance/NoticeStore.php';
}
$__lang = 'en';
if (!empty($_GET['lang']) && preg_match('/^(en|hi)/i', (string) $_GET['lang'])) {
    $__lang = class_exists('EighthSchedule') ? EighthSchedule::normalize((string)$_GET['lang']) : strtolower(substr((string)$_GET['lang'], 0, 2));
}
$__notice = class_exists('NoticeStore') ? NoticeStore::get('cookies', $__lang) : null;
$__csrf = '';
if (class_exists('AppAuth') && method_exists('AppAuth', 'csrfToken')) {
    $__csrf = (string) AppAuth::csrfToken();
} elseif (!empty($_SESSION['csrf'])) {
    $__csrf = (string) $_SESSION['csrf'];
}
?>
<div id="rc-consent-banner" class="no-print" role="dialog" aria-label="Cookie and data consent" hidden
     style="position:fixed;bottom:0;left:0;right:0;z-index:10000;background:var(--rc-card,#fff);color:var(--rc-ink,#201F1E);border-top:1px solid var(--rc-border,#E1DFDD);padding:12px 16px;padding-bottom:calc(12px + env(safe-area-inset-bottom,0px));box-shadow:0 -4px 24px rgba(0,0,0,.08);font:14px/1.45 system-ui,sans-serif">
  <div style="max-width:960px;margin:0 auto;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between">
    <div style="flex:1;min-width:220px">
      <strong style="display:block;margin-bottom:4px"><?= htmlspecialchars($__lang === 'hi' ? 'गोपनीयता विकल्प' : 'Privacy choices', ENT_QUOTES, 'UTF-8') ?></strong>
      <p style="margin:0;color:var(--rc-ink-muted,#605E5C);font-size:13px">
        <?= htmlspecialchars($__lang === 'hi'
          ? 'आवश्यक कुकीज़ साइट चलाने के लिए लगती हैं। वैकल्पिक विश्लेषण केवल आपकी सहमति के बाद।'
          : 'Essential cookies keep the site working. Optional analytics run only after you agree.', ENT_QUOTES, 'UTF-8') ?>
        <?php if ($__notice): ?>
          <a href="?tab=terms&amp;policy=privacy" style="color:var(--rc-accent,#0078D4)"><?= htmlspecialchars($__lang === 'hi' ? 'विवरण' : 'Details', ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
      <button type="button" class="rc-consent-btn" data-consent="deny" style="min-height:44px;min-width:44px;padding:8px 14px;border-radius:6px;border:1px solid var(--rc-border,#E1DFDD);background:transparent;cursor:pointer">
        <?= htmlspecialchars($__lang === 'hi' ? 'केवल आवश्यक' : 'Essential only', ENT_QUOTES, 'UTF-8') ?>
      </button>
      <button type="button" class="rc-consent-btn" data-consent="grant" style="min-height:44px;min-width:44px;padding:8px 14px;border-radius:6px;border:none;background:var(--rc-accent,#0078D4);color:#fff;cursor:pointer;font-weight:600">
        <?= htmlspecialchars($__lang === 'hi' ? 'सहमत' : 'Accept optional', ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>
</div>
<script>
(function () {
  var KEY = 'rc_consent_v1';
  var box = document.getElementById('rc-consent-banner');
  if (!box) return;
  try {
    if (localStorage.getItem(KEY)) return;
  } catch (e) {}
  box.hidden = false;
  box.querySelectorAll('.rc-consent-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var action = btn.getAttribute('data-consent') === 'grant' ? 'grant' : 'deny';
      try { localStorage.setItem(KEY, action + '|' + Date.now()); } catch (e) {}
      box.hidden = true;
      var body = new URLSearchParams();
      body.set('action', 'consent_log');
      body.set('purpose_id', 'analytics_optional');
      body.set('consent_action', action === 'grant' ? 'grant' : 'deny');
      body.set('lang', <?= json_encode($__lang) ?>);
      body.set('notice_version', <?= json_encode($__notice['version'] ?? '1') ?>);
      <?php if ($__csrf !== ''): ?>body.set('csrf', <?= json_encode($__csrf) ?>);<?php endif; ?>
      if (navigator.sendBeacon) {
        navigator.sendBeacon(location.pathname, body);
      } else {
        fetch(location.pathname, { method: 'POST', body: body, credentials: 'same-origin' }).catch(function () {});
      }
    });
  });
})();
</script>
