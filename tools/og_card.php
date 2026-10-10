<?php
declare(strict_types=1);
/**
 * Dynamic Open Graph image (SVG) — share previews for cards, Janam Patri, blood reports.
 * GET: name, role, company, kind, group (blood), subtitle
 * Version: 20260929.37
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
@date_default_timezone_set('Asia/Kolkata');

$name = trim((string)($_GET['name'] ?? 'Profile'));
$role = trim((string)($_GET['role'] ?? ''));
$company = trim((string)($_GET['company'] ?? 'Arthsathi · Resource Centre'));
$kind = trim((string)($_GET['kind'] ?? 'Report'));
$group = trim((string)($_GET['group'] ?? ''));
$subtitle = trim((string)($_GET['subtitle'] ?? ''));

if ($role === '' && $group !== '') {
    $role = 'Blood group ' . $group;
}
if ($subtitle !== '' && $role === '') {
    $role = $subtitle;
}

$esc = static function (string $s): string {
    if (function_exists('mb_substr')) {
        $s = mb_substr($s, 0, 80, 'UTF-8');
    } else {
        $s = substr($s, 0, 80);
    }
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
};

// Luxury wine gradient for Vedic / medical kinds
$kLower = strtolower($kind);
$isVedic = str_contains($kLower, 'janam') || str_contains($kLower, 'kundli') || str_contains($kLower, 'patri') || str_contains($kLower, 'milan');
$isBlood = str_contains($kLower, 'blood') || $group !== '';

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
<?php if ($isBlood): ?>
      <stop offset="0%" stop-color="#1a0a0c"/>
      <stop offset="50%" stop-color="#3b0f18"/>
      <stop offset="100%" stop-color="#7f1d1d"/>
<?php elseif ($isVedic): ?>
      <stop offset="0%" stop-color="#1a0a0c"/>
      <stop offset="45%" stop-color="#2a1216"/>
      <stop offset="100%" stop-color="#5c3038"/>
<?php else: ?>
      <stop offset="0%" stop-color="#0f172a"/>
      <stop offset="55%" stop-color="#1e293b"/>
      <stop offset="100%" stop-color="#1d4ed8"/>
<?php endif; ?>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <circle cx="1080" cy="80" r="160" fill="#d4af6a" fill-opacity="0.15"/>
  <circle cx="100" cy="560" r="120" fill="#e8d5a3" fill-opacity="0.12"/>
  <rect x="56" y="56" width="1088" height="518" rx="28" fill="#0b1220" fill-opacity="0.45" stroke="#e8d5a3" stroke-opacity="0.35" stroke-width="2"/>
  <text x="96" y="150" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="28" font-weight="600" fill="#e8d5a3"><?= $esc(strtoupper($kind)) ?></text>
  <text x="96" y="260" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="64" font-weight="800" fill="#f5efe6"><?= $esc($name) ?></text>
  <?php if ($role !== ''): ?>
  <text x="96" y="330" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="34" font-weight="600" fill="#d4af6a"><?= $esc($role) ?></text>
  <?php endif; ?>
  <text x="96" y="500" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="28" fill="#ebe0d0"><?= $esc($company) ?></text>
  <text x="96" y="545" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="20" fill="#b8a68a">Site developer: Arthsathi Limited · Resource Centre</text>
</svg>
