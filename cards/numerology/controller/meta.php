<?php
// meta.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

// controller/meta.php — Protocol/share URL, QR, photo, OG metadata, nav tabs

// NOTE: $shareDecoded (decoding) is handled in profile.php before this file loads.
// ── Protocol / host / path ────────────────────────────────────────
$rawHost      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$safeHost     = preg_replace('/[^a-zA-Z0-9.:-]/', '', $rawHost);
$protocol     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? "https" : "http") . "://";
$sharePayload = base64_encode(json_encode(['n' => $p['name'], 'd' => ($p['dob_raw'] ?? $p['dob'] ?? ''), 'm' => ($pData['phone'] ?? ''), 'g' => ($p['gender'] ?? 'Unknown')]));
$sharePayload = strtr($sharePayload, ['+' => '-', '/' => '_', '=' => '']);
$currentPath  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

// ── Share URL / QR / WhatsApp ─────────────────────────────────────
$shareUrl = !empty($slug)
    ? $protocol . $safeHost . $currentPath . '?card=numero&slug=' . urlencode($slug) . ($lang === 'hi' ? '&lang=hi' : '&lang=en')
    : $protocol . $safeHost . $currentPath . '?card=numero&share=' . $sharePayload . ($lang === 'hi' ? '&lang=hi' : '&lang=en');
    
$qrUrl       = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($shareUrl);
$whatsappUrl = 'https://wa.me/?text=' . urlencode($lang === 'hi' ? '🔮 ' . $p['name'] . " की वैदिक अंकशास्त्र रिपोर्ट देखें: " . $shareUrl : '🔮 Check ' . $p['name'] . "'s Vedic Numerology Report: " . $shareUrl);

// ── Photo ─────────────────────────────────────────────────────────
$photoFile = trim((string)($p['photo'] ?? ''));
$absPhoto  = '';

if (!empty($photoFile)) {
    if (strpos($photoFile, 'http') === 0) {
        $absPhoto = $photoFile;
    } elseif (preg_match('/\.(jpg|jpeg|png|webp|avif)$/i', $photoFile)) {
        $absPhoto = $protocol . $safeHost . '/images/' . implode('/', array_map('rawurlencode', explode('/', ltrim($photoFile, '/'))));
    }
}
if (empty($absPhoto)) {
    $absPhoto = 'https://ui-avatars.com/api/?name=' . urlencode($p['name'] ?? 'U') . '&background=f1f5f9&color=94a3b8&size=256';
}

// ── Navigation tabs (JSON-driven — single source of truth) ────────────────
$_navData = $_numData['nav_tabs'] ?? [];

$tabs=[];
foreach($_navData as $t){
    $tabs[]=['id'=>$t['id'],'label'=>($lang==='hi'?($t['hi']??$t['en']):$t['en']),'icon'=>$t['icon']];
}

// ── Active tab ─────────────────────────────────────────────────────
$_vt=array_column($_navData,'id');
$initTab=in_array($_GET['tab']??'',$_vt,true)?(string)$_GET['tab']:'executive';

// ── SEO / OG metadata ─────────────────────────────────────────────
$ogTitle = htmlspecialchars($p['name'], ENT_QUOTES)
         . ($lang === 'hi' ? ' — वैदिक अंकशास्त्र रिपोर्ट' : ' — Vedic Numerology Report');
         
$ogDesc  = ($lang === 'hi')
    ? 'Driver ' . (int)$core['driver'] . ' · Conductor ' . (int)$core['conductor'] . ' · वैदिक अंकशास्त्र'
    : 'Driver ' . (int)$core['driver'] . ' · Conductor ' . (int)$core['conductor'] . ' · Vedic Numerology';
    
$clientName='Arthsathi';
if (class_exists('AppDB')) { $_co=AppDB::read('company'); if(!empty($_co['name'])) $clientName=(string)$_co['name']; elseif(!empty($_co[0]['name'])) $clientName=(string)$_co[0]['name']; }

// OG image: profile photo → company logo → dynamic SVG card (WhatsApp/LinkedIn safe)
$_coLogo = '';
if (class_exists('AppDB')) {
    $_co = AppDB::read('company');
    if (is_array($_co) && !empty($_co['logo'])) {
        $_coLogo = $protocol . $safeHost . '/images/' . rawurlencode(basename((string)$_co['logo']));
    } elseif (is_array($_co) && !empty($_co[0]['logo'])) {
        $_coLogo = $protocol . $safeHost . '/images/' . rawurlencode(basename((string)$_co[0]['logo']));
    }
}
$ogImg = '';
if (!empty($absPhoto) && strpos((string)$absPhoto, 'ui-avatars.com') === false) {
    $ogImg = (string)$absPhoto;
} elseif ($_coLogo !== '') {
    $ogImg = $_coLogo;
} else {
    $ogImg = $protocol . $safeHost . '/tools/og_card.php?' . http_build_query([
        'name' => (string)($p['name'] ?? 'Numerology'),
        'role' => 'Vedic Numerology Report',
        'company' => (string)($clientName ?? 'Arthsathi'),
        'kind' => ($lang === 'hi' ? 'वैदिक अंकशास्त्र' : 'Vedic Numerology'),
    ]);
}
// clientName may not be set yet — safe fallback after
$ogUrl = $shareUrl;

// ── Dynamic client name (from AppDB company record) ────────────

// ── V2027.300: Muhurat ────────────────────────────────────────
$muhuratData=[];
if (class_exists('MuhuratEngine')&&!empty($core['driver'])) {
    $_fr=$report['lucky_driver']['friends']??[];
    $muhuratData=['business_start'=>MuhuratEngine::bestDates((int)$core['driver'],$_fr,'business_start'),'name_correction'=>MuhuratEngine::bestDates((int)$core['driver'],$_fr,'name_correction'),'property'=>MuhuratEngine::bestDates((int)$core['driver'],$_fr,'property'),'travel'=>MuhuratEngine::bestDates((int)$core['driver'],$_fr,'travel'),'marriage'=>MuhuratEngine::bestDates((int)$core['driver'],$_fr,'marriage')];
}

// ── V2027.300: Vastu Name Score ───────────────────────────────
$vastuScore=null;
if (class_exists('VastuNameEngine')&&!empty($p['name'])) {
    $_vn=!empty($_GET['vastu_test'])?trim(strip_tags((string)$_GET['vastu_test'])):($p['name']??'');
    if (!empty($_vn)) $vastuScore=VastuNameEngine::score($_vn,(int)$core['driver']);
}

// ── V2027.300: Composite ──────────────────────────────────────
$compositeReport=null;
if (class_exists('CompositeEngine')&&!empty($compareReport)&&!empty($compareReport['core'])) {
    try { $compositeReport=CompositeEngine::build($report,$compareReport); } catch(Exception $e){ $compositeReport=null; }
}

