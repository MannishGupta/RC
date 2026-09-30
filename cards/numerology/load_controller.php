<?php // Version: 260916.14
declare(strict_types=1);
/**
 * num_controller.php — Arthsathi Vedic Numero Controller V17.1
 *
 * REFACTORED: Now a thin loader. All logic lives in controller/ partials.
 * Load order matters: audit first (no engine dep), then AJAX & wacard
 * (exit-early handlers), then profile → transit → meta → mobile_vars.
 *
 * controller/
 *   audit.php        — writeAuditLog() helper
 *   ajax.php         — AJAX: ?ajax=vastu, name_analysis, ?audit=print
 *   wacard.php       — ?wacard= WhatsApp PNG image card (exits early)
 *   profile.php      — Language, profile resolution, report generation
 *   transit.php      — Transit alerts & compatibility report
 *   meta.php         — Share URL, QR, photo, OG meta, nav tabs
 *   mobile_vars.php  — All $_mc* Chaldean mobile template vars + regen
 *
 * Zero breaking changes — all template variables remain identical.
 */
if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
}

$_CTRL = str_replace('\\', '/', __DIR__) . '/controller';

// ── 1. Audit helper (no engine dependency, must be first) ─────────────────
require $_CTRL . '/audit.php';

// ── 2. AJAX handlers (exit early if matched) ─────────────────────────────
require $_CTRL . '/ajax.php';

// ── 3. WhatsApp image card (exit early if ?wacard= present) ───────────────
require $_CTRL . '/wacard.php';

// ── 4. Profile resolution + report generation → sets $report, $p, $core ──
require $_CTRL . '/profile.php';

// ── 5. Transit alerts + compatibility report ──────────────────────────────
require $_CTRL . '/transit.php';

// ── 6. URLs, QR, photo, OG, nav tabs → sets $shareUrl, $tabs, etc. ───────
require $_CTRL . '/meta.php';

// ── 7. All $_mc* mobile template vars + Chaldean regen ───────────────────
require $_CTRL . '/mobile_vars.php';
