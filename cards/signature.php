<?php // Version: 20261003.14
declare(strict_types=1);
/**
 * Email signature generator — dual layouts:
 *  1) New mail (full) — photo, company logo orientation, full contacts + socials
 *  2) Reply/forward (compact) — name, title, phone, email only
 * Logo: landscape/square → 2-column; portrait → 3-column (logo right).
 * Images: absolute media_serve URLs + ui-avatars / logo_proxy fallbacks.
 * Social: real brand SVG marks as data-URI (email-safe) with hosted PNG fallback path.
 */

$rootPath = file_exists(__DIR__ . '/../app/bootstrap.php') ? dirname(__DIR__) : __DIR__;
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
if (!defined('IMG_PATH')) {
    define('IMG_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/images');
}
if (!defined('DOC_PATH')) {
    define('DOC_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/documents');
}
require_once BASE_PATH . '/app/bootstrap.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$ctx  = $slug !== '' && class_exists('CardContext') ? CardContext::get($slug) : null;
if (!$ctx) {
    http_response_code(404);
    die("<div style='font-family:sans-serif;padding:40px;text-align:center;color:#64748b'>Card not found.</div>");
}

$person  = $ctx['person']  ?? [];
$company = $ctx['company'] ?? [];

$name    = trim((string)($person['name'] ?? ''));
$role    = trim((string)($person['designation'] ?? $person['role'] ?? ''));
$dept    = trim((string)($person['department'] ?? ''));
$phone   = trim((string)($person['phone'] ?? $person['mobile'] ?? ''));
$email   = trim((string)($person['email'] ?? ''));
$website = trim((string)($person['website'] ?? $company['website'] ?? ''));
$orgName = trim((string)($company['name'] ?? ''));
$address = trim((string)($company['address'] ?? $person['location'] ?? ''));
$landline = trim((string)($company['phone'] ?? $company['landline'] ?? ''));

$rawHost  = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$safeHost = preg_replace('/[^a-zA-Z0-9.:-]/', '', $rawHost) ?? 'localhost';
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl  = rtrim($scheme . '://' . $safeHost, '/');
$cardUrl  = $baseUrl . '/?card=business&slug=' . rawurlencode($slug);

/** Resolve team/company image to absolute public URL with fallbacks */
$resolveImg = static function (string $file, string $fallback = '') use ($baseUrl): string {
    $file = trim($file);
    if ($file === '') {
        return $fallback;
    }
    if (preg_match('~^https?://~i', $file) || str_starts_with($file, 'data:')) {
        return $file;
    }
    $bn = basename(str_replace(['\\', "\0"], ['/', ''], $file));
    if ($bn === '' || $bn === '.' || $bn === '..') {
        return $fallback;
    }
    // Prefer media gateway (tenant-safe)
    return $baseUrl . '/media_serve.php?f=' . rawurlencode($bn);
};

$photoFile = (string)($person['photo'] ?? '');
$logoFile  = (string)($company['logo'] ?? $company['logo_file'] ?? '');
$photoUrl  = $resolveImg(
    $photoFile,
    'https://ui-avatars.com/api/?name=' . rawurlencode($name !== '' ? $name : 'User') . '&background=1e3a5f&color=ffffff&size=200&bold=true'
);
$logoUrl = $resolveImg($logoFile, '');

// Local path for getimagesize orientation
$logoLocal = '';
foreach ([IMG_PATH, DATA_PATH . '/media/images', DATA_PATH . '/images', BASE_PATH . '/images'] as $dir) {
    if ($logoFile === '' || !is_dir($dir)) {
        continue;
    }
    $cand = $dir . '/' . basename($logoFile);
    if (is_file($cand)) {
        $logoLocal = $cand;
        break;
    }
}
$logoOrient = 'landscape'; // default 2-col
$logoW = 0;
$logoH = 0;
if ($logoLocal !== '' && function_exists('getimagesize')) {
    $sz = @getimagesize($logoLocal);
    if (is_array($sz) && ($sz[0] ?? 0) > 0 && ($sz[1] ?? 0) > 0) {
        $logoW = (int)$sz[0];
        $logoH = (int)$sz[1];
        $logoOrient = ($logoH > $logoW) ? 'portrait' : 'landscape';
    }
}

$hex = ltrim((string)($company['brand_color'] ?? '#1e3a5f'), '#');
if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
    $hex = '1e3a5f';
}

// Social links
$_personSocial  = is_array($person['social'] ?? null) ? $person['social'] : [];
$_companySocial = is_array($company['social'] ?? null) ? $company['social'] : [];
$_validUrl = static function ($v): string {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') {
        return '';
    }
    if (!preg_match('~^[a-z][a-z0-9+.\-]*:~i', $v)) {
        $v = 'https://' . ltrim($v, '/');
    }
    return preg_match('~^https?://~i', $v) ? $v : '';
};

/** Real brand SVG data-URIs (email clients that allow data: images) + absolute PNG fallbacks via simple-icons CDN style shapes */
$socialSvg = static function (string $network): string {
    // Minimal official-ish monochrome brand marks as inline SVG data URIs
    $svgs = [
        'linkedin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><rect width="24" height="24" rx="4" fill="#0A66C2"/><path fill="#fff" d="M6.36 9.5H8.7v7.64H6.36V9.5zM7.53 5.4a1.36 1.36 0 110 2.72 1.36 1.36 0 010-2.72zM10.3 9.5h2.24v1.04h.03c.31-.59 1.07-1.21 2.2-1.21 2.35 0 2.78 1.55 2.78 3.56v4.25h-2.34v-3.77c0-.9-.02-2.05-1.25-2.05-1.25 0-1.44.98-1.44 1.99v3.83H10.3V9.5z"/></svg>',
        'twitter'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><rect width="24" height="24" rx="4" fill="#000"/><path fill="#fff" d="M13.6 10.77L19.05 4.5h-1.29l-4.74 5.45L9.24 4.5H4.5l5.72 8.23L4.5 19.5h1.29l5-5.75 4 5.75H19.5l-5.9-8.73zm-1.77 2.03l-.58-.82L6.25 5.5h1.98l3.72 5.24.58.82 4.84 6.82h-1.98l-4.55-6.58z"/></svg>',
        'instagram'=> '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><defs><linearGradient id="g" x1="0" y1="24" x2="24" y2="0"><stop stop-color="#f58529"/><stop offset=".5" stop-color="#dd2a7b"/><stop offset="1" stop-color="#515bd4"/></linearGradient></defs><rect width="24" height="24" rx="6" fill="url(#g)"/><rect x="6" y="6" width="12" height="12" rx="4" fill="none" stroke="#fff" stroke-width="1.6"/><circle cx="12" cy="12" r="3.2" fill="none" stroke="#fff" stroke-width="1.6"/><circle cx="16.4" cy="7.6" r="1" fill="#fff"/></svg>',
        'facebook' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><rect width="24" height="24" rx="4" fill="#1877F2"/><path fill="#fff" d="M15.1 8.5h-1.3c-.5 0-.8.2-.8.7v1.1H15l-.2 2h-1.8V18h-2.2v-5.7H9.5v-2h1.3V9c0-1.5.9-2.5 2.5-2.5.5 0 1.1.1 1.6.2v1.3z"/></svg>',
        'youtube'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><rect width="24" height="24" rx="4" fill="#FF0000"/><path fill="#fff" d="M10 8.5l5.5 3.5L10 15.5v-7z"/></svg>',
        'whatsapp' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><rect width="24" height="24" rx="4" fill="#25D366"/><path fill="#fff" d="M12 5.5a6.2 6.2 0 00-5.3 9.4L5.5 18.5l3.7-1.1A6.2 6.2 0 1012 5.5zm3.5 8.7c-.15.42-.87.8-1.22.85-.31.05-.7.07-1.13-.07-.26-.08-.6-.2-1.03-.4-1.81-.78-3-2.62-3.09-2.74-.09-.12-.74-1-.74-1.9 0-.9.47-1.34.64-1.52.17-.18.37-.23.5-.23h.36c.11 0 .27-.04.42.32.15.37.52 1.27.57 1.36.05.09.08.2.02.32-.07.13-.1.21-.2.32-.1.11-.21.25-.3.33-.1.09-.2.19-.09.37.11.18.5.83 1.07 1.34.74.66 1.36.87 1.55.96.19.09.3.08.41-.05.11-.13.47-.55.6-.74.13-.19.26-.16.43-.09.18.06 1.12.53 1.31.63.19.1.32.14.37.22.05.08.05.46-.1.88z"/></svg>',
    ];
    $raw = $svgs[$network] ?? '';
    if ($raw === '') {
        return '';
    }
    return 'data:image/svg+xml;base64,' . base64_encode($raw);
};

$socialMeta = [
    'linkedin'  => ['LinkedIn', '0a66c2'],
    'twitter'   => ['X', '000000'],
    'x'         => ['X', '000000'],
    'instagram' => ['Instagram', 'c026d3'],
    'facebook'  => ['Facebook', '1877f2'],
    'youtube'   => ['YouTube', 'ff0000'],
    'whatsapp'  => ['WhatsApp', '25d366'],
];
$socialLinks = [];
foreach ($socialMeta as $_k => [$_label, $_col]) {
    $_v = $_validUrl($_personSocial[$_k] ?? $_companySocial[$_k] ?? '');
    if ($_v === '' && $_k === 'twitter') {
        $_v = $_validUrl($_personSocial['x'] ?? $_companySocial['x'] ?? '');
    }
    if ($_v === '') {
        continue;
    }
    $key = $_k === 'x' ? 'twitter' : $_k;
    $socialLinks[] = [
        'url'   => $_v,
        'label' => $_label,
        'color' => $_col,
        'icon'  => $socialSvg($key),
        'key'   => $key,
    ];
}

$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** Social icon row — real SVG data-URI images with spacing */
$socialRowHtml = static function (array $socials) use ($e): string {
    if ($socials === []) {
        return '';
    }
    $cells = '';
    foreach ($socials as $s) {
        $icon = (string)($s['icon'] ?? '');
        if ($icon !== '') {
            $cells .= '<a href="' . $e($s['url']) . '" style="display:inline-block;margin:0 6px 0 0;text-decoration:none;border:0;">'
                . '<img src="' . $e($icon) . '" width="18" height="18" alt="' . $e($s['label']) . '" '
                . 'style="display:inline-block;border:0;width:18px;height:18px;" />'
                . '</a>';
        } else {
            $cells .= '<a href="' . $e($s['url']) . '" style="display:inline-block;margin:0 6px 0 0;padding:2px 5px;'
                . 'background:#' . $e($s['color']) . ';color:#fff;font-size:9px;font-family:Arial,sans-serif;'
                . 'font-weight:bold;text-decoration:none;border-radius:3px;">' . $e($s['label']) . '</a>';
        }
    }
    return '<tr><td style="padding:6px 0 2px;line-height:18px;">' . $cells . '</td></tr>';
};

/**
 * Full signature — new messages
 * landscape logo → 2 columns (person | company with logo on top)
 * portrait logo → 3 columns (person | divider | logo column)
 */
$sigFull = static function (array $d) use ($e, $socialRowHtml): string {
    $photo = (string)($d['photo'] ?? '');
    $logo  = (string)($d['logo'] ?? '');
    $orient = (string)($d['logo_orient'] ?? 'landscape');
    $hex = (string)($d['hex'] ?? '1e3a5f');

    $photoCell = $photo !== ''
        ? '<img src="' . $e($photo) . '" width="72" height="72" alt="" '
          . 'style="display:block;width:72px;height:72px;border-radius:8px;object-fit:cover;border:0;" />'
        : '';

    $personLines = '';
    $personLines .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:#111;line-height:1.3;">' . $e($d['name']) . '</div>';
    if ($d['role'] !== '') {
        $personLines .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:600;color:#' . $e($hex) . ';margin-top:2px;">' . $e($d['role']) . '</div>';
    }
    if ($d['dept'] !== '') {
        $personLines .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;margin-top:1px;">' . $e($d['dept']) . '</div>';
    }
    $contact = '';
    if ($d['phone'] !== '') {
        $tel = preg_replace('/[^0-9+]/', '', $d['phone']) ?? '';
        $contact .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#334155;margin-top:6px;">'
            . 'M: <a href="tel:' . $e($tel) . '" style="color:#334155;text-decoration:none;">' . $e($d['phone']) . '</a></div>';
    }
    if ($d['email'] !== '') {
        $contact .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#334155;">'
            . 'E: <a href="mailto:' . $e($d['email']) . '" style="color:#334155;text-decoration:none;">' . $e($d['email']) . '</a></div>';
    }
    $contact .= $socialRowHtml($d['socials'] ?? []);

    $corp = '';
    if ($logo !== '' && $orient === 'landscape') {
        $corp .= '<img src="' . $e($logo) . '" alt="' . $e($d['org']) . '" width="140" '
            . 'style="display:block;max-width:140px;max-height:48px;width:auto;height:auto;margin-bottom:6px;border:0;" />';
    }
    if ($d['org'] !== '') {
        $corp .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;color:#111;margin-bottom:2px;">' . $e($d['org']) . '</div>';
    }
    if ($d['address'] !== '') {
        $corp .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;margin-bottom:4px;max-width:220px;">' . $e($d['address']) . '</div>';
    }
    $corpBits = [];
    if ($d['landline'] !== '') {
        $corpBits[] = 'T: ' . $e($d['landline']);
    }
    if ($d['website'] !== '') {
        $w = preg_replace('~^https?://~i', '', $d['website']) ?? $d['website'];
        $corpBits[] = 'W: <a href="' . $e($d['website']) . '" style="color:#2563eb;text-decoration:none;">' . $e($w) . '</a>';
    }
    if ($corpBits !== []) {
        $corp .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#334155;margin-bottom:4px;">' . implode(' &nbsp;|&nbsp; ', $corpBits) . '</div>';
    }
    $corp .= '<div style="font-family:Arial,Helvetica,sans-serif;font-size:10px;margin-top:4px;">'
        . '<a href="' . $e($d['cardUrl']) . '" style="color:#94a3b8;text-decoration:none;">View digital card →</a></div>';

    $divider = '<td style="width:1px;background-color:#e2e8f0;padding:0;font-size:0;line-height:0;">&nbsp;</td>';

    if ($logo !== '' && $orient === 'portrait') {
        // 3 columns: person | divider | logo + company text under
        $logoCol = '<td style="vertical-align:top;padding-left:14px;">'
            . '<img src="' . $e($logo) . '" alt="" width="72" '
            . 'style="display:block;max-width:72px;max-height:96px;width:auto;height:auto;border:0;margin-bottom:6px;" />'
            . $corp
            . '</td>';
        return '<table cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#333;border-collapse:collapse;max-width:560px;">'
            . '<tr>'
            . '<td style="vertical-align:top;padding-right:12px;width:80px;">' . $photoCell . '</td>'
            . '<td style="vertical-align:top;padding-right:12px;">' . $personLines . $contact . '</td>'
            . $divider
            . $logoCol
            . '</tr></table>';
    }

    // 2 columns: person | company (logo top if landscape)
    return '<table cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#333;border-collapse:collapse;max-width:560px;">'
        . '<tr>'
        . '<td style="vertical-align:top;padding-right:12px;width:80px;">' . $photoCell . '</td>'
        . '<td style="vertical-align:top;padding-right:14px;">' . $personLines . $contact . '</td>'
        . $divider
        . '<td style="vertical-align:top;padding-left:14px;">' . $corp . '</td>'
        . '</tr></table>';
};

/** Compact signature — replies / forwards */
$sigCompact = static function (array $d) use ($e): string {
    $bits = [];
    $bits[] = '<strong style="color:#111;">' . $e($d['name']) . '</strong>';
    if ($d['role'] !== '') {
        $bits[] = '<span style="color:#' . $e($d['hex']) . ';">' . $e($d['role']) . '</span>';
    }
    if ($d['org'] !== '') {
        $bits[] = '<span style="color:#64748b;">' . $e($d['org']) . '</span>';
    }
    $line2 = [];
    if ($d['phone'] !== '') {
        $tel = preg_replace('/[^0-9+]/', '', $d['phone']) ?? '';
        $line2[] = '<a href="tel:' . $e($tel) . '" style="color:#334155;text-decoration:none;">' . $e($d['phone']) . '</a>';
    }
    if ($d['email'] !== '') {
        $line2[] = '<a href="mailto:' . $e($d['email']) . '" style="color:#334155;text-decoration:none;">' . $e($d['email']) . '</a>';
    }
    return '<table cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#334155;border-collapse:collapse;">'
        . '<tr><td style="padding:0;line-height:1.45;">' . implode(' · ', $bits) . '</td></tr>'
        . ($line2 !== [] ? '<tr><td style="padding:2px 0 0;line-height:1.4;">' . implode(' · ', $line2) . '</td></tr>' : '')
        . '</table>';
};

$payload = [
    'name' => $name,
    'role' => $role,
    'dept' => $dept,
    'org' => $orgName,
    'phone' => $phone,
    'email' => $email,
    'website' => $website,
    'address' => $address,
    'landline' => $landline,
    'photo' => $photoUrl,
    'logo' => $logoUrl,
    'logo_orient' => $logoOrient,
    'hex' => $hex,
    'cardUrl' => $cardUrl,
    'socials' => $socialLinks,
];

$htmlFull = $sigFull($payload);
$htmlCompact = $sigCompact($payload);

$ogTitle = 'Email Signature — ' . $name . ($orgName !== '' ? ' · ' . $orgName : '');
$ogDesc  = 'Full + compact email signatures for ' . $name . '.';
$sigPageUrl = $baseUrl . '/cards/signature.php?slug=' . rawurlencode($slug);
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= $e($ogTitle) ?></title>
<meta name="description" content="<?= $e($ogDesc) ?>">
<meta property="og:title" content="<?= $e($ogTitle) ?>">
<meta property="og:description" content="<?= $e($ogDesc) ?>">
<meta property="og:url" content="<?= $e($sigPageUrl) ?>">
<meta property="og:image" content="<?= $e($photoUrl) ?>">
<style>
  body { margin:0; font-family: Inter, system-ui, sans-serif; background:#f1f5f9; color:#0f172a; }
  .wrap { max-width:720px; margin:0 auto; padding:24px 16px 48px; }
  h1 { font-size:1.25rem; font-weight:800; margin:0 0 4px; }
  .sub { font-size:0.8rem; color:#64748b; margin:0 0 20px; }
  .tabs { display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
  .tab { appearance:none; border:1px solid #cbd5e1; background:#fff; border-radius:999px; padding:8px 14px;
         font-size:0.75rem; font-weight:700; cursor:pointer; color:#334155; }
  .tab.is-on { background:#1e3a5f; color:#fff; border-color:#1e3a5f; }
  .panel { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; box-shadow:0 1px 2px rgba(15,23,42,.06); }
  .meta { font-size:0.7rem; color:#94a3b8; margin:8px 0 0; }
  .actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
  .btn { appearance:none; border:0; border-radius:8px; padding:10px 16px; font-size:0.8rem; font-weight:700; cursor:pointer; }
  .btn-primary { background:#1e3a5f; color:#fff; }
  .btn-ghost { background:#f1f5f9; color:#334155; }
  .btn.copied { background:#059669; color:#fff; }
  .steps { margin-top:28px; font-size:0.85rem; color:#475569; }
  .steps h2 { font-size:0.9rem; color:#0f172a; margin:16px 0 6px; }
  .hint { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 12px; font-size:0.75rem; color:#1e40af; margin-bottom:14px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Email signatures</h1>
  <p class="sub"><?= $e($name) ?><?= $orgName !== '' ? ' · ' . $e($orgName) : '' ?>
    · Logo layout: <strong><?= $e($logoOrient) ?></strong><?= $logoW ? ' (' . (int)$logoW . '×' . (int)$logoH . ')' : '' ?>
  </p>

  <div class="hint">
    <strong>Two signatures:</strong> use <em>New messages</em> for full detail;
    use <em>Replies &amp; forwards</em> for a short line under quoted mail.
    Images use absolute URLs so Outlook/Gmail can load them (enable “download pictures” if needed).
  </div>

  <div class="tabs" role="tablist">
    <button type="button" class="tab is-on" id="tabFull" onclick="showSig('full')">New messages (full)</button>
    <button type="button" class="tab" id="tabCompact" onclick="showSig('compact')">Replies &amp; forwards (compact)</button>
  </div>

  <div class="panel" id="panelFull">
    <div id="sigFull"><?= $htmlFull ?></div>
    <p class="meta">Full · <?= $logoOrient === 'portrait' ? '3-column (portrait logo)' : '2-column (landscape / square logo)' ?></p>
  </div>
  <div class="panel" id="panelCompact" style="display:none">
    <div id="sigCompact"><?= $htmlCompact ?></div>
    <p class="meta">Compact · name, title, phone, email only</p>
  </div>

  <div class="actions">
    <button type="button" class="btn btn-primary" id="copyBtn" onclick="copySig()">Copy active signature</button>
    <button type="button" class="btn btn-ghost" onclick="toggleSrc()">View HTML</button>
    <a class="btn btn-ghost" href="<?= $e($cardUrl) ?>">Digital card</a>
  </div>
  <textarea id="sigSource" readonly style="display:none;width:100%;margin-top:12px;min-height:140px;font-family:ui-monospace,monospace;font-size:11px;padding:12px;border-radius:8px;border:1px solid #e2e8f0;background:#0f172a;color:#86efac;"></textarea>

  <div class="steps">
    <h2>Gmail</h2>
    <ol>
      <li>Copy the signature above.</li>
      <li>Settings → See all settings → General → Signature.</li>
      <li>Create <strong>two</strong> signatures (e.g. “Full” and “Reply”), paste each, then assign: Full → new emails, Reply → replies/forwards.</li>
    </ol>
    <h2>Outlook</h2>
    <ol>
      <li>File → Options → Mail → Signatures (desktop), or Settings → Compose and reply (web).</li>
      <li>Create two signatures and set defaults for new vs reply/forward.</li>
    </ol>
  </div>
</div>
<script>
var active = 'full';
function showSig(which) {
  active = which;
  document.getElementById('panelFull').style.display = which === 'full' ? 'block' : 'none';
  document.getElementById('panelCompact').style.display = which === 'compact' ? 'block' : 'none';
  document.getElementById('tabFull').classList.toggle('is-on', which === 'full');
  document.getElementById('tabCompact').classList.toggle('is-on', which === 'compact');
  document.getElementById('sigSource').style.display = 'none';
}
function toggleSrc() {
  var box = document.getElementById('sigSource');
  var showing = box.style.display !== 'none';
  if (showing) { box.style.display = 'none'; return; }
  var id = active === 'full' ? 'sigFull' : 'sigCompact';
  box.value = document.getElementById(id).innerHTML.trim();
  box.style.display = 'block';
}
function copySig() {
  var el = document.getElementById(active === 'full' ? 'sigFull' : 'sigCompact');
  var range = document.createRange();
  range.selectNodeContents(el);
  var sel = window.getSelection();
  sel.removeAllRanges();
  sel.addRange(range);
  var ok = false;
  try { ok = document.execCommand('copy'); } catch (e) {}
  sel.removeAllRanges();
  var btn = document.getElementById('copyBtn');
  if (ok) {
    btn.classList.add('copied');
    btn.textContent = 'Copied — paste into your mail client';
    setTimeout(function(){ btn.classList.remove('copied'); btn.textContent = 'Copy active signature'; }, 2500);
  } else {
    alert('Select the signature manually and copy (Ctrl/Cmd+C).');
  }
}
</script>
</body>
</html>
