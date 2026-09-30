<?php
// profile.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

// controller/profile.php — Language, profile resolution, report generation

// ── Language & translations ────────────────────────────────────────
// Default Hindi (hi_IN); English-India via ?lang=en or cookie rc_lang/nr_lang
$_langQ = strtolower(trim((string)($_GET['lang'] ?? '')));
if (in_array($_langQ, ['en', 'en_in', 'english'], true)) {
    $lang = 'en';
} elseif (in_array($_langQ, ['hi', 'hi_in', 'hindi'], true)) {
    $lang = 'hi';
} else {
    $_langC = strtolower(trim((string)($_COOKIE['rc_lang'] ?? $_COOKIE['nr_lang'] ?? '')));
    $lang = ($_langC === 'en') ? 'en' : 'hi';
}
if (!headers_sent()) {
    @setcookie('rc_lang', $lang, [
        'expires' => time() + 86400 * 400,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}
unset($_langQ, $_langC);
$_numData = AppNumeroEngine::getData();
$T        = ($lang === 'hi') ? ($_numData['translations']['hi'] ?? []) : [];


// ── Share-link decoding (?share= base64url payload) ─────────────────────────
// Decode BEFORE profile resolution — the block below uses $shareDecoded.
$shareDecoded = null;
if (!empty($_GET['share'])) {
    $_shareRaw = strtr(trim((string)$_GET['share']), ['-'=>'+','_'=>'/']);
    $_shareRaw = base64_decode($_shareRaw . str_repeat('=', (4 - strlen($_shareRaw) % 4) % 4), true);
    if ($_shareRaw !== false) {
        $_shareArr = json_decode($_shareRaw, true);
        if (is_array($_shareArr) && !empty($_shareArr['n']) && !empty($_shareArr['d'])) {
            $shareDecoded = $_shareArr;
        }
    }
}

// ── Profile resolution ─────────────────────────────────────────────
$slug  = isset($_GET['slug']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['slug']) : '';
$pData = null;

if (!empty($shareDecoded) && !empty($shareDecoded['n']) && !empty($shareDecoded['d'])) {
    $pData = [
        'name'   => trim($shareDecoded['n']),
        'dob'    => trim($shareDecoded['d']),
        'phone'  => $shareDecoded['m'] ?? '',
        'gender' => $shareDecoded['g'] ?? 'Unknown'
    ];
} elseif (!empty($_GET['name']) && !empty($_GET['dob'])) {
    $pData = [
        'name'   => trim(strip_tags((string)$_GET['name'])),
        'dob'    => trim(strip_tags((string)$_GET['dob'])),
        'phone'  => isset($_GET['mobile']) ? preg_replace('/\D/', '', $_GET['mobile']) : '',
        'gender' => isset($_GET['gender']) ? preg_replace('/[^a-zA-Z]/', '', $_GET['gender']) : 'Unknown',
        // Photo: accept filename only (no remote URLs) — prevents SSRF
        'photo'  => (preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|webp|avif)$/i', basename($_GET['photo'] ?? '')) && strpos($_GET['photo'] ?? '', '//') === false) ? basename($_GET['photo']) : ''
    ];
} elseif (!empty($slug)) {
    if (class_exists('AppDB')) {
        if (method_exists('AppDB', 'readOne')) {
            $pData = AppDB::readOne('team', ['slug' => $slug]);
        } else {
            $team = AppDB::read('team') ?? [];
            foreach ($team as $t) {
                if (strcasecmp((string)($t['slug'] ?? ''), $slug) === 0) {
                    $pData = $t;
                    break;
                }
            }
        }
    }
}

if (!$pData) {
    http_response_code(400); exit("<h3 style='font-family:sans-serif'>Profile not found. Please check the link and try again.</h3>");
}

if (!strtotime(str_replace('/', '-', (string)($pData['dob'] ?? '')))) {
    http_response_code(400); exit('<h3 style="font-family:sans-serif">Invalid date of birth format. Please use YYYY-MM-DD.</h3>');
}

// ── Generate report ────────────────────────────────────────────────
// ✅ V16.3: Pass $lang into $pData so AppNumeroEngine::generateReport() calls
//           setLang() internally — eliminates all $_GET['lang'] reads in engine.
$pData['lang'] = $lang;
$tier   = in_array(($pData['tier'] ?? 'elite'), ['free', 'pro', 'elite'], true) ? ($pData['tier'] ?? 'elite') : 'elite';
$report = AppNumeroEngine::generateReport($pData, $tier);
$p      = $report['profile'];
$core   = $report['core'];

// ── Hindi content (JSON-driven) ───────────────────────────────────
$HI_PY   = [];
$_pyJson = $_numData['personal_years'] ?? [];
foreach ($_pyJson as $_pyN => $_pyD) {
    if (!empty($_pyD['hi_title'])) {
        $HI_PY[$_pyN] = [
            'title'         => $_pyD['hi_title'],
            'theme'         => $_pyD['hi_theme'] ?? '',
            'action_timing' => $_pyD['hi_action_timing'] ?? '',
            'avoid'         => $_pyD['hi_avoid'] ?? '',
        ];
    }
}

$HI_PIN = $_numData['hi_pinnacles'] ?? [];
$HI_CHA = $_numData['hi_challenges'] ?? [];

