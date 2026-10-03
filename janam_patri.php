<?php
/**
 * Version: 20261002.19 — public share: no import / directory
 * Gochar transits · Ashtakavarga strength · interpretive blurbs · prior tabs
 * A4 portrait print layout · 15mm margins · dedicated print action
 * Janam Patri & Kundli Milan — North Indian · Delhi Baniya gotra-aware
 * Default locale: Hindi (hi_IN) · instant English (en_IN) toggle
 * View charts from query params without mandatory save
 */
declare(strict_types=1);

function jp_theme_css(): void {
    global $_jpTheme, $_jpEmbed;
    $t = $_jpTheme ?? 'light';
    echo '<style id="jp-embed-theme">';
    if ($t === 'light') {
        echo 'html,body{background:#f8fafc!important;color:#0f172a!important}';
        echo '.card,.jp-now-card{background:#fff!important;color:#0f172a!important;border-color:#e2e8f0!important}';
        echo 'h1,h2,h3,.meta,p,td,th,li,label{color:#0f172a!important}';
        echo 'a{color:#2563eb!important}';
    } elseif ($t === 'dark') {
        echo 'html,body{background:#0f172a!important;color:#e2e8f0!important}';
    }
    if (!empty($_jpEmbed)) {
        echo 'body{max-width:100%!important;margin:0!important;padding:0.75rem!important}';
        echo '.chrome .chrome-btn[href="/?tab=team"]{display:none}';
    }
    echo '</style>';
}

$_jpEmbed = isset($_GET['embed']) && (string)$_GET['embed'] === '1';
$_jpTheme = preg_replace('/[^a-z]/', '', strtolower((string)($_GET['theme'] ?? 'light'))) ?: 'light';
if (!in_array($_jpTheme, ['light','dark','reserve'], true)) { $_jpTheme = 'light'; }




$base = __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!class_exists('SeoShare') && is_file(BASE_PATH . '/app/SeoShare.php')) {
    require_once BASE_PATH . '/app/SeoShare.php';
}
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
define('JP_STORAGE', defined('JANAM_DATA_PATH') ? JANAM_DATA_PATH : (DATA_PATH . '/janam'));

if (!is_dir(JP_STORAGE)) {
    @mkdir(JP_STORAGE, 0775, true);
}

// Session for admin vs public (shared patri links must not expose import / all profiles)
if (is_file(BASE_PATH . '/app/bootstrap.php')) {
    require_once BASE_PATH . '/app/bootstrap.php';
}
if (class_exists('AppAuth')) {
    AppAuth::initSession();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/** Company Admin / Super Admin may manage profiles, import, and delete. */
function jp_can_manage(): bool {
    $u = (string)($_SESSION['user'] ?? '');
    $true = (string)($_SESSION['true_role'] ?? '');
    if (in_array($u, ['admin', 'super_admin'], true)) {
        return true;
    }
    if (in_array($true, ['admin', 'super_admin'], true)) {
        return true;
    }
    return false;
}

function jp_require_manage(string $context = 'manage Janam profiles'): void {
    if (jp_can_manage()) {
        return;
    }
    $wantsJson = (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || isset($_GET['action']) || isset($_POST['action']));
    if ($wantsJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'message' => 'Sign in as Company Admin to ' . $context . '.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    $msg = htmlspecialchars('Sign in as Company Admin to ' . $context . '.', ENT_QUOTES, 'UTF-8');
    $_jpTheme = preg_replace('/[^a-z]/', '', strtolower((string)($_GET['theme'] ?? '')));
if (!in_array($_jpTheme, ['light','dark','reserve'], true)) {
    $_jpTheme = 'light';
}
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Access limited</title></head><body style="font-family:system-ui,sans-serif;max-width:32rem;margin:2rem auto;padding:1rem">'
        . '<h1 style="font-size:1.25rem">Access limited</h1>'
        . '<p>' . $msg . '</p>'
        . '<p class="meta">Shared Janam Patri links only open that person&rsquo;s chart (print / language). '
        . 'They do not include import or the full profile directory.</p>'
        . '<p><a href="./">Resource Centre home</a></p></body></html>';
    exit;
}


/** Locale: hi (default) | en — cookie + query */
function jp_lang(): string {
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $q = strtolower(trim((string)($_GET['lang'] ?? '')));
    if ($q === 'en' || $q === 'en_in' || $q === 'english') {
        $lang = 'en';
    } elseif ($q === 'hi' || $q === 'hi_in' || $q === 'hindi') {
        $lang = 'hi';
    } else {
        $c = strtolower(trim((string)($_COOKIE['jp_lang'] ?? '')));
        $lang = ($c === 'en') ? 'en' : 'hi';
    }
    if (!headers_sent()) {
        setcookie('jp_lang', $lang, [
            'expires' => time() + 86400 * 400,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    return $lang;
}

function jp_is_hi(): bool {
    return jp_lang() === 'hi';
}

/** @param array{0:string,1:string}|string $pair en, hi or single string */
function jp_t($pair): string {
    if (is_array($pair)) {
        return jp_is_hi() ? (string)($pair[1] ?? $pair[0] ?? '') : (string)($pair[0] ?? '');
    }
    return (string)$pair;
}

function jp_lang_toggle_url(): string {
    $params = $_GET;
    $params['lang'] = jp_is_hi() ? 'en' : 'hi';
    $q = http_build_query($params);
    return 'janam_patri.php' . ($q !== '' ? '?' . $q : '');
}


// Legacy BASE_PATH/storage/janam retired — all data under DATA_PATH/janam (JP_STORAGE).

const JP_RASHIS = ['Mesha','Vrishabha','Mithuna','Karka','Simha','Kanya','Tula','Vrischika','Dhanu','Makara','Kumbha','Meena'];
/** Western zodiac labels + Unicode glyphs (aligned 0=Mesha/Aries … 11=Meena/Pisces). */
const JP_ZODIAC = [
    ['Aries', '♈'],
    ['Taurus', '♉'],
    ['Gemini', '♊'],
    ['Cancer', '♋'],
    ['Leo', '♌'],
    ['Virgo', '♍'],
    ['Libra', '♎'],
    ['Scorpio', '♏'],
    ['Sagittarius', '♐'],
    ['Capricorn', '♑'],
    ['Aquarius', '♒'],
    ['Pisces', '♓'],
];

const JP_NAK = [
    'Ashwini','Bharani','Krittika','Rohini','Mrigashira','Ardra','Punarvasu','Pushya','Ashlesha',
    'Magha','Purva Phalguni','Uttara Phalguni','Hasta','Chitra','Swati','Vishakha','Anuradha','Jyeshtha',
    'Mula','Purva Ashadha','Uttara Ashadha','Shravana','Dhanishta','Shatabhisha','Purva Bhadrapada','Uttara Bhadrapada','Revati'
];
const JP_PNAMES = ['Su'=>'Surya','Mo'=>'Chandra','Ma'=>'Mangal','Me'=>'Budha','Ju'=>'Guru','Ve'=>'Shukra','Sa'=>'Shani','Ra'=>'Rahu','Ke'=>'Ketu'];

/** Devanagari + English-India display maps (render-time; storage stays Latin keys). */
const JP_RASHIS_HI = ['मेष','वृषभ','मिथुन','कर्क','सिंह','कन्या','तुला','वृश्चिक','धनु','मकर','कुम्भ','मीन'];
const JP_RASHIS_EN = ['Mesha','Vrishabha','Mithuna','Karka','Simha','Kanya','Tula','Vrischika','Dhanu','Makara','Kumbha','Meena'];
const JP_ZODIAC_EN = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
const JP_NAK_HI = [
    'अश्विनी','भरणी','कृत्तिका','रोहिणी','मृगशिरा','आर्द्रा','पुनर्वसु','पुष्य','अश्लेषा',
    'मघा','पूर्व फाल्गुनी','उत्तर फाल्गुनी','हस्त','चित्रा','स्वाती','विशाखा','अनुराधा','ज्येष्ठा',
    'मूल','पूर्वाषाढ़ा','उत्तराषाढ़ा','श्रवण','धनिष्ठा','शतभिषा','पूर्व भाद्रपद','उत्तर भाद्रपद','रेवती'
];
const JP_NAK_EN = [
    'Ashwini','Bharani','Krittika','Rohini','Mrigashira','Ardra','Punarvasu','Pushya','Ashlesha',
    'Magha','Purva Phalguni','Uttara Phalguni','Hasta','Chitra','Swati','Vishakha','Anuradha','Jyeshtha',
    'Mula','Purva Ashadha','Uttara Ashadha','Shravana','Dhanishta','Shatabhisha','Purva Bhadrapada','Uttara Bhadrapada','Revati'
];
const JP_PLANET_HI = [
    'Su' => 'सूर्य', 'Mo' => 'चंद्र', 'Ma' => 'मंगल', 'Me' => 'बुध',
    'Ju' => 'गुरु', 'Ve' => 'शुक्र', 'Sa' => 'शनि', 'Ra' => 'राहु', 'Ke' => 'केतु',
];
const JP_PLANET_EN = [
    'Su' => 'Surya (Sun)', 'Mo' => 'Chandra (Moon)', 'Ma' => 'Mangal (Mars)', 'Me' => 'Budha (Mercury)',
    'Ju' => 'Guru (Jupiter)', 'Ve' => 'Shukra (Venus)', 'Sa' => 'Shani (Saturn)', 'Ra' => 'Rahu', 'Ke' => 'Ketu',
];
const JP_PLANET_SHORT_HI = [
    'Su' => 'सू', 'Mo' => 'चं', 'Ma' => 'मं', 'Me' => 'बु', 'Ju' => 'गु', 'Ve' => 'शु', 'Sa' => 'श', 'Ra' => 'रा', 'Ke' => 'के',
];
const JP_PLANET_SHORT_EN = [
    'Su' => 'Su', 'Mo' => 'Mo', 'Ma' => 'Ma', 'Me' => 'Me', 'Ju' => 'Ju', 'Ve' => 'Ve', 'Sa' => 'Sa', 'Ra' => 'Ra', 'Ke' => 'Ke',
];
const JP_BHAVA_HI = [
    1 => 'प्रथम', 2 => 'द्वितीय', 3 => 'तृतीय', 4 => 'चतुर्थ',
    5 => 'पंचम', 6 => 'षष्ठ', 7 => 'सप्तम', 8 => 'अष्टम',
    9 => 'नवम', 10 => 'दशम', 11 => 'एकादश', 12 => 'द्वादश',
];
const JP_BHAVA_EN = [
    1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th',
    5 => '5th', 6 => '6th', 7 => '7th', 8 => '8th',
    9 => '9th', 10 => '10th', 11 => '11th', 12 => '12th',
];

function jp_rashi_loc(int $idx): string {
    $i = ((int)$idx) % 12;
    if ($i < 0) { $i += 12; }
    return jp_is_hi() ? (JP_RASHIS_HI[$i] ?? '—') : (JP_RASHIS_EN[$i] ?? '—');
}

function jp_nak_loc(int $idx): string {
    $i = ((int)$idx) % 27;
    if ($i < 0) { $i += 27; }
    return jp_is_hi() ? (JP_NAK_HI[$i] ?? '—') : (JP_NAK_EN[$i] ?? '—');
}

function jp_planet_loc(string $code): string {
    if (jp_is_hi()) {
        return JP_PLANET_HI[$code] ?? (JP_PNAMES[$code] ?? $code);
    }
    return JP_PLANET_EN[$code] ?? (JP_PNAMES[$code] ?? $code);
}

function jp_planet_short(string $code): string {
    return jp_is_hi()
        ? (JP_PLANET_SHORT_HI[$code] ?? $code)
        : (JP_PLANET_SHORT_EN[$code] ?? $code);
}

function jp_bhava_loc(int $h): string {
    $h = max(1, min(12, (int)$h));
    return jp_is_hi() ? (JP_BHAVA_HI[$h] ?? (string)$h) : (JP_BHAVA_EN[$h] ?? (string)$h);
}

function jp_ayanamsa_label(): string {
    return jp_t(['Ayanamsa (Lahiri approx.)', 'अयनांश (लाहड़ी अनुमान)']);
}

function jp_manglik_label(bool $yes, int $house = 0): string {
    if ($yes) {
        return jp_is_hi()
            ? ('हाँ · मंगलिक (भाव ' . $house . ')')
            : ('Yes · Manglik (house ' . $house . ')');
    }
    return jp_t(['No · not Manglik', 'नहीं · अमंगलिक']);
}



function jp_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jp_normalize_dob(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    // Already ISO
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    // DD-MM-YYYY or DD/MM/YYYY
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }
    // DD-MM-YY
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2})$/', $raw, $m)) {
        $y = (int)$m[3];
        $y += ($y < 50) ? 2000 : 1900;
        return sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[1]);
    }
    try {
        $dt = new DateTimeImmutable($raw, new DateTimeZone('Asia/Kolkata'));
        return $dt->format('Y-m-d');
    } catch (Throwable $e) {
        return '';
    }
}
function jp_normalize_tob(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '12:00';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m)) {
        return sprintf('%02d:%02d', min(23, (int)$m[1]), min(59, (int)$m[2]));
    }
    if (preg_match('/^(\d{1,2})\.(\d{2})/', $raw, $m)) {
        return sprintf('%02d:%02d', min(23, (int)$m[1]), min(59, (int)$m[2]));
    }
    return '12:00';
}


function jp_storage(string $file): string {
    return JP_STORAGE . '/' . $file;
}
function jp_load_profiles(): array {
    $path = jp_storage('profiles.json');
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
}
function jp_save_profiles(array $profiles): bool {
    $path = jp_storage('profiles.json');
    $tmp = $path . '.tmp.' . getmypid();
    $json = json_encode(array_values($profiles), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    return @rename($tmp, $path);
}
function jp_load_match_logs(): array {
    $path = jp_storage('match_logs.json');
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? array_values($data) : [];
}
function jp_match_log(array $entry): void {
    $path = jp_storage('match_logs.json');
    $logs = jp_load_match_logs();
    $logs[] = $entry;
    if (count($logs) > 2000) {
        $logs = array_slice($logs, -2000);
    }
    @file_put_contents($path, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function jp_find(array $profiles, string $id): ?array {
    foreach ($profiles as $p) {
        if ((string)($p['id'] ?? '') === $id) {
            return $p;
        }
    }
    return null;
}
function jp_load_team(): array {
    $path = DATA_PATH . '/team.json';
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
}

// ── Astronomy (indicative approximations) ──────────────────────────────────

function jp_name_key(string $name): string {
    $n = mb_strtolower(trim($name), 'UTF-8');
    $n = preg_replace('/\s+/', ' ', $n) ?? $n;
    // strip common double letters for fuzzy (Mannish~Manish, Anjalli~Anjali)
    $n = preg_replace('/(.)\1+/u', '$1', $n) ?? $n;
    return $n;
}

function jp_match_team_member(?string $slug, ?string $name, ?string $dob): ?array {
    $team = jp_load_team();
    if ($team === []) {
        return null;
    }
    $slug = strtolower(trim((string)$slug));
    $name = mb_strtolower(trim((string)$name), 'UTF-8');
    $dobN = jp_normalize_dob((string)$dob);
    // 1) exact slug / id
    if ($slug !== '') {
        foreach ($team as $m) {
            if (!is_array($m)) continue;
            $s = strtolower(trim((string)($m['slug'] ?? $m['id'] ?? '')));
            if ($s !== '' && $s === $slug) {
                return $m;
            }
        }
    }
    // 2) name + dob
    if ($name !== '' && $dobN !== '') {
        foreach ($team as $m) {
            if (!is_array($m)) continue;
            $mn = jp_name_key((string)($m['name'] ?? ''));
            $md = jp_normalize_dob((string)($m['dob'] ?? $m['birthday'] ?? $m['birth_date'] ?? ''));
            if ($mn === jp_name_key($name) && $md !== '' && $md === $dobN) {
                return $m;
            }
        }
    }
    // 3) name only (unique)
    if ($name !== '') {
        $hits = [];
        foreach ($team as $m) {
            if (!is_array($m)) continue;
            $mn = jp_name_key((string)($m['name'] ?? ''));
            if ($mn === jp_name_key($name)) {
                $hits[] = $m;
            }
        }
        if (count($hits) === 1) {
            return $hits[0];
        }
    }
    return null;
}

function jp_profile_from_team(array $m): array {
    $gender = strtolower(trim((string)($m['gender'] ?? 'male')));
    if (!in_array($gender, ['male', 'female'], true)) {
        $gender = 'male';
    }
    $dob = jp_normalize_dob((string)($m['dob'] ?? $m['birthday'] ?? $m['birth_date'] ?? ''));
    $tob = jp_normalize_tob((string)($m['time_of_birth'] ?? $m['tob'] ?? $m['birth_time'] ?? '12:00'));
    $place = trim((string)($m['place_of_birth'] ?? $m['birth_place'] ?? $m['pob'] ?? $m['city'] ?? 'Delhi'));
    if ($place === '') {
        $place = 'Delhi';
    }
    $gotra = trim((string)($m['gotra'] ?? ''));
    $lat = $m['birth_lat'] ?? $m['lat'] ?? null;
    $lng = $m['birth_lng'] ?? $m['lng'] ?? null;
    if (($lat === null || $lng === null) && !empty($m['birth_geo'])) {
        $parts = preg_split('/\s*,\s*/', (string)$m['birth_geo']);
        if (is_array($parts) && count($parts) >= 2) {
            $lat = $parts[0];
            $lng = $parts[1];
        }
    }
    $latS = ($lat !== null && $lat !== '') ? (string)round((float)$lat, 6) : '';
    $lngS = ($lng !== null && $lng !== '') ? (string)round((float)$lng, 6) : '';
    $geo = ($latS !== '' && $lngS !== '') ? ($latS . ', ' . $lngS) : '28.6139, 77.2090';
    $photo = '';
    foreach (['photo', 'image', 'avatar', 'img', 'photo_file'] as $_pk) {
        if (!empty($m[$_pk]) && is_string($m[$_pk])) {
            $photo = trim($m[$_pk]);
            break;
        }
    }
    return [
        'name' => trim((string)($m['name'] ?? '')),
        'dob' => $dob,
        'tob' => $tob !== '' ? $tob : '12:00',
        'place' => $place,
        'gender' => $gender,
        'gotra' => $gotra,
        'lat' => $latS,
        'lng' => $lngS,
        'geo' => $geo,
        'team_slug' => (string)($m['slug'] ?? $m['id'] ?? ''),
        'photo' => $photo,
    ];
}




/**
 * Positive life themes for the client (indicative), with symbols.
 * @return list<array{icon:string,title:string,text:string}>
 */
function jp_client_positives(array $p, array $chart): array {
    $out = [];
    $lagna = (int)($chart['lagna'] ?? 0);
    $moon = (int)($chart['moon_rashi'] ?? 0);
    $lagnaName = (string)($chart['lagna_name'] ?? JP_RASHIS[$lagna] ?? '');
    $moonName = (string)($chart['moon_rashi_name'] ?? JP_RASHIS[$moon] ?? '');
    [$lw, $lg] = jp_zodiac($lagna);
    [$mw, $mg] = jp_zodiac($moon);

    $lagnaGifts = [
        0 => 'Courage, pioneering drive, and the energy to start new ventures.',
        1 => 'Steady growth, reliability, and the gift of building lasting value.',
        2 => 'Communication skill, curiosity, and versatile intellect.',
        3 => 'Emotional intelligence, care for others, and protective strength.',
        4 => 'Leadership presence, creativity, and natural confidence.',
        5 => 'Discernment, service mindset, and practical excellence.',
        6 => 'Balance, partnership grace, and a sense of fairness.',
        7 => 'Depth, transformative will, and research-level focus.',
        8 => 'Wisdom-seeking, optimism, and expansive vision.',
        9 => 'Discipline, ambition, and capacity for structured success.',
        10 => 'Humanitarian insight, originality, and network strength.',
        11 => 'Compassion, imagination, and spiritual receptivity.',
    ];
    $moonGifts = [
        0 => 'Emotional courage and quick recovery from setbacks.',
        1 => 'Nurturing steadiness and loyalty in close bonds.',
        2 => 'Adaptive mind and ease with learning and dialogue.',
        3 => 'Deep empathy and instinctive care for family.',
        4 => 'Warmth, pride in craft, and creative self-expression.',
        5 => 'Clarity, humility in skill, and helpful routines.',
        6 => 'Harmony-seeking nature and diplomatic touch.',
        7 => 'Emotional intensity that fuels meaningful change.',
        8 => 'Faith, hope, and a teaching or guiding instinct.',
        9 => 'Patience, maturity under pressure, and long goals.',
        10 => 'Friendship networks and progressive ideas.',
        11 => 'Intuitive sensitivity and artistic feeling.',
    ];

    $out[] = [
        'icon' => $lg !== '' ? $lg : '☀️',
        'title' => 'Lagna strength — ' . $lagnaName,
        'text' => ($lagnaGifts[$lagna] ?? 'A distinctive life approach and personal magnetism.') . ' Zodiac: ' . $lw . '.',
    ];
    $out[] = [
        'icon' => $mg !== '' ? $mg : '🌙',
        'title' => 'Chandra (Moon) gift — ' . $moonName,
        'text' => ($moonGifts[$moon] ?? 'Emotional intelligence and inner resilience.') . ' Zodiac: ' . $mw . '.',
    ];

    $nak = (string)($chart['moon_nakshatra_name'] ?? '');
    if ($nak !== '') {
        $out[] = [
            'icon' => '✨',
            'title' => 'Nakshatra — ' . $nak,
            'text' => 'Pada ' . (int)($chart['moon_pada'] ?? 0) . ' supports a unique emotional rhythm and life timing signature.',
        ];
    }

    $nav = (string)($chart['nav_lagna_name'] ?? '');
    if ($nav !== '') {
        $out[] = [
            'icon' => '⭐',
            'title' => 'Navamsha lagna — ' . $nav,
            'text' => 'D-9 lagna highlights inner maturity, dharma path, and partnership potential.',
        ];
    }

    if (empty($chart['manglik'])) {
        $out[] = [
            'icon' => '🛡️',
            'title' => 'Kuja / Manglik clear',
            'text' => 'Mars placement does not flag classic Manglik stress in this indicative chart — smoother alliance timing themes.',
        ];
    } else {
        $out[] = [
            'icon' => '🔥',
            'title' => 'Mars energy (active)',
            'text' => 'Strong Mars drive supports courage, initiative, and protective strength when channelled consciously.',
        ];
    }

    $gotra = trim((string)($p['gotra'] ?? ''));
    if ($gotra !== '' && strcasecmp($gotra, 'Pending') !== 0) {
        $out[] = [
            'icon' => '🌿',
            'title' => 'Gotra lineage — ' . $gotra,
            'text' => 'Documented gotra supports clear Baniya-aware matchmaking and family identity continuity.',
        ];
    }

    // Benefic house themes (simplified): Guru/Shukra in kendra/trikona style positive framing
    $benefic = ['Ju' => 'Jupiter', 'Ve' => 'Venus', 'Mo' => 'Moon', 'Me' => 'Mercury'];
    $glyph = ['Ju' => '♃', 'Ve' => '♀', 'Mo' => '☽', 'Me' => '☿', 'Su' => '☉', 'Ma' => '♂', 'Sa' => '♄'];
    foreach ($benefic as $code => $label) {
        $pl = $chart['placements'][$code] ?? null;
        if (!is_array($pl)) {
            continue;
        }
        $h = (int)($pl['house'] ?? 0);
        if (in_array($h, [1, 4, 5, 7, 9, 10, 11], true)) {
            $out[] = [
                'icon' => $glyph[$code] ?? '◆',
                'title' => $label . ' in bhava ' . $h,
                'text' => $label . ' supports growth themes in house ' . $h . ' (' . (string)($pl['rashi_name'] ?? '') . ') — a constructive placement in this indicative model.',
            ];
        }
    }

    $out[] = [
        'icon' => '🙏',
        'title' => 'Overall tone',
        'text' => 'This summary emphasises constructive strengths only. Charts are indicative; use with family wisdom and professional guidance where needed.',
    ];
    return $out;
}

function jp_zodiac(int $rashiIdx): array {
    $i = $rashiIdx % 12;
    if ($i < 0) {
        $i += 12;
    }
    return JP_ZODIAC[$i] ?? ['—', ''];
}

function jp_rashi_label(int $rashiIdx): string {
    $i = $rashiIdx % 12;
    if ($i < 0) {
        $i += 12;
    }
    $name = JP_RASHIS[$i] ?? '—';
    [$west, $glyph] = jp_zodiac($i);
    return $name . ' · ' . $glyph . ' ' . $west;
}

/**
 * Resolve a displayable photo URL for a janam profile (team photo preferred).
 */
function jp_resolve_photo(array $p): string {
    $candidates = [];
    foreach (['photo', 'image', 'avatar', 'img'] as $k) {
        if (!empty($p[$k]) && is_string($p[$k])) {
            $candidates[] = trim($p[$k]);
        }
    }
    $slug = trim((string)($p['team_slug'] ?? ''));
    if ($slug !== '') {
        $team = jp_load_team();
        foreach ($team as $m) {
            if (!is_array($m)) {
                continue;
            }
            $s = (string)($m['slug'] ?? $m['id'] ?? '');
            if ($s === $slug || strcasecmp($s, $slug) === 0) {
                foreach (['photo', 'image', 'avatar', 'img', 'photo_file'] as $k) {
                    if (!empty($m[$k]) && is_string($m[$k])) {
                        $candidates[] = trim($m[$k]);
                    }
                }
                break;
            }
        }
    }
    foreach ($candidates as $raw) {
        if ($raw === '') {
            continue;
        }
        if (preg_match('#^https?://#i', $raw) || str_starts_with($raw, 'data:')) {
            return $raw;
        }
        $file = basename(str_replace(['\\', "\0"], ['/', ''], $raw));
        if ($file === '' || $file === '.' || $file === '..') {
            continue;
        }
        // Prefer public /images/ gateway (media_serve)
        return '/images/' . rawurlencode($file);
    }
    return '';
}

function jp_julian(int $y, int $m, int $d, float $ut): float {
    if ($m <= 2) {
        $y--;
        $m += 12;
    }
    $A = intdiv($y, 100);
    $B = 2 - $A + intdiv($A, 4);
    return floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $d + $ut / 24 + $B - 1524.5;
}
function jp_norm(float $x): float {
    $x = fmod($x, 360.0);
    return $x < 0 ? $x + 360.0 : $x;
}
function jp_ayanamsa(float $jd): float {
    $t = ($jd - 2451545.0) / 36525.0;
    return 24.218466 + 1.39656 * $t;
}
function jp_sidereal_positions(float $jd): array {
    $T = ($jd - 2451545.0) / 36525.0;
    $L0 = jp_norm(280.46646 + 36000.76983 * $T);
    $M = deg2rad(jp_norm(357.52911 + 35999.05029 * $T));
    $C = (1.914602 - 0.004817 * $T) * sin($M) + 0.019993 * sin(2 * $M);
    $sun = jp_norm($L0 + $C);
    $Lm = jp_norm(218.3165 + 481267.8813 * $T);
    $Mm = deg2rad(jp_norm(134.9634 + 477198.8676 * $T));
    $D = deg2rad(jp_norm(297.8502 + 445267.1115 * $T));
    $F = deg2rad(jp_norm(93.272 + 483202.0175 * $T));
    $moon = jp_norm($Lm + 6.289 * sin($Mm) + 1.274 * sin(2 * $D - $Mm) + 0.658 * sin(2 * $D) + 0.214 * sin(2 * $Mm));
    $ma = jp_norm(355.433 + 19140.302 * $T);
    $me = jp_norm(250.399 + 149472.674 * $T);
    $ju = jp_norm(34.351 + 3034.906 * $T);
    $ve = jp_norm(181.979 + 58517.815 * $T);
    $sa = jp_norm(50.077 + 1222.114 * $T);
    $ra = jp_norm(125.04452 - 1934.136261 * $T);
    $ayan = jp_ayanamsa($jd);
    $sid = static fn(float $x) => jp_norm($x - $ayan);
    return [
        'Su' => $sid($sun), 'Mo' => $sid($moon), 'Ma' => $sid($ma), 'Me' => $sid($me),
        'Ju' => $sid($ju), 'Ve' => $sid($ve), 'Sa' => $sid($sa), 'Ra' => $sid($ra), 'Ke' => $sid($ra + 180),
        'ayan' => $ayan, 'sun_trop' => $sun,
    ];
}
function jp_lagna(float $jd, float $lat, float $lng, float $ayan): int {
    $T = ($jd - 2451545.0) / 36525.0;
    $gmst = jp_norm(280.46061837 + 360.98564736629 * ($jd - 2451545.0) + 0.000387933 * $T * $T);
    $lst = jp_norm($gmst + $lng);
    $eps = deg2rad(23.4393 - 0.0000004 * ($jd - 2451545.0));
    $lstR = deg2rad($lst);
    $latR = deg2rad($lat);
    $y = sin($lstR);
    $x = cos($lstR) * cos($eps) + tan($latR) * sin($eps);
    $ramc = rad2deg(atan2($y, $x));
    $ascTrop = jp_norm($ramc);
    return (int)floor(jp_norm($ascTrop - $ayan) / 30.0) % 12;
}
function jp_rashi(float $lon): int {
    return (int)floor(jp_norm($lon) / 30.0) % 12;
}
function jp_nak(float $lon): int {
    return (int)floor(jp_norm($lon) / (360.0 / 27.0)) % 27;
}
function jp_pada(float $lon): int {
    return (int)floor(fmod(jp_norm($lon), 360.0 / 27.0) / (360.0 / 108.0)) % 4 + 1;
}
function jp_house(int $lagna, int $sign): int {
    return (($sign - $lagna + 12) % 12) + 1;
}
function jp_navamsha(float $lon): int {
    $sign = jp_rashi($lon);
    $posIn = fmod(jp_norm($lon), 30.0);
    $nav = (int)floor($posIn / (30.0 / 9.0));
    $start = [0, 9, 6, 3, 0, 9, 6, 3, 0, 9, 6, 3];
    return ($start[$sign] + $nav) % 12;
}

/**
 * Classical-style varga sign (0–11). Indicative; matches common software patterns for D-3/4/7/9/10/12.
 */
function jp_varga_sign(float $lon, int $varga): int {
    $lon = jp_norm($lon);
    $sign = jp_rashi($lon);
    $deg = fmod($lon, 30.0);
    if ($deg < 0) {
        $deg += 30.0;
    }
    $varga = max(1, $varga);
    if ($varga === 1) {
        return $sign;
    }
    if ($varga === 9) {
        return jp_navamsha($lon);
    }
    // Odd rashis in 1-based = even index 0,2,4… in 0-based
    $odd = ($sign % 2 === 0);
    if ($varga === 3) {
        // Drekkana: 0–10° same, 10–20° +4, 20–30° +8
        $part = min(2, (int)floor($deg / 10.0));
        return ($sign + $part * 4) % 12;
    }
    if ($varga === 4) {
        $part = min(3, (int)floor($deg / 7.5));
        return ($sign + $part * 3) % 12;
    }
    if ($varga === 7) {
        $part = min(6, (int)floor($deg / (30.0 / 7.0)));
        $base = $odd ? $sign : (($sign + 6) % 12);
        return ($base + $part) % 12;
    }
    if ($varga === 10) {
        $part = min(9, (int)floor($deg / 3.0));
        $base = $odd ? $sign : (($sign + 8) % 12); // even → 9th
        return ($base + $part) % 12;
    }
    if ($varga === 12) {
        $part = min(11, (int)floor($deg / 2.5));
        return ($sign + $part) % 12;
    }
    // Generic fallback: longitude × N
    return (int)floor(fmod($lon * $varga, 360.0) / 30.0) % 12;
}

/**
 * Build 12-house map for a varga: each house gets sign + planet codes.
 * @return array<int, array{sign:int,sign_name:string,planets:list<string>}>
 */
function jp_varga_houses(array $placementsLon, int $varga, int $lagnaSignD1): array {
    // Divisional lagna from ascendant longitude if provided as 'La', else from D-1 lagna sign only for D-1
    $lagnaLon = $placementsLon['La'] ?? null;
    if ($lagnaLon !== null) {
        $lagnaV = jp_varga_sign((float)$lagnaLon, $varga);
    } else {
        // Approximate: treat mid-sign of D-1 lagna
        $lagnaV = jp_varga_sign($lagnaSignD1 * 30.0 + 15.0, $varga);
    }
    $houses = [];
    for ($h = 1; $h <= 12; $h++) {
        $sign = ($lagnaV + $h - 1) % 12;
        $houses[$h] = [
            'sign' => $sign,
            'sign_name' => JP_RASHIS[$sign] ?? '',
            'planets' => [],
        ];
    }
    foreach ($placementsLon as $pk => $lon) {
        if ($pk === 'La' || $pk === 'ayan' || $pk === 'sun_trop') {
            continue;
        }
        if (!is_numeric($lon) && !is_float($lon) && !is_int($lon)) {
            continue;
        }
        $vs = jp_varga_sign((float)$lon, $varga);
        $h = jp_house($lagnaV, $vs);
        $houses[$h]['planets'][] = (string)$pk;
    }
    return $houses;
}

/** Julian day for a DateTimeImmutable at given clock (IST → UT approx −5.5h). */
function jp_jd_from_dt(\DateTimeInterface $dt): float {
    $ist = \DateTimeImmutable::createFromInterface($dt)->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $y = (int)$ist->format('Y');
    $m = (int)$ist->format('n');
    $d = (int)$ist->format('j');
    $ut = ((int)$ist->format('G')) + ((int)$ist->format('i')) / 60.0 + ((int)$ist->format('s')) / 3600.0 - 5.5;
    return jp_julian($y, $m, $d, $ut);
}

/**
 * Sade Sati: Saturn in 12th / 1st / 2nd from natal Moon.
 * @return array{active:bool,phase:int,phase_label:string,moon_sign:int,saturn_sign:int,as_of:string,next_start:?string,phases:list<array>}
 */
function jp_sade_sati(array $chart): array {
    $moonSign = (int)($chart['moon_rashi'] ?? 0);
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $jd = jp_jd_from_dt($now);
    $pos = jp_sidereal_positions($jd);
    $saSign = jp_rashi((float)$pos['Sa']);
    $rel = ($saSign - $moonSign + 12) % 12; // 0=Moon sign, 11=12th, 1=2nd
    $phase = 0;
    if ($rel === 11) {
        $phase = 1;
    } elseif ($rel === 0) {
        $phase = 2;
    } elseif ($rel === 1) {
        $phase = 3;
    }
    $phaseLabels = [
        0 => jp_t(['Not in Sade Sati', 'साढ़े साती नहीं']),
        1 => jp_t(['Phase 1 of 3 — rising (12th from Moon)', 'चरण 1/3 — उदय (चंद्र से 12वीं)']),
        2 => jp_t(['Phase 2 of 3 — peak (on Moon sign)', 'चरण 2/3 — शिखर (चंद्र राशि पर)']),
        3 => jp_t(['Phase 3 of 3 — setting (2nd from Moon)', 'चरण 3/3 — अस्त (चंद्र से 2री)']),
    ];
    // Scan ~30 years yearly for next window start (when Saturn enters 12th from Moon)
    $nextStart = null;
    if ($phase === 0) {
        $cursor = $now;
        for ($i = 0; $i < 36; $i++) {
            $cursor = $cursor->modify('+3 months');
            $p2 = jp_sidereal_positions(jp_jd_from_dt($cursor));
            $s2 = jp_rashi((float)$p2['Sa']);
            $r2 = ($s2 - $moonSign + 12) % 12;
            if ($r2 === 11 || $r2 === 0 || $r2 === 1) {
                $nextStart = $cursor->format('d-m-Y');
                break;
            }
        }
    }
    $phases = [
        ['n' => 1, 'label' => jp_t(['Rising', 'उदय']), 'desc' => jp_t(['Saturn in 12th from Moon', 'चंद्र से 12वीं में शनि']), 'on' => $phase === 1],
        ['n' => 2, 'label' => jp_t(['Peak', 'शिखर']), 'desc' => jp_t(['Saturn on Moon sign', 'चंद्र राशि पर शनि']), 'on' => $phase === 2],
        ['n' => 3, 'label' => jp_t(['Setting', 'अस्त']), 'desc' => jp_t(['Saturn in 2nd from Moon', 'चंद्र से 2री में शनि']), 'on' => $phase === 3],
    ];
    return [
        'active' => $phase > 0,
        'phase' => $phase,
        'phase_label' => $phaseLabels[$phase] ?? $phaseLabels[0],
        'moon_sign' => $moonSign,
        'saturn_sign' => $saSign,
        'as_of' => $now->format('d-m-Y'),
        'next_start' => $nextStart,
        'phases' => $phases,
    ];
}


/**
 * Today's gochar (transit) vs natal lagna houses.
 * @return array{as_of:string,items:list<array>,houses:array<int,list<string>>}
 */
function jp_gochar(array $chart): array {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $jd = jp_jd_from_dt($now);
    $pos = jp_sidereal_positions($jd);
    $lagna = (int)($chart['lagna'] ?? 0);
    $keys = ['Su', 'Mo', 'Ma', 'Me', 'Ju', 'Ve', 'Sa', 'Ra', 'Ke'];
    $items = [];
    $byHouse = array_fill(1, 12, []);
    foreach ($keys as $pk) {
        if (!isset($pos[$pk])) {
            continue;
        }
        $lon = (float)$pos[$pk];
        $sign = jp_rashi($lon);
        $house = jp_house($lagna, $sign);
        $natalH = (int)($chart['placements'][$pk]['house'] ?? 0);
        $natalS = (int)($chart['placements'][$pk]['rashi'] ?? -1);
        // notable houses
        $notable = in_array($house, [1, 5, 7, 9, 10], true);
        $items[] = [
            'planet' => $pk,
            'name' => jp_planet_loc($pk),
            'sign' => $sign,
            'sign_name' => jp_rashi_loc($sign),
            'house' => $house,
            'natal_house' => $natalH,
            'natal_sign' => $natalS,
            'notable' => $notable,
            'blurb' => jp_gochar_blurb($pk, $house),
        ];
        $byHouse[$house][] = $pk;
    }
    return [
        'as_of' => $now->format('d-m-Y'),
        'items' => $items,
        'houses' => $byHouse,
        'lagna' => $lagna,
    ];
}

function jp_gochar_blurb(string $pk, int $house): string {
    $h = max(1, min(12, $house));
    $houseTheme = [
        1 => jp_t(['self and vitality', 'स्व व ऊर्जा']),
        2 => jp_t(['wealth and speech', 'धन व वाणी']),
        3 => jp_t(['courage and effort', 'साहस व प्रयास']),
        4 => jp_t(['home and peace', 'घर व सुख']),
        5 => jp_t(['creativity and children', 'सृजन व संतान']),
        6 => jp_t(['work and challenges', 'कार्य व चुनौतियाँ']),
        7 => jp_t(['partnerships', 'साझेदारी']),
        8 => jp_t(['transformation', 'परिवर्तन']),
        9 => jp_t(['dharma and fortune', 'धर्म व भाग्य']),
        10 => jp_t(['career and status', 'कर्म व स्थिति']),
        11 => jp_t(['gains and networks', 'लाभ व संग']),
        12 => jp_t(['release and solitude', 'त्याग व एकांत']),
    ];
    $p = jp_planet_loc($pk);
    $theme = $houseTheme[$h] ?? '';
    return jp_is_hi()
        ? ($p . ' गोचर ' . jp_bhava_loc($h) . ' में — ' . $theme . ' पर प्रभाव (संकेतात्मक)।')
        : (jp_planet_loc($pk) . ' transiting ' . jp_bhava_loc($h) . ' — themes of ' . $theme . ' (indicative).');
}

function jp_render_gochar(array $chart): void {
    $g = jp_gochar($chart);
    echo '<section class="jp-now-card" aria-label="Gochar">';
    echo '<h2>🌍 ' . jp_h(jp_t(['Today\'s sky vs your chart', 'आज का गोचर — आपकी कुंडली पर'])) . '</h2>';
    echo '<p class="jp-now-meta">' . jp_h(jp_t(['As of', 'तिथि'])) . ' <strong>' . jp_h($g['as_of']) . '</strong> IST · '
        . jp_h(jp_t(['Natal Lagna fixed; planets shown in transit houses', 'जन्म लग्न स्थिर; ग्रह गोचर भावों में'])) . '</p>';
    echo '</section>';

    // Build hybrid houses: natal sign structure + transit planet markers
    $lagna = (int)$g['lagna'];
    $houses = [];
    for ($h = 1; $h <= 12; $h++) {
        $sign = ($lagna + $h - 1) % 12;
        $natalPl = [];
        foreach ($chart['placements'] ?? [] as $pk => $pl) {
            if ((int)($pl['house'] ?? 0) === $h) {
                $natalPl[] = (string)$pk;
            }
        }
        $tr = $g['houses'][$h] ?? [];
        $houses[$h] = [
            'sign' => $sign,
            'sign_name' => JP_RASHIS[$sign] ?? '',
            'planets' => $natalPl,
            'transit' => $tr,
        ];
    }
    // Custom diamond with transit suffixes
    echo '<div class="card"><h2>' . jp_h(jp_t(['Transit overlay (natal + gochar)', 'गोचर परत (जन्म + गोचर)'])) . '</h2>';
    echo '<p class="meta">' . jp_h(jp_t(['Solid = natal · ( ) = transit today', 'बिना कोष्ठक = जन्म · ( ) = आज का गोचर'])) . '</p>';
    $html = '<div class="ni-chart" aria-label="Gochar chart">';
    for ($h = 1; $h <= 12; $h++) {
        $d = $houses[$h];
        $nat = implode(' ', array_map(static fn($p) => jp_planet_short((string)$p), $d['planets'] ?? []));
        $tr = implode(' ', array_map(static fn($p) => '(' . jp_planet_short((string)$p) . ')', $d['transit'] ?? []));
        $label = trim($nat . ' ' . $tr);
        $hn = jp_is_hi() ? (['१','२','३','४','५','६','७','८','९','१०','११','१२'][$h - 1] ?? (string)$h) : (string)$h;
        $html .= '<div class="ni-house h' . $h . '"><span class="ni-h">' . $hn . '</span>'
            . '<span class="ni-s">' . jp_h(jp_rashi_loc((int)$d['sign'])) . '</span>'
            . '<span class="ni-p">' . jp_h($label) . '</span></div>';
    }
    $html .= '</div>';
    echo $html;

    echo '<h3 style="margin-top:1rem;font-size:.95rem">' . jp_h(jp_t(['Notable transits', 'मुख्य गोचर'])) . '</h3>';
    echo '<table><thead><tr><th>' . jp_h(jp_t(['Graha','ग्रह'])) . '</th><th>' . jp_h(jp_t(['Transit rashi','गोचर राशि'])) . '</th><th>' . jp_h(jp_t(['House','भाव'])) . '</th><th>' . jp_h(jp_t(['Note','टिप्पणी'])) . '</th></tr></thead><tbody>';
    foreach ($g['items'] as $it) {
        if (empty($it['notable']) && !in_array($it['planet'], ['Ju', 'Sa', 'Ra'], true)) {
            continue; // keep list short: notables + slow grahas
        }
        echo '<tr><td>' . jp_h($it['name']) . '</td><td>' . jp_h($it['sign_name']) . '</td><td>' . jp_h(jp_bhava_loc((int)$it['house'])) . '</td><td>' . jp_h($it['blurb']) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p class="meta" style="margin-top:0.75rem">' . jp_h(jp_t([
        'Gochar is a daily snapshot. It is not a permanent reading and does not replace a full muhurta or consultation.',
        'गोचर दैनिक स्नैपशॉट है। स्थायी पाठ नहीं; पूर्ण मुहूर्त/परामर्श का विकल्प नहीं।',
    ])) . '</p></div>';
}

/**
 * Simplified Sarvashtakavarga-style house scores (indicative).
 * Uses compact relative bindu offsets per graha (not full classical recon tables).
 * @return array{scores:array<int,int>,max:int,min:int,as_of:string}
 */
function jp_ashtakavarga_sav(array $chart): array {
    // Relative house offsets (1–12) that receive a bindu from each planet — simplified educational set
    $tables = [
        'Su' => [1, 2, 4, 7, 8, 9, 10, 11],
        'Mo' => [3, 6, 7, 8, 10, 11],
        'Ma' => [1, 2, 4, 7, 8, 9, 10, 11],
        'Me' => [1, 2, 4, 6, 8, 10, 11],
        'Ju' => [1, 2, 3, 4, 7, 8, 9, 10, 11],
        'Ve' => [1, 2, 3, 4, 5, 8, 9, 11],
        'Sa' => [3, 5, 6, 10, 11, 12],
    ];
    $scores = array_fill(1, 12, 0);
    foreach ($tables as $pk => $offs) {
        $pl = $chart['placements'][$pk] ?? null;
        if (!is_array($pl)) {
            continue;
        }
        $sign = (int)($pl['rashi'] ?? 0);
        foreach ($offs as $o) {
            // house counted from planet's sign as "1"
            $targetSign = ($sign + $o - 1) % 12;
            $lagna = (int)($chart['lagna'] ?? 0);
            $h = jp_house($lagna, $targetSign);
            $scores[$h] = ($scores[$h] ?? 0) + 1;
        }
    }
    // mild lagna contribution
    $scores[1] = min(56, $scores[1] + 1);
    $max = max($scores);
    $min = min($scores);
    return [
        'scores' => $scores,
        'max' => $max,
        'min' => $min,
        'as_of' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('d-m-Y'),
    ];
}

function jp_render_ashtakavarga(array $chart): void {
    $a = jp_ashtakavarga_sav($chart);
    $scores = $a['scores'];
    $max = max(1, (int)$a['max']);
    echo '<section class="jp-now-card"><h2>📊 ' . jp_h(jp_t(['House strength (indicative SAV)', 'भाव बल — संकेतात्मक सर्वाष्टकवर्ग'])) . '</h2>';
    echo '<p class="jp-now-meta">' . jp_h(jp_t(['Higher bars = more supporting bindus in this simplified model', 'ऊँचा बार = इस सरल मॉडल में अधिक बिंदु'])) . '</p></section>';
    echo '<div class="card">';
    echo '<div style="display:grid;grid-template-columns:repeat(12,1fr);gap:4px;align-items:end;height:120px;margin:0.5rem 0 1rem">';
    for ($h = 1; $h <= 12; $h++) {
        $sc = (int)$scores[$h];
        $pct = (int)round(($sc / $max) * 100);
        $bg = $sc >= $max - 1 ? '#f59e0b' : ($sc <= (int)$a['min'] + 1 ? '#cbd5e1' : '#38bdf8');
        echo '<div style="display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%">';
        echo '<div style="width:100%;height:' . max(8, $pct) . '%;background:' . $bg . ';border-radius:4px 4px 0 0" title="H' . $h . ': ' . $sc . '"></div>';
        echo '<span style="font-size:0.65rem;font-weight:800;margin-top:2px">' . $h . '</span>';
        echo '<span style="font-size:0.6rem;color:#57534e">' . $sc . '</span>';
        echo '</div>';
    }
    echo '</div>';
    echo '<table><thead><tr><th>' . jp_h(jp_t(['House','भाव'])) . '</th><th>' . jp_h(jp_t(['Bindus','बिंदु'])) . '</th><th>' . jp_h(jp_t(['Reading','पाठ'])) . '</th></tr></thead><tbody>';
    for ($h = 1; $h <= 12; $h++) {
        $sc = (int)$scores[$h];
        if ($sc >= $max - 1) {
            $read = jp_t(['Relatively supported', 'सापेक्षतः समर्थित']);
        } elseif ($sc <= (int)$a['min'] + 1) {
            $read = jp_t(['Needs conscious effort', 'सचेत प्रयास चाहिए']);
        } else {
            $read = jp_t(['Average support', 'मध्यम समर्थन']);
        }
        echo '<tr><td>' . jp_h(jp_bhava_loc($h)) . '</td><td><strong>' . $sc . '</strong></td><td>' . jp_h($read) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p class="meta" style="margin-top:0.75rem">' . jp_h(jp_t([
        'This is a reduced Ashtakavarga-style score for education — not the full classical recon matrix. Treat as directional only.',
        'यह शैक्षिक/सरलीकृत अष्टकवर्ग-शैली है — पूर्ण शास्त्रीय तालिका नहीं। केवल दिशा सूचक।',
    ])) . '</p></div>';
}


function jp_render_sade_sati(array $chart): void {
    $s = jp_sade_sati($chart);
    $badge = $s['active'] ? 'warn' : 'ok';
    echo '<section class="jp-now-card" aria-label="Sade Sati">';
    echo '<h2>♄ ' . jp_h(jp_t(['Sade Sati status', 'साढ़े साती स्थिति'])) . '</h2>';
    echo '<div class="jp-now-main"><span class="jp-now-maha" style="font-size:1.25rem">' . jp_h($s['phase_label']) . '</span></div>';
    echo '<p class="jp-now-meta">' . jp_h(jp_t(['Natal Moon', 'जन्म चंद्र'])) . ': <strong>' . jp_h(jp_rashi_loc($s['moon_sign'])) . '</strong>';
    echo ' · ' . jp_h(jp_t(['Transit Saturn', 'गोचर शनि'])) . ': <strong>' . jp_h(jp_rashi_loc($s['saturn_sign'])) . '</strong>';
    echo ' · ' . jp_h(jp_t(['As of', 'तिथि'])) . ' ' . jp_h($s['as_of']) . '</p>';
    echo '<span class="badge ' . $badge . '">' . jp_h($s['active'] ? jp_t(['Active window', 'सक्रिय अवधि']) : jp_t(['Outside window', 'अवधि से बाहर'])) . '</span>';
    echo '<div class="jp-dasha-bar-row" style="margin-top:0.85rem;height:36px" role="list">';
    foreach ($s['phases'] as $ph) {
        $cls = !empty($ph['on']) ? 'cur' : 'fut';
        $bg = !empty($ph['on']) ? '#fbbf24' : '#e7e5e4';
        echo '<div class="jp-dasha-seg ' . $cls . '" style="flex:1;background:' . $bg . '" title="' . jp_h($ph['desc']) . '">'
            . jp_h((string)$ph['n'] . '. ' . $ph['label']) . '</div>';
    }
    echo '</div>';
    echo '<div class="jp-antar-list" style="margin-top:0.65rem">';
    foreach ($s['phases'] as $ph) {
        $c = !empty($ph['on']) ? ' cur' : '';
        echo '<div class="jp-antar-item' . $c . '"><strong>' . jp_h($ph['label']) . '</strong><br><span class="meta">' . jp_h($ph['desc']) . '</span></div>';
    }
    echo '</div>';
    if (!$s['active'] && !empty($s['next_start'])) {
        echo '<p class="jp-now-meta" style="margin-top:0.75rem">' . jp_h(jp_t(['Next indicative window near', 'अगली संकेतात्मक अवधि लगभग'])) . ' <strong>' . jp_h((string)$s['next_start']) . '</strong></p>';
    }
    echo '<p class="meta" style="margin-top:0.75rem">' . jp_h(jp_t([
        'Sade Sati here is a sign-based Saturn–Moon check (Lahiri-style positions). Timing is approximate; not a substitute for a full gochar reading.',
        'यह साढ़े साती चंद्र–शनि राशि तुलना है (लाहड़ी शैली)। समय अनुमानित है — पूर्ण गोचर का विकल्प नहीं।',
    ])) . '</p></section>';
}

function jp_render_varga_switcher(array $chart, array $p, string $pid): void {
    $allowed = [1 => 'D-1', 3 => 'D-3', 7 => 'D-7', 9 => 'D-9', 10 => 'D-10', 12 => 'D-12'];
    $captions = [
        1 => jp_t(['Lagna — whole life', 'लग्न — समग्र जीवन']),
        3 => jp_t(['Drekkana — siblings & courage', 'द्रेष्काण — सहोदर एवं साहस']),
        7 => jp_t(['Saptamsha — children & progeny', 'सप्तमांश — संतान']),
        9 => jp_t(['Navamsha — spouse & dharma strength', 'नवमांश — जीवनसाथी एवं धर्म बल']),
        10 => jp_t(['Dashamsha — career & public standing', 'दशमांश — कर्म एवं लोक स्थिति']),
        12 => jp_t(['Dwadashamsha — parents & lineage', 'द्वादशांश — माता-पिता एवं वंश']),
    ];
    $v = (int)($_GET['varga'] ?? 1);
    if (!isset($allowed[$v])) {
        $v = 1;
    }
    // Build lon map from chart placements
    $lons = [];
    foreach ($chart['placements'] ?? [] as $pk => $pl) {
        if (isset($pl['lon'])) {
            $lons[$pk] = (float)$pl['lon'];
        } elseif (isset($pl['degree'])) {
            // degree within sign only — reconstruct approx from rashi
            $r = (int)($pl['rashi'] ?? 0);
            $lons[$pk] = $r * 30.0 + (float)$pl['degree'];
        } elseif (isset($pl['rashi'])) {
            $lons[$pk] = (int)$pl['rashi'] * 30.0 + 15.0;
        }
    }
    $lagna = (int)($chart['lagna'] ?? 0);
    // Prefer D-1 houses from chart for varga 1 and 9 if precomputed
    if ($v === 1 && !empty($chart['houses_d1'])) {
        $houses = $chart['houses_d1'];
    } elseif ($v === 9 && !empty($chart['houses_d9'])) {
        $houses = $chart['houses_d9'];
    } else {
        $houses = jp_varga_houses($lons, $v, $lagna);
    }
    $base = '?view=patri&id=' . rawurlencode((string)($p['id'] ?? $pid)) . '&tab=varga&lang=' . jp_lang() . '&varga=';
    echo '<div class="card"><h2>◈ ' . jp_h(jp_t(['Divisional charts (Vargas)', 'वर्ग कुंडलियाँ'])) . '</h2>';
    echo '<p class="meta">' . jp_h($captions[$v] ?? '') . '</p>';
    echo '<div class="rc-subtabs no-print" style="margin:0.5rem 0 1rem" role="tablist">';
    foreach ($allowed as $num => $label) {
        $on = $v === $num ? ' on' : '';
        echo '<a class="rc-subtab' . $on . '" href="' . jp_h($base . $num) . '">' . jp_h($label) . '</a>';
    }
    echo '</div>';
    echo jp_diamond_ni($houses, $allowed[$v]);
    echo '<p class="meta" style="margin-top:0.75rem">' . jp_h(jp_t([
        'Vargas use standard sign-division rules on sidereal longitudes. Indicative only.',
        'वर्ग सिद्धांतों से राशि-खंडन। केवल संकेतात्मक।',
    ])) . '</p></div>';
}


function jp_build_chart(array $p): array {
    [$yh, $mh, $dh] = array_map('intval', explode('-', (string)$p['dob']));
    $tob = (string)($p['tob'] ?? '12:00');
    [$hh, $mm] = array_map('intval', array_pad(explode(':', $tob), 2, 0));
    $lat = (float)($p['lat'] ?? 28.6139);
    $lng = (float)($p['lng'] ?? 77.2090);
    // IST → UT
    $ut = $hh + $mm / 60.0 - 5.5;
    $jd = jp_julian($yh, $mh, $dh, $ut);
    $pos = jp_sidereal_positions($jd);
    $ayan = (float)$pos['ayan'];
    $lagna = jp_lagna($jd, $lat, $lng, $ayan);
    $placements = [];
    foreach (['Su','Mo','Ma','Me','Ju','Ve','Sa','Ra','Ke'] as $k) {
        $lon = (float)$pos[$k];
        $sign = jp_rashi($lon);
        $placements[$k] = [
            'lon' => round($lon, 2),
            'rashi' => $sign,
            'rashi_name' => JP_RASHIS[$sign],
            'house' => jp_house($lagna, $sign),
            'nakshatra' => jp_nak($lon),
            'nakshatra_name' => JP_NAK[jp_nak($lon)],
            'pada' => jp_pada($lon),
            'navamsha' => jp_navamsha($lon),
            'navamsha_name' => JP_RASHIS[jp_navamsha($lon)],
        ];
    }
    $housesD1 = [];
    for ($h = 1; $h <= 12; $h++) {
        $sign = ($lagna + $h - 1) % 12;
        $pl = [];
        foreach ($placements as $pk => $pld) {
            if ((int)$pld['house'] === $h) {
                $pl[] = $pk;
            }
        }
        $housesD1[$h] = ['sign' => $sign, 'sign_name' => JP_RASHIS[$sign], 'planets' => $pl];
    }
    $navLagna = (int)$placements['Su']['navamsha']; // approx anchor
    $navLagna = jp_navamsha(($lagna * 30.0) + 15.0);
    $housesD9 = [];
    for ($h = 1; $h <= 12; $h++) {
        $sign = ($navLagna + $h - 1) % 12;
        $pl = [];
        foreach ($placements as $pk => $pld) {
            if ((int)$pld['navamsha'] === $sign) {
                $pl[] = $pk;
            }
        }
        $housesD9[$h] = ['sign' => $sign, 'sign_name' => JP_RASHIS[$sign], 'planets' => $pl];
    }
    $marsHouse = (int)$placements['Ma']['house'];
    $manglikHouses = [1, 4, 7, 8, 12];
    $manglik = in_array($marsHouse, $manglikHouses, true);
    return [
        'ayanamsa' => round($ayan, 4),
        'lagna' => $lagna,
        'lagna_name' => JP_RASHIS[$lagna],
        'moon_rashi' => jp_rashi((float)$pos['Mo']),
        'moon_rashi_name' => JP_RASHIS[jp_rashi((float)$pos['Mo'])],
        'moon_nakshatra' => jp_nak((float)$pos['Mo']),
        'moon_nakshatra_name' => JP_NAK[jp_nak((float)$pos['Mo'])],
        'moon_pada' => jp_pada((float)$pos['Mo']),
        'placements' => $placements,
        'houses_d1' => $housesD1,
        'houses_d9' => $housesD9,
        'nav_lagna' => $navLagna,
        'nav_lagna_name' => JP_RASHIS[$navLagna],
        'manglik' => $manglik,
        'mars_house' => $marsHouse,
    ];
}

/** Richer Manglik: mutual cancel + common classical exceptions (indicative). */
function jp_manglik_analysis(array $boyChart, array $girlChart): array {
    $b = !empty($boyChart['manglik']);
    $g = !empty($girlChart['manglik']);
    $bh = (int)($boyChart['mars_house'] ?? 0);
    $gh = (int)($girlChart['mars_house'] ?? 0);
    $cancelled = false;
    $reasons = [];
    if ($b && $g) {
        $cancelled = true;
        $reasons[] = 'Mutual Manglik — classical cancellation applies';
    }
    // Same house Mars for both often treated as milder / cancelling in folk practice
    if ($b && $g && $bh === $gh && $bh > 0) {
        $cancelled = true;
        $reasons[] = 'Mars occupies the same house number in both charts';
    }
    // 7th-house Mars for both (marriage house) — mutual
    if ($b && $g && $bh === 7 && $gh === 7) {
        $cancelled = true;
        $reasons[] = 'Both have Mars in the 7th — mutual Kuja pattern';
    }
    $issue = ($b !== $g) && !$cancelled;
    if ($b && !$g && !$cancelled) {
        $reasons[] = 'Boy is Manglik; girl is not — review with a jyotishi';
    }
    if ($g && !$b && !$cancelled) {
        $reasons[] = 'Girl is Manglik; boy is not — review with a jyotishi';
    }
    if (!$b && !$g) {
        $reasons[] = 'Neither chart shows standard Kuja houses (1/4/7/8/12)';
    }
    return [
        'boy' => $b,
        'girl' => $g,
        'boy_house' => $bh,
        'girl_house' => $gh,
        'cancelled' => $cancelled,
        'issue' => $issue,
        'reasons' => $reasons,
    ];
}

// ── Ashtakoot ──────────────────────────────────────────────────────────────
function jp_varna(int $r): int {
    $map = [3 => 4, 7 => 4, 11 => 4, 0 => 3, 4 => 3, 8 => 3, 1 => 2, 5 => 2, 9 => 2, 2 => 1, 6 => 1, 10 => 1];
    return $map[$r] ?? 1;
}
function jp_vashya(int $boyR, int $girlR): float {
    $groups = ['chatushpad' => [0, 1, 8, 9], 'manava' => [2, 5, 6, 10], 'jalachar' => [3, 11], 'vanachar' => [4], 'keet' => [7]];
    $gb = $gg = '';
    foreach ($groups as $g => $signs) {
        if (in_array($boyR, $signs, true)) {
            $gb = $g;
        }
        if (in_array($girlR, $signs, true)) {
            $gg = $g;
        }
    }
    if ($gb === $gg) {
        return 2.0;
    }
    if (($gb === 'chatushpad' && $gg === 'manava') || ($gb === 'manava' && $gg === 'chatushpad')) {
        return 1.0;
    }
    if ($gb === 'jalachar' || $gg === 'jalachar') {
        return 1.0;
    }
    return 0.0;
}
function jp_tara(int $boyNak, int $girlNak): float {
    $tara = ((($girlNak - $boyNak + 27) % 27) % 9) + 1;
    return in_array($tara, [1, 2, 4, 6, 8, 9], true) ? 3.0 : 0.0;
}
function jp_yoni_name(int $n): string {
    $map = [0=>'Ashwa',1=>'Gaja',2=>'Mesha',3=>'Sarpa',4=>'Sarpa',5=>'Shwan',6=>'Marjar',7=>'Mesha',8=>'Marjar',9=>'Mushak',10=>'Mushak',11=>'Gau',12=>'Mahish',13=>'Vyaghra',14=>'Mahish',15=>'Vyaghra',16=>'Mriga',17=>'Mriga',18=>'Shwan',19=>'Vanar',20=>'Nakul',21=>'Vanar',22=>'Simha',23=>'Ashwa',24=>'Simha',25=>'Gau',26=>'Gaja'];
    return $map[$n] ?? 'Gaja';
}
function jp_yoni(int $bn, int $gn): float {
    $yb = jp_yoni_name($bn);
    $yg = jp_yoni_name($gn);
    if ($yb === $yg) {
        return 4.0;
    }
    $enemies = ['Ashwa'=>['Vanar'],'Gaja'=>['Simha'],'Mesha'=>['Vanar'],'Sarpa'=>['Nakul'],'Shwan'=>['Mriga'],'Marjar'=>['Mushak'],'Mushak'=>['Marjar'],'Gau'=>['Vyaghra'],'Mahish'=>['Vyaghra'],'Vyaghra'=>['Gau','Mahish'],'Mriga'=>['Shwan'],'Vanar'=>['Ashwa','Mesha'],'Nakul'=>['Sarpa'],'Simha'=>['Gaja']];
    if (isset($enemies[$yb]) && in_array($yg, $enemies[$yb], true)) {
        return 0.0;
    }
    if (isset($enemies[$yg]) && in_array($yb, $enemies[$yg], true)) {
        return 0.0;
    }
    return 2.0;
}
function jp_graha_maitri(int $boyR, int $girlR): float {
    $lords = [0=>'Ma',1=>'Ve',2=>'Me',3=>'Mo',4=>'Su',5=>'Me',6=>'Ve',7=>'Ma',8=>'Ju',9=>'Sa',10=>'Sa',11=>'Ju'];
    $friends = ['Su'=>['Mo','Ma','Ju'],'Mo'=>['Su','Me'],'Ma'=>['Su','Mo','Ju'],'Me'=>['Su','Ve'],'Ju'=>['Su','Mo','Ma'],'Ve'=>['Me','Sa'],'Sa'=>['Me','Ve']];
    $lb = $lords[$boyR];
    $lg = $lords[$girlR];
    if ($lb === $lg) {
        return 5.0;
    }
    $fb = $friends[$lb] ?? [];
    $fg = $friends[$lg] ?? [];
    if (in_array($lg, $fb, true) && in_array($lb, $fg, true)) {
        return 5.0;
    }
    if (in_array($lg, $fb, true) || in_array($lb, $fg, true)) {
        return 4.0;
    }
    return 1.0;
}
function jp_gana_name(int $n): string {
    $deva = [0, 4, 6, 9, 10, 13, 15, 17, 19, 22, 25];
    $manushya = [1, 5, 7, 11, 14, 16, 20, 23, 26];
    if (in_array($n, $deva, true)) {
        return 'Deva';
    }
    if (in_array($n, $manushya, true)) {
        return 'Manushya';
    }
    return 'Rakshasa';
}
function jp_gana(int $bn, int $gn): float {
    $gb = jp_gana_name($bn);
    $gg = jp_gana_name($gn);
    if ($gb === $gg) {
        return 6.0;
    }
    if (($gb === 'Deva' && $gg === 'Manushya') || ($gb === 'Manushya' && $gg === 'Deva')) {
        return 5.0;
    }
    if (($gb === 'Deva' && $gg === 'Rakshasa') || ($gb === 'Rakshasa' && $gg === 'Deva')) {
        return 1.0;
    }
    return 0.0;
}
function jp_bhakoot(int $boyR, int $girlR): float {
    $diff = min(($boyR - $girlR + 12) % 12, ($girlR - $boyR + 12) % 12);
    if (in_array($diff, [0, 3, 4, 5, 7], true)) {
        return 7.0;
    }
    return 0.0;
}
function jp_nadi_name(int $n): string {
    $adi = [0, 3, 6, 9, 12, 15, 18, 21, 24];
    $madhya = [1, 4, 7, 10, 13, 16, 19, 22, 25];
    if (in_array($n, $adi, true)) {
        return 'Adi';
    }
    if (in_array($n, $madhya, true)) {
        return 'Madhya';
    }
    return 'Antya';
}
function jp_nadi(int $bn, int $gn): array {
    $nb = jp_nadi_name($bn);
    $ng = jp_nadi_name($gn);
    $dosha = ($nb === $ng);
    return ['boy' => $nb, 'girl' => $ng, 'dosha' => $dosha, 'points' => $dosha ? 0.0 : 8.0];
}

function jp_guna_milan(array $boyChart, array $girlChart, array $boyP, array $girlP): array {
    $br = (int)$boyChart['moon_rashi'];
    $gr = (int)$girlChart['moon_rashi'];
    $bn = (int)$boyChart['moon_nakshatra'];
    $gn = (int)$girlChart['moon_nakshatra'];
    $varna = jp_varna($br) >= jp_varna($gr) ? 1.0 : 0.0;
    $vashya = jp_vashya($br, $gr);
    $tara = jp_tara($bn, $gn);
    $yoni = jp_yoni($bn, $gn);
    $graha = jp_graha_maitri($br, $gr);
    $gana = jp_gana($bn, $gn);
    $bhakoot = jp_bhakoot($br, $gr);
    $nadi = jp_nadi($bn, $gn);
    $total = $varna + $vashya + $tara + $yoni + $graha + $gana + $bhakoot + $nadi['points'];
    $boyGotra = mb_strtolower(trim((string)($boyP['gotra'] ?? '')), 'UTF-8');
    $girlGotra = mb_strtolower(trim((string)($girlP['gotra'] ?? '')), 'UTF-8');
    $gotraFail = ($boyGotra !== '' && $girlGotra !== '' && $boyGotra === $girlGotra);
    $manglik = jp_manglik_analysis($boyChart, $girlChart);
    if ($gotraFail) {
        $verdict = 'Not recommended — same Gotra (Baniya exogamy)';
    } elseif ($total >= 28 && !$nadi['dosha'] && !$manglik['issue']) {
        $verdict = 'Excellent match';
    } elseif ($total >= 24 && !$nadi['dosha']) {
        $verdict = 'Good match — consult family jyotishi on remaining points';
    } elseif ($total >= 18) {
        $verdict = 'Average match — review Nadi / Manglik / family tradition';
    } else {
        $verdict = 'Below conventional threshold — detailed consultation advised';
    }
    return [
        'kootas' => [
            ['name' => 'Varna', 'max' => 1, 'points' => $varna],
            ['name' => 'Vashya', 'max' => 2, 'points' => $vashya],
            ['name' => 'Tara', 'max' => 3, 'points' => $tara],
            ['name' => 'Yoni', 'max' => 4, 'points' => $yoni],
            ['name' => 'Graha Maitri', 'max' => 5, 'points' => $graha],
            ['name' => 'Gana', 'max' => 6, 'points' => $gana],
            ['name' => 'Bhakoot', 'max' => 7, 'points' => $bhakoot],
            ['name' => 'Nadi', 'max' => 8, 'points' => $nadi['points']],
        ],
        'total' => $total,
        'max' => 36,
        'nadi_dosha' => $nadi['dosha'],
        'nadi_boy' => $nadi['boy'],
        'nadi_girl' => $nadi['girl'],
        'manglik' => $manglik,
        'manglik_boy' => $manglik['boy'],
        'manglik_girl' => $manglik['girl'],
        'manglik_issue' => $manglik['issue'],
        'manglik_cancelled' => $manglik['cancelled'],
        'gotra_boy' => (string)($boyP['gotra'] ?? ''),
        'gotra_girl' => (string)($girlP['gotra'] ?? ''),
        'gotra_fail' => $gotraFail,
        'verdict' => $verdict,
        'boy_rashi' => $boyChart['moon_rashi_name'],
        'girl_rashi' => $girlChart['moon_rashi_name'],
        'boy_nak' => $boyChart['moon_nakshatra_name'],
        'girl_nak' => $girlChart['moon_nakshatra_name'],
    ];
}

function jp_diamond(array $houses, string $title, int $lagna): string {
    $cell = static function (int $h) use ($houses): string {
        $d = $houses[$h] ?? ['sign_name' => '', 'planets' => []];
        $pl = implode(' ', array_map(static fn($p) => jp_planet_short((string)$p), $d['planets'] ?? []));
        return '<div class="dc"><span class="ds">' . jp_h((string)($d['sign_name'] ?? '')) . '</span><span class="dp">' . jp_h($pl) . '</span><span class="dh">' . $h . '</span></div>';
    };
    // North Indian diamond: fixed house positions
    return '<div class="diamond" role="img" aria-label="' . jp_h($title) . '">'
        . '<div class="d-row">' . $cell(2) . $cell(1) . $cell(12) . '</div>'
        . '<div class="d-row">' . $cell(3) . $cell(0) . $cell(11) . '</div>'
        . '<div class="d-row">' . $cell(4) . $cell(5) . $cell(6) . $cell(7) . $cell(10) . '</div>'
        . '<div class="d-row">' . $cell(8) . $cell(9) . '</div>'
        . '</div>';
}
// Fix diamond: house 0 invalid — use proper layout
function jp_diamond_ni(array $houses, string $title): string {
    $html = '<div class="ni-chart" aria-label="' . jp_h($title) . '">';
    for ($h = 1; $h <= 12; $h++) {
        $d = $houses[$h] ?? ['sign_name' => '', 'planets' => []];
        $pl = implode(' ', array_map(static fn($p) => jp_planet_short((string)$p), $d['planets'] ?? []));
        $html .= '<div class="ni-house h' . $h . '"><span class="ni-h">' . (jp_is_hi() ? (['१','२','३','४','५','६','७','८','९','१०','११','१२'][$h - 1] ?? (string)$h) : (string)$h) . '</span>'
            . '<span class="ni-s">' . jp_h(jp_rashi_loc((int)($d['sign'] ?? 0))) . '</span>'
            . '<span class="ni-p">' . jp_h($pl) . '</span></div>';
    }
    $html .= '</div>';
    return $html;
}

function jp_disclaimer(): string {
    return '<p class="jp-disclaimer no-print">' . jp_h(jp_t(['Indicative algorithmic chart only (Lahiri-style approximations). Not priest-grade. Consult a qualified jyotishi for formal decisions.','यह संकेतात्मक एल्गोरिद्मिक कुंडली है (लाहड़ी शैली)। पौरोहित्य प्रमाणित नहीं। संस्कार/निर्णय हेतु योग्य ज्योतिषी से परामर्श करें।'])) . '</p>';
}


/**
 * A4 portrait print stylesheet — normal bleed (~15mm), high-contrast, clean breaks.
 * Invoked once from jp_header after screen styles.
 */

function jp_print_masthead(string $title, string $subtitle = ''): void {
    $brand = 'Arthsathi Limited';
    $when = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d-m-Y H:i') . ' IST';
    echo '<div class="jp-print-masthead" aria-hidden="true">';
    echo '<h1>' . jp_h($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p class="jp-print-sub">' . jp_h($subtitle) . '</p>';
    }
    echo '<p class="jp-print-brand">' . jp_h(jp_t(['Printed', 'मुद्रित'])) . ' ' . jp_h($when)
        . ' · ' . jp_h($brand) . ' · ' . jp_h(jp_t(['Indicative Vedic chart', 'संकेतात्मक वैदिक कुंडली'])) . '</p>';
    echo '</div>';
}
function jp_print_footer_block(): void {
    echo '<div class="jp-print-footer" aria-hidden="true">';
    if (class_exists(\App\Rc\Domain\Astro\Disclaimer::class, false) || class_exists('App\Rc\Domain\Astro\Disclaimer')) {
        echo '<p class="jp-disclaimer-line">' . jp_h(\App\Rc\Domain\Astro\Disclaimer::text(jp_is_hi() ? 'hi' : 'en')) . '</p>';
    }

    echo jp_h(jp_t([
        'This Janam Patri is an indicative algorithmic chart (Lahiri-style approximations). Not priest-certified. Site developer: Arthsathi Limited.',
        'यह जन्म पत्री संकेतात्मक एल्गोरिद्मिक कुंडली है। पौरोहित्य प्रमाणित नहीं। साइट डेवलपर: Arthsathi Limited।',
    ]));
    echo '</div>';
}
function jp_print_action_bar(string $labelEn = 'Print Janam Patri', string $labelHi = 'जन्म पत्री प्रिंट'): void {
    echo '<div class="jp-print-bar no-print" role="region" aria-label="Print">';
    echo '<button type="button" class="jp-print-btn" onclick="window.print()">'
        . '🖨️ ' . jp_h(jp_t([$labelEn, $labelHi])) . '</button>';
    echo '<span class="meta" style="margin:0">' . jp_h(jp_t([
        'A4 portrait · 15 mm margins · use browser Print → Save as PDF',
        'A4 पोर्ट्रेट · 15 मिमी हाशिया · ब्राउज़र प्रिंट → PDF सहेजें',
    ])) . '</span>';
    echo '</div>';
}


/** Shared report-family chrome (matches Numerology action placement). */
function jp_report_chrome_css(): void {
    echo <<<'CSS'
<style id="jp-report-chrome">
.rc-chrome{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.6rem;padding:.65rem .85rem;margin:0 0 1rem;border-radius:14px;background:rgba(255,253,247,.92);border:1.5px solid #f59e0b;box-shadow:0 4px 16px rgba(12,74,110,.12)}
.rc-chrome-left{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem}
.rc-chrome-right{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-left:auto}
.rc-chrome-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .85rem;border-radius:999px;border:1px solid #d97706;background:#fffbeb;color:#9a3412!important;font-weight:800;font-size:.78rem;text-decoration:none;cursor:pointer;line-height:1.2}
.rc-chrome-btn:hover{background:#fef3c7;color:#7c2d12}
.rc-chrome-btn.pri{background:linear-gradient(135deg,#c2410c,#b45309);color:#fff;border-color:#9a3412}
.rc-chrome-btn.pri:hover{filter:brightness(1.06);color:#fff}
.rc-chrome-title{font-weight:800;font-size:.85rem;color:#0c4a6e;margin-right:.35rem}
.rc-subtabs{display:flex;flex-wrap:wrap;gap:.35rem;margin:0 0 1rem;padding:.35rem;background:rgba(255,251,235,.85);border-radius:12px;border:1px solid #fcd34d}
.rc-subtab{padding:.45rem .9rem;border-radius:10px;font-size:.78rem;font-weight:800;text-decoration:none;color:#9a3412;border:1px solid transparent}
.rc-subtab:hover{background:#ffedd5}
.rc-subtab.on{background:#c2410c;color:#fff;border-color:#9a3412}
.jp-now-card{background:linear-gradient(135deg,#fff7ed 0%,#fef3c7 45%,#fffbeb 100%);border:2px solid #f59e0b;border-radius:18px;padding:1.15rem 1.25rem;margin:0 0 1.1rem;box-shadow:0 8px 28px rgba(194,65,12,.15)}
.jp-now-card h2{margin:0 0 .35rem;font-size:1.05rem;color:#9a3412}
.jp-now-main{display:flex;flex-wrap:wrap;align-items:baseline;gap:.5rem .85rem;margin:.35rem 0 .65rem}
.jp-now-maha{font-size:1.65rem;font-weight:900;color:#7c2d12;letter-spacing:.01em}
.jp-now-antar{font-size:1.05rem;font-weight:800;color:#0c4a6e}
.jp-now-meta{font-size:.85rem;color:#57534e;margin:0 0 .5rem}
.jp-progress{height:10px;border-radius:999px;background:#e7e5e4;overflow:hidden;margin:.4rem 0 .55rem}
.jp-progress>i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#d97706,#c2410c)}
.jp-dasha-bar-row{display:flex;height:28px;border-radius:8px;overflow:hidden;border:1px solid #d6d3d1;margin:0.75rem 0}
.jp-dasha-seg{display:flex;align-items:center;justify-content:center;font-size:.62rem;font-weight:800;color:#1c1917;min-width:0;overflow:hidden;white-space:nowrap;padding:0 2px;border-right:1px solid rgba(0,0,0,.08);cursor:default}
.jp-dasha-seg.cur{box-shadow:inset 0 0 0 2px #b45309;background:#fbbf24!important;color:#000}
.jp-dasha-seg.past{opacity:.55}
.jp-dasha-seg.fut{background:#f5f5f4}
.jp-antar-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.45rem;margin-top:.65rem}
.jp-antar-item{border:1px solid #e7e5e4;border-radius:10px;padding:.45rem .55rem;font-size:.78rem;background:#fff}
.jp-antar-item.cur{border-color:#d97706;background:#fffbeb;font-weight:800}
@media print{
  .rc-chrome,.rc-subtabs{display:none!important}
  .jp-now-card{break-inside:avoid;border:1pt solid #000;box-shadow:none;background:#fff}
}
</style>
CSS;
}

function jp_report_chrome(string $context = 'patri', string $printLabelEn = 'Print', string $printLabelHi = 'प्रिंट'): void {
    $lang = jp_lang();
    $other = $lang === 'hi' ? 'en' : 'hi';
    $params = $_GET;
    $params['lang'] = $other;
    $toggleUrl = 'janam_patri.php?' . http_build_query($params);
    $title = match ($context) {
        'milan' => jp_t(['Kundli Milan', 'कुंडली मिलान']),
        'dasha' => jp_t(['Vimshottari Dasha', 'विंशोत्तरी दशा']),
        'doshas' => jp_t(['Dosha Review', 'दोष समीक्षा']),
        default => jp_t(['Janam Patri', 'जन्म पत्री']),
    };
    echo '<div class="rc-chrome no-print" role="toolbar" aria-label="Report controls">';
    echo '<div class="rc-chrome-left"><span class="rc-chrome-title">' . jp_h($title) . '</span></div>';
    echo '<div class="rc-chrome-right">';
    echo '<a class="rc-chrome-btn" href="' . jp_h($toggleUrl) . '">' . ($lang === 'hi' ? '🇬🇧 English' : '🇮🇳 हिन्दी') . '</a>';
    echo '<button type="button" class="rc-chrome-btn pri" onclick="window.print()">' . jp_h(jp_t([$printLabelEn, $printLabelHi])) . '</button>';
    echo '</div></div>';
}

function jp_patri_subtabs(string $id, string $active): void {
    $tabs = [
        'kundli' => jp_t(['Kundli', 'कुंडली']),
        'dasha' => jp_t(['Dasha', 'दशा']),
        'sade' => jp_t(['Sade Sati', 'साढ़े साती']),
        'gochar' => jp_t(['Transits', 'गोचर']),
        'varga' => jp_t(['Vargas', 'वर्ग']),
        'strength' => jp_t(['Strength', 'बल']),
        'doshas' => jp_t(['Doshas', 'दोष']),
    ];
    echo '<nav class="rc-subtabs no-print" aria-label="Patri sections">';
    foreach ($tabs as $k => $label) {
        $href = '?view=patri&id=' . rawurlencode($id) . '&tab=' . $k . '&lang=' . jp_lang();
        $on = $active === $k ? ' on' : '';
        echo '<a class="rc-subtab' . $on . '" href="' . jp_h($href) . '">' . jp_h($label) . '</a>';
    }
    echo '</nav>';
}

function jp_render_dasha_now(array $dasha): void {
    $cur = $dasha['current'] ?? null;
    $antars = $dasha['antardasha'] ?? [];
    $blurbs = $dasha['blurbs'] ?? [];
    $asOf = (string)($dasha['as_of'] ?? '');
    echo '<section class="jp-now-card" aria-label="Current dasha">';
    echo '<h2>⏱️ ' . jp_h(jp_t(['Now — current Mahadasha', 'अभी — वर्तमान महादशा'])) . '</h2>';
    if ($cur === null) {
        echo '<p class="jp-now-meta">' . jp_h(jp_t(['Could not resolve current period for this birth data.', 'इस जन्म डेटा से वर्तमान दशा निर्धारित नहीं हो सकी।'])) . '</p></section>';
        return;
    }
    $antarCur = null;
    foreach ($antars as $a) {
        if (!empty($a['current'])) {
            $antarCur = $a;
            break;
        }
    }
    echo '<div class="jp-now-main">';
    echo '<span class="jp-now-maha">' . jp_h((string)$cur['name']) . '</span>';
    if ($antarCur) {
        echo '<span class="jp-now-antar">· ' . jp_h(jp_t(['Antardasha', 'अंतर्दशा'])) . ': ' . jp_h((string)$antarCur['name']) . '</span>';
    }
    echo '</div>';
    $prog = (float)($cur['progress'] ?? 0);
    $pct = (int)round($prog * 100);
    $remY = (float)($cur['remaining_years'] ?? 0);
    $remD = (int)($cur['remaining_days'] ?? 0);
    $countdown = $remY >= 1
        ? sprintf(jp_is_hi() ? '%.1f वर्ष शेष' : '%.1f years left', $remY)
        : sprintf(jp_is_hi() ? '%d दिन शेष' : '%d days left', max(0, $remD));
    echo '<p class="jp-now-meta">' . jp_h(jp_t(['Progress', 'प्रगति'])) . ': ' . $pct . '% · ' . jp_h($countdown);
    echo ' · ' . jp_h((string)$cur['from']) . ' → ' . jp_h((string)$cur['to']);
    echo ' · ' . jp_h(jp_t(['As of', 'तिथि'])) . ' ' . jp_h($asOf) . '</p>';
    echo '<div class="jp-progress" role="progressbar" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100"><i style="width:' . $pct . '%"></i></div>';
    $lord = (string)($cur['lord'] ?? '');
    if ($lord !== '' && isset($blurbs[$lord])) {
        echo '<p class="jp-now-meta" style="margin-top:.35rem">' . jp_h((string)$blurbs[$lord]) . '</p>';
    }
    echo '</section>';

    // Full timeline bar
    $periods = $dasha['periods'] ?? [];
    $totalY = 0.0;
    foreach ($periods as $p) {
        $totalY += max(0.5, (float)($p['years'] ?? 1));
    }
    if ($totalY < 1) {
        $totalY = 120;
    }
    echo '<div class="card"><h2>📈 ' . jp_h(jp_t(['Mahadasha timeline', 'महादशा समय-रेखा'])) . '</h2>';
    echo '<div class="jp-dasha-bar-row" role="img" aria-label="Mahadasha timeline">';
    foreach ($periods as $p) {
        $w = max(4, round(((float)$p['years'] / $totalY) * 100, 2));
        $cls = !empty($p['current']) ? 'cur' : ((float)($p['progress'] ?? 0) >= 1 ? 'past' : 'fut');
        $bg = match ((string)$p['lord']) {
            'Su' => '#fdba74', 'Mo' => '#e2e8f0', 'Ma' => '#fca5a5', 'Me' => '#bbf7d0',
            'Ju' => '#fde68a', 'Ve' => '#fbcfe8', 'Sa' => '#cbd5e1', 'Ra' => '#ddd6fe', 'Ke' => '#fecdd3',
            default => '#f5f5f4',
        };
        echo '<div class="jp-dasha-seg ' . $cls . '" style="flex:' . $w . ';background:' . $bg . '" title="'
            . jp_h($p['name'] . ' · ' . $p['from'] . ' → ' . $p['to']) . '">' . jp_h((string)$p['name']) . '</div>';
    }
    echo '</div>';
    echo '<table><thead><tr><th>' . jp_h(jp_t(['Lord','स्वामी'])) . '</th><th>' . jp_h(jp_t(['From','से'])) . '</th><th>' . jp_h(jp_t(['To','तक'])) . '</th><th>' . jp_h(jp_t(['Years','वर्ष'])) . '</th></tr></thead><tbody>';
    foreach ($periods as $p) {
        $rowStyle = !empty($p['current']) ? ' style="font-weight:800;background:#fffbeb"' : '';
        echo '<tr' . $rowStyle . '><td>' . jp_h((string)$p['name']) . '</td><td>' . jp_h((string)$p['from']) . '</td><td>' . jp_h((string)$p['to']) . '</td><td>' . jp_h((string)$p['years']) . '</td></tr>';
    }
    echo '</tbody></table>';
    if ($antars !== []) {
        echo '<h3 style="margin-top:1rem;font-size:.95rem">' . jp_h(jp_t(['Antardasha in current Mahadasha', 'वर्तमान महादशा की अंतर्दशा'])) . '</h3>';
        echo '<div class="jp-antar-list">';
        foreach ($antars as $a) {
            $c = !empty($a['current']) ? ' cur' : '';
            echo '<div class="jp-antar-item' . $c . '"><strong>' . jp_h((string)$a['name']) . '</strong><br><span class="meta">' . jp_h($a['from'] . ' → ' . $a['to']) . '</span></div>';
        }
        echo '</div>';
    }
    echo '<p class="meta" style="margin-top:.75rem">' . jp_h(jp_t([
        'Indicative Vimshottari (Lahiri-style). Balance uses Moon nakshatra progress. Not priest-certified.',
        'संकेतात्मक विंशोत्तरी (लाहड़ी शैली)। शेष भाग चंद्र नक्षत्र प्रगति से। पौरोहित्य प्रमाणित नहीं।',
    ])) . '</p></div>';
}

function jp_render_doshas(array $chart): void {
    $manglik = !empty($chart['manglik']);
    $marsH = (int)($chart['mars_house'] ?? 0);
    // Kaal Sarp heuristic: all planets between Ra and Ke longitudes (simplified)
    $raLon = (float)($chart['placements']['Ra']['lon'] ?? $chart['placements']['Ra']['degree'] ?? -1);
    $keLon = (float)($chart['placements']['Ke']['lon'] ?? $chart['placements']['Ke']['degree'] ?? -1);
    $kaal = false;
    if ($raLon >= 0 && $keLon >= 0) {
        $between = 0;
        $total = 0;
        foreach (['Su','Mo','Ma','Me','Ju','Ve','Sa'] as $pk) {
            if (!isset($chart['placements'][$pk])) {
                continue;
            }
            $total++;
            $lon = (float)($chart['placements'][$pk]['lon'] ?? $chart['placements'][$pk]['degree'] ?? 0);
            $lo = min($raLon, $keLon);
            $hi = max($raLon, $keLon);
            if ($lon >= $lo && $lon <= $hi) {
                $between++;
            }
        }
        $kaal = ($total > 0 && $between === $total);
    }
    $rows = [
        [
            'name' => jp_t(['Manglik (Kuja)', 'मंगलिक (कुज)']),
            'status' => $manglik ? jp_t(['Present', 'उपस्थित']) : jp_t(['Not present', 'अनुपस्थित']),
            'level' => $manglik ? 'warn' : 'ok',
            'text' => $manglik
                ? jp_t(['Mars in house ' . $marsH . ' of this indicative chart. Review with a jyotishi for formal matching.', 'इस संकेतात्मक कुंडली में मंगल भाव ' . $marsH . ' में। औपचारिक मिलान हेतु ज्योतिषी से परामर्श करें।'])
                : jp_t(['Classic Manglik flags are not raised in this model.', 'इस मॉडल में क्लासिक मंगलिक संकेत नहीं।']),
        ],
        [
            'name' => jp_t(['Kaal Sarp (indicative)', 'कालसर्प (संकेतात्मक)']),
            'status' => $kaal ? jp_t(['Possible', 'संभावित']) : jp_t(['Not indicated', 'संकेत नहीं']),
            'level' => $kaal ? 'warn' : 'ok',
            'text' => $kaal
                ? jp_t(['All sample grahas fall between Rahu–Ketu arc in this simplified test — confirm with full ephemeris.', 'सरल परीक्षण में सभी ग्रह राहु–केतु चाप में — पूर्ण पंचांग से पुष्टि करें।'])
                : jp_t(['Planets are not all confined between Rahu and Ketu in this check.', 'इस जाँच में ग्रह राहु–केतु के बीच पूर्णतः सीमित नहीं।']),
        ],
    ];
    echo '<div class="card"><h2>⚖️ ' . jp_h(jp_t(['Dosha checklist', 'दोष जाँच-सूची'])) . '</h2>';
    echo '<table><thead><tr><th>' . jp_h(jp_t(['Item','विषय'])) . '</th><th>' . jp_h(jp_t(['Status','स्थिति'])) . '</th><th>' . jp_h(jp_t(['Note','टिप्पणी'])) . '</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td><strong>' . jp_h($r['name']) . '</strong></td><td><span class="badge ' . $r['level'] . '">' . jp_h($r['status']) . '</span></td><td>' . jp_h($r['text']) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p class="meta">' . jp_h(jp_t([
        'Status is algorithmic and indicative — never alarmist gospel. Professional matching remains human judgment.',
        'स्थिति एल्गोरिद्मिक व संकेतात्मक है — भय फैलाने वाला निर्णय नहीं। व्यावसायिक मिलान मानवीय विवेक है।',
    ])) . '</p></div>';
}


function jp_print_css(): void {
    echo <<<'CSS'
<style id="jp-print-a4">
@page {
  size: A4 portrait;
  margin: 15mm;
}
@media print {
  html, body {
    width: 210mm !important;
    min-height: 297mm !important;
    margin: 0 !important;
    padding: 0 !important;
    background: #ffffff !important;
    color: #000000 !important;
    font-family: "Noto Sans Devanagari", "Inter", "Segoe UI", Arial, sans-serif !important;
    font-size: 10pt !important;
    line-height: 1.35 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  body.jp-vedic {
    background: #ffffff !important;
  }
  /* Chrome UI off */
  .no-print,
  nav.tabs,
  .tabs,
  header.app,
  .dock,
  .jp-lang-toggle,
  .jp-import-toolbar,
  button:not(.jp-print-keep),
  .btn,
  form.no-print,
  a.jp-lang-toggle {
    display: none !important;
  }
  .wrap {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  /* Print masthead (shown only on paper) */
  .jp-print-masthead {
    display: block !important;
    border-bottom: 2pt solid #000;
    padding-bottom: 6pt;
    margin-bottom: 10pt;
    page-break-after: avoid;
  }
  .jp-print-masthead h1 {
    margin: 0;
    font-size: 16pt;
    font-weight: 800;
    color: #000 !important;
  }
  .jp-print-masthead .jp-print-sub {
    margin: 2pt 0 0;
    font-size: 9pt;
    color: #333 !important;
  }
  .jp-print-masthead .jp-print-brand {
    margin-top: 4pt;
    font-size: 8pt;
    color: #444 !important;
  }
  .jp-print-footer {
    display: block !important;
    margin-top: 12pt;
    padding-top: 6pt;
    border-top: 1pt solid #999;
    font-size: 7.5pt;
    color: #444 !important;
    page-break-inside: avoid;
  }
  /* Cards → clean paper blocks */
  .card,
  .jp-mangalarambh,
  .jp-positives,
  .jp-patri-hero {
    background: #fff !important;
    border: 1pt solid #333 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    margin: 0 0 8pt !important;
    padding: 8pt 10pt !important;
    page-break-inside: avoid;
    break-inside: avoid;
    color: #000 !important;
  }
  .card h2, .card h3, h1, h2, h3, strong {
    color: #000 !important;
    -webkit-text-fill-color: #000 !important;
  }
  .meta, .card .meta, dt.meta {
    color: #333 !important;
  }
  p, li, td, dd, span, div {
    color: #000 !important;
  }
  /* Tables */
  table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 9pt !important;
    page-break-inside: auto;
  }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; break-inside: avoid; }
  th {
    background: #eee !important;
    color: #000 !important;
    border: 0.6pt solid #000 !important;
    padding: 3pt 4pt !important;
  }
  td {
    border: 0.5pt solid #666 !important;
    padding: 3pt 4pt !important;
    color: #000 !important;
  }
  /* Charts */
  .ni-chart {
    display: grid !important;
    grid-template-columns: repeat(4, 1fr) !important;
    gap: 3pt !important;
    max-width: 100% !important;
  }
  .ni-house {
    background: #fff !important;
    border: 0.8pt solid #000 !important;
    border-radius: 0 !important;
    min-height: 48pt !important;
    padding: 3pt !important;
    page-break-inside: avoid;
  }
  .ni-h, .ni-s, .ni-p { color: #000 !important; }
  .grid2 {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 8pt !important;
    page-break-inside: avoid;
  }
  @media print and (max-width: 0) { /* noop — keep grid2 for A4 */ }
  .jp-photo-sq, .jp-photo-sq img {
    width: 22mm !important;
    height: 22mm !important;
    border: 0.6pt solid #000 !important;
    border-radius: 0 !important;
  }
  .jp-badge {
    border: 0.5pt solid #000 !important;
    background: #f5f5f5 !important;
    color: #000 !important;
    border-radius: 0 !important;
  }
  .jp-dasha-bar {
    background: #ddd !important;
    height: 6pt !important;
  }
  .jp-dasha-bar > i {
    background: #444 !important;
  }
  .jp-upaya-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 6pt !important;
  }
  .jp-upaya-card {
    border: 0.6pt solid #000 !important;
    background: #fff !important;
    border-radius: 0 !important;
    page-break-inside: avoid;
  }
  .badge, .badge.ok, .badge.warn, .badge.bad {
    background: #f0f0f0 !important;
    color: #000 !important;
    border: 0.5pt solid #000 !important;
  }
  a { color: #000 !important; text-decoration: none !important; }
  a[href]::after { content: none !important; }
  img { max-width: 100% !important; page-break-inside: avoid; }
  .jp-disclaimer {
    display: block !important;
    border: 0.5pt solid #666 !important;
    background: #fafafa !important;
    color: #222 !important;
    font-size: 7.5pt !important;
    padding: 6pt !important;
    margin-top: 8pt !important;
  }
  /* Orphans / widows */
  p, li {
    orphans: 3;
    widows: 3;
  }
  h2, h3 {
    page-break-after: avoid;
    break-after: avoid;
  }
}
@media screen {
  .jp-print-masthead,
  .jp-print-footer {
    display: none !important;
  }
  .jp-print-btn {
    display: inline-flex !important;
    align-items: center;
    gap: 0.45rem;
    padding: 0.65rem 1.25rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-weight: 800;
    font-size: 0.9rem;
    background: linear-gradient(135deg, #c2410c, #b45309);
    color: #fff !important;
    box-shadow: 0 4px 14px rgba(194, 65, 12, 0.35);
  }
  .jp-print-btn:hover { filter: brightness(1.06); }
  .jp-print-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    align-items: center;
    margin: 0.75rem 0 1rem;
    padding: 0.75rem 1rem;
    background: #fffbeb;
    border: 1.5px solid #f59e0b;
    border-radius: 14px;
  }
}
</style>
CSS;
}


function jp_css(): void {
    echo <<<'CSS'
<style>
:root {
  --jp-bg:#0b1220; --jp-card:#ffffff; --jp-ink:#0f172a; --jp-muted:#64748b;
  --jp-line:#e2e8f0; --jp-accent:#4f46e5; --jp-ok:#059669; --jp-warn:#d97706; --jp-bad:#dc2626;
  --jp-dock:rgba(15,23,42,.92);
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f1f5f9;color:var(--jp-ink);font-size:.9rem;line-height:1.5}
header.app{background:linear-gradient(135deg,#0f172a,#1e293b);color:#f8fafc;padding:1.25rem 0;border-bottom:1px solid #334155}
header.app h1{margin:0;font-size:1.25rem;font-weight:800;letter-spacing:-.02em}
header.app p{margin:.25rem 0 0;color:#94a3b8;font-size:.8rem}
.wrap{max-width:960px;margin:0 auto;padding:1rem 1.1rem 5rem}
nav.tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:1rem 0}
nav.tabs a{display:inline-flex;align-items:center;gap:.35rem;padding:.45rem .85rem;border-radius:999px;background:#fff;border:1px solid var(--jp-line);color:var(--jp-ink);text-decoration:none;font-size:.75rem;font-weight:700}
nav.tabs a.on{background:var(--jp-accent);color:#fff;border-color:var(--jp-accent)}
.card{background:var(--jp-card);border:1px solid var(--jp-line);border-radius:16px;padding:1.1rem 1.2rem;margin:0 0 1rem;box-shadow:0 1px 3px rgba(15,23,42,.04)}
.card h2{margin:0 0 .5rem;font-size:1.05rem;font-weight:800}
.meta{color:var(--jp-muted);font-size:.8rem}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
@media(max-width:720px){.grid2{grid-template-columns:1fr}}
label{display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--jp-muted);margin:0 0 .25rem}
input,select,textarea,input[type=date],input[type=time]{width:100%;padding:.55rem .65rem;border:1px solid var(--jp-line);border-radius:10px;font:inherit;background:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;color-scheme:light}
button,.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;border-radius:10px;border:none;background:var(--jp-accent);color:#fff;font-weight:700;font-size:.8rem;cursor:pointer;text-decoration:none}
button.secondary,.btn.secondary{background:#fff;color:var(--jp-ink);border:1px solid var(--jp-line)}
table{width:100%;border-collapse:collapse;font-size:.8rem}
th,td{border-bottom:1px solid var(--jp-line);padding:.5rem .4rem;text-align:left;vertical-align:top}
th{font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;color:var(--jp-muted)}
.badge{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.7rem;font-weight:800}
.badge.ok{background:#d1fae5;color:#065f46}
.badge.warn{background:#fef3c7;color:#92400e}
.badge.bad{background:#fee2e2;color:#991b1b}
.score{font-size:2rem;font-weight:900;letter-spacing:-.03em}
.jp-disclaimer{margin:1rem 0 0;padding:.65rem .85rem;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:.75rem;line-height:1.45}
.ni-chart{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;max-width:420px}
.ni-house{background:#f8fafc;border:1px solid var(--jp-line);border-radius:8px;padding:6px;min-height:64px;position:relative}
.ni-h{position:absolute;top:4px;right:6px;font-size:.65rem;color:var(--jp-muted);font-weight:700}
.ni-s{font-size:.7rem;font-weight:700;color:var(--jp-accent)}
.ni-p{display:block;font-size:.7rem;margin-top:4px}
.footer-note{color:var(--jp-muted);font-size:.75rem;margin-top:1.5rem}
/* RC button contrast lock — never white-on-white */
.rc-chrome-btn.pri,.jp-print-btn,.dock .pri{color:#ffffff!important}
button.rc-chrome-btn,a.rc-chrome-btn{color:#9a3412!important}
button.rc-chrome-btn.pri,a.rc-chrome-btn.pri{color:#ffffff!important;background:linear-gradient(135deg,#c2410c,#b45309)!important}
.jp-print-btn{background:#0f172a!important;color:#f8fafc!important;border:1px solid #334155}
.dock{position:fixed;bottom:1.1rem;right:1.1rem;z-index:40;display:flex;gap:6px;padding:6px;border-radius:14px;background:var(--jp-dock);border:1px solid rgba(148,163,184,.25);box-shadow:0 8px 28px rgba(15,23,42,.35)}
.dock a,.dock button{background:rgba(30,41,59,.95);color:#f8fafc!important;border:1px solid rgba(148,163,184,.35);padding:.45rem .75rem;border-radius:10px;font-size:.7rem;font-weight:700;cursor:pointer;text-decoration:none}
.dock a:hover,.dock button:hover{background:rgba(255,255,255,.1);color:#fff}
.dock .pri{background:#2563eb!important;color:#ffffff!important}
@media print{
  .no-print,nav.tabs,.dock{display:none!important}
  body{background:#fff}
  .card{box-shadow:none;break-inside:avoid}
  .jp-disclaimer{border-color:#ccc;background:#f9f9f9;color:#333}
}
</style>
CSS;
}


/** Build a transient profile array from request + team (no disk write). */

function jp_vimshottari_full(array $chart, string $dobYmd = ''): array {
    // Classic Vimshottari: lord sequence by Moon nakshatra; balance from pada/longitude fraction.
    $lords = ['Ke', 'Ve', 'Su', 'Mo', 'Ma', 'Ra', 'Ju', 'Sa', 'Me'];
    $years = ['Ke' => 7.0, 'Ve' => 20.0, 'Su' => 6.0, 'Mo' => 10.0, 'Ma' => 7.0, 'Ra' => 18.0, 'Ju' => 16.0, 'Sa' => 19.0, 'Me' => 17.0];
    $nak = (int)($chart['moon_nakshatra'] ?? $chart['moon_nak'] ?? 0);
    $pada = max(1, min(4, (int)($chart['moon_pada'] ?? 1)));
    $startIdx = $nak % 9;
    // Fraction elapsed in nakshatra: prefer longitude if present, else pada midpoints
    $moonLon = null;
    if (isset($chart['placements']['Mo']['lon'])) {
        $moonLon = (float)$chart['placements']['Mo']['lon'];
    } elseif (isset($chart['moon_lon'])) {
        $moonLon = (float)$chart['moon_lon'];
    }
    if ($moonLon !== null) {
        $seg = fmod($moonLon, 13.3333333333);
        if ($seg < 0) {
            $seg += 13.3333333333;
        }
        $elapsedFrac = $seg / 13.3333333333;
    } else {
        $elapsedFrac = ($pada - 0.5) / 4.0;
    }
    $elapsedFrac = max(0.0, min(0.999, $elapsedFrac));
    $firstLord = $lords[$startIdx];
    $firstFull = $years[$firstLord];
    $balanceYears = $firstFull * (1.0 - $elapsedFrac);

    $birth = null;
    if ($dobYmd !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dobYmd, $m)) {
        try {
            $birth = new DateTimeImmutable($dobYmd . ' 12:00:00', new DateTimeZone('Asia/Kolkata'));
        } catch (Throwable $e) {
            $birth = null;
        }
    }
    if ($birth === null) {
        $birth = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    }
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));

    $periods = [];
    $cursor = $birth;
    // First mahadasha: only remaining balance from birth
    $end1 = $cursor->modify('+' . (int)round($balanceYears * 365.25) . ' days');
    $periods[] = [
        'lord' => $firstLord,
        'name' => jp_planet_loc($firstLord),
        'years' => round($balanceYears, 2),
        'years_full' => $firstFull,
        'from' => $cursor->format('Y-m-d'),
        'to' => $end1->format('Y-m-d'),
        'from_ts' => $cursor->getTimestamp(),
        'to_ts' => $end1->getTimestamp(),
        'balance_at_birth' => true,
    ];
    $cursor = $end1;
    for ($i = 1; $i < 9; $i++) {
        $lord = $lords[($startIdx + $i) % 9];
        $span = $years[$lord];
        $end = $cursor->modify('+' . (int)round($span * 365.25) . ' days');
        $periods[] = [
            'lord' => $lord,
            'name' => jp_planet_loc($lord),
            'years' => $span,
            'years_full' => $span,
            'from' => $cursor->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'from_ts' => $cursor->getTimestamp(),
            'to_ts' => $end->getTimestamp(),
            'balance_at_birth' => false,
        ];
        $cursor = $end;
    }

    $nowTs = $now->getTimestamp();
    $current = null;
    $currentIdx = 0;
    foreach ($periods as $i => &$p) {
        $spanSec = max(1, (int)$p['to_ts'] - (int)$p['from_ts']);
        $p['current'] = ($nowTs >= $p['from_ts'] && $nowTs < $p['to_ts']);
        if ($p['current']) {
            $doneSec = max(0, $nowTs - (int)$p['from_ts']);
            $remSec = max(0, (int)$p['to_ts'] - $nowTs);
            $p['progress'] = min(1.0, $doneSec / $spanSec);
            $p['remaining_days'] = (int)round($remSec / 86400);
            $p['remaining_years'] = round($remSec / (365.25 * 86400), 2);
            // Assign AFTER progress fields — $current = $p is a copy, not a reference
            $current = $p;
            $currentIdx = $i;
        } else {
            $p['progress'] = ($nowTs >= $p['to_ts']) ? 1.0 : 0.0;
            $p['remaining_days'] = 0;
            $p['remaining_years'] = 0.0;
        }
    }
    unset($p);
    // Safety: if somehow no current matched but periods exist spanning now, recompute
    if ($current === null && $periods !== []) {
        foreach ($periods as $i => $p) {
            if ($nowTs >= $p['from_ts'] && $nowTs < $p['to_ts']) {
                $spanSec = max(1, (int)$p['to_ts'] - (int)$p['from_ts']);
                $doneSec = max(0, $nowTs - (int)$p['from_ts']);
                $remSec = max(0, (int)$p['to_ts'] - $nowTs);
                $p['current'] = true;
                $p['progress'] = min(1.0, $doneSec / $spanSec);
                $p['remaining_days'] = (int)round($remSec / 86400);
                $p['remaining_years'] = round($remSec / (365.25 * 86400), 2);
                $current = $p;
                $currentIdx = $i;
                $periods[$i] = $p;
                break;
            }
        }
    }

    // Antardasha of current mahadasha (same 9 lords starting from maha lord)
    $antars = [];
    if ($current !== null) {
        $mahaLord = $current['lord'];
        $mahaIdx = array_search($mahaLord, $lords, true);
        if ($mahaIdx === false) {
            $mahaIdx = 0;
        }
        $mahaYears = (float)$current['years'];
        $aCursor = (new DateTimeImmutable('@' . $current['from_ts']))->setTimezone(new DateTimeZone('Asia/Kolkata'));
        for ($j = 0; $j < 9; $j++) {
            $al = $lords[($mahaIdx + $j) % 9];
            // antar duration = mahaYears * (full_years[al] / 120)
            $aYears = $mahaYears * ($years[$al] / 120.0);
            $aEnd = $aCursor->modify('+' . max(1, (int)round($aYears * 365.25)) . ' days');
            $aFromTs = $aCursor->getTimestamp();
            $aToTs = $aEnd->getTimestamp();
            $isCur = ($nowTs >= $aFromTs && $nowTs < $aToTs);
            $spanSec = max(1, $aToTs - $aFromTs);
            $antars[] = [
                'lord' => $al,
                'name' => jp_planet_loc($al),
                'years' => round($aYears, 3),
                'from' => $aCursor->format('Y-m-d'),
                'to' => $aEnd->format('Y-m-d'),
                'current' => $isCur,
                'progress' => $isCur ? min(1.0, max(0, $nowTs - $aFromTs) / $spanSec) : (($nowTs >= $aToTs) ? 1.0 : 0.0),
            ];
            $aCursor = $aEnd;
        }
    }

    $blurb = [
        'Ke' => jp_t(['Ketu periods often emphasise detachment, research and sudden shifts.', 'केतु दशा में वैराग्य, शोध और आकस्मिक मोड़ की प्रवृत्ति।']),
        'Ve' => jp_t(['Venus periods favour relationships, arts, comforts and diplomacy.', 'शुक्र दशा में संबंध, कला, सुख और कूटनीति अनुकूल।']),
        'Su' => jp_t(['Sun periods highlight authority, vitality and public standing.', 'सूर्य दशा में अधिकार, ऊर्जा और सार्वजनिक स्थान प्रमुख।']),
        'Mo' => jp_t(['Moon periods emphasise mind, home, emotions and public mood.', 'चंद्र दशा में मन, घर, भावनाएँ और लोक-मनोदशा।']),
        'Ma' => jp_t(['Mars periods bring drive, courage, conflict and initiative.', 'मंगल दशा में साहस, संघर्ष, पहल और ऊर्जा।']),
        'Ra' => jp_t(['Rahu periods can amplify ambition, foreign links and unconventional paths.', 'राहु दशा में महत्वाकांक्षा, विदेश-संपर्क और अपरंपरागत मार्ग।']),
        'Ju' => jp_t(['Jupiter periods support learning, growth, guidance and expansion.', 'गुरु दशा में विद्या, विकास, मार्गदर्शन और विस्तार।']),
        'Sa' => jp_t(['Saturn periods test patience, duty, structure and long effort.', 'शनि दशा में धैर्य, कर्तव्य, अनुशासन और दीर्घ परिश्रम।']),
        'Me' => jp_t(['Mercury periods favour communication, trade, analysis and skill.', 'बुध दशा में संचार, व्यापार, विश्लेषण और कौशल।']),
    ];

    return [
        'periods' => $periods,
        'current' => $current,
        'antardasha' => $antars,
        'blurbs' => $blurb,
        'as_of' => $now->format('d-m-Y'),
    ];
}

/** @deprecated alias */
function jp_vimshottari_approx(array $chart): array {
    $full = jp_vimshottari_full($chart, '');
    return $full['periods'];
}

function jp_ashtakavarga_simple(array $chart): array {
    return jp_ashtakavarga_sav($chart)['scores'];
}

function jp_combust_retro_notes(array $chart): array {
    $notes = [];
    foreach ($chart['placements'] ?? [] as $pk => $pl) {
        $deg = (float)($pl['degree'] ?? $pl['lon'] ?? 0);
        $name = jp_planet_loc((string)$pk);
        // Fake retro flag from hash of name+house for display stability (engine may not compute true retro)
        $retro = !empty($pl['retro']) || !empty($pl['vakri']);
        $combust = !empty($pl['combust']) || !empty($pl['astha']);
        if ($retro) {
            $notes[] = ['planet' => $name, 'flag' => 'Vakri', 'text' => jp_t(['Retrograde motion — results may unfold indirectly.', 'वक्री गति — फल अप्रत्यक्ष रूप से मिल सकते हैं।'])];
        }
        if ($combust) {
            $notes[] = ['planet' => $name, 'flag' => 'Astha', 'text' => jp_t(['Combust near Sun — vitality of significations may be reduced.', 'सूर्य के समीप अस्त — कारकत्व की तीव्रता घट सकती है।'])];
        }
    }
    if ($notes === []) {
        $notes[] = ['planet' => '—', 'flag' => 'OK', 'text' => jp_t(['No strong combust/retro flags in this indicative model.', 'इस संकेतात्मक मॉडल में स्पष्ट अस्त/वक्र चिह्न नहीं।'])];
    }
    return $notes;
}

function jp_remedies(array $p, array $chart): array {
    $lagna = (int)($chart['lagna'] ?? 0);
    $gems = [
        0 => ['Ruby (Manik)', 'माणिक्य', 'Gold', 'Ring finger'],
        1 => ['Pearl (Moti)', 'मोती', 'Silver', 'Little finger'],
        2 => ['Red Coral (Moonga)', 'मूंगा', 'Gold/Copper', 'Ring finger'],
        3 => ['Emerald (Panna)', 'पन्ना', 'Gold', 'Little finger'],
        4 => ['Ruby (Manik)', 'माणिक्य', 'Gold', 'Ring finger'],
        5 => ['Emerald (Panna)', 'पन्ना', 'Gold', 'Little finger'],
        6 => ['Diamond (Heera)', 'हीरा', 'White gold/Platinum', 'Middle finger'],
        7 => ['Red Coral (Moonga)', 'मूंगा', 'Gold', 'Ring finger'],
        8 => ['Yellow Sapphire (Pukhraj)', 'पुखराज', 'Gold', 'Index finger'],
        9 => ['Blue Sapphire (Neelam)*', 'नीलम*', 'Silver', 'Middle finger'],
        10 => ['Blue Sapphire (Neelam)*', 'नीलम*', 'Silver', 'Middle finger'],
        11 => ['Yellow Sapphire (Pukhraj)', 'पुखराज', 'Gold', 'Index finger'],
    ];
    $g = $gems[$lagna % 12];
    $rudraksha = [3, 5, 7][$lagna % 3];
    return [
        'ratna' => jp_is_hi() ? ($g[1] . ' · ' . $g[2] . ' · ' . $g[3]) : ($g[0] . ' · ' . $g[2] . ' · ' . $g[3]),
        'rudraksha' => $rudraksha . '-Mukhi',
        'mantra' => jp_t(['Om Namah Shivaya / Beeja mantra of Lagna lord (108×)', 'ॐ नमः शिवाय / लग्नेश बीज मंत्र (१०८ जप)']),
        'daan' => jp_t(['Feed cows / donate to education on dasha lord day', 'दशा स्वामी के दिन गौ सेवा / विद्या दान']),
        'color' => jp_t(['Prefer saffron, cream, and peacock blue tones', 'केसरिया, मलाई व मोर-नीला रंग शुभ']),
    ];
}

function jp_ephemeral_profile(): ?array {
    $qName = trim((string)($_GET['name'] ?? ''));
    $qDob = function_exists('jp_normalize_dob') ? jp_normalize_dob(trim((string)($_GET['dob'] ?? ''))) : trim((string)($_GET['dob'] ?? ''));
    $qTob = trim((string)($_GET['tob'] ?? $_GET['time_of_birth'] ?? ''));
    $qPob = trim((string)($_GET['pob'] ?? $_GET['place'] ?? ''));
    $qGotra = trim((string)($_GET['gotra'] ?? ''));
    $qSlug = trim((string)($_GET['slug'] ?? $_GET['team_slug'] ?? ''));
    $qGender = strtolower(trim((string)($_GET['gender'] ?? '')));
    $qLat = trim((string)($_GET['lat'] ?? ''));
    $qLng = trim((string)($_GET['lng'] ?? ''));

    $profiles = function_exists('jp_load_profiles') ? jp_load_profiles() : [];
    foreach ($profiles as $_p) {
        if ($qSlug !== '' && ((string)($_p['team_slug'] ?? '') === $qSlug || (string)($_p['id'] ?? '') === $qSlug)) {
            return $_p;
        }
        if ($qName !== '' && $qDob !== '') {
            if (jp_name_key((string)($_p['name'] ?? '')) === jp_name_key($qName)
                && jp_normalize_dob((string)($_p['dob'] ?? '')) === $qDob) {
                return $_p;
            }
        }
    }
    $tm = null;
    if (function_exists('jp_match_team_member')) {
        $tm = jp_match_team_member($qSlug, $qName, $qDob);
    }
    if (is_array($tm) && function_exists('jp_profile_from_team')) {
        $p = jp_profile_from_team($tm);
    } else {
        $p = [
            'id' => 'TEMP',
            'name' => $qName !== '' ? $qName : 'Native',
            'dob' => $qDob,
            'tob' => $qTob !== '' ? $qTob : '12:00',
            'place' => $qPob !== '' ? $qPob : 'Delhi',
            'gender' => in_array($qGender, ['male', 'female'], true) ? $qGender : 'male',
            'gotra' => $qGotra,
            'lat' => $qLat !== '' ? (float)$qLat : 28.6139,
            'lng' => $qLng !== '' ? (float)$qLng : 77.2090,
            'team_slug' => $qSlug,
        ];
    }
    // Overlay explicit query fields
    if ($qName !== '') $p['name'] = $qName;
    if ($qDob !== '') $p['dob'] = $qDob;
    if ($qTob !== '') $p['tob'] = $qTob;
    if ($qPob !== '') $p['place'] = $qPob;
    if ($qGotra !== '') $p['gotra'] = $qGotra;
    if ($qGender !== '') $p['gender'] = $qGender;
    if ($qLat !== '') $p['lat'] = (float)$qLat;
    if ($qLng !== '') $p['lng'] = (float)$qLng;
    if (trim((string)($p['dob'] ?? '')) === '') {
        return null;
    }
    if (trim((string)($p['tob'] ?? '')) === '') {
        $p['tob'] = '12:00';
    }
    return $p;
}


function jp_css_vedic(): void {
    echo <<<'CSS'
<style id="jp-vedic-theme">
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Yatra+One&family=Noto+Sans+Devanagari:wght@400;600;700&display=swap');
:root{
  --jp-saffron:#c2410c;--jp-sindoor:#ea580c;--jp-gold:#b45309;--jp-gold-light:#fbbf24;
  --jp-peacock:#0e4d6b;--jp-peacock-deep:#0c4a6e;--jp-ivory:#fffbeb;--jp-cream:#fef3c7;
  --jp-ink:#1c1917;--jp-muted:#78716c;--jp-ok:#15803d;--jp-warn:#b45309;--jp-bad:#b91c1c;
  --jp-card:#fffdf7;--jp-line:#fcd34d;--jp-dock:rgba(12,74,110,.92);
}
html[lang="hi"] body,html[lang="hi"] .jp-dev{font-family:'Noto Sans Devanagari','Poppins',system-ui,sans-serif}
html[lang="hi"] h1,html[lang="hi"] h2,html[lang="hi"] .jp-title{
  font-family:'Yatra One','Noto Sans Devanagari',serif;font-weight:400;letter-spacing:.02em
}
body.jp-vedic{
  background:linear-gradient(165deg,#0c4a6e 0%,#1e3a5f 35%,#7c2d12 100%);
  background-attachment:fixed;color:var(--jp-ink);min-height:100vh
}
body.jp-vedic .wrap,body.jp-vedic header.app .wrap{max-width:1100px}
body.jp-vedic .card{
  background:var(--jp-card);border:1px solid var(--jp-line);border-radius:16px;
  box-shadow:0 8px 28px rgba(12,74,110,.18);padding:1.15rem 1.25rem;margin-bottom:1rem
}
body.jp-vedic h1,body.jp-vedic h2{color:var(--jp-peacock-deep)}
body.jp-vedic .jp-mangalarambh{
  background:linear-gradient(135deg,#fff7ed,#fef3c7 40%,#fffbeb);
  border:2px solid #f59e0b;border-radius:18px;padding:1.25rem;margin-bottom:1.25rem
}
body.jp-vedic .jp-badge{
  display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .65rem;border-radius:999px;
  font-size:.75rem;font-weight:700;background:#ffedd5;color:#9a3412;border:1px solid #fdba74
}
body.jp-vedic .jp-lang-toggle{
  display:inline-flex;align-items:center;gap:.4rem;padding:.4rem .85rem;border-radius:999px;
  background:linear-gradient(90deg,#ea580c,#b45309);color:#fff;font-weight:700;font-size:.8rem;
  text-decoration:none;border:none;box-shadow:0 4px 12px rgba(194,65,12,.35)
}
body.jp-vedic .jp-lang-toggle:hover{filter:brightness(1.08);color:#fff}
body.jp-vedic .ni-chart{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;max-width:440px}
body.jp-vedic .ni-house{
  background:linear-gradient(160deg,#fffbeb,#fef3c7);border:1.5px solid #d97706;
  border-radius:10px;padding:8px;min-height:70px;position:relative
}
body.jp-vedic .ni-h{color:#9a3412;font-weight:800}
body.jp-vedic .ni-s{color:#0c4a6e;font-weight:800}
body.jp-vedic .ni-p{color:#1c1917;font-weight:600}
body.jp-vedic .jp-dasha-bar{
  height:10px;border-radius:999px;background:#e7e5e4;overflow:hidden;margin-top:.35rem
}
body.jp-vedic .jp-dasha-bar>i{display:block;height:100%;background:linear-gradient(90deg,#ea580c,#fbbf24);border-radius:999px}
body.jp-vedic .jp-upaya-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));gap:.75rem}
body.jp-vedic .jp-upaya-card{
  border-radius:12px;padding:.85rem;background:#fff;border:1px solid #fcd34d;
  border-left:4px solid #ea580c
}
body.jp-vedic table{width:100%;border-collapse:collapse;font-size:.85rem}
body.jp-vedic th{background:#0c4a6e;color:#fff;padding:.45rem .5rem;text-align:left}
body.jp-vedic td{padding:.4rem .5rem;border-bottom:1px solid #fde68a}
body.jp-vedic nav.tabs{
  display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0;padding:.65rem;
  background:rgba(255,251,235,.95);border-radius:14px;border:1px solid #f59e0b
}
body.jp-vedic nav.tabs a,
body.jp-vedic .tabs a{
  background:#fffbeb !important;color:#9a3412 !important;
  border:1.5px solid #d97706 !important;border-radius:999px !important;
  padding:.5rem .9rem !important;text-decoration:none !important;
  font-weight:700 !important;font-size:.8rem !important;line-height:1.3 !important;
  white-space:nowrap !important
}
body.jp-vedic nav.tabs a:hover,
body.jp-vedic .tabs a:hover{
  background:#ffedd5 !important;color:#7c2d12 !important
}
body.jp-vedic nav.tabs a.on,
body.jp-vedic .tabs a.on{
  background:#c2410c !important;color:#fff !important;border-color:#9a3412 !important
}
body.jp-vedic nav.tabs a + a{margin-left:0}
/* Dates & form fields — never white-on-white */
body.jp-vedic input,
body.jp-vedic select,
body.jp-vedic textarea,
body.jp-vedic input[type="date"],
body.jp-vedic input[type="time"],
body.jp-vedic input[type="text"],
body.jp-vedic input[type="number"]{
  background:#ffffff !important;color:#1c1917 !important;
  border:1.5px solid #d6d3d1 !important;-webkit-text-fill-color:#1c1917 !important;
  color-scheme:light
}
body.jp-vedic input::placeholder{color:#78716c !important;opacity:1}
body.jp-vedic label{color:#44403c !important}
body.jp-vedic .meta,
body.jp-vedic .card .meta{color:#57534e !important}
body.jp-vedic .card,
body.jp-vedic .card p,
body.jp-vedic .card li,
body.jp-vedic .card td,
body.jp-vedic .card th,
body.jp-vedic .card h2,
body.jp-vedic .card h3,
body.jp-vedic .card strong{color:#1c1917 !important}
body.jp-vedic .card th{background:#0c4a6e !important;color:#fff !important}
body.jp-vedic .footer-note,
body.jp-vedic .jp-disclaimer{
  color:#1c1917 !important;background:#fffbeb;border:1px solid #fcd34d;
  border-radius:10px;padding:.75rem 1rem
}
body.jp-vedic header.app h1{color:#fffbeb !important}
body.jp-vedic header.app p{color:#fde68a !important}

body.jp-vedic .footer-note,body.jp-vedic .jp-disclaimer{color:#fef3c7;opacity:.9}
body.jp-vedic .meta{color:#78716c}
/* print rules delegated to jp_print_css() */
</style>
CSS;
}

function jp_header(string $title, array $seo = []): void {
    $cur = (string)($_GET['view'] ?? 'home');
    echo '<!DOCTYPE html><html lang="' . (jp_is_hi() ? 'hi' : 'en') . '-IN" data-theme="reserve"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    if (defined('BASE_PATH') && is_file(BASE_PATH . '/app/views/partials/rc_report_assets.php')) { require BASE_PATH . '/app/views/partials/rc_report_assets.php'; }
    elseif (defined('BASE_PATH') && is_file(BASE_PATH . '/app/views/partials/rc_theme_head.php')) { require BASE_PATH . '/app/views/partials/rc_theme_head.php'; }
    $base = class_exists('SeoShare') ? SeoShare::baseUrl() : '';
    $pageUrl = (string)($seo['url'] ?? ($base . '/janam_patri.php?view=' . rawurlencode($cur)));
    $desc = (string)($seo['description'] ?? 'North Indian Janam Patri and Kundli Milan. Indicative charts for Lagna and Navamsha, Ashtakoot Guna Milan, and Baniya gotra-aware match guidance.');
    $img = (string)($seo['image'] ?? '');
    if ($img === '' || !preg_match('#^https?://#i', $img)) {
        $b = $base !== '' ? $base : (( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $img = rtrim($b, '/') . '/tools/og_card.php?' . http_build_query([
            'name' => (string)($seo['name'] ?? $title),
            'role' => (string)($seo['role'] ?? 'Janam Patri · Kundli'),
            'company' => (string)($seo['site_name'] ?? 'Arthsathi · Resource Centre'),
            'kind' => 'Janam Patri',
        ]);
    }
    $robots = (string)($seo['robots'] ?? (!empty($seo['index']) ? 'index, follow' : 'noindex, follow'));
    if (class_exists('SeoShare')) {
        echo SeoShare::tags([
            'title' => $title . ' · Janam Patri',
            'description' => $desc,
            'url' => $pageUrl,
            'image' => $img,
            'type' => (string)($seo['type'] ?? 'profile'),
            'robots' => $robots,
            'site_name' => (string)($seo['site_name'] ?? 'Janam Patri · Resource Centre'),
        ]);
    } else {
        echo '<title>' . jp_h($title) . ' · Janam Patri</title>';
        if ($desc !== '') echo '<meta name="description" content="' . jp_h($desc) . '">';
        if ($img !== '') {
            echo '<meta property="og:image" content="' . jp_h($img) . '">';
            echo '<meta name="twitter:card" content="summary_large_image">';
            echo '<meta name="twitter:image" content="' . jp_h($img) . '">';
        }
    }
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">';
    jp_css();
    if (function_exists('jp_css_vedic')) { jp_css_vedic(); }
    if (function_exists('jp_print_css')) { jp_print_css(); }
    if (function_exists('jp_report_chrome_css')) { jp_report_chrome_css(); }
    echo '<link rel="stylesheet" href="/assets/contrast-lock.css?v=20260929.06">
</head><body class="rc-report-body jp-vedic">';
    echo '<header class="app no-print"><div class="wrap" style="padding-top:0.75rem;padding-bottom:0.5rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem">';
    echo '<div><h1 style="margin:0;color:#fef3c7">🕉️ ' . jp_h(jp_t(['Janam Patri & Kundli Milan', 'जन्म पत्री एवं कुंडली मिलान'])) . '</h1>';
    echo '<p style="margin:0.25rem 0 0;color:#fde68a;font-size:0.85rem">' . jp_h(jp_t(['North Indian · Baniya gotra-aware · Resource Centre', 'उत्तर भारतीय · बनिया गोत्र सजग · संसाधन केंद्र'])) . '</p></div>';
    echo '<a class="jp-lang-toggle" href="' . jp_h(jp_lang_toggle_url()) . '">' . (jp_is_hi() ? '🇬🇧 English' : '🇮🇳 हिन्दी') . '</a>';
    echo '</div></header><div class="wrap">';
    echo '<nav class="tabs no-print">';
    $tabs = [
        'home' => jp_t(['Profiles', 'प्रोफ़ाइल']),
        'new' => jp_t(['New profile', 'नई प्रोफ़ाइल']),
        'import' => jp_t(['Import from Team', 'टीम से आयात']),
        'match' => jp_t(['Matchmaking', 'कुंडली मिलान']),
        'history' => jp_t(['Match history', 'मिलान इतिहास']),
    ];
    foreach ($tabs as $k => $lab) {
        $on = ($cur === $k || ($cur === 'patri' && $k === 'home') || ($cur === 'edit' && $k === 'new')) ? ' on' : '';
        $href = '?view=' . $k . '&lang=' . jp_lang();
        echo '<a class="' . trim($on) . '" href="' . $href . '">' . jp_h($lab) . '</a>';
    }
    echo '<a href="index.php?tab=team">← RC</a></nav>';
}
function jp_footer(): void {
    echo jp_disclaimer();
    echo '<p class="footer-note">' . jp_h(jp_t(['Data stored in local data/janam JSON. Same-gotra alliances flagged per Baniya tradition.','डेटा स्थानीय data/janam JSON में संचित। समान गोत्र मिलान पर बनिया परंपरा अनुसार चेतावनी।'])) . '</p>';
    echo '<nav class="dock no-print" aria-label="Quick actions">';
    if (jp_can_manage()) {
        echo '<a class="pri" href="?view=match&lang=' . jp_lang() . '">' . jp_h(jp_t(['Milan','मिलान'])) . '</a>';
        echo '<a href="?view=import&lang=' . jp_lang() . '">' . jp_h(jp_t(['Import','आयात'])) . '</a>';
        echo '<a href="?view=history&lang=' . jp_lang() . '">' . jp_h(jp_t(['History','इतिहास'])) . '</a>';
        echo '<a href="?view=home&lang=' . jp_lang() . '">' . jp_h(jp_t(['Profiles','प्रोफ़ाइल'])) . '</a>';
    }
    echo '<button type="button" onclick="window.print()">' . jp_h(jp_t(['Print','प्रिंट'])) . '</button>';
    echo '</nav></div></body></html>';
}

function jp_save_profile(array $in): array {
    $name = trim((string)($in['name'] ?? ''));
    $gender = strtolower(trim((string)($in['gender'] ?? '')));
    $dob = trim((string)($in['dob'] ?? ''));
    $tob = trim((string)($in['tob'] ?? '12:00'));
    $place = trim((string)($in['place'] ?? ''));
    $geo = trim((string)($in['geo'] ?? $in['birth_geo'] ?? ''));
    if ($geo !== '' && preg_match('/(-?\d+(?:\.\d+)?)\s*[,\s]+\s*(-?\d+(?:\.\d+)?)/', $geo, $gm)) {
        $in['lat'] = $gm[1];
        $in['lng'] = $gm[2];
    }
    $lat = round((float)($in['lat'] ?? 28.6139), 6);
    $lng = round((float)($in['lng'] ?? 77.2090), 6);
    $gotra = trim((string)($in['gotra'] ?? ''));
    $id = trim((string)($in['id'] ?? ''));
    $teamSlug = trim((string)($in['team_slug'] ?? ''));
    if ($name === '' || $dob === '' || $gotra === '') {
        return ['status' => 'error', 'message' => 'Name, Date of Birth, and Gotra are mandatory.'];
    }
    if (!in_array($gender, ['male', 'female'], true)) {
        $gender = 'male';
    }
    $dob = jp_normalize_dob($dob);
    if ($dob === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        return ['status' => 'error', 'message' => 'DOB must be a valid date (YYYY-MM-DD or DD-MM-YYYY).'];
    }
    if (!preg_match('/^\d{1,2}:\d{2}/', $tob)) {
        $tob = '12:00';
    }
    $tob = substr($tob, 0, 5);
    $profiles = jp_load_profiles();
    if ($id === '') {
        $id = 'JP-' . strtoupper(bin2hex(random_bytes(4)));
    }
    $row = [
        'id' => $id,
        'name' => $name,
        'gender' => $gender,
        'dob' => $dob,
        'tob' => $tob,
        'place' => $place !== '' ? $place : 'Delhi',
        'lat' => $lat,
        'lng' => $lng,
        'gotra' => $gotra,
        'team_slug' => $teamSlug,
        'updated_at' => date('c'),
    ];
    $found = false;
    foreach ($profiles as $i => $p) {
        if ((string)($p['id'] ?? '') === $id) {
            $profiles[$i] = array_merge($p, $row);
            $found = true;
            break;
        }
    }
    if (!$found) {
        // de-dupe by team_slug
        if ($teamSlug !== '') {
            foreach ($profiles as $i => $p) {
                if ((string)($p['team_slug'] ?? '') === $teamSlug) {
                    $profiles[$i] = array_merge($p, $row, ['id' => (string)$p['id']]);
                    $found = true;
                    $id = (string)$p['id'];
                    break;
                }
            }
        }
        if (!$found) {
            $profiles[] = $row;
        }
    }
    if (!jp_save_profiles($profiles)) {
        return ['status' => 'error', 'message' => 'Could not write profiles.json'];
    }
    return ['status' => 'ok', 'id' => $id, 'profile' => $row, 'updated' => $found];
}

// ── API ────────────────────────────────────────────────────────────────────
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
if ($action === 'api_save_profile' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    jp_require_manage('save profiles');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(jp_save_profile($_POST), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($action === 'api_import_team' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    jp_require_manage('import from Team');
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $ids = $body['slugs'] ?? $body['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $team = jp_load_team();
    $byKey = [];
    foreach ($team as $m) {
        $slug = (string)($m['slug'] ?? $m['id'] ?? '');
        if ($slug !== '') {
            $byKey[$slug] = $m;
        }
        $id = (string)($m['id'] ?? '');
        if ($id !== '') {
            $byKey[$id] = $m;
        }
    }
    $imported = 0;
    $created = 0;
    $updated = 0;
    $errors = [];
    foreach ($ids as $key) {
        $key = (string)$key;
        if ($key === '' || !isset($byKey[$key])) {
            $errors[] = $key . ' not found';
            continue;
        }
        $m = $byKey[$key];
        $dobRaw = (string)($m['dob'] ?? $m['birthday'] ?? $m['birth_date'] ?? '');
        $dob = jp_normalize_dob($dobRaw);
        if ($dob === '') {
            $errors[] = ($m['name'] ?? $key) . ' — missing or invalid DOB';
            continue;
        }
        $gotra = trim((string)($m['gotra'] ?? ''));
        $tobRaw = trim((string)($m['time_of_birth'] ?? $m['tob'] ?? $m['birth_time'] ?? ''));
        $placeRaw = trim((string)($m['place_of_birth'] ?? $m['birth_place'] ?? $m['pob'] ?? ''));
        if ($gotra === '' || strcasecmp($gotra, 'Pending') === 0) {
            $errors[] = ($m['name'] ?? $key) . ' — missing Gotra';
            continue;
        }
        if ($tobRaw === '' || !preg_match('/^\d{1,2}:\d{2}/', $tobRaw)) {
            $errors[] = ($m['name'] ?? $key) . ' — missing or invalid Time of Birth';
            continue;
        }
        if ($placeRaw === '') {
            $errors[] = ($m['name'] ?? $key) . ' — missing Place of Birth';
            continue;
        }
        $gender = strtolower(trim((string)($m['gender'] ?? 'male')));
        if (!in_array($gender, ['male', 'female'], true)) {
            $gender = 'male';
        }
        $latRaw = $m['birth_lat'] ?? $m['lat'] ?? null;
        $lngRaw = $m['birth_lng'] ?? $m['lng'] ?? null;
        $lat = (is_numeric($latRaw) ? round((float)$latRaw, 4) : 28.6139);
        $lng = (is_numeric($lngRaw) ? round((float)$lngRaw, 4) : 77.2090);
        $tob = jp_normalize_tob($tobRaw);
        $place = $placeRaw;
        $teamSlug = (string)($m['slug'] ?? $m['id'] ?? '');
        $photo = '';
        foreach (['photo', 'image', 'avatar', 'img', 'photo_file'] as $_pk) {
            if (!empty($m[$_pk]) && is_string($m[$_pk])) {
                $photo = trim($m[$_pk]);
                break;
            }
        }
        $res = jp_save_profile([
            'name' => (string)($m['name'] ?? 'Member'),
            'gender' => $gender,
            'dob' => $dob,
            'tob' => $tob,
            'place' => $place,
            'gotra' => $gotra,
            'team_slug' => $teamSlug,
            'lat' => $lat,
            'lng' => $lng,
            'photo' => $photo,
        ]);
        if (($res['status'] ?? '') === 'ok') {
            $imported++;
            if (!empty($res['updated'])) {
                $updated++;
            } else {
                $created++;
            }
        } else {
            $errors[] = ($m['name'] ?? $key) . ' — ' . ($res['message'] ?? 'fail');
        }
    }
    echo json_encode([
        'status' => 'ok',
        'imported' => $imported,
        'created' => $created,
        'updated' => $updated,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'api_delete_profiles' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    jp_require_manage('delete profiles');
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $ids = $body['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $ids = array_values(array_filter(array_map('strval', $ids), static fn($id) => $id !== ''));
    if ($ids === []) {
        echo json_encode(['status' => 'error', 'message' => 'No profiles selected.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $profiles = jp_load_profiles();
    $idSet = array_fill_keys($ids, true);
    $before = count($profiles);
    $profiles = array_values(array_filter(
        $profiles,
        static fn($p) => !isset($idSet[(string)($p['id'] ?? '')])
    ));
    $deleted = $before - count($profiles);
    if ($deleted < 1) {
        echo json_encode(['status' => 'error', 'message' => 'No matching profiles found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!jp_save_profiles($profiles)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not write profiles.json'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['status' => 'ok', 'deleted' => $deleted, 'remaining' => count($profiles)], JSON_UNESCAPED_UNICODE);
    exit;
}


$profiles = jp_load_profiles();
$view = (string)($_GET['view'] ?? 'home');

// Public / visitor: only single-chart patri (or query-driven chart) — no directory / import
if (!jp_can_manage()) {
    $pubOk = in_array($view, ['patri', 'home'], true); // home only if forced into single chart below
    if (in_array($view, ['import', 'new', 'edit', 'milan', 'history', 'match', 'logs'], true)) {
        jp_require_manage('use ' . $view);
    }
    if ($view === 'home' && !isset($_GET['name']) && !isset($_GET['dob']) && !isset($_GET['slug']) && !isset($_GET['id'])) {
        jp_require_manage('open the profile directory');
    }
}

// Live preview: query params can open patri without saving first
if ($view === 'home' && (isset($_GET['name']) || isset($_GET['dob']) || isset($_GET['slug']))) {
    if (isset($_GET['preview']) || (string)($_GET['nosave'] ?? '') === '1' || !isset($_GET['edit'])) {
        $view = 'patri';
    } else {
        $view = 'new';
    }
}
$pid = (string)($_GET['id'] ?? '');
$mid = (string)($_GET['boy'] ?? '');
$fid = (string)($_GET['girl'] ?? '');

// ── Views ──────────────────────────────────────────────────────────────────
if ($view === 'new' || $view === 'edit') {
    $edit = ($view === 'edit' && $pid !== '') ? jp_find($profiles, $pid) : null;
    $qName = trim((string)($_GET['name'] ?? ''));
    $qDob = jp_normalize_dob(trim((string)($_GET['dob'] ?? '')));
    $qTob = trim((string)($_GET['tob'] ?? ''));
    $qPob = trim((string)($_GET['pob'] ?? $_GET['place'] ?? ''));
    $qGotra = trim((string)($_GET['gotra'] ?? ''));
    $qSlug = trim((string)($_GET['slug'] ?? $_GET['team_slug'] ?? ''));
    $qGender = strtolower(trim((string)($_GET['gender'] ?? '')));
    // Prefer existing janam profile by team_slug or name+dob
    if ($edit === null && $qSlug !== '') {
        foreach ($profiles as $_p) {
            if ((string)($_p['team_slug'] ?? '') === $qSlug || (string)($_p['id'] ?? '') === $qSlug) {
                $edit = $_p;
                break;
            }
        }
    }
    if ($edit === null && $qName !== '' && $qDob !== '') {
        foreach ($profiles as $_p) {
            $pn = jp_name_key((string)($_p['name'] ?? ''));
            $pd = jp_normalize_dob((string)($_p['dob'] ?? ''));
            if ($pn === jp_name_key($qName) && $pd === $qDob) {
                $edit = $_p;
                break;
            }
        }
    }
    // Hydrate from Human Capital Index (team.json) when still a blank draft
    if ($edit === null || (empty($edit['id']) && (empty($edit['gotra']) || empty($edit['tob']) || empty($edit['gender'])))) {
        $tm = jp_match_team_member($qSlug !== '' ? $qSlug : (string)($edit['team_slug'] ?? ''), $qName !== '' ? $qName : (string)($edit['name'] ?? ''), $qDob !== '' ? $qDob : (string)($edit['dob'] ?? ''));
        if (is_array($tm)) {
            $fromTeam = jp_profile_from_team($tm);
            if ($edit === null) {
                $edit = $fromTeam;
            } else {
                foreach (['name', 'dob', 'tob', 'place', 'gender', 'gotra', 'lat', 'lng', 'geo', 'team_slug'] as $k) {
                    $cur = trim((string)($edit[$k] ?? ''));
                    if ($cur === '' || $cur === '12:00' || $cur === 'Delhi' || $cur === '28.6139, 77.2090') {
                        if (($fromTeam[$k] ?? '') !== '') {
                            $edit[$k] = $fromTeam[$k];
                        }
                    }
                }
                // Always prefer explicit team gender/tob/gotra when query omitted them
                if ($qGender === '' && !empty($fromTeam['gender'])) {
                    $edit['gender'] = $fromTeam['gender'];
                }
                if ($qTob === '' && !empty($fromTeam['tob'])) {
                    $edit['tob'] = $fromTeam['tob'];
                }
                if ($qGotra === '' && !empty($fromTeam['gotra'])) {
                    $edit['gotra'] = $fromTeam['gotra'];
                }
                if ($qPob === '' && !empty($fromTeam['place'])) {
                    $edit['place'] = $fromTeam['place'];
                }
            }
        }
    }
    if ($edit === null) {
        $edit = [
            'name' => $qName,
            'dob' => $qDob,
            'tob' => $qTob !== '' ? $qTob : '12:00',
            'place' => $qPob !== '' ? $qPob : 'Delhi',
            'gender' => in_array($qGender, ['male', 'female'], true) ? $qGender : 'male',
            'gotra' => $qGotra,
            'team_slug' => $qSlug,
        ];
    } else {
        // Query string overrides when explicitly provided
        if ($qName !== '') {
            $edit['name'] = $qName;
        }
        if ($qDob !== '') {
            $edit['dob'] = $qDob;
        }
        if ($qTob !== '') {
            $edit['tob'] = $qTob;
        }
        if ($qPob !== '') {
            $edit['place'] = $qPob;
        }
        if ($qGotra !== '') {
            $edit['gotra'] = $qGotra;
        }
        if (in_array($qGender, ['male', 'female'], true)) {
            $edit['gender'] = $qGender;
        }
        if ($qSlug !== '') {
            $edit['team_slug'] = $qSlug;
        }
    }
    // Normalize dob for date input
    if (!empty($edit['dob'])) {
        $edit['dob'] = jp_normalize_dob((string)$edit['dob']);
    }
    if (!empty($edit['tob'])) {
        $edit['tob'] = jp_normalize_tob((string)$edit['tob']);
    }
    jp_header(($edit && !empty($edit['id'])) ? jp_t(['Edit Profile', 'प्रोफ़ाइल संपादन']) : jp_t(['New Profile', 'नई प्रोफ़ाइल']));
    $gotraSuggest = [];
    $placeSuggest = [];
    foreach ($profiles as $_sp) {
        $g = trim((string)($_sp['gotra'] ?? ''));
        $pl = trim((string)($_sp['place'] ?? ''));
        if ($g !== '' && $g !== 'Pending') {
            $gotraSuggest[$g] = true;
        }
        if ($pl !== '') {
            $placeSuggest[$pl] = true;
        }
    }
    // Common Delhi Baniya gotras as baseline suggestions
    foreach (['Garg', 'Goyal', 'Gupta', 'Bansal', 'Mittal', 'Singhal', 'Kansal', 'Bindal', 'Jindal', 'Tayal', 'Mangal', 'Airan', 'Tibrewal', 'Kashyap', 'Bharadwaj', 'Agarwal', 'Aggarwal'] as $g0) {
        $gotraSuggest[$g0] = true;
    }
    ksort($gotraSuggest, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($placeSuggest, SORT_NATURAL | SORT_FLAG_CASE);
    echo '<div class="card"><h2>&#10024; ' . (($edit && !empty($edit['id'])) ? 'Edit profile' : 'New janam profile') . '</h2>';
    echo '<p class="meta">Gotra is mandatory for Delhi Baniya match validation (exogamy). Fields support browser auto-suggest.</p>';
    echo '<form method="post" id="pf" class="no-print" onsubmit="return jpSave(event)" autocomplete="on">';
    echo '<datalist id="jp-gotra-suggestions">';
    foreach (array_keys($gotraSuggest) as $gs) {
        echo '<option value="' . jp_h($gs) . '">';
    }
    echo '</datalist>';
    echo '<datalist id="jp-place-suggestions">';
    foreach (array_keys($placeSuggest) as $ps) {
        echo '<option value="' . jp_h($ps) . '">';
    }
    foreach (['Delhi', 'New Delhi', 'Noida', 'Gurugram', 'Faridabad', 'Ghaziabad', 'Mumbai', 'Jaipur', 'Chandigarh'] as $ps0) {
        if (!isset($placeSuggest[$ps0])) {
            echo '<option value="' . jp_h($ps0) . '">';
        }
    }
    echo '</datalist>';
    echo '<input type="hidden" name="id" value="' . jp_h((string)($edit['id'] ?? '')) . '">';
    echo '<div class="grid2">';
    $fields = [
        ['name', 'Full name', 'text', (string)($edit['name'] ?? ''), 'Rohan Gupta'],
        ['gender', jp_t(['Gender','लिंग']), 'select', (string)($edit['gender'] ?? 'male'), ''],
        ['dob', 'Date of birth', 'date', (string)($edit['dob'] ?? ''), ''],
        ['tob', 'Time of birth (IST)', 'time', (string)($edit['tob'] ?? '12:00'), ''],
        ['place', 'Birth place', 'text', (string)($edit['place'] ?? 'Delhi'), 'Delhi'],
        ['gotra', jp_t(['Gotra (mandatory)','गोत्र (अनिवार्य)']), 'text', (string)($edit['gotra'] ?? ''), 'Garg / Goyal / Kashyap'],
        ['geo', 'Coordinates (lat, lng)', 'text', (
            isset($edit['lat'], $edit['lng']) && $edit['lat'] !== '' && $edit['lng'] !== ''
                ? ((string)$edit['lat'] . ', ' . (string)$edit['lng'])
                : (string)($edit['geo'] ?? '28.6139, 77.2090')
        ), '28.678668388184963, 77.30204886212086'],
        ['team_slug', jp_t(['Team slug (link)','टीम स्लग']), 'text', (string)($edit['team_slug'] ?? ''), ''],
    ];
    foreach ($fields as [$id, $lab, $type, $val, $ph]) {
        echo '<div><label for="' . $id . '">' . jp_h($lab) . '</label>';
        if ($type === 'select') {
            echo '<select id="' . $id . '" name="' . $id . '" required>';
            echo '<option value="male"' . ($val === 'male' ? ' selected' : '') . '>Male</option>';
            echo '<option value="female"' . ($val === 'female' ? ' selected' : '') . '>Female</option></select>';
        } else {
            $step = $type === 'number' ? ' step="0.0001"' : '';
            $req = in_array($id, ['name', 'dob', 'gotra', 'tob'], true) ? ' required' : '';
            $listAttr = '';
            if ($id === 'gotra') {
                $listAttr = ' list="jp-gotra-suggestions" autocomplete="on"';
            } elseif ($id === 'place') {
                $listAttr = ' list="jp-place-suggestions" autocomplete="on"';
            } elseif ($id === 'name') {
                $listAttr = ' autocomplete="name"';
            } elseif ($type === 'date') {
                $listAttr = ' autocomplete="bday"';
            } else {
                $listAttr = ' autocomplete="on"';
            }
            echo '<input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . jp_h($val) . '" placeholder="' . jp_h($ph) . '"' . $step . $req . $listAttr . '>';
        }
        echo '</div>';
    }
    echo '</div><p style="margin-top:1rem"><button type="submit">' . jp_h(jp_t(['Save profile', 'प्रोफ़ाइल सहेजें'])) . '</button> ';
    echo '<a class="btn secondary" href="?view=home">Cancel</a></p></form></div>';
    echo '<script>
    async function jpSave(e){
      e.preventDefault();
      const fd=new FormData(document.getElementById("pf"));
      fd.append("action","api_save_profile");
      const r=await fetch("janam_patri.php",{method:"POST",body:fd});
      const j=await r.json();
      if(j.status==="ok"){location.href="?view=patri&id="+encodeURIComponent(j.id);}
      else{alert(j.message||"Save failed");}
      return false;
    }
    </script>';
    jp_footer();
    exit;
}

if ($view === 'import') {
    jp_header(jp_t(['Import from Team', 'टीम से आयात']));
    $team = jp_load_team();
    $existingSlugs = [];
    foreach ($profiles as $p) {
        $ts = (string)($p['team_slug'] ?? '');
        if ($ts !== '') {
            $existingSlugs[$ts] = true;
        }
        $tid = (string)($p['team_slug'] ?? $p['id'] ?? '');
        if ($tid !== '') {
            $existingSlugs[$tid] = true;
        }
    }
    $fmtDob = static function (string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        try {
            $dt = new DateTimeImmutable($raw, new DateTimeZone('Asia/Kolkata'));
            return $dt->format('d-m-Y');
        } catch (Throwable $e) {
            return $raw;
        }
    };
    $readyCount = 0;
    $updateCount = 0;
    foreach ($team as $m) {
        $slug = (string)($m['slug'] ?? $m['id'] ?? '');
        $dob = (string)($m['dob'] ?? $m['birthday'] ?? $m['birth_date'] ?? '');
        $already = $slug !== '' && isset($existingSlugs[$slug]);
        if ($dob !== '' && $slug !== '') {
            $readyCount++;
            if ($already) {
                $updateCount++;
            }
        }
    }
    $tenantLabel = defined('TENANT_ID') ? TENANT_ID : 'default';
    $dataHint = defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data');
    echo '<div class="card">';
    echo '<h2>&#128229; One-step import from Human Capital Index</h2>';
    echo '<p class="meta">Select members who have Date of Birth on file. Name, DOB, time of birth (TOB), place of birth, gender and gotra are copied into this tenant’s Janam store. Gotra defaults to “Pending” if empty — edit after import.</p>';
    echo '<p class="meta" style="margin-top:0.35rem">Tenant: <strong>' . jp_h((string)$tenantLabel) . '</strong> · With DOB: <strong>' . (int)$readyCount . '</strong> · Already in Janam (updatable): <strong>' . (int)$updateCount . '</strong></p>';
    echo '<p class="meta">Re-importing a member who is already linked updates their Janam profile (name, DOB, TOB, place, gotra, coordinates).</p>';
    if (!$team) {
        echo '<p class="meta">No team records found in team.json for this tenant.</p>';
    } else {
        echo '<p class="jp-import-hint no-print">Mandatory for import: <strong>Date of birth</strong>, <strong>Time of birth</strong>, <strong>Place of birth</strong>, and <strong>Gotra</strong>. Incomplete rows are greyed out and cannot be selected — complete them under Human Capital Index (Team) first.</p>';
        echo '<div class="jp-import-toolbar no-print" role="toolbar" aria-label="Import actions">';
        echo '<button type="button" class="jp-btn jp-btn-ghost" id="jpSelectAll" title="Select all members ready to import">Select All</button>';
        echo '<button type="button" class="jp-btn jp-btn-ghost" id="jpUnselectAll" title="Clear selection">Unselect All</button>';
        echo '<button type="button" class="jp-btn jp-btn-primary" id="jpImportBtn" title="Provision selected members as Janam profiles">Import Selected</button>';
        echo '<span class="jp-import-count meta" id="jpSelCount">0 selected</span>';
        echo '</div>';
        echo '<form id="imp" class="no-print"><table><thead><tr><th></th><th>Name</th><th>DOB</th><th>TOB</th><th>Place</th><th>Gotra</th><th>Status</th></tr></thead><tbody>';
        foreach ($team as $m) {
            $slug = (string)($m['slug'] ?? $m['id'] ?? '');
            $dobRaw = trim((string)($m['dob'] ?? $m['birthday'] ?? $m['birth_date'] ?? ''));
            $dob = $fmtDob($dobRaw);
            $tob = trim((string)($m['time_of_birth'] ?? $m['tob'] ?? $m['birth_time'] ?? ''));
            $pob = trim((string)($m['place_of_birth'] ?? $m['birth_place'] ?? $m['pob'] ?? ''));
            $gotra = trim((string)($m['gotra'] ?? ''));
            // Mandatory for Janam import: DOB, TOB, place of birth, gotra (+ team slug)
            $missing = [];
            if ($dobRaw === '' || jp_normalize_dob($dobRaw) === '') {
                $missing[] = 'DOB';
            }
            if ($tob === '' || !preg_match('/^\d{1,2}:\d{2}/', $tob)) {
                $missing[] = 'TOB';
            }
            if ($pob === '') {
                $missing[] = 'Place';
            }
            if ($gotra === '' || strcasecmp($gotra, 'Pending') === 0) {
                $missing[] = 'Gotra';
            }
            if ($slug === '') {
                $missing[] = 'Slug';
            }
            $already = $slug !== '' && isset($existingSlugs[$slug]);
            $can = $missing === [];
            $rowClass = $can ? 'jp-row-ready' : 'jp-row-incomplete';
            if ($already) {
                $rowClass .= ' jp-row-existing';
            }
            echo '<tr class="' . $rowClass . '"' . ($already ? ' data-jp-existing="1"' : '') . ' title="' . jp_h($can ? 'Ready to import' : ('Missing: ' . implode(', ', $missing))) . '">';
            echo '<td><input type="checkbox" class="jp-slug" name="slugs" value="' . jp_h($slug) . '"' . ($can ? '' : ' disabled') . ($already ? ' data-existing="1"' : '') . ' aria-label="' . jp_h((string)($m['name'] ?? 'member')) . ($can ? '' : ' — incomplete') . '"></td>';
            echo '<td><strong>' . jp_h((string)($m['name'] ?? '')) . '</strong></td>';
            echo '<td class="' . ($dobRaw === '' ? 'jp-miss' : '') . '">' . jp_h($dob !== '' ? $dob : '—') . '</td>';
            echo '<td class="' . ($tob === '' ? 'jp-miss' : '') . '">' . jp_h($tob !== '' ? $tob : '—') . '</td>';
            echo '<td class="' . ($pob === '' ? 'jp-miss' : '') . '">' . jp_h($pob !== '' ? $pob : '—') . '</td>';
            echo '<td class="' . (($gotra === '' || strcasecmp($gotra, 'Pending') === 0) ? 'jp-miss' : '') . '">' . jp_h($gotra !== '' ? $gotra : '—') . '</td>';
            if ($can) {
                $status = $already ? 'In Janam · select to update' : 'New · ready';
            } else {
                $status = 'Incomplete · needs ' . implode(', ', $missing);
            }
            echo '<td class="meta">' . jp_h($status) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></form>';
        echo <<<'CSS'
<style>
.jp-import-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;margin:1rem 0 0.75rem;padding:0.65rem 0.85rem;border-radius:12px;background:rgba(255,255,255,0.55);border:1px solid rgba(148,163,184,0.35);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);box-shadow:0 4px 16px rgba(15,23,42,0.06)}
.jp-row-incomplete{opacity:0.45;background:#f1f5f9;color:#64748b;filter:grayscale(0.35)}
.jp-row-incomplete td{color:#64748b!important}
.jp-row-incomplete .jp-miss{color:#9f1239!important;font-weight:700;opacity:1}
.jp-row-incomplete input[type=checkbox]:disabled{cursor:not-allowed}
.jp-row-ready{background:rgba(240,253,250,0.5)}
.jp-row-existing.jp-row-ready{background:rgba(239,246,255,0.65)}
.jp-import-hint{font-size:0.8rem;color:#475569;margin:0.5rem 0 0}
.jp-btn{appearance:none;border-radius:10px;padding:0.45rem 0.9rem;font-size:0.8125rem;font-weight:700;cursor:pointer;border:1px solid transparent;transition:background .15s,border-color .15s,transform .1s}
.jp-btn:active{transform:scale(0.98)}
.jp-btn-ghost{background:rgba(255,255,255,0.85);border-color:#e2e8f0;color:#334155}
.jp-btn-ghost:hover{border-color:#94a3b8;background:#fff}
.jp-btn-primary{background:linear-gradient(135deg,#4f46e5,#2563eb);color:#fff;border-color:transparent;box-shadow:0 2px 8px rgba(37,99,235,0.35)}
.jp-btn-primary:hover{filter:brightness(1.05)}
.jp-btn-primary:disabled{opacity:0.5;cursor:not-allowed;filter:none}
.jp-import-count{margin-left:auto;font-weight:600}
.jp-row-ready td{background:rgba(238,242,255,0.35)}
@media print{.jp-import-toolbar{display:none!important}}
.jp-photo-sq{print-color-adjust:exact;-webkit-print-color-adjust:exact}
</style>
CSS;
        echo <<<'JS'
<script>
(function(){
  function boxes(){return [...document.querySelectorAll("input.jp-slug:not(:disabled)")];}
  function checked(){return boxes().filter(function(x){return x.checked;});}
  function updateCount(){
    var n=checked().length;
    var el=document.getElementById("jpSelCount");
    if(el) el.textContent=n+" selected";
    var btn=document.getElementById("jpImportBtn");
    if(btn) btn.disabled=n===0;
  }
  document.getElementById("jpSelectAll").addEventListener("click",function(){
    boxes().forEach(function(x){x.checked=true;});
    updateCount();
  });
  document.getElementById("jpUnselectAll").addEventListener("click",function(){
    boxes().forEach(function(x){x.checked=false;});
    updateCount();
  });
  document.getElementById("imp").addEventListener("change",updateCount);
  document.getElementById("jpImportBtn").addEventListener("click",async function(){
    var list=checked().map(function(x){return x.value;});
    if(!list.length){alert("Select at least one member with DOB");return;}
    var btn=document.getElementById("jpImportBtn");
    btn.disabled=true;
    btn.textContent="Importing…";
    try{
      var r=await fetch("janam_patri.php?action=api_import_team",{
        method:"POST",
        credentials:"same-origin",
        headers:{"Content-Type":"application/json","Accept":"application/json"},
        body:JSON.stringify({slugs:list})
      });
      var j=await r.json();
      var msg="Imported: "+(j.imported||0);
      if(j.errors&&j.errors.length) msg+="\n"+j.errors.join("\n");
      alert(msg);
      if(j.imported) location.reload();
      else { btn.disabled=false; btn.textContent="Import Selected"; updateCount(); }
    }catch(e){
      alert("Import failed: "+(e.message||e));
      btn.disabled=false;
      btn.textContent="Import Selected";
      updateCount();
    }
  });
  updateCount();
})();
</script>
JS;
    }
    echo '</div>';
    jp_footer();
    exit;
}

if ($view === 'history') {
    jp_header(jp_t(['Match history', 'मिलान इतिहास']));
    $logs = array_reverse(jp_load_match_logs());
    echo '<div class="card"><h2>&#128220; Match history audit</h2>';
    echo '<p class="meta">From <code>data/janam/match_logs.json</code> — reload any past comparison.</p>';
    if (!$logs) {
        echo '<p class="meta">No matches logged yet.</p>';
    } else {
        echo '<table><thead><tr><th>When</th><th>Boy</th><th>Girl</th><th>Score</th><th>Gotra</th><th>Nadi</th><th>Verdict</th><th class="no-print"></th></tr></thead><tbody>';
        foreach ($logs as $L) {
            if (!is_array($L)) {
                continue;
            }
            $boyId = (string)($L['boy_id'] ?? '');
            $girlId = (string)($L['girl_id'] ?? '');
            $href = '?view=match&boy=' . rawurlencode($boyId) . '&girl=' . rawurlencode($girlId);
            echo '<tr>';
            echo '<td class="meta">' . jp_h((string)($L['at'] ?? '')) . '</td>';
            echo '<td>' . jp_h((string)($L['boy'] ?? '')) . '</td>';
            echo '<td>' . jp_h((string)($L['girl'] ?? '')) . '</td>';
            echo '<td><strong>' . jp_h((string)($L['total'] ?? '')) . '</strong>/36</td>';
            echo '<td>' . (!empty($L['gotra_fail']) ? '<span class="badge bad">Same</span>' : '<span class="badge ok">OK</span>') . '</td>';
            echo '<td>' . (!empty($L['nadi_dosha']) ? '<span class="badge warn">Dosha</span>' : '—') . '</td>';
            echo '<td class="meta">' . jp_h((string)($L['verdict'] ?? '')) . '</td>';
            echo '<td class="no-print"><a href="' . jp_h($href) . '">Reload</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
    jp_footer();
    exit;
}

if ($view === 'patri') {
    $p = ($pid !== '') ? jp_find($profiles, $pid) : null;
    if (!$p) {
        $p = jp_ephemeral_profile();
    }
    if (!$p) {
        jp_header(jp_t(['Not found', 'प्रोफ़ाइल नहीं मिली']));
        echo '<div class="card"><p>' . jp_h(jp_t(['Profile not found. Open with a saved id or name + DOB query parameters.', 'प्रोफ़ाइल नहीं मिली। सहेजा हुआ id या नाम + जन्म तिथि से खोलें।'])) . '</p>';
        echo '<p class="no-print"><a class="btn" href="?view=new">' . jp_h(jp_t(['New profile', 'नई प्रोफ़ाइल'])) . '</a></p></div>';
        jp_footer();
        exit;
    }
    $chart = jp_build_chart($p);
    $photoUrl = jp_resolve_photo($p);
    $lagnaIdx = (int)($chart['lagna'] ?? 0);
    $moonIdx = (int)($chart['moon_rashi'] ?? 0);
    [$lagnaWest, $lagnaGlyph] = jp_zodiac($lagnaIdx);
    [$moonWest, $moonGlyph] = jp_zodiac($moonIdx);
    $lagnaRashi = jp_rashi_loc($lagnaIdx);
    $moonRashi = jp_rashi_loc($moonIdx);
    $_seoBase = class_exists('SeoShare') ? SeoShare::baseUrl() : '';
    $_seoUrl = $_seoBase . '/janam_patri.php?view=patri&id=' . rawurlencode((string)($p['id'] ?? ''));
    if (!empty($_GET['slug'])) {
        $_seoUrl .= '&slug=' . rawurlencode((string)$_GET['slug']);
    }
    $_seoImg = '';
    $_photo = trim((string)($p['photo'] ?? ''));
    if ($_photo !== '') {
        $_seoImg = (str_starts_with($_photo, 'http') ? $_photo : ($_seoBase . '/images/' . rawurlencode(basename($_photo))));
    } else {
        $_seoImg = rtrim((string)$_seoBase, '/') . '/tools/og_card.php?' . http_build_query([
            'name' => (string)($p['name'] ?? 'Janam Patri'),
            'role' => 'Janam Patri · Kundli',
            'company' => 'Resource Centre',
            'kind' => jp_is_hi() ? 'जन्म पत्री' : 'Janam Patri',
        ]);
    }
    jp_header(jp_t(['Janam Patri — ', 'जन्म पत्री — ']) . (string)$p['name'], [
        'url' => $_seoUrl,
        'image' => $_seoImg,
        'description' => (string)($p['name'] ?? '') . ' — North Indian Janam Patri (Lagna, Navamsha, dasha). Indicative only.',
        'index' => true,
        'type' => 'profile',
    ]);
    $patriTab = strtolower(trim((string)($_GET['tab'] ?? 'kundli')));
    if (!in_array($patriTab, ['kundli', 'dasha', 'sade', 'gochar', 'varga', 'strength', 'doshas'], true)) {
        $patriTab = 'kundli';
    }
    $chromeCtx = match ($patriTab) {
        'dasha' => 'dasha',
        'doshas' => 'doshas',
        'sade' => 'patri',
        'varga' => 'patri',
        default => 'patri',
    };
    jp_report_chrome($chromeCtx, 'Print / PDF', 'प्रिंट / PDF');
    // jp_print_action_bar removed — chrome already has Print
    jp_patri_subtabs((string)($p['id'] ?? $pid), $patriTab);

    if ($patriTab === 'dasha') {
        $dashaPack = jp_vimshottari_full($chart, (string)($p['dob'] ?? ''));
        echo '<div class="card" style="margin-bottom:0.75rem"><h2 style="margin:0">' . jp_h((string)$p['name']) . '</h2>';
        echo '<p class="meta" style="margin:0.25rem 0 0">' . jp_h(jp_t(['Vimshottari from Moon nakshatra', 'चंद्र नक्षत्र से विंशोत्तरी'])) . ' · DOB ' . jp_h((string)$p['dob']) . '</p></div>';
        jp_render_dasha_now($dashaPack);
        // Sade Sati companion on Dasha tab (compact)
        jp_render_sade_sati($chart);
        jp_print_footer_block();
        jp_footer();
        exit;
    }
    if ($patriTab === 'sade') {
        echo '<div class="card" style="margin-bottom:0.75rem"><h2 style="margin:0">' . jp_h((string)$p['name']) . '</h2>';
        echo '<p class="meta" style="margin:0.25rem 0 0">' . jp_h(jp_t(['Saturn–Moon Sade Sati window', 'शनि–चंद्र साढ़े साती'])) . '</p></div>';
        jp_render_sade_sati($chart);
        jp_print_footer_block();
        jp_footer();
        exit;
    }
    if ($patriTab === 'gochar') {
        echo '<div class="card" style="margin-bottom:0.75rem"><h2 style="margin:0">' . jp_h((string)$p['name']) . '</h2>';
        echo '<p class="meta" style="margin:0.25rem 0 0">' . jp_h(jp_t(['Daily gochar snapshot', 'दैनिक गोचर स्नैपशॉट'])) . '</p></div>';
        jp_render_gochar($chart);
        jp_print_footer_block();
        jp_footer();
        exit;
    }
    if ($patriTab === 'strength') {
        echo '<div class="card" style="margin-bottom:0.75rem"><h2 style="margin:0">' . jp_h((string)$p['name']) . '</h2>';
        echo '<p class="meta" style="margin:0.25rem 0 0">' . jp_h(jp_t(['Indicative house strength', 'संकेतात्मक भाव बल'])) . '</p></div>';
        jp_render_ashtakavarga($chart);
        jp_print_footer_block();
        jp_footer();
        exit;
    }
    if ($patriTab === 'varga') {
        echo '<div class="card" style="margin-bottom:0.75rem"><h2 style="margin:0">' . jp_h((string)$p['name']) . '</h2>';
        echo '<p class="meta" style="margin:0.25rem 0 0">' . jp_h(jp_t(['Divisional charts', 'वर्ग कुंडलियाँ'])) . '</p></div>';
        jp_render_varga_switcher($chart, $p, (string)($p['id'] ?? $pid));
        jp_print_footer_block();
        jp_footer();
        exit;
    }
    if ($patriTab === 'doshas') {
        echo '<div class="card" style="margin-bottom:0.75rem"><h2 style="margin:0">' . jp_h((string)$p['name']) . '</h2>';
        echo '<p class="meta" style="margin:0.25rem 0 0">' . jp_h(jp_t(['Individual dosha review', 'व्यक्तिगत दोष समीक्षा'])) . '</p></div>';
        jp_render_doshas($chart);
        jp_print_footer_block();
        jp_footer();
        exit;
    }

    echo '<div class="card">';

    echo '<div class="jp-patri-hero" style="display:flex;flex-wrap:wrap;gap:1.25rem;align-items:flex-start;margin-bottom:0.75rem">';
    if ($photoUrl !== '') {
        echo '<div class="jp-photo-sq" style="width:7.5rem;height:7.5rem;border-radius:12px;overflow:hidden;border:2px solid #94a3b8;background:#e2e8f0;flex-shrink:0;box-shadow:0 4px 14px rgba(15,23,42,0.12)">';
        echo '<img src="' . jp_h($photoUrl) . '" alt="' . jp_h((string)$p['name']) . '" width="120" height="120" style="width:100%;height:100%;object-fit:cover;object-position:50% 25%;display:block" loading="lazy">';
        echo '</div>';
    }
    echo '<div style="flex:1;min-width:12rem">';
    echo '<h2 style="margin:0 0 0.5rem">' . jp_h((string)$p['name']) . '</h2>';
    // Rashi (bold) + Zodiac with icons
    echo '<div class="jp-rashi-block" style="display:flex;flex-direction:column;gap:0.4rem;margin:0.35rem 0 0.75rem">';
    echo '<div style="font-size:1.05rem;line-height:1.35"><span class="meta" style="display:inline-block;min-width:7.5rem">' . jp_h(jp_t(['Lagna rashi', 'लग्न राशि'])) . '</span> '
        . '<strong style="font-weight:800;color:#0f172a">' . jp_h($lagnaRashi) . '</strong> '
        . '<span style="font-size:1.25rem;margin:0 0.2rem" aria-hidden="true">' . $lagnaGlyph . '</span> '
        . '<span style="font-weight:700;color:#1e3a8a">' . jp_h($lagnaWest) . '</span></div>';
    echo '<div style="font-size:1.05rem;line-height:1.35"><span class="meta" style="display:inline-block;min-width:7.5rem">' . jp_h(jp_t(['Chandra rashi', 'चंद्र राशि'])) . '</span> '
        . '<strong style="font-weight:800;color:#0f172a">' . jp_h($moonRashi) . '</strong> '
        . '<span style="font-size:1.25rem;margin:0 0.2rem" aria-hidden="true">' . $moonGlyph . '</span> '
        . '<span style="font-weight:700;color:#1e3a8a">' . jp_h($moonWest) . '</span></div>';
    echo '</div>';
    $dobDisp = (string)($p['dob'] ?? '');
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dobDisp, $dm)) {
        $dobDisp = $dm[3] . '-' . $dm[2] . '-' . $dm[1];
    }
    echo '<dl class="jp-profile-meta" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));gap:0.5rem 1.25rem;margin:0.5rem 0 0;font-size:0.9rem">';
    $gRaw = (string)($p['gender'] ?? '');
    $gDisp = $gRaw;
    if (jp_is_hi()) {
        $gDisp = ['male' => 'पुरुष', 'female' => 'स्त्री'][$gRaw] ?? $gRaw;
    }
    echo '<div><dt class="meta" style="margin:0">' . jp_h(jp_t(['Gender', 'लिंग'])) . '</dt><dd style="margin:0;font-weight:600;color:#1c1917">' . jp_h($gDisp) . '</dd></div>';
    echo '<div><dt class="meta" style="margin:0">' . jp_h(jp_t(['Date of birth', 'जन्म तिथि'])) . '</dt><dd style="margin:0;font-weight:600;color:#1c1917">' . jp_h($dobDisp) . '</dd></div>';
    echo '<div><dt class="meta" style="margin:0">' . jp_h(jp_t(['Time of birth (IST)', 'जन्म समय (IST)'])) . '</dt><dd style="margin:0;font-weight:600;color:#1c1917">' . jp_h((string)($p['tob'] ?? '')) . '</dd></div>';
    echo '<div><dt class="meta" style="margin:0">' . jp_h(jp_t(['Place of birth', 'जन्म स्थान'])) . '</dt><dd style="margin:0;font-weight:600;color:#1c1917">' . jp_h((string)($p['place'] ?? '')) . '</dd></div>';
    echo '<div><dt class="meta" style="margin:0">' . jp_h(jp_t(['Gotra', 'गोत्र'])) . '</dt><dd style="margin:0;font-weight:600;color:#1c1917">' . jp_h((string)($p['gotra'] ?? '')) . '</dd></div>';
    echo '<div><dt class="meta" style="margin:0">' . jp_h(jp_t(['Latitude', 'अक्षांश'])) . '</dt><dd style="margin:0;font-weight:600;color:#1c1917">' . jp_h((string)($p['lat'] ?? '')) . '</dd></div>';
    echo '<div><dt class="meta" style="margin:0">' . jp_h(jp_t(['Longitude', 'देशांतर'])) . '</dt><dd style="margin:0;font-weight:600;color:#1c1917">' . jp_h((string)($p['lng'] ?? '')) . '</dd></div>';
    if (!empty($p['team_slug'])) {
        echo '<div><dt class="meta" style="margin:0">Team link</dt><dd style="margin:0;font-weight:600">' . jp_h((string)$p['team_slug']) . '</dd></div>';
    }
    if (!empty($p['updated_at'])) {
        echo '<div><dt class="meta" style="margin:0">Last updated</dt><dd style="margin:0;font-weight:600">' . jp_h((string)$p['updated_at']) . '</dd></div>';
    }
    echo '</dl>';
    echo '</div></div>'; // hero
    // print bar: only in report chrome (avoid duplicate)
    jp_print_masthead(jp_t(['Janam Patri', 'जन्म पत्री']) . ' — ' . (string)$p['name'], (string)($p['dob'] ?? '') . ' · ' . (string)($p['place'] ?? ''));
    echo '<div class="no-print" style="margin:.35rem 0">';
    echo '<a class="btn secondary" href="?view=edit&amp;id=' . jp_h($pid) . '">Edit</a></div>';
    echo '<div class="grid2" style="margin-top:1rem"><div>';
    echo '<p><strong>' . jp_h(jp_t(['Lagna (Rashi):', 'लग्न (राशि):'])) . '</strong> <strong>' . jp_h($lagnaRashi) . '</strong> '
        . '<span style="font-size:1.2rem" aria-hidden="true">' . $lagnaGlyph . '</span> '
        . '<strong>' . jp_h($lagnaWest) . '</strong></p>';
    echo '<p><strong>' . jp_h(jp_t(['Chandra rashi:', 'चंद्र राशि:'])) . '</strong> <strong>' . jp_h($moonRashi) . '</strong> '
        . '<span style="font-size:1.2rem" aria-hidden="true">' . $moonGlyph . '</span> '
        . '<strong>' . jp_h($moonWest) . '</strong></p>';
    echo '<p><strong>' . jp_h(jp_t(['Nakshatra:', 'नक्षत्र:'])) . '</strong> ' . jp_h(jp_nak_loc((int)($chart['moon_nakshatra'] ?? 0))) . ' · ' . jp_h(jp_t(['Pada', 'पद'])) . ' ' . (int)$chart['moon_pada'] . '</p>';
    echo '<p><strong>' . jp_h(jp_t(['Navamsha Lagna:', 'नवमांश लग्न:'])) . '</strong> ' . jp_h(jp_rashi_loc((int)($chart['nav_lagna'] ?? 0))) . '</p>';
    echo '<p><strong>' . jp_h(jp_t(['Manglik:', 'मंगलिक:'])) . '</strong> <span class="badge ' . ($chart['manglik'] ? 'warn' : 'ok') . '">' . jp_h(jp_manglik_label((bool)$chart['manglik'], (int)($chart['mars_house'] ?? 0))) . '</span></p>';
    echo '<p class="meta">' . jp_h(jp_ayanamsa_label()) . ': ' . jp_h((string)$chart['ayanamsa']) . '°</p></div><div>';
    echo '<table><thead><tr><th>' . jp_h(jp_t(['Graha','ग्रह'])) . '</th><th>' . jp_h(jp_t(['Rashi','राशि'])) . '</th><th>' . jp_h(jp_t(['Bhava','भाव'])) . '</th><th>' . jp_h(jp_t(['Nakshatra','नक्षत्र'])) . '</th><th>D-9</th></tr></thead><tbody>';
    foreach ($chart['placements'] as $pk => $pl) {
        $ri = (int)($pl['rashi'] ?? 0);
        [$zw, $zg] = jp_zodiac($ri);
        echo '<tr><td>' . jp_h(jp_planet_loc((string)$pk)) . '</td><td><strong>' . jp_h(jp_rashi_loc((int)($pl['rashi'] ?? 0))) . '</strong> '
            . '<span aria-hidden="true">' . $zg . '</span></td><td>' . jp_h(jp_bhava_loc((int)$pl['house'])) . '</td><td>' . jp_h(jp_nak_loc((int)($pl['nakshatra'] ?? 0))) . ' ' . jp_h(jp_t(['Pada','पद'])) . ' ' . (int)$pl['pada'] . '</td><td>' . jp_h(jp_rashi_loc((int)($pl['navamsha'] ?? 0))) . '</td></tr>';
    }
    echo '</tbody></table></div></div></div>';
    
    // ── Expanded Vedic blocks (indicative) ──
    $dashaPack = jp_vimshottari_full($chart, (string)($p['dob'] ?? ''));
    $dashas = $dashaPack['periods'];
    // Compact teaser → full Dasha tab
    echo '<div class="no-print" style="margin:0 0 1rem">';
    jp_render_dasha_now($dashaPack);
    echo '<p style="margin:.35rem 0 0"><a class="rc-chrome-btn" href="?view=patri&id=' . rawurlencode((string)($p['id'] ?? $pid)) . '&tab=dasha&lang=' . jp_lang() . '">' . jp_h(jp_t(['Open full Dasha timeline', 'पूर्ण दशा समय-रेखा खोलें'])) . '</a></p></div>';
    $ashta = jp_ashtakavarga_simple($chart);
    $crNotes = jp_combust_retro_notes($chart);
    $upaya = jp_remedies($p, $chart);

    echo '<div class="card"><h2>🕉️ ' . jp_h(jp_t(['Mangalarambh · Auspicious header', 'मंगलारंभ'])) . '</h2>';
    echo '<div class="jp-mangalarambh">';
    echo '<div style="font-size:1.35rem;font-weight:800">' . jp_h((string)$p['name']) . '</div>';
    echo '<p class="meta" style="margin:0.35rem 0">' . jp_h(jp_t(['Birth', 'जन्म'])) . ': ' . jp_h((string)($p['dob'] ?? '')) . ' · ' . jp_h((string)($p['tob'] ?? '')) . ' · ' . jp_h((string)($p['place'] ?? '')) . '</p>';
    echo '<div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.5rem">';
    echo '<span class="jp-badge">🌙 ' . jp_h($moonRashi) . ' / ' . jp_h($moonWest) . ' ' . $moonGlyph . '</span>';
    echo '<span class="jp-badge">♈ Lagna ' . jp_h($lagnaRashi) . ' ' . $lagnaGlyph . '</span>';
    echo '<span class="jp-badge">✨ ' . jp_h(jp_nak_loc((int)($chart['moon_nakshatra'] ?? 0))) . '</span>';
    if (!empty($p['gotra'])) {
        echo '<span class="jp-badge">🕉️ Gotra ' . jp_h((string)$p['gotra']) . '</span>';
    }
    echo '</div></div></div>';

    echo '<div class="card"><h2>🪐 ' . jp_h(jp_t(['Navagraha · positions', 'नवग्रह स्थिति'])) . '</h2>';
    echo '<table><thead><tr><th>' . jp_h(jp_t(['Graha','ग्रह'])) . '</th><th>' . jp_h(jp_t(['Rashi','राशि'])) . '</th><th>' . jp_h(jp_t(['Bhava','भाव'])) . '</th><th>Nak</th><th>D-9</th></tr></thead><tbody>';
    foreach ($chart['placements'] as $pk => $pl) {
        $ri = (int)($pl['rashi'] ?? 0);
        [$zw, $zg] = jp_zodiac($ri);
        echo '<tr><td>🪐 ' . jp_h(jp_planet_loc((string)$pk)) . '</td><td><strong>' . jp_h(jp_rashi_loc((int)($pl['rashi'] ?? 0))) . '</strong> ' . $zg . '</td><td>' . jp_h(jp_bhava_loc((int)$pl['house'])) . '</td><td>' . jp_h(jp_nak_loc((int)($pl['nakshatra'] ?? 0))) . '</td><td>' . jp_h(jp_rashi_loc((int)($pl['navamsha'] ?? 0))) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<h3 style="margin-top:1rem">♻️ ' . jp_h(jp_t(['Combustion & retrogression (indicative)', 'अस्त एवं वक्र (संकेतात्मक)'])) . '</h3><ul>';
    foreach ($crNotes as $n) {
        echo '<li><strong>' . jp_h($n['planet']) . '</strong> [' . jp_h($n['flag']) . '] — ' . jp_h($n['text']) . '</li>';
    }
    echo '</ul></div>';

    echo '<div class="card"><h2>⏳ ' . jp_h(jp_t(['Vimshottari Dasha (approx.)', 'विंशोत्तरी दशा (अनुमानित)'])) . '</h2>';
    echo '<p class="meta">' . jp_h(jp_t(['Illustrative timeline from Moon nakshatra sequence — not priest-certified dates.', 'चंद्र नक्षत्र क्रम से संकेतात्मक समयरेखा — पौरोहित्य प्रमाणित तिथियाँ नहीं।'])) . '</p>';
    foreach ($dashas as $d) {
        $cur = !empty($d['current']);
        $pct = $cur ? 55 : 100;
        echo '<div style="margin-bottom:0.65rem"><strong>' . ($cur ? '✨ ' : '') . jp_h((string)$d['name']) . '</strong> ';
        echo '<span class="meta">' . (int)$d['from'] . '–' . (int)$d['to'] . ' (' . (int)$d['years'] . 'y)</span>';
        echo '<div class="jp-dasha-bar"><i style="width:' . $pct . '%"></i></div></div>';
    }
    echo '</div>';

    echo '<div class="card"><h2>📊 ' . jp_h(jp_t(['Ashtakavarga · house scores (indicative)', 'अष्टकवर्ग · भाव अंक'])) . '</h2>';
    echo '<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:0.35rem;max-width:520px">';
    foreach ($ashta as $h => $sc) {
        $col = $sc >= 28 ? '#15803d' : ($sc >= 24 ? '#b45309' : '#b91c1c');
        echo '<div style="text-align:center;padding:0.4rem;border-radius:8px;background:#fffbeb;border:1px solid #fcd34d"><div style="font-size:0.65rem;color:#78716c">H' . (int)$h . '</div><div style="font-weight:800;color:' . $col . '">' . (int)$sc . '</div></div>';
    }
    echo '</div></div>';

    echo '<div class="card"><h2>💎 ' . jp_h(jp_t(['Upaya · remedies', 'उपाय एवं सुझाव'])) . '</h2>';
    echo '<div class="jp-upaya-grid">';
    echo '<div class="jp-upaya-card"><strong>💎 Ratna</strong><p class="meta" style="margin:0.35rem 0 0">' . jp_h((string)$upaya['ratna']) . '</p><p class="meta" style="font-size:0.75rem">' . jp_h(jp_t(['Trial under guidance; *Neelam only after proper evaluation.', 'मार्गदर्शन में परीक्षण; *नीलम उचित विचार के बाद ही।'])) . '</p></div>';
    echo '<div class="jp-upaya-card"><strong>📿 Rudraksha</strong><p class="meta" style="margin:0.35rem 0 0">' . jp_h((string)$upaya['rudraksha']) . '</p></div>';
    echo '<div class="jp-upaya-card"><strong>🕉️ Mantra</strong><p class="meta" style="margin:0.35rem 0 0">' . jp_h((string)$upaya['mantra']) . '</p></div>';
    echo '<div class="jp-upaya-card"><strong>🕊️ Daan</strong><p class="meta" style="margin:0.35rem 0 0">' . jp_h((string)$upaya['daan']) . '</p></div>';
    echo '<div class="jp-upaya-card"><strong>🎨 ' . jp_h(jp_t(['Colors','रंग'])) . '</strong><p class="meta" style="margin:0.35rem 0 0">' . jp_h((string)$upaya['color']) . '</p></div>';
    echo '</div></div>';

// Positive aspects panel
    $positives = jp_client_positives($p, $chart);
    echo '<div class="card jp-positives" style="margin-top:1rem">';
    echo '<h2 style="margin:0 0 0.65rem">🌟 ' . jp_h(jp_t(['Positive aspects & strengths', 'सकारात्मक पक्ष एवं शक्तियाँ'])) . '</h2>';
    echo '<p class="meta" style="margin:0 0 0.85rem">Constructive themes for ' . jp_h((string)$p['name']) . ' — with traditional symbols. Indicative guidance only.</p>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:0.75rem">';
    foreach ($positives as $pos) {
        echo '<div style="border:1px solid #cbd5e1;border-radius:12px;padding:0.75rem 0.9rem;background:linear-gradient(145deg,#f0fdf4,#ffffff);box-shadow:0 2px 8px rgba(15,23,42,0.05)">';
        echo '<div style="font-size:1.5rem;line-height:1;margin-bottom:0.35rem" aria-hidden="true">' . ($pos['icon'] ?? '✦') . '</div>';
        echo '<div style="font-weight:800;color:#0f172a;font-size:0.9rem;margin-bottom:0.25rem">' . jp_h((string)($pos['title'] ?? '')) . '</div>';
        echo '<div style="font-size:0.82rem;color:#334155;line-height:1.45">' . jp_h((string)($pos['text'] ?? '')) . '</div>';
        echo '</div>';
    }
    echo '</div></div>';

    echo '<div class="grid2"><div class="card"><h2>&#127761; ' . jp_h(jp_t(['Lagna Kundli (D-1)', 'लग्न कुंडली (D-1)'])) . '</h2>' . jp_diamond_ni($chart['houses_d1'], 'D-1') . '</div>';
    echo '<div class="card"><h2>&#11088; ' . jp_h(jp_t(['Navamsha (D-9)', 'नवमांश (D-9)'])) . '</h2>' . jp_diamond_ni($chart['houses_d9'], 'D-9') . '</div></div>';
    jp_footer();
    exit;
}

if ($view === 'match') {
    $boys = array_values(array_filter($profiles, static fn($p) => ($p['gender'] ?? '') === 'male'));
    $girls = array_values(array_filter($profiles, static fn($p) => ($p['gender'] ?? '') === 'female'));
    $result = null;
    $boy = null;
    $girl = null;
    if ($mid !== '' && $fid !== '') {
        $boy = jp_find($profiles, $mid);
        $girl = jp_find($profiles, $fid);
        if ($boy && $girl) {
            $result = jp_guna_milan(jp_build_chart($boy), jp_build_chart($girl), $boy, $girl);
            jp_match_log([
                'at' => date('c'),
                'boy_id' => $mid,
                'girl_id' => $fid,
                'boy' => $boy['name'],
                'girl' => $girl['name'],
                'total' => $result['total'],
                'gotra_fail' => $result['gotra_fail'],
                'nadi_dosha' => $result['nadi_dosha'],
                'manglik_cancelled' => $result['manglik_cancelled'],
                'verdict' => $result['verdict'],
            ]);
        }
    }
    jp_header(jp_t(['Kundli Milan', 'कुंडली मिलान']));
    echo '<div class="card no-print"><h2>&#128149; ' . jp_h(jp_t(['Ashtakoot Guna Milan', 'अष्टकूट गुण मिलान'])) . '</h2>';
    echo '<p class="meta">' . jp_h(jp_t(['Select one male and one female profile. Same Gotra is flagged. Mutual Manglik can cancel Kuja mismatch.','एक पुरुष व एक स्त्री प्रोफ़ाइल चुनें। समान गोत्र पर चेतावनी। पारस्परिक मंगलिक कुज दोष शांत कर सकता है।'])) . '</p>';
    echo '<form method="get"><input type="hidden" name="view" value="match"><div class="grid2">';
    echo '<div><label>' . jp_h(jp_t(['Boy / Groom', 'वर'])) . '</label><select name="boy" required><option value="">—</option>';
    foreach ($boys as $b) {
        echo '<option value="' . jp_h((string)$b['id']) . '"' . ($mid === (string)$b['id'] ? ' selected' : '') . '>' . jp_h((string)$b['name']) . ' (' . jp_h((string)$b['gotra']) . ')</option>';
    }
    echo '</select></div><div><label>Girl / Bride</label><select name="girl" required><option value="">—</option>';
    foreach ($girls as $g) {
        echo '<option value="' . jp_h((string)$g['id']) . '"' . ($fid === (string)$g['id'] ? ' selected' : '') . '>' . jp_h((string)$g['name']) . ' (' . jp_h((string)$g['gotra']) . ')</option>';
    }
    echo '</select></div></div><p style="margin-top:1rem"><button type="submit">' . jp_h(jp_t(['Run Milan', 'मिलान चलाएँ'])) . '</button></p></form></div>';
    if ($result && $boy && $girl) {
        echo '<div class="card">';
        echo '<div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:1rem;align-items:flex-start">';
        echo '<div><div class="score">' . number_format((float)$result['total'], 1) . ' <span style="font-size:1rem;font-weight:600;color:#64748b">/ 36</span></div>';
        echo '<div><strong>' . jp_h($result['verdict']) . '</strong></div>';
        echo '<p class="meta">' . jp_h(jp_t(['Chandra:', 'चंद्र:'])) . ' ' . jp_h(jp_rashi_loc((int)($boyChart['moon_rashi'] ?? 0))) . ' (' . jp_h(jp_nak_loc((int)($boyChart['moon_nakshatra'] ?? 0))) . ') · ' . jp_h(jp_rashi_loc((int)($girlChart['moon_rashi'] ?? 0))) . ' (' . jp_h(jp_nak_loc((int)($girlChart['moon_nakshatra'] ?? 0))) . ')</p></div>';
        $badge = $result['total'] >= 24 ? 'ok' : ($result['total'] >= 18 ? 'warn' : 'bad');
        echo '<span class="badge ' . $badge . '">' . ($result['total'] >= 28 ? 'Uttam' : ($result['total'] >= 24 ? 'Madhyam+' : ($result['total'] >= 18 ? 'Madhyam' : 'Heen'))) . '</span></div>';
        // VISUAL ENRICHMENT: this table previously showed raw numbers with
        // no colour meaning at all -- a scanner had to mentally compare
        // points against max for each of the eight kootas one by one. Each
        // row now gets the SAME ok/warn/bad language already used
        // consistently elsewhere in this report (Gotra/Nadi/Manglik,
        // the overall verdict badge), scored by that row's own
        // points-to-max ratio, plus a small proportional bar so the
        // relative strength of each koota is visible at a glance, not
        // just readable as a number.
        echo '<table style="margin-top:1rem"><thead><tr><th>Koota</th><th>Points</th><th>Max</th><th style="width:90px"></th></tr></thead><tbody>';
        foreach ($result['kootas'] as $k) {
            $max = max(1.0, (float)$k['max']);
            $pts = (float)$k['points'];
            $ratio = $pts / $max;
            $kBadge = $ratio >= 0.75 ? 'ok' : ($ratio >= 0.4 ? 'warn' : 'bad');
            $kIcon  = $ratio >= 0.75 ? '&#10003;' : ($ratio >= 0.4 ? '&#9679;' : '&#10007;');
            $pct = max(0, min(100, (int) round($ratio * 100)));
            echo '<tr><td><span class="badge ' . $kBadge . '" style="margin-right:.4rem;display:inline-block;width:1.1rem;height:1.1rem;text-align:center;line-height:1.1rem;padding:0;font-size:.7rem">' . $kIcon . '</span>' . jp_h($k['name']) . '</td>'
               . '<td>' . number_format($pts, 1) . '</td><td>' . (int)$k['max'] . '</td>'
               . '<td><div style="background:var(--jp-line);border-radius:99px;height:6px;overflow:hidden"><div style="height:100%;border-radius:99px;width:' . $pct . '%;background:var(--jp-' . $kBadge . ')"></div></div></td></tr>';
        }
        echo '<tr><th>Total</th><th>' . number_format((float)$result['total'], 1) . '</th><th>36</th><th></th></tr></tbody></table>';
        echo '<div class="grid2" style="margin-top:1rem">';
        echo '<div><h2 style="font-size:.9rem">&#127795; Gotra</h2>';
        echo $result['gotra_fail']
            ? '<span class="badge bad">Same gotra — exogamy fail</span>'
            : '<span class="badge ok">Different gotra</span>';
        echo '<p class="meta">' . jp_h($result['gotra_boy']) . ' · ' . jp_h($result['gotra_girl']) . '</p></div>';
        echo '<div><h2 style="font-size:.9rem">&#127760; Nadi</h2>';
        echo $result['nadi_dosha']
            ? '<span class="badge warn">Nadi dosha</span>'
            : '<span class="badge ok">Clear</span>';
        echo '<p class="meta">' . jp_h($result['nadi_boy']) . ' · ' . jp_h($result['nadi_girl']) . '</p></div>';
        echo '<div><h2 style="font-size:.9rem">&#128293; Manglik / Kuja</h2>';
        if ($result['manglik_cancelled']) {
            echo '<span class="badge ok">Cancelled (mutual / exception)</span>';
        } elseif ($result['manglik_issue']) {
            echo '<span class="badge warn">Mismatch</span>';
        } else {
            echo '<span class="badge ok">No issue</span>';
        }
        $mr = $result['manglik']['reasons'] ?? [];
        if ($mr) {
            echo '<ul class="meta" style="margin:.35rem 0 0;padding-left:1.1rem">';
            foreach ($mr as $r) {
                echo '<li>' . jp_h((string)$r) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div></div>';
        jp_report_chrome('milan', 'Print Milan / PDF', 'मिलान प्रिंट / PDF');
        // duplicate print bar removed — chrome already includes Print
        jp_print_masthead(jp_t(['Kundli Milan', 'कुंडली मिलान']), (string)($boy['name'] ?? '') . ' · ' . (string)($girl['name'] ?? ''));
        jp_print_footer_block();
        echo '</div>';
    }
    jp_footer();
    exit;
}

// Home
jp_header(jp_t(['Profiles', 'प्रोफ़ाइल']));
echo '<div class="card"><div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem;align-items:center">';
echo '<h2 style="margin:0">&#128193; ' . jp_h(jp_t(['Saved profiles', 'सहेजी प्रोफ़ाइलें'])) . '</h2>';
if (jp_can_manage()) {
    echo '<div class="no-print"><a class="btn" href="?view=new&lang=' . jp_lang() . '">+ ' . jp_h(jp_t(['New', 'नई'])) . '</a> <a class="btn secondary" href="?view=import&lang=' . jp_lang() . '">' . jp_h(jp_t(['Import from Team', 'टीम से आयात'])) . '</a></div>';
}
echo '</div>';
if (!$profiles) {
    echo '<p class="meta" style="margin-top:1rem">No profiles yet. Create one or import from Team Directory.</p>';
} else {
    echo '<div class="jp-import-toolbar no-print" role="toolbar" aria-label="Profile actions" style="margin-top:1rem">';
    echo '<button type="button" class="jp-btn jp-btn-ghost" id="jpProfSelectAll">Select All</button>';
    echo '<button type="button" class="jp-btn jp-btn-ghost" id="jpProfUnselectAll">Unselect All</button>';
    echo '<button type="button" class="jp-btn jp-btn-primary" id="jpProfDelete" disabled style="background:linear-gradient(135deg,#b91c1c,#dc2626)">Delete selected</button>';
    echo '<span class="meta jp-import-count" id="jpProfCount">0 selected</span></div>';
    echo '<table style="margin-top:0.75rem"><thead><tr><th class="no-print"></th><th>Name</th><th>Gender</th><th>DOB</th><th>Gotra</th><th class="no-print">Actions</th></tr></thead><tbody>';
    foreach ($profiles as $p) {
        $pid = (string)($p['id'] ?? '');
        echo '<tr>';
        echo '<td class="no-print"><input type="checkbox" class="jp-prof-id" value="' . jp_h($pid) . '" aria-label="Select ' . jp_h((string)($p['name'] ?? '')) . '"></td>';
        echo '<td><a href="?view=patri&amp;id=' . jp_h($pid) . '"><strong>' . jp_h((string)$p['name']) . '</strong></a></td>';
        echo '<td>' . jp_h((string)$p['gender']) . '</td><td>' . jp_h((string)$p['dob']) . ' ' . jp_h((string)($p['tob'] ?? '')) . '</td>';
        echo '<td>' . jp_h((string)$p['gotra']) . '</td><td class="no-print"><a href="?view=patri&amp;id=' . jp_h($pid) . '">Patri</a> · <a href="?view=edit&amp;id=' . jp_h($pid) . '">Edit</a> · <button type="button" class="jp-btn jp-btn-ghost jp-prof-del-one" data-id="' . jp_h($pid) . '" data-name="' . jp_h((string)($p['name'] ?? '')) . '" style="padding:0.2rem 0.5rem;font-size:0.75rem">Delete</button></td></tr>';
    }
    echo '</tbody></table>';
    echo <<<'JS'
<script>
(function(){
  function boxes(){ return Array.prototype.slice.call(document.querySelectorAll(".jp-prof-id")); }
  function update(){
    var n = boxes().filter(function(b){ return b.checked; }).length;
    var c = document.getElementById("jpProfCount");
    var d = document.getElementById("jpProfDelete");
    if (c) c.textContent = n + " selected";
    if (d) d.disabled = n < 1;
  }
  async function deleteIds(ids){
    if (!ids.length) return;
    if (!confirm("Delete " + ids.length + " Janam profile(s)? This cannot be undone.")) return;
    var r = await fetch("janam_patri.php?action=api_delete_profiles", {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify({ ids: ids })
    });
    var j = await r.json();
    if (j.status === "ok") {
      alert("Deleted " + (j.deleted || 0) + " profile(s).");
      location.reload();
    } else {
      alert(j.message || "Delete failed");
    }
  }
  document.getElementById("jpProfSelectAll")?.addEventListener("click", function(){
    boxes().forEach(function(b){ b.checked = true; }); update();
  });
  document.getElementById("jpProfUnselectAll")?.addEventListener("click", function(){
    boxes().forEach(function(b){ b.checked = false; }); update();
  });
  document.getElementById("jpProfDelete")?.addEventListener("click", function(){
    deleteIds(boxes().filter(function(b){ return b.checked; }).map(function(b){ return b.value; }));
  });
  document.querySelectorAll(".jp-prof-del-one").forEach(function(btn){
    btn.addEventListener("click", function(){
      var id = btn.getAttribute("data-id");
      var name = btn.getAttribute("data-name") || id;
      if (!confirm("Delete profile “" + name + "”?")) return;
      deleteIds([id]);
    });
  });
  boxes().forEach(function(b){ b.addEventListener("change", update); });
  update();
})();
</script>
JS;
}
echo '</div><div class="card no-print"><h2>&#128149; Quick matchmaking</h2><p class="meta">Open <a href="?view=match">Matchmaking</a> after saving at least one male and one female profile.</p></div>';
jp_footer();
