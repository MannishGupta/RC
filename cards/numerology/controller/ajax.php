<?php
// ajax.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

// controller/ajax.php — AJAX endpoint handlers: vastu, name_analysis, print-audit

// ── AJAX: Vastu score (?ajax=vastu) ──────────────────────────────
if (!empty($_GET['ajax']) && $_GET['ajax']==='vastu') {
    header('Content-Type: application/json; charset=utf-8');
    $ajN=(string)mb_substr(trim(strip_tags($_GET['name']??'')),0,120); $ajD=max(1,min(9,(int)($_GET['driver']??1)));
    if (empty($ajN)||mb_strlen($ajN)<2||!class_exists('VastuNameEngine')) { echo json_encode(['error'=>'unavailable']); exit; }
    echo json_encode(VastuNameEngine::score($ajN,$ajD),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}

// ── AJAX: Name analysis (?ajax=name_analysis) ─────────────────────
if (!empty($_GET['ajax']) && $_GET['ajax']==='name_analysis') {
    header('Content-Type: application/json; charset=utf-8');
    // Basic same-origin guard: only accept requests from this host
    $_ajRef = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?? '';
    $_myHost = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($_ajRef) && $_ajRef !== $_myHost) { echo json_encode(['error'=>'forbidden']); exit; }
    $ajName   = mb_substr(trim(strip_tags((string)($_GET['name'] ?? ''))), 0, 120);
    $ajDriver = max(1, min(9, (int)($_GET['driver'] ?? 1)));
    if (empty($ajName)) { echo json_encode(['error' => 'name required']); exit; }
    try {
        // PERFORMANCE FIX: this endpoint only ever needs the name matrix —
        // it previously called AppNumeroEngine::generateReport() (which also
        // computes the grid, yogas, mobile engine, karmic debt, pinnacles,
        // and timeline, all of it discarded) just to read out three numbers.
        // analyzeName() + CompoundEngine::analyze() are the lightweight
        // building blocks the full report itself is built from — using them
        // directly here is functionally identical for this endpoint's
        // output but skips all of the unrelated computation.
        // NOTE: this no longer requires $ajDob at all, since name-matrix
        // math never depended on date of birth in the first place.
        if (class_exists('AppNumeroEngine')) {
            $nm = AppNumeroEngine::analyzeName($ajName);
            $compounds = AppNumeroEngine::getData()['compounds'] ?? [];
            $friends   = AppNumeroEngine::getData()['planets'][(string)$ajDriver]['friends'] ?? [];

            echo json_encode([
                'full'        => ['compound' => $nm['full']['compound'],
                                  'root'     => $nm['full']['root'],
                                  'verdict'  => CompoundEngine::analyze($nm['full']['compound'], $compounds)['verdict'] ?? 'Neutral',
                                  'name'     => CompoundEngine::analyze($nm['full']['compound'], $compounds)['name']    ?? ''],
                'soul_urge'   => ['compound' => $nm['soul_urge']['compound'],
                                  'root'     => $nm['soul_urge']['root']],
                'personality' => ['compound' => $nm['personality']['compound'],
                                  'root'     => $nm['personality']['root']],
                'harmony'     => ['status' => AppNumeroEngine::evaluateHarmony($nm['full']['root'], $ajDriver, $friends),
                                  'desc'   => 'Relational vibrational alignment vs planetary driver.'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['error' => 'engine unavailable']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: Print audit (?audit=print) ─────────────────────────────
if (!empty($_GET['audit']) && $_GET['audit']==='print') {
    writeAuditLog('print',['slug'=>preg_replace('/[^a-zA-Z0-9_-]/','',($_GET['slug']??'')),'tab'=>($_GET['tab']??'')]);
    header('Content-Type: application/json'); echo '{"ok":true}'; exit;
}
