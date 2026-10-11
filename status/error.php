<?php
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | Release v14.1.0
   error.php — custom error page. Referenced by <httpErrors> if you enable it.
   Safe to deploy even when web.config does not use it. (Audit §16)
   ───────────────────────────────────────────────────────────────────────── */
declare(strict_types=1);
require_once __DIR__ . '/lib.php';
AppLocale::boot();

$code = (int)($_GET['code'] ?? 500);
if (!in_array($code, [400, 403, 404, 500], true)) { $code = 500; }
http_response_code($code);

$titles = [400 => 'Bad request', 403 => 'Not available', 404 => 'Page not found', 500 => 'Temporarily unavailable'];
$blurbs = [
    400 => 'That request could not be understood.',
    403 => 'This resource is not available.',
    404 => 'The page you asked for does not exist.',
    500 => 'This page could not be displayed. Please try again shortly.',
];
$company = (string)cfg('brand.company', '');

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
?><!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(AppLocale::lang(), ENT_QUOTES, 'UTF-8'); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo htmlspecialchars($titles[$code], ENT_QUOTES, 'UTF-8'); ?><?php echo $company ? ' — ' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') : ''; ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?php echo @filemtime(__DIR__ . '/assets/app.css') ?: time(); ?>">
</head>
<body class="err-body">
  <div class="err-card">
    <p class="err-code"><?php echo $code; ?></p>
    <h1><?php echo htmlspecialchars($titles[$code], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?php echo htmlspecialchars($blurbs[$code], ENT_QUOTES, 'UTF-8'); ?></p>
    <a href="<?php echo htmlspecialchars(appBaseUrl(), ENT_QUOTES, 'UTF-8'); ?>">Back to the dashboard</a>
  </div>
</body>
</html>
