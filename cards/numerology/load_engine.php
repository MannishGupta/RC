<?php // Version: 260916.14
declare(strict_types=1);
/**
 * num_engine.php — Arthsathi Vedic Numero Engine V23.1
 *
 * REFACTORED: Now a thin loader. All class definitions live in engine/ partials.
 * Load order is dependency-correct — AppNumeroEngine (core) is always first.
 *
 * engine/
 *   core.php             — AppNumeroEngine: init, reduce, analyzeName, generateReport
 *   grid.php             — GridEngine, YogaEngine, ArrowEngine, LoShuCellMeta
 *   compat.php           — CompatibilityRemediesEngine
 *   compound.php         — CompoundEngine, DiagnosticsEngine, InterpretationEngine
 *   mobile_engine.php    — MobileEngine (Vedic pattern scoring)
 *   scoring.php          — ScoringEngine, FinalDecisionEngine
 *   derived.php          — PersonalYearEngine, PinnacleEngine, PersonalCycleEngine,
 *                          MaturityEngine, TimelineEngine
 *   extended.php         — MuhuratEngine, VastuNameEngine, CompositeEngine
 *   chaldean_mobile.php  — ChaldeaMobileEngine (strict Chaldean validation & batch scoring)
 *
 * All existing callers (card_numero.php, api_numero.php, etc.) require this
 * file unchanged — zero breaking changes.
 */
if (!defined('BASE_PATH')) exit;

$_ENGINE = str_replace('\\', '/', __DIR__) . '/engine';

// ── 1. Core (must be first — all other engine files call AppNumeroEngine) ─
require_once $_ENGINE . '/core.php';

// ── 2. Grid, Yoga, Arrow, LoShu ───────────────────────────────────────────
require_once $_ENGINE . '/grid.php';

// ── 3. Compatibility remedies ─────────────────────────────────────────────
require_once $_ENGINE . '/compat.php';

// ── 4. Compound analysis, diagnostics, interpretation ─────────────────────
require_once $_ENGINE . '/compound.php';

// ── 5. Mobile (Vedic pattern scoring) ────────────────────────────────────
require_once $_ENGINE . '/mobile_engine.php';

// ── 6. Alignment scoring & final harmoniser ───────────────────────────────
require_once $_ENGINE . '/scoring.php';

// ── 7. Derived systems (PY, Pinnacle, Cycle, Maturity, Timeline) ──────────
require_once $_ENGINE . '/derived.php';

// ── 8. Extended systems (Muhurat, Vastu, Composite) ──────────────────────
require_once $_ENGINE . '/extended.php';

// ── 9. Chaldean Mobile validation & batch scoring ────────────────────────
require_once $_ENGINE . '/chaldean_mobile.php';

// ── 10. Dasha calculator (pure LP/pinnacles/PY helpers) ────────────────
require_once $_ENGINE . '/dasha.php';
