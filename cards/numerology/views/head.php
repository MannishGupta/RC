<?php // Version: 260921.32
/**
 * num_template.php — Arthsathi Vedic Numero Template V17.0
 *
 * Pure HTML rendering. Zero logic. Zero data loading.
 * All variables provided by num_controller.php
 */
if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit;
?>
<!DOCTYPE html>
<?php /* initTab set by num_controller.php */ ?>
<html lang="<?= htmlspecialchars(($lang === 'hi' ? 'hi' : 'en'), ENT_QUOTES, 'UTF-8') ?>" dir="ltr" x-data="{
    activeTab: '<?= $initTab ?>',
    shareOpen: false,
    lang: '<?= $lang ?>',
    settingsOpen: false,
    alertsOpen: false,
    openPhoto: false,
    reportMode: localStorage.getItem('numero_mode') || 'advanced',
    setMode(m) { this.reportMode = m; localStorage.setItem('numero_mode', m); },
    tabVisible(id) {
        const basic = ['executive','timeline','forecast','structural','personality'];
        return this.reportMode === 'advanced' || basic.includes(id);
    }
}" id="html-root">
<head>
<link rel="stylesheet" href="/assets/numerology.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php
    /*
     * OG / Social meta — V17.6 fixes:
     *  [1] Use controller's $ogTitle / $ogImg / $ogUrl (set in meta.php) — never recompute
     *  [2] ogDesc: plain Unicode · not HTML &middot; (OG content is NOT HTML)
     *  [3] og:site_name, og:locale, og:image:width/height added
     *  [4] twitter:card → summary_large_image (full-width preview)
     *  [5] twitter:description + twitter:url added
     */
    // Use controller vars — with safe fallbacks if meta.php hasn't run
    $_ogTitle  = isset($ogTitle)
        ? $ogTitle
        : htmlspecialchars((string)$p['name'], ENT_QUOTES) . ($lang==='hi' ? ' — वैदिक अंकशास्त्र रिपोर्ट' : ' — Vedic Numerology Report');
    $_ogDesc   = 'Driver ' . (int)$core['driver']
               . ' · ' . htmlspecialchars((string)($report['lucky_driver']['name'] ?? ''), ENT_QUOTES)
               . ' · Score ' . (int)$report['summary']['score'] . '/100';   // plain ·, NOT &middot;
    $_ogImgOut = htmlspecialchars((string)($ogImg ?? $absPhoto ?? ''), ENT_QUOTES);
    $_ogUrlOut = htmlspecialchars((string)($ogUrl ?? $shareUrl ?? ''), ENT_QUOTES);
    $_ogLocale = ($lang === 'hi') ? 'hi_IN' : 'en_IN';
    $_siteName = htmlspecialchars((string)($clientName ?? 'Arthsathi'), ENT_QUOTES);
    ?>
    <title><?= $_ogTitle ?></title>
    <meta property="og:site_name"    content="<?= $_siteName ?>">
    <meta property="og:title"        content="<?= $_ogTitle ?>">
    <meta property="og:description"  content="<?= $_ogDesc ?>">
    <meta property="og:url"          content="<?= $_ogUrlOut ?>">
    <meta property="og:image"        content="<?= $_ogImgOut ?>">
    <meta property="og:image:width"  content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:type"         content="profile">
    <meta property="og:locale"       content="<?= $_ogLocale ?>">
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="<?= $_ogTitle ?>">
    <meta name="twitter:description" content="<?= $_ogDesc ?>">
    <meta name="twitter:image"       content="<?= $_ogImgOut ?>">
    <meta name="twitter:url"         content="<?= $_ogUrlOut ?>">
    <link id="dynamic-favicon" rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%230f172a'/%3E%3Cpath d='M30 70V50M50 70V30M70 70V40' stroke='%2338bdf8' stroke-width='10' stroke-linecap='round'/%3E%3C/svg%3E">
    <script>
        // BUG FIX (260916.12): this IIFE toggled .dark on <html> based on
        // system preference / localStorage, and Tailwind's dark: variant is
        // used HUNDREDS of times across the ten report tabs (dark:bg-slate-700,
        // dark:text-emerald-400, and dozens more colour families) — none of
        // which the gold/obsidian reskin's structural override (260916.05)
        // was ever scoped to touch; that override only targeted a handful of
        // base classes (bg-white, bg-slate-50/100/800/900, border/text
        // neutrals). The practical effect: elements using those specific
        // base classes were forced into gold/obsidian regardless of system
        // preference, while everything using a dark: variant kept actively
        // flipping between generic light and dark Tailwind colours depending
        // on the visitor's OS setting — a genuinely inconsistent, "odd"
        // result, and the report's whole point was ONE fixed premium theme,
        // the same way the cards (business/ID/visiting) have no light/dark
        // distinction at all.
        // Auditing and overriding every dark: variant individually would be
        // hundreds of new rules with real risk of missing some. The correct,
        // scoped fix is the root cause: .dark is never applied at all, so
        // every dark: variant everywhere becomes permanently inactive and
        // the page settles into one stable, predictable state instead of
        // shifting based on a preference the reskin was never designed to
        // respect. The manual toggle button was already removed
        // (footer_ui.php) as the same fix from the other direction.
        (function(){
            document.documentElement.classList.remove('dark');
        })();
    </script>
    <!-- Tailwind Play CDN kept: numerology uses ~170 colour utilities (amber/
         emerald/rose/…) not present in assets/app.css. Dropping CDN requires
         a one-time compile when markup stabilises (see assets/TAILWIND.md). -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <script>tailwind = { config: { darkMode: 'class' } };</script>
    <link rel="stylesheet" href="/assets/app.css">
    <script>
        // ✅ V16.3: Removed duplicate `tailwind.config = {darkMode:'class'}` — pre-CDN
        //           assignment on the line above (tailwind={config:{...}}) is authoritative.
        // Full NumeroTheme API
        (function(){
            var KEY = 'numero_theme';
            // BUG FIX (260916.12): neutralised for the same reason as the
            // earlier pre-Tailwind IIFE above — see that comment. The
            // NumeroTheme object itself (localStorage.setItem, current(),
            // etc.) is left intact and harmless in case anything else ever
            // calls it; only the one function that actually toggled .dark
            // is disabled.
            function applyTheme(t) {
                document.documentElement.classList.remove('dark');
            }
            function highlightBtn(t) {
                document.querySelectorAll('[data-theme]').forEach(function(b) {
                    var on = (b.dataset.theme === t);
                    b.classList.toggle('bg-white',     on);
                    b.classList.toggle('text-slate-900', on);
                    b.classList.toggle('shadow-sm',    on);
                    b.classList.toggle('text-slate-400', !on);
                });
            }
            // Listen for OS changes when in system mode
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
                if ((localStorage.getItem(KEY) || 'system') === 'system') applyTheme('system');
            });
            window.NumeroTheme = {
                set: function(t) {
                    localStorage.setItem(KEY, t);
                    applyTheme(t);
                    highlightBtn(t);
                },
                current: function() { return localStorage.getItem(KEY) || 'system'; },
                init: function() {
                    highlightBtn(this.current());
                }
            };
        })();
    </script>
    <script defer src="/assets/vendor/alpine.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            NumeroTheme.init();
        });
    </script>
    <link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? (string)APP_VERSION : '1') ?>" rel="stylesheet">
    <link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? (string)APP_VERSION : '1') ?>" rel="stylesheet" media="all">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <?php if ($lang === 'hi'): ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
    
    <?php endif; ?>
    
<?php require dirname(__DIR__, 2) . '/partials/lightbox.php'; ?>
<link rel="stylesheet" href="/assets/contrast-lock.css?v=20260928.07">
</head>
