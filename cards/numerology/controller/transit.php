<?php
// transit.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

// controller/transit.php — Transit alerts & compatibility report

// ── Transit alerts ─────────────────────────────────────────────────
$transitAlerts = [];
$py = (int)$report['personal_year']['number'];

if ($py === 8) {
    $transitAlerts[] = [
        'level' => 'high',
        'icon'  => 'fa-bolt',
        'title' => $lang === 'hi' ? 'शक्ति वर्ष 8 सक्रिय' : 'Power Year 8 Active',
        'desc'  => $lang === 'hi' ? 'यह वर्ष भौतिक शक्ति और वित्तीय निर्णयों के लिए महत्वपूर्ण है।' : 'This is your most powerful year for material decisions and financial moves.',
    ];
}
if ($py === 9) {
    $transitAlerts[] = [
        'level' => 'medium',
        'icon'  => 'fa-circle-half-stroke',
        'title' => $lang === 'hi' ? 'समापन वर्ष 9' : 'Completion Year 9',
        'desc'  => $lang === 'hi' ? 'पुरानी चीजें छोड़ें, नए चक्र की तैयारी करें।' : 'Release what no longer serves you. A new 9-year cycle begins next year.',
    ];
}
if ($py === 1) {
    $transitAlerts[] = [
        'level' => 'medium',
        'icon'  => 'fa-seedling',
        'title' => $lang === 'hi' ? 'नया चक्र शुरू' : 'New Cycle Beginning',
        'desc'  => $lang === 'hi' ? '9 वर्षीय नया चक्र शुरू हो रहा है। साहसी निर्णय लें।' : 'You have entered a fresh 9-year cycle. Decisions made now shape the next decade.',
    ];
}

foreach ($report['pinnacles']['pinnacles'] as $alertPin) {
    $alertAges = explode('-', str_replace(['–', '—'], '-', $alertPin['age_range'] ?? ''));
    if (count($alertAges) === 2) {
        $alertDobTs  = strtotime(str_replace('/', '-', (string)($pData['dob'] ?? '')));
        // Correct age: subtract 1 if birthday hasn't occurred yet this calendar year
        $alertCurAge = 0;
        if ($alertDobTs) {
            $alertCurAge = (int)date('Y') - (int)date('Y', $alertDobTs);
            if (date('md') < date('md', $alertDobTs)) $alertCurAge--;
        }
        if ($alertCurAge > 0 && abs($alertCurAge - (int)trim($alertAges[0])) <= 1) {
            $alertTheme = htmlspecialchars($alertPin['data']['theme'] ?? '');
            $transitAlerts[] = [
                'level' => 'high',
                'icon'  => 'fa-mountain',
                'title' => $lang === 'hi' ? 'शिखर बदलाव' : 'Pinnacle Transition',
                'desc'  => $lang === 'hi'
                    ? ('आप शिखर ' . (int)$alertPin['phase'] . ' में प्रवेश कर रहे हैं। ' . $alertTheme . ' का समय है।')
                    : ('You are entering Pinnacle ' . (int)$alertPin['phase'] . '. Theme: ' . $alertTheme . '.'),
            ];
        }
    }
}

// ── Compatibility ──────────────────────────────────────────────────
$compareReport      = null;
$compatibilityData  = null;
$compatAxisRemedies = [];
$compatPairInsight  = [];
$compatTiming       = [];

if (!empty($_GET['compare_name']) && !empty($_GET['compare_dob'])) {
    $cName    = trim(strip_tags((string)$_GET['compare_name']));
    $cDob     = trim(strip_tags((string)$_GET['compare_dob']));
    $cDobNorm = str_replace('/', '-', $cDob);
    
    if (!strtotime($cDobNorm) && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $cDobNorm, $m2)) {
        $cDobNorm = $m2[3] . '-' . $m2[2] . '-' . $m2[1];
    }
    
    if (strtotime($cDobNorm)) {
        $cDob = $cDobNorm;
        try {
            $compareReport = AppNumeroEngine::generateReport([
                'name'   => $cName,
                'dob'    => $cDob,
                'phone'  => '',
                'gender' => preg_replace('/[^a-zA-Z]/', '', (string)($_GET['compare_gender'] ?? 'Unknown'))
            ], 'elite');
            
            $compatibilityData = AppNumeroEngine::buildCompatibilityMetrics($report, $compareReport);
            
            if (class_exists('CompatibilityRemediesEngine')) {
                $compatAxisRemedies = CompatibilityRemediesEngine::getAxisRemedies($compatibilityData);
                $compatPairInsight  = CompatibilityRemediesEngine::getDriverPairInsight((int)$report['core']['driver'], (int)$compareReport['core']['driver']);
                $compatTiming       = CompatibilityRemediesEngine::getRelationshipTimingAdvice($report, $compareReport);
            } else {
                $compatAxisRemedies = [];
                $compatPairInsight  = ['verdict' => 'Neutral', 'description' => 'Compatibility analysis available in full engine.', 'joint_remedy' => ''];
                $compatTiming       = ['joint_py' => 0, 'py_a' => 0, 'py_b' => 0, 'advice' => ''];
            }
        } catch (Exception $e) {
            $compatPairInsight = ['verdict' => 'Error', 'description' => htmlspecialchars($e->getMessage()), 'joint_remedy' => ''];
            $compatTiming      = ['joint_py' => 0, 'py_a' => 0, 'py_b' => 0, 'advice' => ''];
        }
    }  // end if (strtotime($cDobNorm))
}  // end if (!empty($_GET['compare_name']) && !empty($_GET['compare_dob']))
