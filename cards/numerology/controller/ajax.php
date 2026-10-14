<?php
 
declare(strict_types=1);
if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

 
if (!empty($_GET['ajax']) && $_GET['ajax'] === 'dasha') {
    header('Content-Type: application/json; charset=utf-8');
    if (!function_exists('numero_dasha_report')) {
        $_dasha = dirname(__DIR__) . '/engine/dasha.php';
        if (is_file($_dasha)) {
            require_once $_dasha;
        }
    }
    if (!function_exists('numero_dasha_report')) {
        echo json_encode(['ok' => false, 'error' => 'dasha engine unavailable']);
        exit;
    }
    $name = mb_substr(trim(strip_tags((string)($_GET['name'] ?? $_POST['name'] ?? ''))), 0, 120);
    $dob  = trim(strip_tags((string)($_GET['dob'] ?? $_POST['dob'] ?? '')));
    $sys  = strtolower(trim((string)($_GET['system'] ?? $_POST['system'] ?? 'pythagorean')));
    $tgt  = trim(strip_tags((string)($_GET['target'] ?? $_POST['target'] ?? 'now')));
    if ($name === '' || $dob === '') {
        echo json_encode(['ok' => false, 'error' => 'name and dob required']);
        exit;
    }
    $report = numero_dasha_report($name, $dob, [
        'system' => ($sys === 'chaldean') ? 'chaldean' : 'pythagorean',
        'target' => $tgt !== '' ? $tgt : 'now',
    ]);
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

 

 
if (!empty($_GET['ajax']) && $_GET['ajax']==='vastu') {
    header('Content-Type: application/json; charset=utf-8');
    $ajN=(string)mb_substr(trim(strip_tags($_GET['name']??'')),0,120); $ajD=max(1,min(9,(int)($_GET['driver']??1)));
    if (empty($ajN)||mb_strlen($ajN)<2||!class_exists('VastuNameEngine')) { echo json_encode(['error'=>'unavailable']); exit; }
    echo json_encode(VastuNameEngine::score($ajN,$ajD),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}

 
if (!empty($_GET['ajax']) && $_GET['ajax']==='name_analysis') {
    header('Content-Type: application/json; charset=utf-8');
     
    $_ajRef = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?? '';
    $_myHost = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($_ajRef) && $_ajRef !== $_myHost) { echo json_encode(['error'=>'forbidden']); exit; }
    $ajName   = mb_substr(trim(strip_tags((string)($_GET['name'] ?? ''))), 0, 120);
    $ajDriver = max(1, min(9, (int)($_GET['driver'] ?? 1)));
    if (empty($ajName)) { echo json_encode(['error' => 'name required']); exit; }
    try {
         
         
         
         
         
         
         
         
         
         
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

 
if (!empty($_GET['audit']) && $_GET['audit']==='print') {
    writeAuditLog('print',['slug'=>preg_replace('/[^a-zA-Z0-9_-]/','',($_GET['slug']??'')),'tab'=>($_GET['tab']??'')]);
    header('Content-Type: application/json'); echo '{"ok":true}'; exit;
}
