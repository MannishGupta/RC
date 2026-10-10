<?php
declare(strict_types=1);
/**
 * Simple speed-check page for administrators.
 * Version: 20260929.23
 */
$root = dirname(__DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $root);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';
require_once BASE_PATH . '/app/bootstrap.php';
if (class_exists('AppAuth') && method_exists('AppAuth', 'initSession')) {
    AppAuth::initSession();
} elseif (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$user = (string)($_SESSION['user'] ?? '');
$true = (string)($_SESSION['true_role'] ?? $user);
$ok = in_array($user, ['super_admin', 'admin'], true)
    || in_array($true, ['super_admin', 'admin'], true);
if (!$ok) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem"><p>Please sign in as administrator first.</p></body></html>';
    exit;
}

$ext = extension_loaded('Zend OPcache') || extension_loaded('opcache');
$hits = 0;
$misses = 0;
$scripts = 0;
$enabledFlag = false;
$st = null;
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    if (is_array($st)) {
        $enabledFlag = !empty($st['opcache_enabled']);
        $scripts = (int)($st['opcache_statistics']['num_cached_scripts'] ?? 0);
        $hits = (int)($st['opcache_statistics']['hits'] ?? 0);
        $misses = (int)($st['opcache_statistics']['misses'] ?? 0);
    }
}

// Practical verdict: if scripts are cached and hits exist, speed boost is ON
$working = $ext && ($scripts > 0 || $hits > 0 || $enabledFlag);
$ratio = ($hits + $misses) > 0 ? round(100 * $hits / ($hits + $misses), 1) : 0;

$wantJson = isset($_GET['json']) || (strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
if ($wantJson) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => $working,
        'message' => $working
            ? 'Site speed helper is ON and working.'
            : 'Site speed helper is not active. Ask hosting support to enable PHP OPcache.',
        'hits' => $hits,
        'misses' => $misses,
        'cached_files' => $scripts,
        'hit_rate_percent' => $ratio,
        'php_version' => PHP_VERSION,
    ], JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
$color = $working ? '#166534' : '#b91c1c';
$bg = $working ? '#dcfce7' : '#fee2e2';
$label = $working ? 'GOOD — speed helper is working' : 'NEEDS HOSTING HELP — speed helper off';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Resource Centre OPcache status — restricted operator tool.">
<meta name="robots" content="noindex,nofollow">
<title>Site speed check</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto; padding: 0 1rem; color: #0f172a; }
    .card { border-radius: 12px; padding: 1.25rem; background: <?= $bg ?>; border: 1px solid <?= $color ?>; }
    h1 { font-size: 1.15rem; margin: 0 0 0.5rem; color: <?= $color ?>; }
    p { margin: 0.4rem 0; line-height: 1.45; }
    .muted { color: #475569; font-size: 0.9rem; }
    a { color: #1d4ed8; }
  </style>
</head>
<body>
  <div class="card">
    <h1><?= htmlspecialchars($label) ?></h1>
    <?php if ($working): ?>
      <p>Your website is already using a built-in PHP speed helper. No emergency fix is required.</p>
      <p class="muted">Rough score: <?= (int)$ratio ?>% of requests served from cache (<?= (int)$hits ?> fast / <?= (int)$misses ?> slow). Cached files: <?= (int)$scripts ?>.</p>
    <?php else: ?>
      <p>Ask your hosting support (Plesk / BigRock): <strong>“Please enable PHP OPcache for this domain.”</strong></p>
      <p class="muted">You do not need to change website files for this.</p>
    <?php endif; ?>
    <p class="muted">PHP <?= htmlspecialchars(PHP_VERSION) ?> · <a href="?">Refresh</a> · <a href="?json=1">Technical details</a></p>
  </div>
  <p class="muted" style="margin-top:1.5rem"><a href="/">← Back to Resource Centre</a></p>
</body>
</html>
