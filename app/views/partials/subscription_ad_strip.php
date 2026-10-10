<?php
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}
if (!class_exists('ModuleRegistry') && is_file(BASE_PATH . '/app/ModuleRegistry.php')) {
    require_once BASE_PATH . '/app/ModuleRegistry.php';
}
$__adOn = true;
if (class_exists('ModuleRegistry')) {
    $__adOn = ModuleRegistry::isEnabled('subscription_ad');
}
if (!$__adOn) {
    return;
}
?>
<div id="rc-sub-ad" class="subscription-ad-strip no-print" role="region" aria-label="Subscription notice">
  <div class="rc-sub-ad__row">
    <a class="rc-sub-ad__cta" href="https://www.arthsathi.com" target="_blank" rel="noopener noreferrer">Pay subscription</a>
    <div class="rc-sub-ad__marquee" aria-hidden="true">
      <div class="rc-sub-ad__track">
        <span>Arthsathi Resource Centre — Subscribe to remove this notice · licensing@arthsathi.com</span>
        <span>Arthsathi Resource Centre — Subscribe to remove this notice · licensing@arthsathi.com</span>
      </div>
    </div>
  </div>
</div>
<style>
#rc-sub-ad.subscription-ad-strip{flex:0 0 auto;width:100%;z-index:40;background:#0f172a;color:#e2e8f0;font-size:12px;line-height:1.2;border-bottom:1px solid rgba(255,255,255,.08)}
#rc-sub-ad .rc-sub-ad__row{display:flex;align-items:center;min-height:28px}
#rc-sub-ad .rc-sub-ad__cta{flex:0 0 auto;z-index:2;padding:4px 12px;margin:2px 0 2px 6px;background:#2563eb;color:#fff!important;font-weight:700;font-size:11px;border-radius:999px;text-decoration:none;white-space:nowrap}
#rc-sub-ad .rc-sub-ad__marquee{flex:1 1 auto;overflow:hidden}
#rc-sub-ad .rc-sub-ad__track{display:inline-flex;gap:3rem;white-space:nowrap;animation:rcSubAd 28s linear infinite;padding-left:1rem}
@keyframes rcSubAd{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@media (prefers-reduced-motion:reduce){#rc-sub-ad .rc-sub-ad__track{animation:none}}
</style>
