<?php
/**
 * Version: 2.0 — Sacred command bar
 * Hierarchical: system mode · primary chapters · advanced modules · share tools
 */
if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit;

$basicTabIds = ['executive', 'timeline', 'forecast', 'structural', 'personality'];
$basicTabs = array_values(array_filter($tabs, function ($t) use ($basicTabIds) {
    return in_array($t['id'], $basicTabIds, true);
}));
$advTabs = array_values(array_filter($tabs, function ($t) use ($basicTabIds) {
    return !in_array($t['id'], $basicTabIds, true);
}));

$_modeBase = $_GET;
unset($_modeBase['mobile_mode']);
$_activeMode = $_mcMode ?? 'vedic';
$_vedicHref = '?' . http_build_query(array_merge($_modeBase, ['mobile_mode' => 'vedic']));
$_chaldeanHref = '?' . http_build_query(array_merge($_modeBase, ['mobile_mode' => 'chaldean']));

$wcUrl = '';
if (!empty($slug) && function_exists('imagecreatetruecolor')) {
    $wcUrl = ($protocol ?? '') . ($safeHost ?? '') . ($currentPath ?? '') . '?card=numero&wacard=1&slug=' . urlencode((string)$slug);
}

$isHi = ($lang === 'hi');
?>
<body class="nr-sacred p-3 sm:p-6 md:p-8 transition-colors duration-300">

<div id="report-container" class="nr-manuscript max-w-5xl mx-auto rounded-xl shadow-xl overflow-hidden relative">
    <div class="nr-particles" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>

    <header id="action-bar" class="nr-cmd print:hidden" role="banner">
        <!-- Row A: Title strip + system + share -->
        <div class="nr-cmd-top">
            <div class="nr-cmd-brand">
                <span class="nr-cmd-kicker"><?= $isHi ? 'वैदिक अंकशास्त्र' : 'Sacred Numerology' ?></span>
                <span class="nr-cmd-title"><?= $isHi ? 'बुद्धिमानी रिपोर्ट' : 'Intelligence Report' ?></span>
            </div>

            <div class="nr-cmd-right">
                <div class="nr-seg" role="group" aria-label="<?= $isHi ? 'पद्धति' : 'Calculation system' ?>">
                    <a id="mode-btn-vedic"
                       class="mode-toggle-btn nr-seg-btn<?= $_activeMode === 'vedic' ? ' nr-seg-on' : '' ?>"
                       data-href="<?= htmlspecialchars($_vedicHref, ENT_QUOTES) ?>"
                       href="<?= htmlspecialchars($_vedicHref, ENT_QUOTES) ?>"
                       title="<?= $isHi ? 'वैदिक पद्धति' : 'Vedic system — driver & friend planets' ?>">
                        <span class="nr-seg-glyph" aria-hidden="true">☸</span>
                        <span><?= $isHi ? 'वैदिक' : 'Vedic' ?></span>
                    </a>
                    <a id="mode-btn-chaldean"
                       class="mode-toggle-btn nr-seg-btn<?= $_activeMode === 'chaldean' ? ' nr-seg-on nr-seg-on-gold' : '' ?>"
                       data-href="<?= htmlspecialchars($_chaldeanHref, ENT_QUOTES) ?>"
                       href="<?= htmlspecialchars($_chaldeanHref, ENT_QUOTES) ?>"
                       title="<?= $isHi ? 'चालदेय पद्धति' : 'Chaldean system — BEST_SUMS' ?>">
                        <span class="nr-seg-glyph" aria-hidden="true">✦</span>
                        <span><?= $isHi ? 'चालदेय' : 'Chaldean' ?></span>
                    </a>
                </div>

                <?php
            $_nrLangOther = ($lang === 'hi') ? 'en' : 'hi';
            $_nrQs = $_GET;
            $_nrQs['lang'] = $_nrLangOther;
            $_nrLangHref = strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($_nrQs);
            ?>
                <div class="nr-utils" role="group" aria-label="<?= $isHi ? 'शेयर' : 'Share tools' ?>
                    <a class="nr-util-btn" href="<?= htmlspecialchars($_nrLangHref, ENT_QUOTES) ?>"
                       title="<?= $lang === 'hi' ? 'English' : 'हिन्दी' ?>">
                        <span class="nr-util-label"><?= $lang === 'hi' ? 'EN' : 'हिं' ?></span>
                    </a>
                    <button type="button" class="nr-util-btn" onclick="window.print()"
                            title="<?= $lang === 'hi' ? 'प्रिंट / PDF' : 'Print / PDF' ?>">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                        <span class="nr-util-label"><?= $lang === 'hi' ? 'प्रिंट' : 'Print' ?></span>
                    </button>
">
                    <button type="button" @click="shareOpen = !shareOpen"
                            class="nr-util-btn"
                            :class="shareOpen ? 'nr-util-on' : ''"
                            title="<?= $isHi ? 'शेयर एवं तुलना' : 'Share & compare' ?>">
                        <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
                        <span class="nr-util-label"><?= $isHi ? 'शेयर' : 'Share' ?></span>
                    </button>
                    <?php if (!empty($whatsappUrl)): ?>
                    <a class="nr-util-btn nr-util-wa" href="<?= htmlspecialchars((string)$whatsappUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener"
                       title="WhatsApp">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                        <span class="nr-util-label">WA</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($wcUrl !== ''): ?>
                    <a class="nr-util-btn" href="<?= htmlspecialchars($wcUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener"
                       title="<?= $isHi ? 'छवि कार्ड' : 'Image card' ?>">
                        <i class="fa-solid fa-image" aria-hidden="true"></i>
                        <span class="nr-util-label"><?= $isHi ? 'कार्ड' : 'Card' ?></span>
                    </a>
                    <?php endif; ?>
                    <div class="nr-qr-wrap">
                        <button type="button" class="nr-util-btn" id="nr-qr-toggle" aria-expanded="false" aria-controls="nr-qr-pop"
                                title="<?= $isHi ? 'QR स्कैन' : 'Scan QR code' ?>">
                            <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
                            <span class="nr-util-label">QR</span>
                        </button>
                        <div id="nr-qr-pop" class="nr-qr-pop" hidden>
                            <img src="<?= htmlspecialchars((string)($qrUrl ?? ''), ENT_QUOTES) ?>" width="132" height="132" alt="QR Code"
                                 style="display:block;border-radius:12px;background:#fff;padding:8px">
                            <div class="nr-qr-cap"><?= $isHi ? 'रिपोर्ट खोलने के लिए स्कैन करें' : 'Scan to open this report' ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row B: Primary chapters -->
        <nav class="nr-cmd-nav" aria-label="<?= $isHi ? 'मुख्य अध्याय' : 'Primary chapters' ?>">
            <span class="nr-cmd-nav-label"><?= $isHi ? 'अध्याय' : 'Chapters' ?></span>
            <div class="nr-cmd-pills">
                <?php foreach ($basicTabs as $tab):
                    $tid = htmlspecialchars((string)$tab['id'], ENT_QUOTES);
                    $tlabel = htmlspecialchars((string)$tab['label'], ENT_QUOTES);
                    $ticon = htmlspecialchars((string)($tab['icon'] ?? 'fa-circle'), ENT_QUOTES);
                ?>
                <button type="button"
                        @click="activeTab = '<?= $tid ?>'"
                        class="nr-pill"
                        :class="activeTab === '<?= $tid ?>' ? 'nr-pill-on' : ''">
                    <i class="fa-solid <?= $ticon ?>" aria-hidden="true"></i>
                    <span><?= $tlabel ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </nav>

        <!-- Row C: Advanced modules -->
        <nav class="nr-cmd-nav nr-cmd-nav-adv" x-show="reportMode === 'advanced'" x-cloak
             aria-label="<?= $isHi ? 'उन्नत मॉड्यूल' : 'Advanced modules' ?>">
            <span class="nr-cmd-nav-label"><?= $isHi ? 'उन्नत' : 'Advanced' ?></span>
            <div class="nr-cmd-pills">
                <?php foreach ($advTabs as $tab):
                    $tid = htmlspecialchars((string)$tab['id'], ENT_QUOTES);
                    $tlabel = htmlspecialchars((string)$tab['label'], ENT_QUOTES);
                    $ticon = htmlspecialchars((string)($tab['icon'] ?? 'fa-circle'), ENT_QUOTES);
                ?>
                <button type="button"
                        @click="activeTab = '<?= $tid ?>'"
                        class="nr-pill nr-pill-soft"
                        :class="activeTab === '<?= $tid ?>' ? 'nr-pill-on' : ''">
                    <i class="fa-solid <?= $ticon ?>" aria-hidden="true"></i>
                    <span><?= $tlabel ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </nav>
    </header>

    <!-- Expandable share panel -->
    <div x-show="shareOpen" x-collapse class="nr-share-panel print:hidden" x-cloak>
        <div class="nr-share-inner">
            <div class="nr-share-label"><?= $isHi ? 'शेयर करने योग्य रिपोर्ट लिंक' : 'Shareable report link' ?></div>
            <div class="nr-share-row">
                <input readonly value="<?= htmlspecialchars((string)($shareUrl ?? ''), ENT_QUOTES) ?>" class="nr-share-input">
                <button type="button" onclick="NumeroUI.copyLink()" class="nr-util-btn nr-util-on">
                    <i class="fa-solid fa-copy" aria-hidden="true"></i>
                    <span><?= $isHi ? 'कॉपी' : 'Copy' ?></span>
                </button>
            </div>
            <form method="GET" class="nr-share-compare" id="compare-form">
                <?php foreach ($_GET as $gk => $gv):
                    if ($gk === 'compare_name' || $gk === 'compare_dob') continue;
                    if (is_array($gv)) continue;
                ?>
                <input type="hidden" name="<?= htmlspecialchars((string)$gk, ENT_QUOTES) ?>" value="<?= htmlspecialchars((string)$gv, ENT_QUOTES) ?>">
                <?php endforeach; ?>
                <input name="compare_name" placeholder="<?= $isHi ? 'तुलना: पूरा नाम' : 'Compare: full name' ?>"
                       class="nr-share-input" value="<?= htmlspecialchars((string)($_GET['compare_name'] ?? ''), ENT_QUOTES) ?>">
                <input name="compare_dob" type="date" class="nr-share-input nr-share-date"
                       value="<?= htmlspecialchars((string)($_GET['compare_dob'] ?? ''), ENT_QUOTES) ?>">
                <button type="submit" class="nr-util-btn nr-util-on">
                    <i class="fa-solid fa-people-arrows" aria-hidden="true"></i>
                    <span><?= $isHi ? 'तुलना' : 'Compare' ?></span>
                </button>
            </form>
        </div>
    </div>

<script>
(function () {
    function patchModeLinks() {
        document.querySelectorAll('.mode-toggle-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var base = btn.dataset.href || btn.getAttribute('href') || '';
                if (!base) return;
                try {
                    var u = new URL(base, window.location.href);
                    // Preserve current hash / active experience
                    window.location.href = u.pathname + u.search + window.location.hash;
                } catch (err) {
                    window.location.href = base;
                }
            });
        });
    }
    function bindQr() {
        var t = document.getElementById('nr-qr-toggle');
        var p = document.getElementById('nr-qr-pop');
        if (!t || !p) return;
        t.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = p.hasAttribute('hidden');
            if (open) { p.removeAttribute('hidden'); t.setAttribute('aria-expanded', 'true'); }
            else { p.setAttribute('hidden', ''); t.setAttribute('aria-expanded', 'false'); }
        });
        document.addEventListener('click', function () {
            p.setAttribute('hidden', '');
            t.setAttribute('aria-expanded', 'false');
        });
        p.addEventListener('click', function (e) { e.stopPropagation(); });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { patchModeLinks(); bindQr(); });
    } else {
        patchModeLinks();
        bindQr();
    }
})();
</script>

<style>
/* ── Command bar layout ───────────────────────────────────────── */
.nr-cmd {
  position: relative;
  z-index: 5;
  background: linear-gradient(180deg, rgba(10, 14, 32, 0.98) 0%, rgba(16, 22, 48, 0.95) 100%);
  border-bottom: 1px solid rgba(212, 175, 55, 0.22);
}
.nr-cmd-top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem 1rem;
  padding: 0.85rem 1rem 0.65rem;
}
.nr-cmd-brand {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  min-width: 0;
}
.nr-cmd-kicker {
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #c9a227;
}
.nr-cmd-title {
  font-family: 'Cinzel', 'Cormorant Garamond', Georgia, serif;
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  color: #f5f0e6;
}
.nr-cmd-right {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.55rem;
}

.nr-cmd-nav {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  padding: 0.35rem 1rem 0.75rem;
  border-top: 1px solid rgba(148, 163, 184, 0.08);
}
.nr-cmd-nav-adv {
  padding-top: 0.15rem;
  padding-bottom: 0.85rem;
  border-top: none;
}
.nr-cmd-nav-label {
  flex-shrink: 0;
  margin-top: 0.45rem;
  width: 4.5rem;
  font-size: 0.58rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #94a3b8 !important;
}
.nr-cmd-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  flex: 1;
  min-width: 0;
}

/* Chapter / module pills */
.nr-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  height: 2.25rem;
  padding: 0 0.9rem;
  border-radius: 999px;
  border: 1px solid #64748b;
  background: rgba(15, 23, 42, 0.85);
  color: #f8fafc !important;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: border-color 0.18s, background 0.18s, color 0.18s, box-shadow 0.18s, transform 0.15s;
  white-space: nowrap;
}
.nr-pill span { color: inherit !important; }
.nr-pill i { font-size: 0.7rem; opacity: 1; color: inherit !important; }
.nr-pill:hover {
  border-color: rgba(212, 175, 55, 0.45);
  color: #fef9c3;
  transform: translateY(-1px);
}
.nr-pill-soft {
  border-color: rgba(148, 163, 184, 0.14);
  background: rgba(15, 23, 42, 0.35);
  color: #a8a29a;
}
.nr-pill-on {
  border-color: rgba(232, 197, 71, 0.65) !important;
  background: linear-gradient(135deg, rgba(201, 162, 39, 0.28), rgba(30, 58, 138, 0.45)) !important;
  color: #fffbeb !important;
  box-shadow: 0 0 20px rgba(201, 162, 39, 0.2);
  font-weight: 750;
}

/* System segmented control */
.nr-seg {
  display: inline-flex;
  padding: 3px;
  border-radius: 999px;
  background: rgba(8, 12, 28, 0.85);
  border: 1px solid rgba(212, 175, 55, 0.28);
  gap: 2px;
  flex-shrink: 0;
}
.nr-seg-btn {
  display: inline-flex !important;
  align-items: center !important;
  gap: 0.35rem !important;
  padding: 0.4rem 0.85rem !important;
  border-radius: 999px !important;
  font-size: 0.68rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.06em !important;
  text-transform: uppercase !important;
  color: #a8a29a !important;
  text-decoration: none !important;
  background: transparent !important;
  border: 0 !important;
  transition: background 0.18s, color 0.18s, box-shadow 0.18s !important;
  box-shadow: none !important;
  height: auto !important;
}
.nr-seg-glyph { opacity: 0.9; }
.nr-seg-btn:hover { color: #f5f0e6 !important; background: rgba(255,255,255,0.04) !important; }
.nr-seg-on {
  background: linear-gradient(135deg, #0f766e, #115e59) !important;
  color: #ecfdf5 !important;
  box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25) !important;
}
.nr-seg-on-gold {
  background: linear-gradient(135deg, #c9a227, #a16207) !important;
  color: #1c1917 !important;
  box-shadow: 0 4px 14px rgba(201, 162, 39, 0.35) !important;
}

/* Share tools */
.nr-utils {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  flex-shrink: 0;
}
.nr-util-btn {
  display: inline-flex !important;
  align-items: center !important;
  gap: 0.35rem !important;
  height: 2.05rem !important;
  padding: 0 0.7rem !important;
  border-radius: 999px !important;
  border: 1px solid rgba(212, 175, 55, 0.22) !important;
  background: rgba(15, 23, 42, 0.5) !important;
  color: #d6d3d1 !important;
  font-size: 0.68rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.04em !important;
  text-decoration: none !important;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s, color 0.15s, box-shadow 0.15s !important;
  box-shadow: none !important;
  text-transform: none !important;
}
.nr-util-btn:hover {
  border-color: rgba(232, 197, 71, 0.5) !important;
  color: #fef9c3 !important;
  box-shadow: 0 0 16px rgba(201, 162, 39, 0.15) !important;
}
.nr-util-on {
  border-color: rgba(96, 165, 250, 0.5) !important;
  color: #bfdbfe !important;
  background: rgba(37, 99, 235, 0.18) !important;
}
.nr-util-wa:hover { border-color: #34d399 !important; color: #6ee7b7 !important; }
.nr-util-label { display: none; }
@media (min-width: 640px) {
  .nr-util-label { display: inline; }
}

.nr-qr-wrap { position: relative; }
.nr-qr-pop {
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  z-index: 90;
  padding: 12px;
  border-radius: 14px;
  background: #0b1220;
  border: 1px solid rgba(212, 175, 55, 0.3);
  box-shadow: 0 20px 48px rgba(0,0,0,0.55);
}
.nr-qr-cap {
  margin-top: 8px;
  text-align: center;
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #78716c;
}

/* Share expand panel */
.nr-share-panel {
  border-bottom: 1px solid rgba(212, 175, 55, 0.15);
  background: rgba(8, 12, 28, 0.92);
}
.nr-share-inner { padding: 0.85rem 1rem 1rem; }
.nr-share-label {
  font-size: 0.6rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #a8a29a;
  margin-bottom: 0.4rem;
}
.nr-share-row, .nr-share-compare {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}
.nr-share-compare { margin-top: 0.65rem; }
.nr-share-input {
  flex: 1;
  min-width: 140px;
  height: 2.2rem;
  padding: 0 0.75rem;
  border-radius: 0.65rem;
  border: 1px solid rgba(71, 85, 105, 0.8);
  background: #0f172a;
  color: #e7e5e4;
  font-size: 0.75rem;
  font-family: ui-monospace, Menlo, monospace;
  outline: none;
}
.nr-share-input:focus { border-color: rgba(201, 162, 39, 0.5); }
.nr-share-date { max-width: 11rem; font-family: inherit; }

@media (max-width: 640px) {
  .nr-cmd-nav-label { display: none; } /* labels stay on pills */
  .nr-cmd-top { padding: 0.75rem 0.75rem 0.5rem; }
  .nr-cmd-nav { padding-left: 0.75rem; padding-right: 0.75rem; }
  .nr-pill { height: 1.95rem; padding: 0 0.7rem; font-size: 0.68rem; }
}
</style>
