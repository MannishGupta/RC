<?php // Version: 20261003.20
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

/**
 * Social icons for email: iOS/Gmail reject data:image/svg+xml (become file attachments).
 * Use same-origin PNG via social_icon.php (GD) with absolute HTTPS URL.
 * Fallback: coloured HTML badge (no image) if icon URL empty.
 */
$socialIconUrl = static function (string $network) use ($baseUrl): string {
    $network = strtolower(preg_replace('/[^a-z]/', '', $network) ?? '');
    if ($network === 'x') {
        $network = 'twitter';
    }
    if ($network === '') {
        return '';
    }
    // Prefer static brand PNG (real logo artwork)
    $staticRel = '/assets/icons/social/' . $network . '.png';
    $_bp = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    if (is_file($_bp . $staticRel)) {
        return $baseUrl . $staticRel . '?v=20261003.20';
    }
    return $baseUrl . '/social_icon.php?n=' . rawurlencode($network) . '&s=64&v=20261003.20';
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
$order = ['linkedin', 'twitter', 'instagram', 'facebook', 'youtube', 'whatsapp'];
$seen = [];
foreach ($order as $_k) {
    if (!isset($socialMeta[$_k])) {
        continue;
    }
    [$_label, $_col] = $socialMeta[$_k];
    $_v = $_validUrl($_personSocial[$_k] ?? $_companySocial[$_k] ?? '');
    if ($_v === '' && $_k === 'twitter') {
        $_v = $_validUrl($_personSocial['x'] ?? $_companySocial['x'] ?? '');
    }
    if ($_v === '' || isset($seen[$_k])) {
        continue;
    }
    $seen[$_k] = true;
    $socialLinks[] = [
        'url'   => $_v,
        'label' => $_label,
        'color' => $_col,
        'icon'  => $socialIconUrl($_k),
        'key'   => $_k,
    ];
}

$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** Social icon row — real brand PNG logos (hosted on same origin; iOS-safe when remote images load) */
$socialRowHtml = static function (array $socials) use ($e): string {
    if ($socials === []) {
        return '';
    }
    $fallbackGlyph = [
        'LinkedIn' => ['in', '0A66C2'],
        'X' => ['X', '111111'],
        'Instagram' => ['ig', 'E1306C'],
        'Facebook' => ['f', '1877F2'],
        'YouTube' => ['YT', 'FF0000'],
        'WhatsApp' => ['wa', '25D366'],
    ];
    $inner = '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;"><tr>';
    foreach ($socials as $s) {
        $label = (string)($s['label'] ?? '');
        $icon  = (string)($s['icon'] ?? '');
        $url   = (string)($s['url'] ?? '');
        $inner .= '<td style="padding:0 6px 0 0;vertical-align:middle;">';
        $inner .= '<a href="' . $e($url) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;border:0;line-height:0;">';
        if ($icon !== '') {
            $inner .= '<img src="' . $e($icon) . '" width="22" height="22" alt="' . $e($label) . '" '
                . 'title="' . $e($label) . '" '
                . 'style="display:block;width:22px;height:22px;border:0;border-radius:4px;" />';
        } else {
            $pair = $fallbackGlyph[$label] ?? ['•', $s['color'] ?? '64748b'];
            $inner .= '<span style="display:inline-block;width:22px;height:22px;line-height:22px;text-align:center;'
                . 'background:#' . $e($pair[1]) . ';color:#fff;font-family:Arial,sans-serif;font-size:10px;font-weight:700;'
                . 'border-radius:4px;">' . $e($pair[0]) . '</span>';
        }
        $inner .= '</a></td>';
    }
    $inner .= '</tr></table>';
    return '<tr><td style="padding:8px 0 2px;text-align:left;">' . $inner . '</td></tr>';
};




/**
 * Full signature — new messages
 * landscape logo → 2 columns (person | company with logo on top)
 * portrait logo → 3 columns (person | divider | logo column)
 */
$sigFull = static function (array $d) use ($e, $socialRowHtml): string {
    $photo  = (string)($d['photo'] ?? '');
    $logo   = (string)($d['logo'] ?? '');
    $orient = (string)($d['logo_orient'] ?? 'landscape');
    $hex    = (string)($d['hex'] ?? '1e3a5f');

    // Left: person block (tight, ≤10–12px — Outlook-friendly)
    $left = '<table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">';
    if ($photo !== '') {
        $left .= '<tr><td style="padding:0 0 8px 0;">'
            . '<img src="' . $e($photo) . '" width="64" height="64" alt="" '
            . 'style="display:block;width:64px;height:64px;border-radius:6px;object-fit:cover;border:0;" />'
            . '</td></tr>';
    }
    $left .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;color:#111111;line-height:1.25;padding:0;">'
        . $e($d['name']) . '</td></tr>';
    if ($d['role'] !== '') {
        $left .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:600;color:#' . $e($hex) . ';line-height:1.3;padding:2px 0 0 0;">'
            . $e($d['role']) . '</td></tr>';
    }
    if ($d['dept'] !== '') {
        $left .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#64748b;line-height:1.3;padding:1px 0 0 0;">'
            . $e($d['dept']) . '</td></tr>';
    }
    if ($d['phone'] !== '') {
        $tel = preg_replace('/[^0-9+]/', '', $d['phone']) ?? '';
        $left .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#334155;line-height:1.4;padding:6px 0 0 0;">'
            . 'M: <a href="tel:' . $e($tel) . '" style="color:#334155;text-decoration:none;">' . $e($d['phone']) . '</a></td></tr>';
    }
    if ($d['email'] !== '') {
        $left .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#334155;line-height:1.4;padding:1px 0 0 0;">'
            . 'E: <a href="mailto:' . $e($d['email']) . '" style="color:#334155;text-decoration:none;">' . $e($d['email']) . '</a></td></tr>';
    }
    $left .= '</table>';

    // Right: company block — landscape logo on top; portrait logo beside/above stack
    $right = '<table cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">';
    if ($logo !== '' && $orient !== 'portrait') {
        // Landscape / square: logo above company name (max ~140×48)
        $right .= '<tr><td style="padding:0 0 6px 0;">'
            . '<img src="' . $e($logo) . '" alt="' . $e($d['org']) . '" width="140" '
            . 'style="display:block;max-width:140px;max-height:48px;width:auto;height:auto;border:0;" />'
            . '</td></tr>';
    }
    if ($logo !== '' && $orient === 'portrait') {
        $right .= '<tr><td style="padding:0 0 6px 0;">'
            . '<img src="' . $e($logo) . '" alt="' . $e($d['org']) . '" width="72" '
            . 'style="display:block;max-width:72px;max-height:96px;width:auto;height:auto;border:0;" />'
            . '</td></tr>';
    }
    if ($d['org'] !== '') {
        $right .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;color:#111111;line-height:1.25;padding:0;">'
            . $e($d['org']) . '</td></tr>';
    }
    if ($d['address'] !== '') {
        $right .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#64748b;line-height:1.35;padding:3px 0 0 0;max-width:260px;">'
            . $e($d['address']) . '</td></tr>';
    }
    $tw = [];
    if ($d['landline'] !== '') {
        $tw[] = 'T: ' . $e($d['landline']);
    }
    if ($d['website'] !== '') {
        $w = preg_replace('~^https?://~i', '', $d['website']) ?? $d['website'];
        $w = rtrim($w, '/');
        $tw[] = 'W: <a href="' . $e($d['website']) . '" style="color:#2563eb;text-decoration:none;">' . $e($w) . '</a>';
    }
    if ($tw !== []) {
        $right .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#334155;line-height:1.4;padding:4px 0 0 0;">'
            . implode('&nbsp;|&nbsp;', $tw) . '</td></tr>';
    }
    // Social links — left-aligned under company details band
    $soc = $socialRowHtml($d['socials'] ?? []);
    if ($soc !== '') {
        $right .= $soc;
    }
    $right .= '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:10px;padding:4px 0 0 0;">'
        . '<a href="' . $e($d['cardUrl']) . '" style="color:#94a3b8;text-decoration:none;">View digital card →</a></td></tr>';
    $right .= '</table>';

    // Exactly 3 cells: left | 1px rule | right — no trailing empty columns
    return '<table cellpadding="0" cellspacing="0" border="0" '
        . 'style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#333333;border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;">'
        . '<tr>'
        . '<td valign="top" style="vertical-align:top;padding:0 14px 0 0;">' . $left . '</td>'
        . '<td valign="top" width="1" style="width:1px;border-left:1px solid #d1d5db;padding:0;font-size:0;line-height:0;">&nbsp;</td>'
        . '<td valign="top" style="vertical-align:top;padding:0 0 0 14px;">' . $right . '</td>'
        . '</tr>'
        . '</table>';
};

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
    Social icons are hosted PNGs (not SVG attachments). On iPhone Mail, tap “Load Remote Content” / download pictures once so icons appear.
  </div>

  <div class="tabs" role="tablist">
    <button type="button" class="tab is-on" id="tabFull" onclick="showSig('full')">New messages (full)</button>
    <button type="button" class="tab" id="tabCompact" onclick="showSig('compact')">Replies &amp; forwards (compact)</button>
  </div>

  <div class="panel" id="panelFull">
    <div id="sigFull"><?= $htmlFull ?></div>
    <p class="meta">Full · person | company · logo <?= $logoOrient === 'portrait' ? 'portrait (side column style on top of company)' : 'landscape (above company name)' ?></p>
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
