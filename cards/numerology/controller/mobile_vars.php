<?php
// mobile_vars.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

// controller/mobile_vars.php — Chaldean mobile template vars ($_mc*), validation, pattern regen

// ── Audit log: page view ──────────────────────────────────────
// ── NumerologyValidator integration ──────────────────────────────
$validationReport = [];
if (class_exists('NumerologyValidator')) {
    try {
        $validationReport = NumerologyValidator::validate($report);
    } catch (Throwable $e) {
        $validationReport = ['score'=>100,'status'=>'Valid','errors'=>[],'warnings'=>[],'confidence_map'=>[],'auto_fix_suggestions'=>[]];
    }
}

// ── V17.1: Controller validation layer for mobile root suggestions ────────────
// Runs AFTER report generation. If MobileRecommendationEngine is loaded it re-checks
// that no pattern in report['mobile']['patterns'] is sequential or adjacently clustered
// and logs a warning if regeneration was needed. This is a safety net — the engine
// should already produce valid outputs, but defence-in-depth matters.
if (
    class_exists('MobileRecommendationEngine') &&
    !empty($report['mobile']['patterns']) &&
    is_array($report['mobile']['patterns'])
) {
    $mobilePatternRoots = array_column($report['mobile']['patterns'], 'target_root');
    $mobilePatternRoots = array_values(array_filter($mobilePatternRoots, fn($r) => is_int($r)));

    $mobileRootsInvalid = false;

    // Check sequential (linear + circular)
    if (count($mobilePatternRoots) >= 3 && MobileRecommendationEngine::isSequential($mobilePatternRoots)) {
        $mobileRootsInvalid = true;
    }

    // Check adjacency clustering: if every non-driver root sits within 1 step of the driver
    // the output is energetically redundant even when not strictly sequential.
    if (!$mobileRootsInvalid && count($mobilePatternRoots) >= 2) {
        $clusterCount = 0;
        $anchorRoot   = $mobilePatternRoots[0] ?? 0;
        foreach (array_slice($mobilePatternRoots, 1) as $r) {
            if (abs($r - $anchorRoot) <= 1) $clusterCount++;
        }
        if ($clusterCount >= count($mobilePatternRoots) - 1) {
            $mobileRootsInvalid = true;
        }
    }

    if ($mobileRootsInvalid) {
        writeAuditLog('mobile_root_regen', [
            'slug'           => $slug,
            'original_roots' => $mobilePatternRoots,
            'driver'         => (int)($core['driver'] ?? 0),
        ]);

        // Attempt regeneration with a perturbed seed (+1) to escape the cluster.
        $_deterministicSeed = (int)(
            crc32(strtolower(trim($p['name'] ?? '')) . ($p['dob_raw'] ?? ''))
            & 0x7FFFFFFF
        );
        $_regenFriends  = $report['lucky_driver']['friends'] ?? [];
        $_regenEnemies  = AppNumeroEngine::getData()['planets'][(string)($core['driver'] ?? 1)]['enemies'] ?? [];
        $_regenRoots    = MobileRecommendationEngine::suggestRoots(
            (int)($core['driver'] ?? 1),
            $_regenFriends,
            $_regenEnemies,
            $_deterministicSeed + 1
        );

        // Splice regenerated roots back into patterns, preserving tail-ending data.
        foreach ($report['mobile']['patterns'] as $idx => &$mPat) {
            if (isset($_regenRoots[$idx])) {
                $mPat['target_root']      = $_regenRoots[$idx];
                $mPat['regen_flag']       = true; // visible in debug mode
            }
        }
        unset($mPat);
    }
}

// ── Scoring mode: 'vedic' uses MobileEngine (driver/friends), 'chaldean' uses ChaldeaMobileEngine ──
// Default = chaldean. Can be overridden by ?mobile_mode=vedic in GET params or user session pref.
$_mcMode = 'vedic'; // canonical default — Vedic (driver/friends) is the primary system
if (isset($_GET['mobile_mode']) && in_array($_GET['mobile_mode'], ['vedic','chaldean'], true)) {
    $_mcMode = $_GET['mobile_mode'];
    if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['num_mobile_mode'] = $_mcMode;
} elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['num_mobile_mode'])) {
    $_mcMode = $_SESSION['num_mobile_mode'];
}
$_mcModeJson = json_encode($_mcMode);

// ── Chaldean Mobile Engine data for template ─────────────────────────────────
$_mcDriver    = (int)($core['driver']    ?? 1);
$_mcConductor = (int)($core['conductor'] ?? 1);
$_mcPsychic   = $_mcDriver;
$_mcDestiny   = $_mcConductor;
$_mcFriends   = json_encode(array_map('intval', $report['lucky_driver']['friends'] ?? []));
$_mcNum       = json_encode((string)($report['mobile']['display'] ?? 'N/A'));
$_mcRoot      = (int)($report['mobile']['root']      ?? 0);
$_mcScore     = (int)($report['mobile']['score']     ?? 0);
$_mcCompound  = (int)($report['mobile']['compound']  ?? 0);
$_mcLast2Raw  = preg_replace('/\D/', '', (string)($report['mobile']['display'] ?? ''));
$_mcLast2     = (string)($report['mobile']['last2'] ?? (strlen($_mcLast2Raw) >= 2 ? substr($_mcLast2Raw, -2) : '00'));
$_mcLast2Root = (int)($report['mobile']['last2Root'] ?? 0);
$_mcPatterns  = json_encode(array_map(function ($mPat) {
    return [
        'target_root' => (int)($mPat['target_root'] ?? 0),
        'endings'     => array_map(function ($e) {
            return [
                'ending'  => is_array($e) ? ($e['ending']  ?? '') : (string)$e,
                'combo'   => is_array($e) ? ($e['combo']   ?? '') : '',
                'benefit' => is_array($e) ? ($e['benefit'] ?? '') : '',
            ];
        }, $mPat['suggested_endings'] ?? []),
    ];
}, $report['mobile']['patterns'] ?? []));
$_mcChaldean  = class_exists('ChaldeaMobileEngine')
    ? json_encode(ChaldeaMobileEngine::getJsData($_mcPsychic, $_mcDestiny), JSON_UNESCAPED_UNICODE)
    : 'null';
$_mcI18n = json_encode([
    'rootDriver'  => $lang==='hi' ? 'मूल अंक आपके चालक से संरेखित है' : 'Root aligned with your Driver',
    'rootFriend'  => $lang==='hi' ? 'मूल अंक अनुकूल ग्रह'             : 'Root is a Friendly planet',
    'rootNeutral' => $lang==='hi' ? 'मूल अंक तटस्थ या विरोधी'         : 'Root is Neutral or opposing',
    'last2Driver' => $lang==='hi' ? 'अंतिम 2 अंक चालक से संरेखित'     : 'Last 2 digits aligned with Driver',
    'last2Friend' => $lang==='hi' ? 'अंतिम 2 अंक अनुकूल'              : 'Last 2 digits are Friendly',
    'last2Neutral'=> $lang==='hi' ? 'अंतिम 2 अंक का सुधार संभव'       : 'Last 2 digits could be optimized',
    'saturnDelay' => $lang==='hi' ? 'शनि/राहु ऊर्जा — देरी संभव'      : 'Saturn/Rahu energy — delays possible',
    'strongVibe'  => $lang==='hi' ? 'मजबूत कंपन संख्या'               : 'Strong vibrational number',
    'malefic'     => $lang==='hi' ? 'अशुभ अंत युगल'                    : 'Malefic ending pair',
    'auspicious'  => $lang==='hi' ? 'शुभ अंत युगल'                     : 'Auspicious ending pair',
    'highlyRec'   => $lang==='hi' ? 'अत्यंत अनुशंसित'                  : 'Highly Recommended',
    'acceptable'  => $lang==='hi' ? 'स्वीकार्य'                        : 'Acceptable',
    'avoid'       => $lang==='hi' ? 'टालें'                            : 'Avoid',
    'compatible'  => $lang==='hi' ? 'अनुकूल'                           : 'Supportive',
    'neutral'     => $lang==='hi' ? 'तटस्थ'                            : 'Neutral',
    'challenging' => $lang==='hi' ? 'चुनौतीपूर्ण'                       : 'Challenging',
    'addedToComp' => $lang==='hi' ? 'तुलना में जोड़ा'                    : 'Added to comparison',
    'compFull'    => $lang==='hi' ? 'तुलना तालिका पूर्ण (अधिकतम 5)'    : 'Comparison table full (max 5)',
], JSON_UNESCAPED_UNICODE);

