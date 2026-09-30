<?php // Version: 260917.01
declare(strict_types=1);
/**
 * cards/signature.php — Email signature generator.
 *
 * Not "sync with Google Workspace" — that needs a Workspace domain-wide
 * delegation grant, which is an admin decision for THEIR Google org, not
 * something this app can request on its own. Instead: a signature is
 * generated from the same team record the digital card already uses, shown
 * ready to copy-paste into Gmail's or Outlook's signature settings.
 * Gmail/Outlook/Apple Mail all accept pasted HTML signatures — this covers
 * the same ground with a five-second manual step instead of an OAuth grant.
 *
 * PUBLIC BY DESIGN, same as the card itself: ?card=business&slug=... has no
 * session gate, and a signature is not more sensitive than the card it is
 * built from. Anyone who can view the card can generate its signature.
 */

$rootPath = file_exists(__DIR__ . '/../app/bootstrap.php') ? dirname(__DIR__) : __DIR__;
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
// BUG FIX: this file defined DATA_PATH/IMG_PATH/DOC_PATH directly from
// BASE_PATH, completely bypassing tenant resolution -- on a multi-tenant
// deployment (tenants/{id}/data/, resolved by host in
// app/tenant_bootstrap.php), every request to this file read and wrote
// the WRONG tenant's data regardless of which domain it was requested on.
// Fixed to require tenant_bootstrap.php first (as index.php and the
// correctly-wired standalone entry points already do) and guard every
// fallback define with if (!defined(...)) so tenant_bootstrap's
// resolution always wins when available, with these as the fallback for
// a genuine single-tenant install with no tenants/ folder at all.
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH'))  if (!defined('IMG_PATH')) define('IMG_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/images');
if (!defined('DOC_PATH'))  if (!defined('DOC_PATH')) define('DOC_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/documents');
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
$phone   = trim((string)($person['phone'] ?? ''));
$email   = trim((string)($person['email'] ?? ''));
$website = trim((string)($person['website'] ?? $company['website'] ?? ''));
$orgName = trim((string)($company['name'] ?? ''));

$rawHost  = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$safeHost = preg_replace('/[^a-zA-Z0-9.:-]/', '', $rawHost);
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl  = rtrim($scheme . '://' . $safeHost, '/');
$cardUrl  = $baseUrl . '/?card=business&slug=' . urlencode($slug);

$photoUrl = !empty($person['photo'])
    ? $baseUrl . '/images/' . rawurlencode(basename((string)$person['photo']))
    : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1e3a5f&color=ffffff&size=200';
$logoUrl  = !empty($company['logo']) ? $baseUrl . '/images/' . rawurlencode(basename((string)$company['logo'])) : '';

$hex = ltrim((string)($company['brand_color'] ?? '#1e3a5f'), '#');
if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) $hex = '1e3a5f';

// ── Social links: personal-over-company, same validated merge as the
// digital business card (cards/business.php). This file never resolved
// socials at all before — needed now to add hyperlinked icons on the
// website row.
$_personSocial  = is_array($person['social']  ?? null) ? $person['social']  : [];
$_companySocial = is_array($company['social'] ?? null) ? $company['social'] : [];
$_validUrl = function ($v): string {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return '';
    if (!preg_match('~^[a-z][a-z0-9+.\-]*:~i', $v)) $v = 'https://' . ltrim($v, '/');
    return preg_match('~^https?://~i', $v) ? $v : '';
};
// Small, letter-badge icons — not icon-font glyphs (Gmail strips <style>,
// most clients have no icon font installed) and not hosted image files
// (blocked by default in most clients until "show images" is clicked, and
// this project has no bundled icon-image assets to host). A coloured table
// cell with a one/two-letter mark is plain HTML/inline-CSS, renders
// identically everywhere, and is exactly what several professional
// signature tools fall back to for the same reason.
$socialMeta = [
    'linkedin'  => ['in', '0a66c2'],
    'twitter'   => ['X',  '000000'],
    'instagram' => ['ig', 'c026d3'],
    'facebook'  => ['f',  '1877f2'],
    'youtube'   => ['yt', 'ff0000'],
    'telegram'  => ['tg', '229ed9'],
];
$socialLinks = [];
foreach ($socialMeta as $_k => [$_label, $_col]) {
    $_v = $_validUrl($_personSocial[$_k] ?? '');
    if ($_v === '') $_v = $_validUrl($_companySocial[$_k] ?? '');
    if ($_v !== '') $socialLinks[] = ['url' => $_v, 'label' => $_label, 'color' => $_col];
}

/**
 * Email-client HTML is a hostile environment: Outlook renders via Word's
 * engine (no flexbox/grid, only table layout and inline styles), and Gmail
 * strips <style> blocks entirely. So the markup below is intentionally
 * old-fashioned — nested <table>s, every style inline, no CSS classes — the
 * same constraints professional signature tools operate under.
 */
function sig_html(array $d): string {
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $rows = '';
    $line = function (string $icon, string $text, ?string $href = null) use (&$rows, $e) {
        if ($text === '') return;
        $inner = $href
            ? '<a href="' . $e($href) . '" style="color:#334155;text-decoration:none;">' . $e($text) . '</a>'
            : $e($text);
        $rows .= '<tr><td style="padding:1px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#334155;">'
               . $icon . '&nbsp; ' . $inner . '</td></tr>';
    };
    $line('&#9742;', $d['phone'],   $d['phone']   ? 'tel:' . preg_replace('/[^0-9+]/', '', $d['phone']) : null);
    $line('&#9993;', $d['email'],   $d['email']   ? 'mailto:' . $d['email'] : null);
    $line('&#127760;', preg_replace('~^https?://~', '', $d['website']), $d['website'] ?: null);

    // Social icons, added right after the website line. Not icon-font
    // glyphs (Gmail strips <style>, most mail clients have no icon font)
    // and not hosted image files (blocked by default until "show images"
    // is clicked, and this project has no bundled icon assets to host
    // anyway) — small coloured letter-badges in a plain inline-styled
    // table, which renders identically everywhere a table does.
    if (!empty($d['socials'])) {
        $badges = '';
        foreach ($d['socials'] as $s) {
            $badges .= '<a href="' . $e($s['url']) . '" style="display:inline-block;width:20px;height:20px;'
                     . 'line-height:20px;text-align:center;border-radius:5px;background:#' . $e($s['color']) . ';'
                     . 'color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:9px;font-weight:bold;'
                     . 'text-decoration:none;margin-right:5px;">' . $e($s['label']) . '</a>';
        }
        $rows .= '<tr><td style="padding:5px 0 1px;">' . $badges . '</td></tr>';
    }

    $logoCell = $d['logo']
        ? '<td style="padding-left:18px;vertical-align:middle;"><img src="' . $e($d['logo']) . '" alt="" height="37" style="display:block;border:0;max-height:37px;width:auto;"></td>'
        : '';

    return '
<table cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,Helvetica,sans-serif;">
  <tr>
    <td style="vertical-align:top;">
      <img src="' . $e($d['photo']) . '" width="72" height="72" alt="' . $e($d['name']) . '"
           style="border-radius:12px;display:block;border:1px solid #e2e8f0;">
    </td>
    <td style="vertical-align:top;padding-left:16px;border-left:1px solid #e2e8f0;padding-left:16px;">
      <table cellpadding="0" cellspacing="0" border="0">
        <tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#0f172a;padding-bottom:2px;">' . $e($d['name']) . '</td></tr>'
        . ($d['role'] ? '<tr><td style="font-family:Arial,Helvetica,sans-serif;font-size:12.5px;color:#' . $e($d['hex']) . ';font-weight:bold;padding-bottom:6px;">' . $e($d['role']) . ($d['org'] ? ' &middot; ' . $e($d['org']) : '') . '</td></tr>' : '')
        . $rows . '
        <tr><td style="padding-top:8px;"><a href="' . $e($d['cardUrl']) . '" style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#94a3b8;text-decoration:none;">View digital card &rarr;</a></td></tr>
      </table>
    </td>' . $logoCell . '
  </tr>
</table>';
}

$sigHtml = sig_html([
    'name' => $name, 'role' => $role, 'org' => $orgName, 'phone' => $phone,
    'email' => $email, 'website' => $website, 'photo' => $photoUrl, 'logo' => $logoUrl,
    'hex' => $hex, 'cardUrl' => $cardUrl, 'socials' => $socialLinks,
]);

// Open Graph / Twitter — same branded og-{slug}.jpg as the business card
$ogSlug = class_exists('AppUtils') ? AppUtils::sanitizeSlug($slug) : preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
$ogFile = 'og-' . $ogSlug . '.jpg';
if (class_exists('AppMedia') && (!is_file(IMG_PATH . '/' . $ogFile))) {
    $generated = AppMedia::generateOgImage($person, $company, $ogSlug);
    if ($generated) $ogFile = $generated;
}
$ogImage = is_file(IMG_PATH . '/' . $ogFile)
    ? $baseUrl . '/images/' . rawurlencode($ogFile)
    : $photoUrl;
$ogTitle = 'Email Signature — ' . $name . ($orgName !== '' ? ' · ' . $orgName : '');
$ogDesc  = 'Copy-ready email signature for ' . $name
         . ($role !== '' ? ', ' . $role : '')
         . ($orgName !== '' ? ' at ' . $orgName : '') . '.';
$sigPageUrl = $baseUrl . '/cards/signature.php?slug=' . rawurlencode($slug);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Email Signature — <?= htmlspecialchars($name) ?></title>
<meta name="description" content="<?= htmlspecialchars($ogDesc) ?>">
<meta property="og:site_name" content="<?= htmlspecialchars($orgName !== '' ? $orgName : 'Corporate Directory') ?>">
<meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
<meta property="og:image:secure_url" content="<?= htmlspecialchars($ogImage) ?>">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="<?= htmlspecialchars($sigPageUrl) ?>">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($ogDesc) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',system-ui,sans-serif;background:#f1f5f9;color:#0f172a;padding:24px 16px calc(24px + env(safe-area-inset-bottom));-webkit-font-smoothing:antialiased}
    .wrap{max-width:640px;margin:0 auto}
    h1{font-size:19px;font-weight:700;margin-bottom:4px}
    .sub{font-size:13px;color:#64748b;margin-bottom:20px}
    .panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:22px;margin-bottom:16px}
    .panel-label{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;margin-bottom:12px}
    .preview{border:1px dashed #cbd5e1;border-radius:10px;padding:18px;background:#fafbfc;overflow-x:auto}
    .steps{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:20px 22px}
    .steps h2{font-size:13px;font-weight:700;margin-bottom:10px}
    .steps ol{padding-left:18px;font-size:13px;color:#334155;line-height:1.9}
    .btnrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
    button,.btn{font:600 13px 'Inter',sans-serif;padding:10px 16px;border-radius:9px;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
    .btn.primary{background:#0f172a;color:#fff;border-color:#0f172a}
    .copied{background:#16a34a!important;color:#fff!important;border-color:#16a34a!important}
    @media (max-width:480px){.panel,.steps{padding:16px}}
</style>
</head>
<body>
<div class="wrap">
    <h1>Email Signature</h1>
    <p class="sub">Generated from <?= htmlspecialchars($name) ?>'s digital card. Nothing here is uploaded anywhere — copy it straight into your email client.</p>

    <div class="panel">
        <div class="panel-label">Preview</div>
        <div class="preview" id="sigPreview"><?= $sigHtml ?></div>
        <div class="btnrow">
            <button class="btn primary" id="copyBtn" onclick="copySig()"><i>&#128203;</i> Copy Signature</button>
            <button class="btn" id="srcBtn" onclick="toggleSrc()">View HTML source</button>
        </div>
        <textarea id="sigSource" readonly style="display:none;width:100%;margin-top:12px;min-height:160px;
            font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11px;padding:12px;border-radius:8px;
            border:1px solid #e2e8f0;background:#0f172a;color:#86efac;resize:vertical;"></textarea>
    </div>

    <div class="steps">
        <h2>Gmail</h2>
        <ol>
            <li>Copy the signature above.</li>
            <li>Settings (gear icon) &rarr; <strong>See all settings</strong> &rarr; General &rarr; Signature.</li>
            <li>Create or edit a signature, click inside the box, and paste (Ctrl/Cmd+V).</li>
            <li>Save Changes.</li>
        </ol>
        <h2 style="margin-top:16px">Outlook (desktop)</h2>
        <ol>
            <li>File &rarr; Options &rarr; Mail &rarr; Signatures.</li>
            <li>New signature, paste into the editing box, then set it as default for new messages and replies.</li>
        </ol>
        <h2 style="margin-top:16px">Outlook (web) / Apple Mail</h2>
        <ol>
            <li>Settings &rarr; Mail &rarr; Compose and reply &rarr; paste into the signature box (Outlook Web), or Mail &rarr; Settings &rarr; Signatures (Apple Mail).</li>
        </ol>
    </div>
</div>

<script>
function toggleSrc() {
    var box = document.getElementById('sigSource');
    var btn = document.getElementById('srcBtn');
    var showing = box.style.display !== 'none';
    if (showing) { box.style.display = 'none'; btn.textContent = 'View HTML source'; return; }
    box.value = document.getElementById('sigPreview').innerHTML.trim();
    box.style.display = 'block';
    btn.textContent = 'Hide HTML source';
}

function copySig() {
    var el = document.getElementById('sigPreview');
    var range = document.createRange();
    range.selectNodeContents(el);
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);

    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    sel.removeAllRanges();

    var btn = document.getElementById('copyBtn');
    if (ok) {
        btn.classList.add('copied');
        btn.innerHTML = '&#10003; Copied — now paste into your email client';
        setTimeout(function () { btn.classList.remove('copied'); btn.innerHTML = '<i>&#128203;</i> Copy Signature'; }, 2500);
    } else {
        alert('Could not copy automatically. Please select the signature above manually and copy it (Ctrl/Cmd+C).');
    }
}
</script>
</body>
</html>
