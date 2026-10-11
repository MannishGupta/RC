<?php
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | Release v14.1.0
   consent.php — records a visitor's DPDP consent decision.

   Under the Digital Personal Data Protection Act 2023, consent must be a clear
   affirmative action and must be as easy to withdraw as it was to give. This
   endpoint handles both directions, plus an erasure request.

   Actions
     grant     → analytics processing permitted
     withdraw  → analytics processing refused; existing rows for this visitor
                 are erased as well, because withdrawal should not leave the
                 previously collected data sitting there
     erase     → Right to Erasure for a specific visitor id
   ───────────────────────────────────────────────────────────────────────── */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
AppLocale::boot();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   isHttps() ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name(cfg('site.cookie_prefix', 'fusion') . '_public_session');
session_start();

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

/* Consent must be the visitor's own act, so reject cross-site submissions:
   forced consent is not valid consent under the Act. */
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$refer  = (string)($_SERVER['HTTP_REFERER'] ?? '');
$host   = (string)($_SERVER['HTTP_HOST'] ?? '');
$sameSite = static function (string $url) use ($host): bool {
    if ($url === '') {
        return true;   // some browsers omit both on a same-origin form POST
    }
    $h = parse_url($url, PHP_URL_HOST);
    $p = parse_url($url, PHP_URL_PORT);
    if ($h === null) {
        return false;
    }
    return strcasecmp($h . ($p ? ':' . $p : ''), $host) === 0 || strcasecmp((string)$h, explode(':', $host)[0]) === 0;
};
if (!$sameSite($origin) || !$sameSite($refer)) {
    http_response_code(403);
    exit('Cross-site request refused.');
}

$nonce = (string)($_SESSION['_consent_nonce'] ?? '');
if ($nonce === '' || !hash_equals($nonce, (string)($_POST['_nonce'] ?? ''))) {
    http_response_code(419);
    exit('This page expired. Please reload and try again.');
}

$action = (string)($_POST['action'] ?? '');
$back   = appUrl('index.php');
$msg    = '';

switch ($action) {
    case 'grant':
        consentStore('analytics');
        $msg = 'consent=granted';
        break;

    case 'essential':
        consentStore('essential');
        $msg = 'consent=essential';
        break;

    case 'withdraw':
        consentStore('essential');
        // Withdrawal erases what was already collected for this visitor.
        $vid = (string)($_POST['visitor_id'] ?? '');
        if ($vid !== '') {
            try {
                analyticsEraseVisitor($vid);
            } catch (Throwable $e) {
                error_log('consent withdraw erase: ' . $e->getMessage());
            }
        }
        $msg  = 'consent=withdrawn';
        $back = appUrl('privacy.php');
        break;

    case 'erase':
        $vid     = (string)($_POST['visitor_id'] ?? '');
        $removed = 0;
        try {
            $removed = analyticsEraseVisitor($vid);
        } catch (Throwable $e) {
            error_log('consent erase: ' . $e->getMessage());
        }
        $msg  = 'erased=' . $removed;
        $back = appUrl('privacy.php');
        break;

    default:
        http_response_code(400);
        exit('Unknown action.');
}

header('Location: ' . $back . '?' . $msg);
exit;
