<?php // Version: 260916.14
/**
 * num_template.php — Arthsathi Vedic Numero Template V17.5
 *
 * REFACTORED: Now a thin orchestrator. All rendering is delegated to tmpl/ partials.
 * Zero logic. Zero data loading. All variables provided by num_controller.php.
 *
 * Partial map (tmpl/):
 *   head.php              — DOCTYPE, <head>, meta, CDN scripts, CSS
 *   actionbar.php         — Tab navigation bar (basic + advanced rows)
 *   profile_header.php    — Profile card (name, photo, core number pills)
 *   tab_executive.php     — Executive Summary
 *   tab_timeline.php      — Timeline & Life Phases
 *   tab_forecast.php      — Forecast (personal year 12-month grid)
 *   tab_structural.php    — Structural Matrix (Lo Shu grid + growth reservoirs)
 *   tab_corrections.php   — Compound Diagnostics & Name Corrections (tier-gated)
 *   tab_mobile_matrix.php — Mobile Frequency Matrix (active analysis + bulk scorer)
 *   tab_personality.php   — Personality & Blueprint
 *   tab_remedies.php      — Remedies & Gem Prescription
 *   tab_compatibility.php — Compatibility Analysis
 *   tab_muhurat.php       — Auspicious Muhurat
 *   tab_vastu.php         — Vastu Name Analysis
 *   footer_ui.php         — Footer info card, calc widget, disclaimer modal, FAB cluster
 *
 * BUG FIXES (V17.5):
 *   - FIX 1: Mobile Number Matrix removed from corrections tab and promoted to its
 *             own dedicated "mobile_matrix" tab (see tab_mobile_matrix.php).
 *             Add "mobile_matrix" to numero_meanings.json → nav_tabs to show button.
 *   - FIX 2: Duplicate evaluateMobile() / chaldeanReduce() JS functions consolidated
 *             into a single definition inside tab_mobile_matrix.php.
 *   - FIX 3: mobileMatrixTab (bulk upload scorer) is now co-located with
 *             mobileCompare (single-number analyser) in the same tab file.
 */
if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit;

// Windows-safe path helper: PHP accepts forward slashes on all platforms.
// Using DIRECTORY_SEPARATOR here would produce backslashes on Windows which,
// when mixed with the forward-slash suffixes below, causes "failed to open stream".
$_TMPL = str_replace('\\', '/', __DIR__) . '/views';

// ── Head ─────────────────────────────────────────────────────────────────────
require $_TMPL . '/head.php';

// ── Body: action bar ──────────────────────────────────────────────────────────
require $_TMPL . '/actionbar.php';

// ── Body: profile card ────────────────────────────────────────────────────────
require $_TMPL . '/profile_header.php';
?>

    <div class="p-4 md:p-6 space-y-6">

<?php
// ── Tabs (basic) ─────────────────────────────────────────────────────────────
require $_TMPL . '/tab_executive.php';
require $_TMPL . '/tab_timeline.php';
require $_TMPL . '/tab_forecast.php';
require $_TMPL . '/tab_structural.php';

// ── Tabs (advanced) ──────────────────────────────────────────────────────────
require $_TMPL . '/tab_corrections.php';     // tier-gated; no mobile section
require $_TMPL . '/tab_mobile_matrix.php';   // mobile analysis + bulk scorer
require $_TMPL . '/tab_personality.php';
require $_TMPL . '/tab_remedies.php';
require $_TMPL . '/tab_compatibility.php';
require $_TMPL . '/tab_muhurat.php';
require $_TMPL . '/tab_vastu.php';
?>

    </div><!-- /p-4 md:p-6 space-y-6 -->

<?php
// ── Footer, modals, FAB ───────────────────────────────────────────────────────
require $_TMPL . '/footer_ui.php';
?>
