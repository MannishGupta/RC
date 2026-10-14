<?php

declare(strict_types=1);

require_once __DIR__ . '/lib.php';
AppLocale::boot();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   isHttps() ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name(cfg('site.cookie_prefix', 'fusion') . '_public_session');
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

 
function done(string $status, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['status' => $status], JSON_UNESCAPED_SLASHES);
    exit;
}

if (!cfg('analytics.enabled', true)) {
    done('disabled');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    done('method', 405);
}

if (!analyticsConsentGiven()) {
    done('no-consent', 403);
}

 
$raw = file_get_contents('php://input', false, null, 0, 8192);
if ($raw === false || $raw === '') {
    done('empty', 400);
}

$in = json_decode($raw, true);
if (!is_array($in)) {
    done('bad-json', 400);
}

$expected  = (string)($_SESSION['analytics_token'] ?? '');
$submitted = (string)($in['token'] ?? '');
if ($expected === '' || !hash_equals($expected, $submitted)) {
    done('unauthorised', 403);
}

$allowedActions = [
    'page_load', 'time_spent', 'button_click',
    'directory_filter', 'image_viewed', 'video_play',
];
$action = (string)($in['action'] ?? '');
if (!in_array($action, $allowedActions, true)) {
    done('unknown-action', 400);
}

$limit  = max(1, (int)cfg('analytics.rate_per_min', 60));
$minute = (int)floor(time() / 60);
if (($_SESSION['_an_window'] ?? -1) !== $minute) {
    $_SESSION['_an_window'] = $minute;
    $_SESSION['_an_count']  = 0;
}
$_SESSION['_an_count'] = (int)($_SESSION['_an_count'] ?? 0) + 1;
if ($_SESSION['_an_count'] > $limit) {
    done('rate-limited', 429);
}

$saltFile = DATA_DIR . DIRECTORY_SEPARATOR . 'ip_salt.txt';
$salt     = is_file($saltFile) ? trim((string)file_get_contents($saltFile)) : '';
if ($salt === '') {
    $salt = bin2hex(random_bytes(32));
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    @file_put_contents($saltFile, $salt, LOCK_EX);
}

 
function uaShort(string $ua): string {
    $ua = preg_replace('/[^A-Za-z0-9\/\.\; \(\)_-]+/', '', $ua) ?? '';
    return mb_substr($ua, 0, 120);
}

 
function cleanDetails(mixed $details): array {
    if (!is_array($details)) {
        return [];
    }
    $allowed = ['seconds', 'title', 'comp_id', 'button_name', 'src', 'filename', 'referrer', 'screen'];
    $out = [];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $details)) {
            continue;
        }
        $v = $details[$k];
        if (is_array($v) || is_object($v)) {
            continue;
        }
        if ($k === 'seconds') {
            $out[$k] = max(0, min(86400, (int)$v));    
            continue;
        }
        $out[$k] = mb_substr(strip_tags((string)$v), 0, 300);
    }
    return $out;
}

$entry = [
    'timestamp'  => date('c'),
    'ip_hash'    => hash_hmac('sha256', getRealIp(), $salt),
    'visitor_id' => mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($in['visitor_id'] ?? '')) ?? '', 0, 40),
    'session_id' => mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($in['session_id'] ?? '')) ?? '', 0, 40),
    'action'     => $action,
    'url'        => mb_substr(strip_tags((string)($in['url'] ?? '')), 0, 300),
    'ua_short'   => uaShort((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
    'details'    => cleanDetails($in['details'] ?? []),
];

$cap  = max(100, (int)cfg('analytics.cap', 10000));
$logs = safeReadJson(ANALYTICS_FILE);
$logs[] = $entry;
if (count($logs) > $cap) {
    $logs = array_slice($logs, -$cap);
}

try {
    atomicWriteJson(ANALYTICS_FILE, $logs);
} catch (Throwable $e) {
    error_log('analytics.php: ' . $e->getMessage());
    done('store-failed', 500);
}

done('ok');
