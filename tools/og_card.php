<?php
declare(strict_types=1);
/**
 * Dynamic Open Graph image (SVG) for digital cards — commercial share previews.
 * Usage: /tools/og_card.php?name=...&role=...&company=...
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
@date_default_timezone_set('Asia/Kolkata');

$name = trim((string)($_GET['name'] ?? 'Contact'));
$role = trim((string)($_GET['role'] ?? ''));
$company = trim((string)($_GET['company'] ?? 'Resource Centre'));
$kind = trim((string)($_GET['kind'] ?? 'Card'));

$esc = static function (string $s): string {
    return htmlspecialchars(mb_substr($s, 0, 80), ENT_QUOTES | ENT_XML1, 'UTF-8');
};

header('Content-Type: image/svg+xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#0f172a"/>
      <stop offset="55%" stop-color="#1e293b"/>
      <stop offset="100%" stop-color="#1d4ed8"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <circle cx="1080" cy="80" r="160" fill="#3b82f6" fill-opacity="0.2"/>
  <circle cx="100" cy="560" r="120" fill="#6366f1" fill-opacity="0.25"/>
  <rect x="56" y="56" width="1088" height="518" rx="28" fill="#0b1220" fill-opacity="0.55" stroke="#e2e8f0" stroke-opacity="0.2" stroke-width="2"/>
  <text x="96" y="150" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="28" font-weight="600" fill="#93c5fd"><?= $esc(strtoupper($kind)) ?></text>
  <text x="96" y="260" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="64" font-weight="800" fill="#f8fafc"><?= $esc($name) ?></text>
  <?php if ($role !== ''): ?>
  <text x="96" y="330" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="34" font-weight="600" fill="#bfdbfe"><?= $esc($role) ?></text>
  <?php endif; ?>
  <text x="96" y="500" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="28" fill="#cbd5e1"><?= $esc($company) ?></text>
  <text x="96" y="545" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="20" fill="#94a3b8">Official digital card · Resource Centre</text>
</svg>
