<?php declare(strict_types=1);
// index.php
// Version: 260916.14
//
// CHANGELOG v2027.300 (PHP 8.4.2 / WinNT+Plesk hardening pass):
//  - SECURITY/CRITICAL: `display_errors` is now OFF in production. The
//    previous version set `ini_set('display_errors','1')` and
//    `error_reporting(E_ALL)` directly in this file, meaning any PHP
//    warning, deprecation notice, or uncaught error/exception could print
//    full stack traces (including server file paths) straight into API
//    responses and rendered pages. A global error + exception handler now
//    logs everything via AppLog instead, and returns a generic message.
//    This matters specifically on PHP 8.4: this file declares
//    strict_types=1, so any scalar-typed call made from here with a
//    mismatched type now throws a fatal TypeError instead of silently
//    coercing — previously that would have been printed verbatim to users.
//  - SECURITY: Hardcoded plaintext passwords ('lifeisgood' / 'thankyougod')
//    removed. Login now goes through AppAuth::verifyCredentials(), which
//    checks bcrypt hashes in data/auth_config.php. See that file — rotate
//    the passwords there.
//  - SECURITY: The upload handler's file-type filter previously only
//    blocked filenames containing ".php" (weaker than AppMedia::import()'s
//    own check, and NOT applied to document uploads at all). All uploads —
//    including doc_file — now go through the shared
//    AppMedia::isDangerousUpload() deny-list, which also blocks
//    .asp/.aspx/.phtml/.phar/etc. This closes a real remote-code-execution
//    path on an IIS/Plesk host, where .aspx is natively server-executable.
//  - No behavioural/API changes beyond the above. Every action, response
//    shape, and side effect is otherwise identical to the previous version.

// ── Production-safe error handling ──────────────────────────────────────────
// Errors are always logged; they are NEVER echoed to the client.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Kolkata');

// PHP floor: 8.1 (array_is_list + match + str_*). Target production: 8.4.x
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Resource Centre requires PHP 8.1 or newer (this host: ' . PHP_VERSION . '). Recommended: PHP 8.2–8.4.';
    exit;
}
if (function_exists('locale_set_default')) { @locale_set_default('en_IN'); }

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) return false; // respects @-suppression
    if (!class_exists('AppLog')) return true;
    // Notices/warnings → warn level (do not flood ERROR / look like fatal production outages)
    $isNoise = in_array($errno, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT], true)
        || (defined('E_WARNING') && in_array($errno, [E_WARNING, E_USER_WARNING], true) && (
            str_contains($errstr, 'Undefined variable')
            || str_contains($errstr, 'Undefined array key')
            || str_contains($errstr, 'Trying to access array offset')
        ));
    if ($isNoise) {
        if (method_exists('AppLog', 'warn')) {
            AppLog::warn("PHP [{$errno}]: {$errstr}", ['file' => basename($errfile), 'line' => $errline]);
        }
        return true;
    }
    AppLog::error("PHP Error [{$errno}]: {$errstr}", ['file' => basename($errfile), 'line' => $errline]);
    return true;
});

set_exception_handler(function (\Throwable $e): void {
    if (class_exists('AppLog')) {
        AppLog::critical('Uncaught exception: ' . $e->getMessage(), [
            'file' => basename($e->getFile()), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString(),
        ]);
    }
    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    $ctype  = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    $isJson = (strpos($accept, 'application/json') !== false)
        || (strpos($ctype, 'json') !== false)
        || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && strpos($ctype, 'json') !== false);
    if (!headers_sent()) {
        // Prefer 200 + JSON error for XHR optimise so UI can show message (not bare HTTP 500)
        if ($isJson) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'message' => 'An unexpected server error occurred. The issue has been logged.',
                'logs' => ['CRITICAL: An unexpected server error occurred.'],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        http_response_code(500);
    }
    echo "<div style='font-family:sans-serif;padding:2rem;text-align:center;color:#b91c1c'>Something went wrong. The issue has been logged.</div>";
});

// ── The gap that caused the "numero blank page" mystery ────────────────────
// set_error_handler() and set_exception_handler() above catch every warning,
// notice and THROWN Error/Exception — but PHP's genuine fatal-error class
// (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR — a syntax error in an
// included file, memory exhaustion, a max-execution-time timeout) bypasses
// BOTH handlers entirely and simply kills the script. Those handlers WERE
// registered before every route in this file, including numero's, and were
// therefore active — so a numero fatal that produced nothing anywhere is
// consistent with exactly this class of error, the one kind neither handler
// above can ever see.
// register_shutdown_function() + error_get_last() is the only way to catch
// it: PHP always runs registered shutdown functions on exit, fatal or not,
// and error_get_last() reports what killed the script if anything did.
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return; // normal exit, or something already handled above — nothing to do
    }

    if (class_exists('AppLog')) {
        AppLog::critical('Fatal error: ' . $e['message'], [
            'file' => basename((string)$e['file']),
            'line' => (int)$e['line'],
            'type' => match ($e['type']) {
                E_ERROR => 'E_ERROR', E_PARSE => 'E_PARSE',
                E_CORE_ERROR => 'E_CORE_ERROR', E_COMPILE_ERROR => 'E_COMPILE_ERROR',
                default => (string)$e['type'],
            },
            'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        ]);
    }

    // A fatal this late may have already sent partial output (headers, a
    // half-rendered page) — do not risk corrupting that with a second
    // response body. Only speak if nothing has gone out yet.
    if (!headers_sent() && ob_get_level() > 0 && ob_get_length() === 0) {
        http_response_code(500);
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if (strpos($accept, 'application/json') !== false) {
            echo json_encode(['status' => 'error', 'message' => 'An unexpected server error occurred.']);
        } else {
            echo "<div style='font-family:sans-serif;padding:2rem;text-align:center;color:#b91c1c'>Something went wrong. The issue has been logged.</div>";
        }
    }
});

ob_start();

// GLOBAL UTF-8 SAFETY NET (added after diagnosing WA_EMOJIS corruption):
// This host's PHP was found to be silently corrupting raw multi-byte UTF-8
// bytes emitted via json_encode(..., JSON_UNESCAPED_UNICODE) — almost
// certainly mbstring's output-buffering layer attempting (and failing) an
// automatic charset conversion. That bug wasn't limited to emoji: the same
// mechanism would threaten the Hindi (Devanagari) text used throughout the
// numerology report templates, which is also raw non-ASCII UTF-8. Disabling
// mbstring's output conversion here, plus an explicit charset header, closes
// that off globally rather than leaving it to resurface unpredictably.
if (function_exists('mb_http_output')) {
    mb_internal_encoding('UTF-8');
    mb_http_output('pass'); // never let mbstring re-encode our output
}
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

define('BASE_PATH', __DIR__);
/**
 * Multi-tenant: domain / rc.{tenant}.* wildcard → tenants/{id}/data/
 * See app/tenant_bootstrap.php and tenants/map.json
 * Single-site (no tenants/): still uses BASE_PATH/data.
 */
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (is_file(BASE_PATH . '/app/TenantTombstone.php')) { require_once BASE_PATH . '/app/TenantTombstone.php'; }
if (is_file(BASE_PATH . '/app/VehicleCatalog.php')) { require_once BASE_PATH . '/app/VehicleCatalog.php'; }
if (class_exists('HostPolicy') && !HostPolicy::isHostAllowed((string)($_SERVER['HTTP_HOST'] ?? ''))) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Host not authorised for this Resource Centre (strict_hosts).';
    exit;
}

// tenant_bootstrap defines DATA_PATH, IMG_PATH, DOC_PATH, SESSION_*, TENANT_ID, …

if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
foreach ([
    DATA_PATH,
    defined('IMG_PATH') ? IMG_PATH : (DATA_PATH . '/media/images'),
    defined('DOC_PATH') ? DOC_PATH : (DATA_PATH . '/media/documents'),
    defined('SESSION_PATH') ? SESSION_PATH : (DATA_PATH . '/sessions'),
    defined('LOG_PATH') ? LOG_PATH : (DATA_PATH . '/logs'),
    defined('BACKUP_PATH') ? BACKUP_PATH : (DATA_PATH . '/backups'),
    defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : (DATA_PATH . '/dispatch'),
    defined('JANAM_DATA_PATH') ? JANAM_DATA_PATH : (DATA_PATH . '/janam'),
    defined('VALUE_DATA_PATH') ? VALUE_DATA_PATH : (DATA_PATH . '/value'),
    defined('RUNNERS_DATA_PATH') ? RUNNERS_DATA_PATH : (DATA_PATH . '/runners'),
    defined('CONFIG_DATA_PATH') ? CONFIG_DATA_PATH : (DATA_PATH . '/config'),
    BASE_PATH . '/tools',
] as $dir) {
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
}

/**
 * Windows/IIS-safe require: retries when FTP/AV locks a file
 * ("Resource temporarily unavailable").
 */
if (!function_exists('rc_require_once')) {
    function rc_require_once(string $path, bool $required = true): bool {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (!is_file($path)) {
            if ($required) {
                http_response_code(503);
                header('Content-Type: text/plain; charset=UTF-8');
                header('Retry-After: 5');
                echo "Resource Centre temporarily unavailable.\nMissing: " . basename($path) . "\n";
                echo "If you just uploaded files via FTP, wait for the transfer to finish, then reload.\n";
                exit;
            }
            return false;
        }
        $last = null;
        for ($i = 0; $i < 8; $i++) {
            try {
                require_once $path;
                return true;
            } catch (\Throwable $e) {
                $last = $e;
                $msg = $e->getMessage();
                if (stripos($msg, 'temporarily unavailable') === false
                    && stripos($msg, 'Failed opening required') === false
                    && stripos($msg, 'Failed to open stream') === false) {
                    throw $e;
                }
                usleep(50_000 * ($i + 1)); // 50ms, 100ms, …
            }
        }
        // Fallback: include once more without catch for clear error
        if ($required) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Retry-After: 10');
            echo "Resource Centre temporarily unavailable (file lock).\n";
            echo "File: " . basename($path) . "\n";
            echo "Finish FTP upload, disable AV scan on the site folder if needed, then reload.\n";
            if ($last) {
                echo "Detail: " . $last->getMessage() . "\n";
            }
            exit;
        }
        return false;
    }
}

rc_require_once(BASE_PATH . '/app/bootstrap.php', true);
// Defensive request routing (Phase-1)
$action = class_exists('RcRequest') ? RcRequest::action('dashboard') : (string)($_GET['action'] ?? $_POST['action'] ?? 'dashboard');

if (class_exists('AppLocale')) { AppLocale::boot(); }

AppAuth::setSecureHeaders();
AppAuth::initSession();

/* Cache policy: public cards brief cache; signed-in UI no-store */
if (class_exists('RcCachePolicy', false)) {
    RcCachePolicy::applyForRequest();
} elseif (class_exists('RcPublicCache')) {
    $__hasUser = !empty($_SESSION['user']) && (string)$_SESSION['user'] !== 'public';
    $__isCard = !empty($_GET['card']) || !empty($_GET['slug']);
    if (!$__hasUser && $__isCard) {
        RcPublicCache::sendCardHeaders(180);
    } elseif ($__hasUser) {
        RcPublicCache::sendNoStore();
    }
    unset($__hasUser, $__isCard);
}


/* RC_ACTION_BOOT: never leave $action undefined for error handlers / partial includes */
if (!isset($action) || !is_string($action)) {
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
}
if (!isset($input) || !is_array($input)) {
    $input = [];
}

rc_require_once(BASE_PATH . '/app/Optimizer.php', true);
foreach (['DataCache.php','PublicTeam.php','TenantBackup.php','AssetHelper.php','PathJail.php','AuditLog.php','ShareToken.php','Redaction.php','CardCache.php','HostPolicy.php','TenantPaths.php','RolePack.php'] as $_rcf) {
    rc_require_once(BASE_PATH . '/app/' . $_rcf, false);
}

$rawSlug  = trim((string)($_GET['slug'] ?? ''));
$cardType = trim((string)($_GET['card'] ?? 'business'));

if (empty($rawSlug)) {
    $reqPath  = trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''), '/');
    $sysPaths = ['index.php', 'version.php', 'tools', 'api', 'images', 'data', 'docs', 'cards', 'asset', 'app', 'vehicle-tags', 'favicon.ico'];
    if (!empty($reqPath) && !in_array($reqPath, $sysPaths, true) && !file_exists($reqPath)) $rawSlug = $reqPath;
}

if ($cardType === 'numero') {
    if (ob_get_length()) ob_end_clean();
    $numeroPaths = [BASE_PATH . '/cards/numero.php'];
    foreach ($numeroPaths as $path) { if (file_exists($path)) { require $path; exit; } }
    die("Diagnostic Engine Error: card_numero.php not found.");
}

if (!empty($rawSlug)) {
    $actualSlug = null;

    $indexFile = DATA_PATH . '/team_index.json';
    if (file_exists($indexFile)) {
        $index = json_decode((string)file_get_contents($indexFile), true) ?: [];
        $searchKey = strtolower($rawSlug);
        if (isset($index[$searchKey])) {
            $teamData = AppDB::read('team');
            if (isset($teamData[$index[$searchKey]])) {
                $actualSlug = $teamData[$index[$searchKey]]['slug'] ?? null;
            }
        }
    }

    if (!$actualSlug) {
        $teamData = AppDB::read('team');
        foreach ($teamData as $member) {
            if (strcasecmp((string)($member['slug'] ?? ''), $rawSlug) === 0) {
                $actualSlug = (string)$member['slug'];
                break;
            }
        }
    }

    if ($actualSlug) {
        // Public URL shape (?card=business&slug=…) is UNCHANGED — only the
        // server-side filenames lost their redundant 'card_' prefix.
        $viewMap  = ['business' => 'cards/business.php', 'visiting' => 'cards/visiting.php', 'qr' => 'cards/qr.php', 'id' => 'cards/id.php'];
        $viewFile = $viewMap[$cardType] ?? 'cards/business.php';

        // Optional signed share (ShareToken); legacy unsigned links still work unless require_sig
        if (class_exists('ShareToken')) {
            $sig = isset($_GET['sig']) ? (string)$_GET['sig'] : '';
            $exp = isset($_GET['exp']) ? (int)$_GET['exp'] : 0;
            if (ShareToken::requireSig()) {
                if (!ShareToken::verify($cardType, $actualSlug, $sig, $exp)) {
                    http_response_code(403);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'This share link is missing, invalid, or expired.';
                    exit;
                }
            } elseif ($sig !== '' && $exp > 0 && !ShareToken::verify($cardType, $actualSlug, $sig, $exp)) {
                // Soft-fail: ignore bad sig and continue unsigned
            }
        }

        // Fragment cache (GET only, no preview bots write)
        if (class_exists('CardCache') && empty($_GET['nocache'])) {
            $cached = CardCache::get($cardType, $actualSlug);
            if ($cached !== null) {
                header('X-RC-Card-Cache: HIT');
                echo $cached;
                exit;
            }
        }

        if (file_exists($viewFile)) {
            $cardCtx = class_exists('CardContext') ? CardContext::get($actualSlug) : null;
            if ($cardCtx) {
                extract($cardCtx);
                $person  = $cardCtx['profile'] ?? $cardCtx['person'] ?? [];
                $company = $cardCtx['company'] ?? [];
                if (class_exists('Redaction') && is_array($person)) {
                    $person = Redaction::member($person, Redaction::isVisitorSession(), true);
                    $cardCtx['profile'] = $person;
                    $cardCtx['person'] = $person;
                }

                $seoTags = AppSEO::generateTags($cardCtx);

                // ── View counter ──────────────────────────────────────────
                // One increment per render, keyed by slug + card type
                // (business/visiting/qr/id counted separately, so "3 QR scans"
                // and "40 profile views" stay distinguishable). Deliberately a
                // flat counter file, not a per-event log: an event row per
                // view would grow without bound on a card that gets shared
                // widely, for a number nobody needs at second-by-second
                // resolution. A day bucket is kept too, cheaply, so a simple
                // trend line is possible without full event logging.
                //
                // Excludes bots by user-agent sniff — imperfect, but it stops
                // the obvious cases (link-preview crawlers hitting the URL
                // once per share) from inflating the count.
                $_ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
                if (!preg_match('/bot|crawl|spider|facebookexternalhit|whatsapp|telegrambot|slackbot|preview/i', $_ua)) {
                    $_vf = DATA_PATH . '/card_views.json';
                    $_vd = is_file($_vf) ? (json_decode((string)@file_get_contents($_vf), true) ?: []) : [];
                    $_vk = $actualSlug . ':' . $cardType;
                    $_today = date('Y-m-d');
                    if (!isset($_vd[$_vk])) $_vd[$_vk] = ['total' => 0, 'days' => []];
                    $_vd[$_vk]['total']++;
                    $_vd[$_vk]['days'][$_today] = ($_vd[$_vk]['days'][$_today] ?? 0) + 1;
                    // Cap history per card at 90 days so this file cannot grow
                    // without bound on a card viewed daily for years.
                    if (count($_vd[$_vk]['days']) > 90) {
                        arsort($_vd[$_vk]['days']);
                        $_vd[$_vk]['days'] = array_slice($_vd[$_vk]['days'], 0, 90, true);
                    }
                    @file_put_contents($_vf, json_encode($_vd), LOCK_EX);
                }

                if (ob_get_length()) ob_clean();
                $_GET['slug'] = $actualSlug;
                require $viewFile;
                $html = ob_get_clean();
                $out = strpos($html, '<head>') !== false ? str_replace('<head>', "<head>\n" . $seoTags, $html) : $seoTags . $html;
                if (class_exists('CardCache') && empty($_GET['nocache'])) {
                    CardCache::put($cardType, $actualSlug, $out);
                    header('X-RC-Card-Cache: MISS');
                }
                echo $out;

                exit;
            }
        }
    }
}

/** Human-readable size for the docs list. */
function self_format_bytes(int $b): string {
    if (class_exists(\App\Rc\Support\Bytes::class, false) || class_exists('App\Rc\Support\Bytes')) {
        return \App\Rc\Support\Bytes::format($b);
    }
    if ($b <= 0) return '';
    $u = ['B','KB','MB','GB']; $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return round($b, $b < 10 && $i > 0 ? 1 : 0) . ' ' . $u[$i];
}

function sendJson($data, bool $cacheable = true) {
    // Prefer shared helper when present (always exits)
    $jr = BASE_PATH . '/app/Rc/Http/JsonResponse.php';
    if (is_file($jr)) {
        require_once $jr;
    }
    if (class_exists(\App\Rc\Http\JsonResponse::class, false) || class_exists('App\Rc\Http\JsonResponse')) {
        \App\Rc\Http\JsonResponse::send($data, $cacheable);
        exit; // belt-and-braces if send() ever changes
    }
    // Inline fallback — always emit a body (never 304 when !$cacheable)
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || $json === '') {
        $json = '{"status":"error","message":"JSON encode failed"}';
        $cacheable = false;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Vary: Accept-Encoding, Cookie');
        if ($cacheable) {
            $etag = '"' . hash('sha256', $json) . '"';
            $inm = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
            if ($inm !== '' && (hash_equals($etag, $inm) || str_contains($inm, trim($etag, '"')))) {
                http_response_code(304);
                header('ETag: ' . $etag);
                header('Cache-Control: private, must-revalidate');
                exit;
            }
            header('ETag: ' . $etag);
            header('Cache-Control: private, must-revalidate');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        }
    }
    echo $json;
    exit;
}

/**
 * Pre-generate UPI QR PNG into images/ for a bank record.
 * Returns filename (relative to images/) or null.
 */
function generateBankQrPng(array $bank): ?string {
    if (class_exists(\App\Rc\Domain\Bank\UpiQr::class, false) || class_exists('App\Rc\Domain\Bank\UpiQr')) {
        return \App\Rc\Domain\Bank\UpiQr::generatePng($bank);
    }
    return null;
}




// Request routing primitives — defined before any action dispatch
$input = [];
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

$currentUser = $_SESSION['user'] ?? null;
// Normalize legacy crm → public
if ($currentUser === 'crm') {
    $currentUser = 'public';
    $_SESSION['user'] = 'public';
}
$isSuperAdmin = ($currentUser === 'super_admin');
$isAdmin      = ($currentUser === 'admin' || $isSuperAdmin); // write access
$isPublic     = ($currentUser === 'public');
$isLoggedIn   = ($isAdmin || $isPublic || $isSuperAdmin);

// ── PageSpeed / Lighthouse public preview (temporary shareable PSI URL) ──
// Enable only with a long random token in data/psi_token.txt (one line).
// Open: https://your-host/?psi_preview=YOUR_TOKEN&tab=team
// Then paste that exact URL into pagespeed.web.dev → share the report link.
// Disable: delete data/psi_token.txt (or empty it). Preview is read-only CRM.
$psiPreviewActive = false;
$psiTokenFilePresent = false;
$psiTokenFile = DATA_PATH . '/psi_token.txt';
$psiQuery = (string)($_GET['psi_preview'] ?? '');
if (is_file($psiTokenFile)) {
    $psiTokenFilePresent = true;
    $psiMtime = (int)@filemtime($psiTokenFile);
    $psiAge = time() - $psiMtime;
    $psiRawLine = trim((string)@file_get_contents($psiTokenFile));
    // Token line: SECRET  or  SECRET ips=1.2.3.4,5.6.7.8
    $psiExpected = $psiRawLine;
    $psiAllowIps = [];
    if (preg_match('/^(.*?)\s+ips=([0-9a-fA-F,:.]+)\s*$/', $psiRawLine, $pm)) {
        $psiExpected = trim($pm[1]);
        $psiAllowIps = array_values(array_filter(array_map('trim', explode(',', $pm[2]))));
    }
    if ($psiAge > 21600) { // 6 hours
        if (class_exists('AppLog') && empty($GLOBALS['RC_PSI_EXPIRED_LOGGED'])) {
            $GLOBALS['RC_PSI_EXPIRED_LOGGED'] = true;
            @AppLog::warn('PSI preview token ignored — psi_token.txt older than 6 hours (touch file to renew)', [
                'age_hours' => round($psiAge / 3600, 1),
            ]);
        }
        $psiTokenFilePresent = true; // still warn admins the file exists
    } elseif ($psiQuery !== '' && $psiExpected !== '' && strlen($psiExpected) >= 24
        && hash_equals($psiExpected, $psiQuery)) {
        $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($psiAllowIps !== [] && !in_array($clientIp, $psiAllowIps, true)) {
            if (class_exists('AppLog')) {
                @AppLog::warn('PSI preview rejected — IP not in allowlist', ['ip' => $clientIp]);
            }
        } else {
            $psiPreviewActive = true;
            $_SESSION['user'] = 'public';
            $currentUser = 'public';
            $isAdmin = false;
            $isSuperAdmin = false;
            $isPublic = true;
            $isLoggedIn = true;
            if (!headers_sent()) {
                header('X-Robots-Tag: noindex, nofollow');
                header('Cache-Control: no-store, private');
            }
            if (class_exists('AppLog')) {
                @AppLog::info('PSI preview session granted', [
                    'ip' => $clientIp,
                    'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
                ]);
            }
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // If the whole request exceeds post_max_size, PHP discards $_POST AND
    // $_FILES entirely and carries on — the handler sees an empty POST with no
    // action, and the browser gets a bare page back. CONTENT_LENGTH is still
    // set, so the overflow is detectable, and saying so is far better than the
    // silent nothing that happened before.
    $_postMax = trim((string)ini_get('post_max_size'));
    if ($_postMax !== '') {
        $_unit  = strtoupper(substr($_postMax, -1));
        $_bytes = (int)$_postMax;
        if ($_unit === 'G')      $_bytes *= 1073741824;
        elseif ($_unit === 'M')  $_bytes *= 1048576;
        elseif ($_unit === 'K')  $_bytes *= 1024;
        $_len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($_bytes > 0 && $_len > $_bytes && empty($_POST) && empty($_FILES)) {
            sendJson(['status' => 'error', 'message' =>
                "Upload too large. The whole request must stay under {$_postMax}. "
                . 'Compress the file, or add it as an External URL instead.']);
        }
    }

    $contentType = (string)($_SERVER["CONTENT_TYPE"] ?? '');
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];
        if (!is_array($input)) {
            $input = [];
        }
        // Hosts sometimes strip custom headers; mirror JSON CSRF into $_POST
        // so AppAuth::verify_csrf() still sees the token.
        foreach (['csrf_token', 'csrf', '_csrf'] as $_ck) {
            if (!empty($input[$_ck]) && empty($_POST[$_ck])) {
                $_POST[$_ck] = (string)$input[$_ck];
            }
        }
        if (!empty($input['csrf_token']) && empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = (string)$input['csrf_token'];
        } elseif (!empty($input['csrf']) && empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = (string)$input['csrf'];
        }
    } else {
        $input = $_POST;
    }
}

if (!isset($input) || !is_array($input)) {
    $input = [];
}
// Defensive: action from POST body (JSON/form) or query string — never undefined
$action = $input['action'] ?? ($_GET['action'] ?? $_POST['action'] ?? '');
// Read-only size probes may arrive as GET from the document editor
if ($action === '' && isset($_GET['action'])) {
    $action = (string)$_GET['action'];
}
// Always a string — never leave $action undefined on any request path
$action = is_string($action) ? $action : '';

// ── Lightweight GET/JSON actions (after $action is defined) ───────────────
if ($action === 'synthetic_hosts') {
    $role = (string)($_SESSION['user'] ?? '');
    if ($role !== 'super_admin' && empty($isSuperAdmin)) {
        http_response_code(403);
        sendJson(['status' => 'error', 'message' => 'Super Admin only']);
    }
    $rows = class_exists('HostPolicy') ? HostPolicy::syntheticChecks() : [];
    sendJson(['status' => 'success', 'strict' => class_exists('HostPolicy') && HostPolicy::strictMode(), 'hosts' => $rows]);
}

if ($action === 'share_link') {
    if (!$isLoggedIn || empty($isAdmin)) {
        http_response_code(403);
        sendJson(['status' => 'error', 'message' => 'Administrator sign-in required to mint share links.']);
    }
    $card = preg_replace('/[^a-z]/', '', strtolower((string)($_GET['card'] ?? ($input['card'] ?? 'business'))));
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['slug'] ?? ($input['slug'] ?? ''))));
    if ($card === '' || $slug === '') {
        sendJson(['status' => 'error', 'message' => 'card and slug required']);
    }
    if (!class_exists('ShareToken')) {
        sendJson(['status' => 'error', 'message' => 'ShareToken unavailable']);
    }
    $ttl = (int)($_GET['ttl'] ?? ($input['ttl'] ?? 86400 * 30));
    if ($ttl < 60) { $ttl = 60; }
    if ($ttl > 86400 * 90) { $ttl = 86400 * 90; }
    $url = ShareToken::url($card, $slug, $ttl);
    if (class_exists('AuditLog')) {
        AuditLog::write('share_link', ['card' => $card, 'slug' => $slug]);
    }
    sendJson(['status' => 'success', 'url' => $url, 'ttl' => $ttl]);
}

if ($action === 'team_public_json') {
    if (!$isLoggedIn) {
        http_response_code(401);
        sendJson(['status' => 'error', 'message' => 'Sign-in required.']);
    }
    $path = (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/team_public.json';
    if (!is_file($path) && class_exists('PublicTeam')) {
        PublicTeam::writeFromTeam();
    }
    if (is_file($path)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=60');
        readfile($path);
        exit;
    }
    sendJson([]);
}

if ($action === 'statistics' || $action === 'stats') {
    // Isolated statistics view — full page within dashboard shell via tab redirect
    if (!headers_sent()) {
        header('Location: ?tab=statistics', true, 302);
        exit;
    }
}


// GET size probes (session required; no CSRF on safe GET)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && in_array($action, ['probe_doc_size', 'probe_url_size'], true)) {
    if (!$isLoggedIn) {
        http_response_code(401);
        sendJson(['ok' => false, 'error' => 'Unauthorized']);
    }
    if ($action === 'probe_doc_size') {
        $file = basename(str_replace(['\\', "\0"], '', (string)($_GET['file'] ?? '')));
        $file = preg_replace('/[^a-zA-Z0-9._\- ]/', '', $file) ?? '';
        if ($file === '') sendJson(['ok' => false, 'error' => 'Invalid file']);
        $path = rtrim(str_replace('\\', '/', DOC_PATH), '/') . '/' . $file;
        $realDoc = realpath(DOC_PATH);
        $realFile = is_file($path) ? realpath($path) : false;
        if ($realDoc === false || $realFile === false || strpos($realFile, $realDoc) !== 0) {
            sendJson(['ok' => false, 'bytes' => 0, 'error' => 'Not found']);
        }
        $bytes = (int) filesize($realFile);
        sendJson(['ok' => true, 'bytes' => $bytes, 'formatted' => self_format_bytes($bytes)]);
    }
    $url = trim((string)($_GET['url'] ?? ''));
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) sendJson(['ok' => false, 'error' => 'Invalid URL']);
    $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: ''));
    if (!in_array($scheme, ['http', 'https'], true)) sendJson(['ok' => false, 'error' => 'Invalid scheme']);
    $bytes = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'ResourceCenter-SizeProbe/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $len = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);
        if ($len !== false && (float)$len > 0) $bytes = (int) round((float)$len);
    }
    if ($bytes > 0) sendJson(['ok' => true, 'bytes' => $bytes, 'formatted' => self_format_bytes($bytes)]);
    sendJson(['ok' => false, 'bytes' => 0, 'error' => 'Remote size unavailable']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // capture_lead is the one write action a signed-OUT stranger must be
    // able to reach — it is what a card visitor hits when they submit the
    // "share your contact" form. It cannot carry a CSRF token because the
    // visitor never had a session to get one from. That is exactly why it
    // needs its own protections below (rate limit, honeypot, field caps)
    // instead of relying on the session-based guard everything else uses.
    // logout must work even with a stale CSRF/session — the goal is to leave
    if ($action !== 'login' && $action !== 'capture_lead' && $action !== 'logout') { AppAuth::verify_csrf(); }

    if ($action === 'capture_lead') {
        // A dedicated throttle, NOT AppAuth::checkRateLimit() — that limiter
        // is shared by the login form. Reusing it here would let a burst of
        // genuine lead submissions (a busy office behind one NAT IP) lock out
        // that IP's login attempts, and a failed-login burst block lead
        // submission. Same one-file-per-IP pattern already used for vehicle-
        // tag scan verification.
        $_lf  = DATA_PATH . '/lead_throttle.json';
        $_lip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $_lnow = time();
        $_ld = is_file($_lf) ? (json_decode((string)@file_get_contents($_lf), true) ?: []) : [];
        foreach ($_ld as $_k => $_v) { if (($_lnow - ($_v['t'] ?? 0)) > 3600) unset($_ld[$_k]); }
        $_lrec = $_ld[$_lip] ?? ['n' => 0, 't' => $_lnow];
        if (($_lnow - $_lrec['t']) > 900) $_lrec = ['n' => 0, 't' => $_lnow];
        $_lrec['n']++; $_lrec['t'] = $_lnow;
        $_ld[$_lip] = $_lrec;
        @file_put_contents($_lf, json_encode($_ld), LOCK_EX);
        if ($_lrec['n'] > 8) {   // 8 submissions / 15 min / IP
            sendJson(['status' => 'error', 'message' => 'Too many submissions. Please try again shortly.']);
        }

        // Honeypot: a hidden field named 'website_hp' that a human never
        // fills and a bot almost always does, by autofilling every input on
        // the form. Silent success — telling a bot it failed only teaches it
        // to try again differently.
        if (trim((string)($input['website_hp'] ?? '')) !== '') {
            sendJson(['status' => 'success']);
        }

        $cardSlug = preg_replace('/[^a-z0-9\-_]/i', '', (string)($input['card_slug'] ?? ''));
        $name     = mb_substr(trim(strip_tags((string)($input['name']  ?? ''))), 0, 100);
        $leadMail = mb_substr(trim((string)($input['email'] ?? '')), 0, 190);
        $leadTel  = preg_replace('/[^0-9+ ]/', '', (string)($input['phone'] ?? ''));
        $leadOrg  = mb_substr(trim(strip_tags((string)($input['company'] ?? ''))), 0, 120);
        $note     = mb_substr(trim(strip_tags((string)($input['note'] ?? ''))), 0, 500);

        if ($cardSlug === '') sendJson(['status' => 'error', 'message' => 'Invalid card.']);
        if ($name === '')     sendJson(['status' => 'error', 'message' => 'Name is required.']);
        if ($leadMail === '' && $leadTel === '') {
            sendJson(['status' => 'error', 'message' => 'Please provide an email or phone number.']);
        }
        if ($leadMail !== '' && !filter_var($leadMail, FILTER_VALIDATE_EMAIL)) {
            sendJson(['status' => 'error', 'message' => 'Please enter a valid email address.']);
        }

        // Confirm the slug is a real, current card before accepting a lead
        // against it — otherwise the form accepts leads for cards that were
        // renamed or removed, which then have nowhere to surface.
        $cardOwnerId = '';
        foreach (AppDB::read('team') ?: [] as $_m) {
            if (strcasecmp((string)($_m['slug'] ?? ''), $cardSlug) === 0) {
                $cardOwnerId = (string)($_m['id'] ?? '');
                break;
            }
        }
        if ($cardOwnerId === '') sendJson(['status' => 'error', 'message' => 'This card is no longer active.']);

        $leads = AppDB::read('leads') ?: [];
        $leads[] = [
            'id'            => uniqid('', true),
            'card_slug'     => $cardSlug,
            'card_owner_id' => $cardOwnerId,
            'name'          => $name,
            'email'         => $leadMail,
            'phone'         => $leadTel,
            'company'       => $leadOrg,
            'note'          => $note,
            'status'        => 'new',
            'tags'          => [],
            'created_at'    => date('c'),
            'ip'            => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
        if (count($leads) > 20000) $leads = array_slice($leads, -20000);

        if (!AppDB::save('leads', $leads)) {
            sendJson(['status' => 'error', 'message' => 'Could not save right now. Please try again.']);
        }

        if (class_exists('AppLog')) AppLog::info('Lead captured', ['card' => $cardSlug]);
        sendJson(['status' => 'success']);
    }

    if ($action === 'setup_complete') {
        if (!class_exists('AppAuth') || !AppAuth::needsSetup()) {
            sendJson(['status' => 'error', 'message' => 'Setup not required or already complete.'], false);
        }
        $token = trim((string)($input['setup_token'] ?? $_POST['setup_token'] ?? ''));
        $passwords = [
            'super_admin' => (string)($input['password_super_admin'] ?? $_POST['password_super_admin'] ?? ''),
            'admin' => (string)($input['password_admin'] ?? $_POST['password_admin'] ?? ''),
            'public' => (string)($input['password_public'] ?? $_POST['password_public'] ?? ''),
        ];
        if (AppAuth::completeSetup($passwords, $token)) {
            if (class_exists('AuditLog')) {
                @AuditLog::write('auth_setup_complete', ['roles' => array_keys(array_filter($passwords))]);
            }
            sendJson(['status' => 'success', 'message' => 'Access keys saved. You can sign in now.'], false);
        }
        sendJson(['status' => 'error', 'message' => 'Setup failed. Check token and passwords (min 8 characters for admin).'], false);
    }

    if ($action === 'login') {
        // Always answer with a real JSON body (never 304 / empty).
        if (class_exists('AppAuth') && method_exists('AppAuth', 'needsSetup') && AppAuth::needsSetup()) {
            sendJson(['status' => 'error', 'message' => 'First-run setup required. Open the login page to set access keys.'], false);
        }
        if (!AppAuth::checkRateLimit()) {
            sendJson(['status' => 'error', 'message' => 'Too many attempts. Please try again later.'], false);
        }

        $pass = trim((string)($input['password'] ?? ''));
        if ($pass === '' && isset($_POST['password'])) {
            $pass = trim((string)$_POST['password']);
        }
        $role = AppAuth::verifyCredentials($pass);

        if ($role !== null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_regenerate_id(true);
            }
            $_SESSION['user'] = $role;
            $_SESSION['true_role'] = $role;
            $_SESSION['login_at'] = time();
            AppAuth::logAttempt(true);
            if (class_exists('AppLog')) {
                @AppLog::info(ucfirst((string)$role) . ' Login Success');
            }
            if (class_exists('AuditLog')) {
                @AuditLog::write('login_ok', ['role' => $role]);
            }
            // Close session BEFORE response so locks/cookies flush cleanly (IIS)
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            // Drain ALL output buffers so the JSON body is the only payload
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            // Single path: always a real JSON body, never cacheable, never optimizer first
            sendJson(['status' => 'success', 'role' => (string)$role], false);
        }

        AppAuth::logAttempt(false);
        if (class_exists('AppLog')) {
            AppLog::error('Invalid Login Attempt');
        }
        if (class_exists('AuditLog')) {
            AuditLog::write('login_fail', ['ip' => (string)($_SERVER['REMOTE_ADDR'] ?? '')]);
        }
        sendJson(['status' => 'error', 'message' => 'Invalid Password'], false);
    }




    if ($action === 'social_fetch') {
        // LEGACY alias → official import only (no HTML profile scraping)
        if (empty($isAdmin) && empty($isSuperAdmin)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Admin required']);
        }
        if (!class_exists('MediaKitSources') && is_file(BASE_PATH . '/app/MediaKitSources.php')) {
            require_once BASE_PATH . '/app/MediaKitSources.php';
        }
        $handle = trim((string)($input['handle'] ?? $_POST['handle'] ?? ''));
        if ($handle === '') {
            sendJson([
                'status' => 'error',
                'message' => 'Paste a single public post URL (oEmbed), or connect an official API / RSS under Media Kit connections. Profile-feed scraping is not supported.',
            ]);
        }
        // Only accept full post URLs for oEmbed — not bare profile handles
        if (!preg_match('~^https?://~i', $handle)) {
            sendJson([
                'status' => 'error',
                'message' => 'Enter a full post URL (https://…). Profile handles require an official API connection (Meta Graph, YouTube, LinkedIn, X) or an RSS feed.',
            ]);
        }
        if (!class_exists('MediaKitSources')) {
            sendJson(['status' => 'error', 'message' => 'MediaKitSources module missing']);
        }
        $oe = MediaKitSources::oEmbed($handle);
        if (empty($oe['ok'])) {
            sendJson([
                'status' => 'error',
                'message' => (string)($oe['message'] ?? 'oEmbed failed'),
                'provider' => $oe['provider'] ?? '',
            ]);
        }
        $list = class_exists('AppDB') ? (AppDB::read('mediakit') ?: []) : [];
        if (!is_array($list)) {
            $list = [];
        }
        if (isset($list['id']) && !isset($list[0])) {
            $list = [$list];
        }
        $fp = sha1(strtolower($handle));
        $row = [
            'id' => 'mk' . substr($fp, 0, 12),
            'name' => $oe['title'] !== '' ? $oe['title'] : 'Social post',
            'category' => 'social',
            'platform' => $oe['provider'],
            'caption' => '',
            'notes' => 'Imported via oEmbed · ' . $handle,
            'url' => $handle,
            'file' => '',
            'photo' => '',
            'oembed_html' => $oe['html'],
            'oembed_thumb' => $oe['thumbnail_url'],
            'created_at' => date('c'),
            'source' => 'oembed',
            'item_kind' => 'post',
            'content_fp' => $fp,
        ];
        // merge dedupe by id/url
        $found = false;
        foreach ($list as $i => $ex) {
            if (!is_array($ex)) {
                continue;
            }
            if ((string)($ex['id'] ?? '') === $row['id'] || strtolower((string)($ex['url'] ?? '')) === strtolower($handle)) {
                $list[$i] = array_merge($ex, $row);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $list[] = $row;
        }
        AppDB::save('mediakit', array_values($list));
        if (class_exists('AuditLog')) {
            AuditLog::write('mediakit_oembed', ['url' => $handle, 'provider' => $oe['provider']]);
        }
        sendJson([
            'status' => 'success',
            'item' => $row,
            'items' => [$row],
            'message' => $found ? 'Updated existing post' : ('Saved via oEmbed · ' . $oe['provider']),
        ]);
    }

    if ($action === 'mediakit_sync_official') {
        if (empty($isAdmin) && empty($isSuperAdmin)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Admin required']);
        }
        if (!class_exists('MediaKitSources') && is_file(BASE_PATH . '/app/MediaKitSources.php')) {
            require_once BASE_PATH . '/app/MediaKitSources.php';
        }
        if (!class_exists('MediaKitSources')) {
            sendJson(['status' => 'error', 'message' => 'MediaKitSources module missing']);
        }
        $status = MediaKitSources::connectionStatus();
        $any = false;
        foreach ($status as $st) {
            if (!empty($st['connected'])) {
                $any = true;
                break;
            }
        }
        if (!$any) {
            sendJson([
                'status' => 'error',
                'message' => 'No official connections or RSS configured. Add credentials under Media Kit connections, or paste post URLs / upload files.',
                'connections' => $status,
            ]);
        }
        $fetched = MediaKitSources::fetchAllConnected(25);
        $list = class_exists('AppDB') ? (AppDB::read('mediakit') ?: []) : [];
        if (!is_array($list)) {
            $list = [];
        }
        if (isset($list['id']) && !isset($list[0])) {
            $list = [$list];
        }
        $byFp = [];
        foreach ($list as $i => $ex) {
            if (is_array($ex)) {
                $byFp[(string)($ex['content_fp'] ?? $ex['id'] ?? '')] = $i;
            }
        }
        $created = 0;
        $updated = 0;
        foreach ($fetched as $it) {
            $fp = sha1(strtolower($it['platform'] . '|' . $it['id'] . '|' . $it['permalink']));
            $row = [
                'id' => 'mk' . substr($fp, 0, 12),
                'name' => $it['title'] !== '' ? $it['title'] : ($it['platform'] . ' post'),
                'category' => 'social',
                'platform' => $it['platform'],
                'caption' => $it['caption'],
                'notes' => 'Official API · ' . $it['platform'] . ' · ' . $it['timestamp'],
                'url' => $it['permalink'],
                'file' => '',
                'photo' => '',
                'remote_media' => $it['media_url'],
                'created_at' => date('c'),
                'source' => 'official_api',
                'item_kind' => 'post',
                'content_fp' => $fp,
                'post_date' => $it['timestamp'],
            ];
            if (isset($byFp[$fp])) {
                $list[$byFp[$fp]] = array_merge($list[$byFp[$fp]], $row);
                $updated++;
            } elseif (isset($byFp[$row['id']])) {
                $list[$byFp[$row['id']]] = array_merge($list[$byFp[$row['id']]], $row);
                $updated++;
            } else {
                $list[] = $row;
                $byFp[$fp] = count($list) - 1;
                $created++;
            }
        }
        AppDB::save('mediakit', array_values($list));
        if (class_exists('AuditLog')) {
            AuditLog::write('mediakit_sync_official', ['created' => $created, 'updated' => $updated]);
        }
        sendJson([
            'status' => 'success',
            'created' => $created,
            'updated' => $updated,
            'fetched' => count($fetched),
            'connections' => $status,
            'message' => 'Official sync · ' . $created . ' new · ' . $updated . ' updated · ' . count($fetched) . ' fetched',
        ]);
    }

    if ($action === 'mediakit_save_connections') {
        if (empty($isAdmin) && empty($isSuperAdmin)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Admin required']);
        }
        if (!class_exists('MediaKitSources') && is_file(BASE_PATH . '/app/MediaKitSources.php')) {
            require_once BASE_PATH . '/app/MediaKitSources.php';
        }
        $payload = $input['connections'] ?? $_POST['connections'] ?? null;
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
        if (!is_array($payload)) {
            sendJson(['status' => 'error', 'message' => 'Invalid connections payload']);
        }
        // Merge with existing so empty token fields don't wipe secrets on partial save
        $existing = MediaKitSources::loadConnections();
        foreach (['meta', 'youtube', 'linkedin', 'x', 'rss'] as $k) {
            if (!isset($payload[$k]) || !is_array($payload[$k])) {
                continue;
            }
            foreach ($payload[$k] as $fk => $fv) {
                if (is_string($fv) && $fv === '' && in_array($fk, ['access_token', 'api_key', 'bearer_token'], true)) {
                    continue; // keep existing secret
                }
                $existing[$k][$fk] = $fv;
            }
        }
        if (!MediaKitSources::saveConnections($existing)) {
            sendJson(['status' => 'error', 'message' => 'Could not save connections']);
        }
        if (class_exists('AuditLog')) {
            AuditLog::write('mediakit_save_connections', ['platforms' => array_keys($payload)]);
        }
        sendJson([
            'status' => 'success',
            'connections' => MediaKitSources::connectionStatus(),
            'message' => 'Connections saved',
        ]);
    }

    if ($action === 'mediakit_bulk_upload') {
        if (empty($isAdmin) && empty($isSuperAdmin)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Admin required']);
        }
        $category = preg_replace('/[^a-z_]/', '', strtolower((string)($input['category'] ?? $_POST['category'] ?? 'promo')));
        if ($category === '') {
            $category = 'promo';
        }
        $platform = trim((string)($input['platform'] ?? $_POST['platform'] ?? ''));
        $files = $_FILES['files'] ?? $_FILES['files'] ?? null;
        if ($files === null && !empty($_FILES)) {
            // files[] from FormData
            foreach ($_FILES as $k => $v) {
                if (is_array($v['name'] ?? null) || isset($v['tmp_name'])) { $files = $v; break; }
            }
        }
        if ($files === null && isset($_FILES['file'])) {
            $files = $_FILES['file'];
        }
        if (!is_array($files) || empty($files['name'])) {
            sendJson(['status' => 'error', 'message' => 'No files received']);
        }
        // Normalize multi vs single
        $names = $files['name'];
        $isMulti = is_array($names);
        $count = $isMulti ? count($names) : 1;
        if ($count > 20) {
            sendJson(['status' => 'error', 'message' => 'Maximum 20 files per batch. You selected ' . $count . '.']);
        }
        $totalBytes = 0;
        for ($__i = 0; $__i < $count; $__i++) {
            $totalBytes += $isMulti ? (int)$files['size'][$__i] : (int)$files['size'];
        }
        if ($totalBytes > 80 * 1024 * 1024) {
            sendJson(['status' => 'error', 'message' => 'Batch exceeds 80 MB total (' . round($totalBytes / 1048576, 1) . ' MB).']);
        }
        $destDir = defined('IMG_PATH') ? IMG_PATH : (DATA_PATH . '/media/images');
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        $list = class_exists('AppDB') ? (AppDB::read('mediakit') ?: []) : [];
        if (!is_array($list)) {
            $list = [];
        }
        if (isset($list['id']) && !isset($list[0])) {
            $list = [$list];
        }
        $created = [];
        $errors = [];
        $allowedExt = ['jpg','jpeg','png','gif','webp','avif','svg','pdf','mp4','webm','mov'];
        for ($i = 0; $i < $count; $i++) {
            $orig = $isMulti ? (string)$names[$i] : (string)$names;
            $tmp = $isMulti ? (string)$files['tmp_name'][$i] : (string)$files['tmp_name'];
            $err = $isMulti ? (int)$files['error'][$i] : (int)$files['error'];
            $size = $isMulti ? (int)$files['size'][$i] : (int)$files['size'];
            if ($err !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
                $errors[] = $orig . ': upload error ' . $err;
                continue;
            }
            if ($size > 12 * 1024 * 1024) {
                $errors[] = $orig . ': exceeds 12 MB per file';
                continue;
            }
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = $orig . ': type not allowed';
                continue;
            }
            $baseName = pathinfo($orig, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^a-z0-9]+/i', '-', strtolower($baseName));
            $safeBase = trim($safeBase, '-') ?: 'media';
            $filename = 'mk-' . $safeBase . '-' . substr(str_replace('.', '', uniqid('', true)), -6) . '.' . $ext;
            $destPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

            if ($ext === 'svg') {
                $raw = (string)@file_get_contents($tmp);
                $clean = class_exists('AppMedia') ? AppMedia::sanitizeSvg($raw) : $raw;
                if ($clean === '' || strlen($clean) < 32) {
                    $errors[] = $orig . ': SVG rejected by sanitiser';
                    continue;
                }
                if (!str_starts_with(ltrim($clean), '<?xml')) {
                    $clean = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $clean;
                }
                if (@file_put_contents($destPath, $clean, LOCK_EX) === false) {
                    $errors[] = $orig . ': could not save SVG';
                    continue;
                }
            } else {
                if (!@move_uploaded_file($tmp, $destPath) && !@copy($tmp, $destPath)) {
                    $errors[] = $orig . ': could not save file';
                    continue;
                }
            }
            @chmod($destPath, 0664);
            // Optional optimise raster (non-fatal)
            if (in_array($ext, ['jpg','jpeg','png','webp'], true) && class_exists('Optimizer')) {
                try {
                    if (method_exists('Optimizer', 'optimiseFile')) {
                        Optimizer::optimiseFile($destPath);
                    }
                } catch (Throwable $e) {}
            }
            $id = 'mk' . substr(sha1(uniqid($filename, true)), 0, 12);
            $cat = $category;
            if ($ext === 'svg' && $cat === 'promo') {
                $cat = 'logo';
            }
            $w = null; $h = null; $bytes = @filesize($destPath) ?: $size;
            if (in_array($ext, ['jpg','jpeg','png','gif','webp','avif','bmp'], true)) {
                $info = @getimagesize($destPath);
                if (is_array($info)) {
                    $w = (int)($info[0] ?? 0) ?: null;
                    $h = (int)($info[1] ?? 0) ?: null;
                }
            }
            $row = [
                'id' => $id,
                'name' => trim(str_replace(['-', '_'], ' ', $baseName)),
                'category' => $cat,
                'platform' => $platform,
                'file' => $filename,
                'photo' => $filename,
                'file_type' => $ext,
                'width' => $w,
                'height' => $h,
                'bytes' => $bytes ?: null,
                'size_hint' => ($w && $h) ? ($w . '×' . $h . ' px') : '',
                'caption' => '',
                'notes' => '',
                'created_at' => date('c'),
            ];
            $list[] = $row;
            $created[] = $row;
        }
        // Dedupe list
        $seen = [];
        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) continue;
            $mid = (string)($row['id'] ?? '');
            if ($mid === '' || isset($seen[$mid])) continue;
            $seen[$mid] = true;
            $out[] = $row;
        }
        if (!AppDB::save('mediakit', array_values($out))) {
            sendJson(['status' => 'error', 'message' => 'Saved files but failed to update mediakit index']);
        }
        if (class_exists('AuditLog')) {
            AuditLog::write('mediakit_bulk_upload', ['count' => count($created)]);
        }
        sendJson([
            'status' => 'success',
            'created' => count($created),
            'errors' => $errors,
            'items' => $created,
        ]);
    }

    if ($action === 'mediakit_hub_save') {
        if (empty($isAdmin) && empty($isSuperAdmin)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Admin required']);
        }
        $url = trim((string)($input['library_url'] ?? $_POST['library_url'] ?? ''));
        $label = trim((string)($input['library_label'] ?? $_POST['library_label'] ?? 'Full media kit library'));
        if ($url !== '' && !preg_match('~^https?://~i', $url)) {
            sendJson(['status' => 'error', 'message' => 'Library URL must start with https://']);
        }
        if ($url !== '' && !preg_match('~^https?://[a-z0-9.-]+\.[a-z]{2,}(/.*)?$~i', $url) && !preg_match('~sharepoint\.com|onedrive\.live\.com|1drv\.ms~i', $url)) {
            // still allow sharepoint short links with :f:
            if (!preg_match('~^https?://~i', $url)) {
                sendJson(['status' => 'error', 'message' => 'Invalid library URL']);
            }
        }
        $dir = (defined('DATA_PATH') ? DATA_PATH : '') . '/config';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/mediakit_hub.json';
        $payload = [
            'library_url' => $url,
            'library_label' => $label !== '' ? $label : 'Full media kit library',
            'updated_at' => date('c'),
        ];
        $ok = @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        if ($ok === false) {
            sendJson(['status' => 'error', 'message' => 'Could not write mediakit_hub.json']);
        }
        if (class_exists('AuditLog')) {
            AuditLog::write('mediakit_hub_save', ['url' => $url !== '' ? 'set' : 'cleared']);
        }
        sendJson(['status' => 'success', 'library_url' => $url, 'library_label' => $payload['library_label']]);
    }

    if ($action === 'logout') {
        // Full sign-out — must never 500 (PHP 8 rejects empty/invalid SameSite)
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION = [];
                if (ini_get('session.use_cookies') && !headers_sent()) {
                    $p = session_get_cookie_params();
                    $path = (string)($p['path'] ?? '/');
                    if ($path === '') {
                        $path = '/';
                    }
                    $ss = strtolower(trim((string)($p['samesite'] ?? 'Lax')));
                    if (!in_array($ss, ['lax', 'strict', 'none'], true)) {
                        $ss = 'lax';
                    }
                    // SameSite=None requires Secure
                    $secure = !empty($p['secure']) || ($ss === 'none');
                    $opts = [
                        'expires'  => time() - 42000,
                        'path'     => $path,
                        'secure'   => $secure,
                        'httponly' => true,
                        'samesite' => ucfirst($ss),
                    ];
                    $domain = trim((string)($p['domain'] ?? ''));
                    if ($domain !== '') {
                        $opts['domain'] = $domain;
                    }
                    @setcookie((string)session_name(), '', $opts);
                    // Legacy fall-back clear (some proxies ignore options array)
                    @setcookie((string)session_name(), '', time() - 42000, $path);
                }
                @session_destroy();
            }
        } catch (Throwable $e) {
            // still report success so the client leaves the shell
            if (class_exists('AppLog')) {
                @AppLog::error('Logout cookie/session cleanup failed', ['err' => $e->getMessage()]);
            }
        }
        try {
            if (class_exists('AppLog')) {
                @AppLog::info('User signed out', ['ip' => (string)($_SERVER['REMOTE_ADDR'] ?? '')]);
            }
            if (class_exists('AuditLog')) {
                @AuditLog::write('logout', []);
            }
        } catch (Throwable $e) {
            // ignore audit failures
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        sendJson(['status' => 'success'], false);
    }

    if ($action === 'team_import') {
        if (!$isLoggedIn || (empty($isAdmin) && empty($isSuperAdmin))) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Administrator sign-in required to import team CSV.']);
        }
        $file = $_FILES['file'] ?? $_FILES['csv'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            sendJson(['status' => 'error', 'message' => 'CSV file required.']);
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $orig = strtolower((string)($file['name'] ?? ''));
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            sendJson(['status' => 'error', 'message' => 'Invalid upload.']);
        }
        if (!str_ends_with($orig, '.csv') && (string)($file['type'] ?? '') !== 'text/csv') {
            sendJson(['status' => 'error', 'message' => 'Only .csv files are accepted.']);
        }
        $fh = fopen($tmp, 'r');
        if ($fh === false) {
            sendJson(['status' => 'error', 'message' => 'Could not read CSV.']);
        }
        $header = fgetcsv($fh);
        if (!is_array($header) || $header === []) {
            fclose($fh);
            sendJson(['status' => 'error', 'message' => 'CSV has no header row.']);
        }
        // Strip UTF-8 BOM from first cell
        if (isset($header[0]) && is_string($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
        }
        $map = [];
        $allowed = [
            'name' => 'name', 'slug' => 'slug', 'phone' => 'phone', 'email' => 'email',
            'designation' => 'designation_name', 'department' => 'department_name',
            'location' => 'location_name', 'dob' => 'dob', 'blood group' => 'blood_group',
            'blood_group' => 'blood_group', 'gender' => 'gender',
        ];
        foreach ($header as $i => $h) {
            $key = strtolower(trim((string)$h));
            if (isset($allowed[$key])) {
                $map[$i] = $allowed[$key];
            }
        }
        if (!in_array('name', $map, true)) {
            fclose($fh);
            sendJson(['status' => 'error', 'message' => 'CSV must include a Name column.']);
        }
        $existing = AppDB::read('team') ?: [];
        if (!is_array($existing)) {
            $existing = [];
        }
        $slugUsed = [];
        $emailUsed = [];
        foreach ($existing as $m) {
            if (!is_array($m)) {
                continue;
            }
            $s = strtolower(trim((string)($m['slug'] ?? '')));
            $e = strtolower(trim((string)($m['email'] ?? '')));
            if ($s !== '') {
                $slugUsed[$s] = true;
            }
            if ($e !== '') {
                $emailUsed[$e] = true;
            }
        }
        $imported = 0;
        $skipped = 0;
        $rows = 0;
        while (($row = fgetcsv($fh)) !== false) {
            if ($rows >= 2000) {
                break;
            }
            $rows++;
            if (!is_array($row)) {
                $skipped++;
                continue;
            }
            $rec = [
                'id' => '',
                'name' => '',
                'slug' => '',
                'phone' => '',
                'email' => '',
                'designation_name' => '',
                'department_name' => '',
                'location_name' => '',
                'dob' => '',
                'blood_group' => '',
                'gender' => '',
            ];
            foreach ($map as $i => $field) {
                $rec[$field] = trim((string)($row[$i] ?? ''));
            }
            $name = $rec['name'];
            if ($name === '') {
                $skipped++;
                continue;
            }
            $slug = $rec['slug'];
            if ($slug === '' && class_exists('AppUtils')) {
                $slug = AppUtils::sanitizeSlug($name);
            } elseif ($slug === '' && class_exists('AppSlug')) {
                $slug = method_exists('AppSlug', 'fromName') ? AppSlug::fromName($name) : preg_replace('/[^a-z0-9]+/', '-', strtolower($name));
            } elseif ($slug === '') {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
            }
            $slug = strtolower(trim((string)$slug));
            $email = strtolower(trim($rec['email']));
            if ($slug === '' || isset($slugUsed[$slug]) || ($email !== '' && isset($emailUsed[$email]))) {
                $skipped++;
                continue;
            }
            $id = 'tm-' . substr(sha1($slug . '|' . $email . '|' . microtime(true)), 0, 10);
            $new = [
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'phone' => $rec['phone'],
                'email' => $rec['email'],
                'dob' => $rec['dob'],
                'blood_group' => $rec['blood_group'],
                'gender' => $rec['gender'],
                'designation_name' => $rec['designation_name'],
                'department_name' => $rec['department_name'],
                'location_name' => $rec['location_name'],
                'photo' => '',
                'social' => [],
            ];
            $existing[] = $new;
            $slugUsed[$slug] = true;
            if ($email !== '') {
                $emailUsed[$email] = true;
            }
            $imported++;
        }
        fclose($fh);
        AppDB::save('team', $existing);
        if (class_exists('AuditLog')) {
            AuditLog::write('team_import', ['imported' => $imported, 'skipped' => $skipped]);
        }
        sendJson(['status' => 'success', 'imported' => $imported, 'skipped' => $skipped]);
    }

        if ($action === 'master_logo_upload') {
        if (empty($isSuperAdmin)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Super Admin required.']);
        }
        $kind = strtolower(trim((string)($input['kind'] ?? $_POST['kind'] ?? '')));
        if (!in_array($kind, ['banks', 'oems'], true)) {
            sendJson(['status' => 'error', 'message' => 'kind must be banks or oems.']);
        }
        $slug = strtolower(preg_replace('/[^a-z0-9\-]/', '', (string)($input['slug'] ?? $_POST['slug'] ?? '')) ?? '');
        if ($slug === '') {
            sendJson(['status' => 'error', 'message' => 'slug required.']);
        }
        if (!class_exists('MasterDirectory')) {
            require_once BASE_PATH . '/app/MasterDirectory.php';
        }
        $listKey = ($kind === 'oems') ? 'vehicle_oems' : 'banks';
        $found = false;
        foreach (MasterDirectory::read($listKey) as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (strtolower((string)($row['slug'] ?? '')) === $slug) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            sendJson(['status' => 'error', 'message' => 'Unknown brand slug — not in master list.']);
        }
        $file = $_FILES['file'] ?? $_FILES['logo'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            sendJson(['status' => 'error', 'message' => 'Logo file required.']);
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $orig = strtolower((string)($file['name'] ?? ''));
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['svg', 'png', 'webp'], true)) {
            sendJson(['status' => 'error', 'message' => 'Only svg, png, webp allowed.']);
        }
        $destDir = DATA_PATH . '/masters/logos/' . $kind;
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        $destPath = $destDir . '/' . $slug . '.' . $ext;
        if (class_exists('PathJail')) {
            try {
                PathJail::assertWritable($destPath, DATA_PATH . '/masters/logos');
            } catch (Throwable $e) {
                sendJson(['status' => 'error', 'message' => 'Path rejected.']);
            }
        }
        if ($ext === 'svg') {
            $clean = AppMedia::sanitizeSvg((string)@file_get_contents($tmp));
            if ($clean === '') {
                sendJson(['status' => 'error', 'message' => 'That SVG could not be accepted safely. It must be a well-formed SVG with no scripts, external references or undefined entities. Re-export it as plain/optimised SVG (CorelDRAW: Save as SVG; Illustrator: SVG Profile 1.1, entities off), or upload a PNG/WebP instead.']);
            }
            if (@file_put_contents($destPath, $clean, LOCK_EX) === false) {
                sendJson(['status' => 'error', 'message' => 'Write failed.']);
            }
        } else {
            if (!is_uploaded_file($tmp) || !move_uploaded_file($tmp, $destPath)) {
                sendJson(['status' => 'error', 'message' => 'Upload move failed.']);
            }
        }
        @chmod($destPath, 0664);
        $url = '/media_serve.php?m=' . rawurlencode($kind . '/' . $slug . '.' . $ext) . '&v=' . (string)@filemtime($destPath);
        if (class_exists('AuditLog')) {
            AuditLog::write('master_logo_upload', ['kind' => $kind, 'slug' => $slug, 'ext' => $ext]);
        }
        sendJson(['status' => 'success', 'url' => $url]);
    }

    if ($action === 'analytics_inc') {
        $key = (string)($input['key'] ?? $_POST['key'] ?? '');
        if (!in_array($key, ['share_clicks', 'print_clicks'], true)) {
            sendJson(['status' => 'error', 'message' => 'Invalid metric']);
        }
        if (function_exists('rc_analytics_inc')) {
            rc_analytics_inc($key);
        }
        sendJson(['status' => 'success']);
    }


    // ── Document size probes (authenticated; admin for write-path consistency) ──
    if ($action === 'probe_doc_size' || $action === 'probe_url_size') {
        if (!$isLoggedIn) {
            http_response_code(401);
            sendJson(['ok' => false, 'error' => 'Unauthorized']);
        }
        if ($action === 'probe_doc_size') {
            $file = basename(str_replace(['\\', "\0"], '', (string)($input['file'] ?? $_GET['file'] ?? '')));
            $file = preg_replace('/[^a-zA-Z0-9._\- ]/', '', $file) ?? '';
            if ($file === '' || $file === '.' || $file === '..') {
                sendJson(['ok' => false, 'error' => 'Invalid file']);
            }
            $path = rtrim(str_replace('\\', '/', DOC_PATH), '/') . '/' . $file;
            $realDoc = realpath(DOC_PATH);
            $realFile = is_file($path) ? realpath($path) : false;
            if ($realDoc === false || $realFile === false || strpos($realFile, $realDoc) !== 0) {
                sendJson(['ok' => false, 'bytes' => 0, 'error' => 'Not found in vault']);
            }
            $bytes = (int) filesize($realFile);
            sendJson(['ok' => true, 'bytes' => $bytes, 'formatted' => self_format_bytes($bytes), 'file' => $file]);
        }
        // probe_url_size
        $url = trim((string)($input['url'] ?? $_GET['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            sendJson(['ok' => false, 'error' => 'Invalid URL']);
        }
        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?: ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            sendJson(['ok' => false, 'error' => 'Only http(s) URLs are permitted']);
        }
        $bytes = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_USERAGENT => 'ResourceCenter-SizeProbe/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $len = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($ch);
            if ($code >= 200 && $code < 400 && $len !== false && (float)$len > 0) {
                $bytes = (int) round((float)$len);
            }
            // Some hosts reject HEAD — fall back to ranged GET
            if ($bytes <= 0) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_CONNECTTIMEOUT => 6,
                    CURLOPT_TIMEOUT => 12,
                    CURLOPT_HTTPHEADER => ['Range: bytes=0-0'],
                    CURLOPT_USERAGENT => 'ResourceCenter-SizeProbe/1.0',
                    CURLOPT_HEADER => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $raw = curl_exec($ch);
                curl_close($ch);
                if (is_string($raw) && preg_match('/Content-Range:\s*bytes\s+\d+-\d+\/(\d+)/i', $raw, $m)) {
                    $bytes = (int) $m[1];
                } elseif (is_string($raw) && preg_match('/Content-Length:\s*(\d+)/i', $raw, $m)) {
                    $bytes = (int) $m[1];
                }
            }
        }
        if ($bytes > 0) {
            sendJson(['ok' => true, 'bytes' => $bytes, 'formatted' => self_format_bytes($bytes)]);
        }
        sendJson(['ok' => false, 'bytes' => 0, 'error' => 'Remote size unavailable']);
    }


    // Super Admin role switch must run even when viewing as public (General HR)
    if ($action === 'switch_role') {
        $true = (string)($_SESSION['true_role'] ?? '');
        if ($true !== 'super_admin') {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Only Super Admin may switch roles.']);
        }
        $target = strtolower(trim((string)($input['role'] ?? $_GET['role'] ?? '')));
        $map = [
            'super_admin' => 'super_admin',
            'superadmin' => 'super_admin',
            'super' => 'super_admin',
            'admin' => 'admin',
            'company' => 'admin',
            'company_admin' => 'admin',
            'co_admin' => 'admin',
            'public' => 'public',
            'hr' => 'public',
            'general_hr' => 'public',
            'visitor' => 'public',
        ];
        if (!isset($map[$target])) {
            sendJson(['status' => 'error', 'message' => 'Unknown role. Use super_admin, admin, or public.']);
        }
        $new = $map[$target];
        $_SESSION['user'] = $new;
        $_SESSION['true_role'] = 'super_admin';
        if (class_exists('AuditLog')) {
            AuditLog::write('switch_role', ['to' => $new]);
        }
        if (class_exists('AppLog')) {
            AppLog::info('Role switch to ' . $new, ['by' => 'super_admin']);
        }
        sendJson(['status' => 'success', 'role' => $new, 'message' => 'Viewing as ' . $new]);
    }

    if (!$isAdmin) {
        http_response_code(403);
        sendJson(['status' => 'error', 'message' => 'Unauthorized: View Access Only']);
    }


    // ── Provision / map tenant (Super Admin) ───────────────────────────────
    if ($action === 'tenant_provision') {
        $role = (string)($_SESSION['user'] ?? '');
        if ($role !== 'super_admin') {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Super Admin required to provision tenants.']);
        }
        $host = strtolower(trim((string)($input['host'] ?? $input['domain'] ?? '')));
        $host = preg_replace('#^https?://#', '', $host);
        $host = rtrim($host, '/');
        $host = preg_replace('/:\d+$/', '', $host);
        $tenantId = strtolower(trim((string)($input['tenant_id'] ?? $input['id'] ?? '')));
        $tenantId = preg_replace('/[^a-z0-9\-]/', '', $tenantId);
        $displayName = trim((string)($input['name'] ?? $input['company_name'] ?? ''));
        if ($host === '' && $tenantId === '') {
            sendJson(['status' => 'error', 'message' => 'Provide host (e.g. rc.dhruvkhandelwal.in) or tenant id.']);
        }
        if ($tenantId === '' && $host !== '') {
            // rc.{slug}.tld → slug
            if (preg_match('/^rc\.([a-z0-9][a-z0-9\-]{0,62})\./i', $host, $m)) {
                $tenantId = strtolower($m[1]);
            } elseif (preg_match('/^([a-z0-9][a-z0-9\-]{0,62})\./i', $host, $m)) {
                $tenantId = strtolower($m[1]);
            } else {
                $tenantId = preg_replace('/[^a-z0-9\-]/', '', $host);
            }
        }
        if ($tenantId === '' || strlen($tenantId) < 2) {
            sendJson(['status' => 'error', 'message' => 'Invalid tenant id.']);
        }
        if (in_array($tenantId, ['map', 'default', 'tenants'], true)) {
            sendJson(['status' => 'error', 'message' => 'Reserved tenant id.']);
        }
        // Intentional re-provision clears tombstone so folder may return
        if (class_exists('TenantTombstone')) {
            TenantTombstone::clear($tenantId);
        }
        $tenantsDir = BASE_PATH . '/tenants';
        $tenantRoot = $tenantsDir . '/' . $tenantId;
        $dataPath = $tenantRoot . '/data';
        if (!is_dir($tenantsDir)) {
            @mkdir($tenantsDir, 0775, true);
        }
        if (!is_dir($tenantRoot)) {
            @mkdir($tenantRoot, 0775, true);
        }
        if (!is_dir($dataPath)) {
            @mkdir($dataPath, 0775, true);
        }
        foreach (['sessions', 'logs', 'media', 'media/images', 'media/docs', 'janam', 'runners', 'tmp'] as $sub) {
            $p = $dataPath . '/' . $sub;
            if (!is_dir($p)) {
                @mkdir($p, 0775, true);
            }
        }
        $seedFiles = [
            'team.json' => '[]',
            'company.json' => '{}',
            'documents.json' => '[]',
            'banking.json' => '[]',
            'locations.json' => '[]',
            'events.json' => '[]',
            'designations.json' => '[]',
            'departments.json' => '[]',
            'statutory.json' => '[]',
            'cartags.json' => '[]',
            'cctv.json' => '[]',
            'leads.json' => '[]',
            'analytics.json' => '{"visits":0,"share":0,"print":0}',
        ];
        foreach ($seedFiles as $fn => $content) {
            $fp = $dataPath . '/' . $fn;
            if (!is_file($fp)) {
                @file_put_contents($fp, $content, LOCK_EX);
            }
        }
        $cfgPath = $tenantRoot . '/config.json';
        if ($displayName === '') {
            $displayName = ucwords(str_replace(['-', '_'], ' ', $tenantId));
        }
        if (!is_file($cfgPath)) {
            @file_put_contents($cfgPath, json_encode([
                'name' => $displayName,
                'tenant_id' => $tenantId,
                'created_at' => date('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        // Update map.json exact hosts
        $mapFile = $tenantsDir . '/map.json';
        $map = ['exact' => [], 'rules' => [], 'fallback' => 'default'];
        if (is_file($mapFile)) {
            $decoded = json_decode((string)file_get_contents($mapFile), true);
            if (is_array($decoded)) {
                $map = array_merge($map, $decoded);
            }
        }
        if (!isset($map['exact']) || !is_array($map['exact'])) {
            $map['exact'] = [];
        }
        if ($host !== '') {
            $map['exact'][$host] = $tenantId;
            if (!str_starts_with($host, 'www.')) {
                $map['exact']['www.' . $host] = $tenantId;
            }
        }
        // Ensure wildcard rules present
        if (empty($map['rules']) || !is_array($map['rules'])) {
            $map['rules'] = [
                ['match' => '/^rc\\.([a-z0-9][a-z0-9-]{0,62})\\./i', 'group' => 1, 'comment' => 'rc.{tenant}.tld'],
                ['match' => '/^([a-z0-9][a-z0-9-]{0,62})\\.rc\\./i', 'group' => 1, 'comment' => '{tenant}.rc.tld'],
            ];
        }
        if (!isset($map['fallback'])) {
            $map['fallback'] = 'default';
        }
        $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($mapFile, $json, LOCK_EX) === false) {
            sendJson(['status' => 'error', 'message' => 'Tenant folders created but map.json could not be written. Check permissions on tenants/.']);
        }
        if (class_exists('AuditLog')) AuditLog::write('tenant_provision', ['tenant_id' => $tenantId, 'host' => $host]);
        sendJson([
            'status' => 'success',
            'tenant_id' => $tenantId,
            'host' => $host,
            'path' => 'tenants/' . $tenantId . '/data',
            'message' => 'Tenant provisioned. Point DNS A record for ' . ($host ?: 'rc.' . $tenantId . '.…') . ' to this server DocumentRoot, then open the host.',
        ]);
    }

    if ($action === 'tenant_update') { /* audit on success below */
        $role = (string)($_SESSION['user'] ?? '');
        if ($role !== 'super_admin') {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Super Admin only.']);
        }
        $tenantId = strtolower(preg_replace('/[^a-z0-9\-]/', '', (string)($input['tenant_id'] ?? '')));
        if ($tenantId === '' || in_array($tenantId, ['map', 'default', 'tenants'], true)) {
            sendJson(['status' => 'error', 'message' => 'Invalid or reserved tenant id.']);
        }
        $tenantRoot = BASE_PATH . '/tenants/' . $tenantId;
        if (!is_dir($tenantRoot)) {
            sendJson(['status' => 'error', 'message' => 'Tenant folder not found.']);
        }
        $displayName = trim((string)($input['name'] ?? ''));
        $hostsRaw = trim((string)($input['hosts'] ?? ''));
        // hosts: comma or newline separated
        $newHosts = [];
        foreach (preg_split('/[\s,;]+/', $hostsRaw) ?: [] as $h) {
            $h = strtolower(trim($h));
            $h = preg_replace('#^https?://#', '', $h);
            $h = rtrim($h, '/');
            if ($h !== '' && preg_match('/^[a-z0-9.\-]+$/', $h)) {
                $newHosts[] = $h;
            }
        }
        $newHosts = array_values(array_unique($newHosts));

        $cfgPath = $tenantRoot . '/config.json';
        $cfg = [];
        if (is_file($cfgPath)) {
            $cfg = json_decode((string)@file_get_contents($cfgPath), true) ?: [];
        }
        if (!is_array($cfg)) {
            $cfg = [];
        }
        if ($displayName !== '') {
            $cfg['name'] = $displayName;
            $cfg['label'] = $displayName;
        }
        $cfg['tenant_id'] = $tenantId;
        $cfg['id'] = $tenantId;
        $cfg['hosts'] = $newHosts;
        $cfg['updated_at'] = date('c');
        $cfgJson = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($cfgJson === false || @file_put_contents($cfgPath, $cfgJson, LOCK_EX) === false) {
            sendJson(['status' => 'error', 'message' => 'Could not write tenants/' . $tenantId . '/config.json — check folder permissions.']);
        }

        // map.json shape: { "exact": { host: tenantId }, "rules": [...], "fallback": "default" }
        $mapFile = BASE_PATH . '/tenants/map.json';
        $map = [];
        if (is_file($mapFile)) {
            $map = json_decode((string)@file_get_contents($mapFile), true) ?: [];
        }
        if (!is_array($map)) {
            $map = [];
        }
        if (!isset($map['exact']) || !is_array($map['exact'])) {
            $map['exact'] = [];
            // Migrate any accidental flat host keys from older broken saves
            foreach (array_keys($map) as $hk) {
                if (in_array($hk, ['exact', 'rules', 'fallback', 'notes'], true)) {
                    continue;
                }
                if (is_string($map[$hk] ?? null)) {
                    $map['exact'][$hk] = $map[$hk];
                    unset($map[$hk]);
                }
            }
        }
        // Drop exact entries that pointed at this tenant
        foreach (array_keys($map['exact']) as $hk) {
            if (($map['exact'][$hk] ?? '') === $tenantId) {
                unset($map['exact'][$hk]);
            }
        }
        foreach ($newHosts as $hk) {
            $map['exact'][$hk] = $tenantId;
            if (!str_starts_with($hk, 'www.')) {
                $map['exact']['www.' . $hk] = $tenantId;
            }
        }
        if (empty($map['rules']) || !is_array($map['rules'])) {
            $map['rules'] = [
                ['match' => '/^rc\.([a-z0-9][a-z0-9-]{0,62})\./i', 'group' => 1, 'comment' => 'rc.{tenant}.tld'],
                ['match' => '/^([a-z0-9][a-z0-9-]{0,62})\.rc\./i', 'group' => 1, 'comment' => '{tenant}.rc.tld'],
            ];
        }
        if (!isset($map['fallback']) || $map['fallback'] === '') {
            $map['fallback'] = 'default';
        }
        $mapJson = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($mapJson === false || @file_put_contents($mapFile, $mapJson, LOCK_EX) === false) {
            sendJson(['status' => 'error', 'message' => 'config.json saved but map.json could not be written. Check write permission on tenants/.']);
        }

        sendJson([
            'status' => 'success',
            'tenant_id' => $tenantId,
            'name' => $cfg['name'] ?? $tenantId,
            'hosts' => $newHosts,
            'message' => 'Tenant updated.',
        ]);
    }

    if ($action === 'tenant_delete') {
        $role = (string)($_SESSION['user'] ?? '');
        if ($role !== 'super_admin') {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Super Admin only.']);
        }
        $tenantId = strtolower(preg_replace('/[^a-z0-9\-]/', '', (string)($input['tenant_id'] ?? '')));
        $confirm = (string)($input['confirm'] ?? '');
        if ($tenantId === '' || in_array($tenantId, ['map', 'default', 'tenants'], true)) {
            sendJson(['status' => 'error', 'message' => 'Invalid or protected tenant id.']);
        }
        if ($confirm !== $tenantId) {
            sendJson(['status' => 'error', 'message' => 'Type the tenant id to confirm deletion.']);
        }
        // Never delete the tenant currently serving this request
        if (defined('TENANT_ID') && TENANT_ID === $tenantId) {
            sendJson(['status' => 'error', 'message' => 'Cannot delete the active tenant for this host. Open Super Admin from another tenant host.']);
        }
        $tenantRoot = BASE_PATH . '/tenants/' . $tenantId;
        if (!is_dir($tenantRoot)) {
            sendJson(['status' => 'error', 'message' => 'Tenant folder not found.']);
        }
        // Remove map entries (exact host map only — preserve rules/fallback)
        $mapFile = BASE_PATH . '/tenants/map.json';
        if (is_file($mapFile)) {
            $map = json_decode((string)@file_get_contents($mapFile), true) ?: [];
            if (is_array($map)) {
                if (!isset($map['exact']) || !is_array($map['exact'])) {
                    $map['exact'] = [];
                }
                foreach (array_keys($map['exact']) as $hk) {
                    if (($map['exact'][$hk] ?? '') === $tenantId) {
                        unset($map['exact'][$hk]);
                    }
                }
                // Clean accidental flat keys
                foreach (array_keys($map) as $hk) {
                    if (in_array($hk, ['exact', 'rules', 'fallback', 'notes'], true)) {
                        continue;
                    }
                    if (($map[$hk] ?? '') === $tenantId) {
                        unset($map[$hk]);
                    }
                }
                @file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            }
        }
        // Recursive delete tenant folder
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tenantRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $file) {
            if ($file->isDir()) @rmdir($file->getPathname());
            else @unlink($file->getPathname());
        }
        @rmdir($tenantRoot);
        if (class_exists('TenantTombstone')) { TenantTombstone::mark($tenantId, (string)($_SESSION['user'] ?? 'super_admin')); }
        // Remove ghost folders on next optimise; hide from lists immediately
        if (class_exists('AuditLog')) AuditLog::write('tenant_delete', ['tenant_id' => $tenantId ?? '']);
        sendJson(['status' => 'success', 'tenant_id' => $tenantId, 'message' => 'Tenant removed from map and disk.']);
    }


    if (in_array($action, ['save', 'delete'], true)) {
        $nsToCheck = preg_replace('/[^a-z_]/', '', (string)($input['ns'] ?? ''));
        if (!in_array($nsToCheck, ['company', 'team', 'departments', 'locations', 'designations', 'bank', 'docs', 'mediakit', 'events', 'statutory', 'cartags', 'cctv', 'leads'], true)) {
            http_response_code(403); sendJson(['status' => 'error', 'message' => 'Unauthorized namespace']);
        }
    }

    if ($action === 'delete') {
        $ns = preg_replace('/[^a-z_]/', '', (string)($input['ns'] ?? ''));
        $id = $input['id'] ?? '';

        // Registry data must not outlive the tag it describes — otherwise
        // deleted vehicles leave orphaned third-party data on disk.
        if ($ns === 'cartags' && $id !== '') {
            require_once BASE_PATH . '/app/VehicleRegistry.php';
            foreach (AppDB::read('cartags') ?: [] as $_row) {
                if ((string)($_row['id'] ?? '') === (string)$id) {
                    VehicleRegistry::forget((string)($_row['registration_number'] ?? $_row['plate'] ?? ''));
                    break;
                }
            }
        }
        if (empty($ns) || empty($id)) sendJson(['status' => 'error', 'message' => 'Invalid parameters']);
        $dbData = AppDB::read($ns);
        if (is_array($dbData)) {
            $filtered = array_filter($dbData, fn($item) => (string)($item['id'] ?? '') !== (string)$id);
            if (!AppDB::save($ns, array_values($filtered))) sendJson(['status' => 'error', 'message' => 'Atomic write failed during deletion.']);
            if (class_exists('AuditLog')) AuditLog::write('delete', ['ns' => $ns, 'id' => (string)$id]);
            if (class_exists('CardCache') && in_array($ns, ['team', 'company', 'locations', 'departments', 'designations'], true)) CardCache::bust();
            if (class_exists('AppLog')) AppLog::info("Deleted record ID: {$id} from {$ns}");
            sendJson(['status' => 'success']);
        } else {
            sendJson(['status' => 'error', 'message' => 'Namespace not found']);
        }
    }

    // ── Vehicle registry lookup (paid API; admin-triggered only) ─────────────
    // Deliberately NOT reachable from the public scan page: a billable call
    // behind an anonymous QR scan is a cost-amplification vector.
    // ── Settings / Integrations config read-write ────────────────────────────
    // Admin-only. Backs the Settings tab. Each integration's config file
    // remains the single source of truth (the classes that USE these — e.g.
    // VehicleRegistry, GoogleWallet, SmtpMailer — read the files directly,
    // not through this endpoint), so a config edited by hand over FTP and
    // one edited through this UI behave identically. This is a convenience
    // layer over the same files, not a second source of truth.
    if ($action === 'settings_get' || $action === 'settings_save') {
        if (!$isAdmin) { http_response_code(403); sendJson(['status' => 'error', 'message' => 'Admin access required.']); }

        $_settingsMap = [
            'google_wallet' => ['file' => 'google_wallet_config.php', 'secrets' => []],  // secret lives in a separate JSON key file, not this array
            'vehicle_registry' => ['file' => 'registry_config.php', 'secrets' => ['api_key']],
            'whatsapp' => ['file' => 'whatsapp_config.php', 'secrets' => ['access_token']],
            'smtp' => ['file' => 'smtp_config.php', 'secrets' => ['password']],
            'apple_wallet' => ['file' => 'apple_wallet_config.php', 'secrets' => []],
        ];

        $_key = (string)($input['key'] ?? '');
        if (!isset($_settingsMap[$_key])) sendJson(['status' => 'error', 'message' => 'Unknown settings section.']);
        $_meta = $_settingsMap[$_key];
        $_path = DATA_PATH . '/' . $_meta['file'];

        if ($action === 'settings_get') {
            $_cfg = is_readable($_path) ? (array)(@include $_path) : [];
            // Secrets are never sent to the browser in cleartext — only whether
            // one is already set. A config page that echoes stored API keys
            // back into the page source is a needless exposure surface.
            foreach ($_meta['secrets'] as $_s) {
                $_cfg['_has_' . $_s] = !empty($_cfg[$_s]);
                unset($_cfg[$_s]);
            }
            sendJson(['status' => 'success', 'config' => $_cfg]);
        }

        if ($action === 'settings_save') {
            $_payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
            $_existing = is_readable($_path) ? (array)(@include $_path) : [];

            // A secret field left blank in the form means "keep the existing
            // value", not "clear it" — otherwise every save that doesn't
            // re-type the password would silently wipe it.
            foreach ($_meta['secrets'] as $_s) {
                if (!isset($_payload[$_s]) || trim((string)$_payload[$_s]) === '') {
                    $_payload[$_s] = $_existing[$_s] ?? '';
                }
            }

            $_merged = array_merge($_existing, $_payload);
            // Never let arbitrary POST data invent new top-level keys — only
            // keys the existing template already defines may be written.
            $_merged = array_intersect_key($_merged, $_existing ?: $_merged);

            $_php = "<?php
// {$_meta['file']} — updated via Settings tab " . date('c') . "
if (!defined('BASE_PATH')) exit('No direct script access');
return " . var_export($_merged, true) . ";
";

            if (@file_put_contents($_path, $_php, LOCK_EX) === false) {
                sendJson(['status' => 'error', 'message' => 'Could not write the config file. Check that data/ is writable.']);
            }
            if (class_exists('AppLog')) AppLog::info('Settings updated', ['section' => $_key, 'by' => $_SESSION['user']]);
            if (class_exists('AuditLog')) AuditLog::write('settings_save', ['section' => (string)$_key]);
            sendJson(['status' => 'success']);
        }
    }

    if ($action === 'registry_fetch') {
        require_once BASE_PATH . '/app/VehicleRegistry.php';
        $reg   = (string)($input['registration_number'] ?? '');
        $force = !empty($input['force']);
        if (trim($reg) === '') sendJson(['status' => 'error', 'message' => 'Registration number required.']);
        $r = VehicleRegistry::fetch($reg, $force);
        sendJson([
            'status'  => $r['ok'] ? 'success' : 'error',
            'cached'  => $r['cached'],
            'data'    => $r['data'],
            'message' => $r['error'],
        ]);
    }

    // ── Cleanup scanner: report only, deletes nothing ────────────────────────
    if ($action === 'cleanup_scan') {
        if (class_exists('AppLog')) AppLog::info('Cleanup scan requested');
        sendJson(SystemDataOptimizer::scanCleanup());
    }

    // ── Cleanup apply: deletes ONLY the paths the admin confirmed, and only
    //    after applyCleanup() re-validates each one against a fresh scan.
    if ($action === 'cleanup_apply') {
        $paths = $input['paths'] ?? [];
        if (!is_array($paths) || empty($paths)) {
            sendJson(['status' => 'error', 'message' => 'No files selected.']);
        }
        if (count($paths) > 5000) {
            sendJson(['status' => 'error', 'message' => 'Too many files in one request.']);
        }
        sendJson(SystemDataOptimizer::applyCleanup($paths));
    }

    // ── Delete a single orphaned data/*.json file (Monitor Storage Integrity)
    if ($action === 'delete_orphan') {
        if (empty($isAdmin)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Admin only.']);
        }
        $file = basename((string)($input['file'] ?? ''));
        if ($file === '' || !str_ends_with($file, '.json') || str_contains($file, '..')) {
            sendJson(['status' => 'error', 'message' => 'Invalid file name.']);
        }
        $path = (defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data')) . '/' . $file;
        // Never delete core namespaces even if scanner mislabels them
        $protected = ['team.json','company.json','banking.json','locations.json','documents.json',
                      'events.json','statutory.json','departments.json','designations.json',
                      'cartags.json','settings.json','users.json'];
        if (in_array(strtolower($file), $protected, true)) {
            sendJson(['status' => 'error', 'message' => 'Protected data file cannot be deleted here.']);
        }
        if (!is_file($path) || !is_writable($path)) {
            sendJson(['status' => 'error', 'message' => 'File not found or not writable.']);
        }
        if (!@unlink($path)) {
            sendJson(['status' => 'error', 'message' => 'Delete failed.']);
        }
        if (class_exists('AppLog')) {
            AppLog::info('Orphan data file deleted', ['file' => $file, 'by' => 'admin']);
        }
        sendJson(['status' => 'success', 'message' => 'Deleted ' . $file, 'file' => $file]);
    }


    if ($action === 'optimise') {
        $super = !empty($isSuperAdmin) || (($_SESSION['user'] ?? '') === 'super_admin');
        if (!$super) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Optimisation is restricted to Super Admin.']);
        }
        if (class_exists('AppLog')) {
            AppLog::info('System Optimizer Run Initiated (manual, all tenants)');
        }
        // Capture fatals (OOM, parse) that bypass try/catch and return JSON
        $GLOBALS['RC_OPT_FATAL_BUFFER'] = '';
        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if (!$err || !in_array((int)$err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            if (!empty($GLOBALS['RC_OPT_DONE'])) {
                return;
            }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(200);
            }
            $detail = ($err['message'] ?? 'fatal') . ' @ ' . ($err['file'] ?? '?') . ':' . ($err['line'] ?? 0);
            echo json_encode([
                'status' => 'error',
                'message' => 'Optimizer fatal: ' . $detail,
                'logs' => ['FATAL: ' . $detail],
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        });
        try {
            if (!headers_sent()) {
                http_response_code(200);
            }
            @ini_set('memory_limit', '512M');
            @ini_set('display_errors', '0');
            @set_time_limit(240);
            if (!class_exists('SystemDataOptimizer')) {
                sendJson(['status' => 'error', 'message' => 'Optimizer class missing', 'logs' => []]);
            }
            $result = SystemDataOptimizer::runAllTenants(true);
            $GLOBALS['RC_OPT_DONE'] = true;
            if (!is_array($result)) {
                $result = ['status' => 'success', 'logs' => [(string)$result], 'message' => 'Completed'];
            }
            if (empty($result['status'])) {
                $result['status'] = 'success';
            }
            // Cap log payload so response stays JSON-friendly
            if (!empty($result['logs']) && is_array($result['logs']) && count($result['logs']) > 400) {
                $result['logs'] = array_slice($result['logs'], -400);
                array_unshift($result['logs'], '… log truncated to last 400 lines …');
            }
            sendJson($result);
        } catch (\Throwable $e) {
            $GLOBALS['RC_OPT_DONE'] = true;
            if (class_exists('AppLog')) {
                AppLog::error('Optimizer fatal: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            http_response_code(200);
            sendJson([
                'status' => 'error',
                'message' => 'Optimizer error: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine(),
                'logs' => [
                    'ERROR: ' . $e->getMessage(),
                    'File: ' . $e->getFile() . ':' . $e->getLine(),
                    'Class: ' . get_class($e),
                ],
            ]);
        }
    }

    // Watched folders + JSON namespaces for Monitor
    if ($action === 'layout_inventory') {
        if (empty($isSuperAdmin) && ($_SESSION['user'] ?? '') !== 'super_admin') {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Super Admin only.']);
        }
        sendJson(SystemDataOptimizer::inventoryWatched());
    }


    // Batch Windows installers (per-tenant ARP + shortcut) — Super Admin only
    if ($action === 'installer_build') {
        if (empty($isSuperAdmin) && (($_SESSION['user'] ?? '') !== 'super_admin')) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Super Admin only.']);
        }
        // CSRF: accept token from JSON body or header
        $csrf = (string)($input['csrf_token'] ?? $input['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $sess = (string)($_SESSION['csrf_token'] ?? '');
        if ($sess === '' || $csrf === '' || !hash_equals($sess, $csrf)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Invalid CSRF token.']);
        }
        if (!class_exists('InstallerBuild')) {
            http_response_code(500);
            sendJson(['status' => 'error', 'message' => 'InstallerBuild class missing.']);
        }
        try {
            [$zipPath, $zipName, $count, $tmp] = InstallerBuild::buildZip();
            if (class_exists('AppLog')) {
                AppLog::info('Windows installers built', ['tenants' => $count, 'file' => $zipName]);
            }
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . (string)filesize($zipPath));
            header('X-Robots-Tag: noindex');
            readfile($zipPath);
            @unlink($zipPath);
            // clean temp dir
            foreach (glob($tmp . '/*') ?: [] as $f) { @unlink($f); }
            @rmdir($tmp);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            sendJson(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // Force layout migration; optional removal of empty legacy folders
    if ($action === 'layout_migrate') {
        if (empty($isSuperAdmin) && ($_SESSION['user'] ?? '') !== 'super_admin') {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Super Admin only.']);
        }
        $remove = !empty($input['remove_empty_legacy']);
        if (class_exists('AppLog')) {
            AppLog::info('Data layout migration', ['remove_empty_legacy' => $remove]);
        }
        $result = SystemDataOptimizer::migrateDataLayout($remove);
        sendJson($result);
    }

    // ── toggle_slug_lock ──────────────────────────────────────────────────────
    if ($action === 'toggle_slug_lock') {
        $ns     = preg_replace('/[^a-z_]/', '', (string)($input['ns'] ?? 'team'));
        $id     = trim((string)($input['id'] ?? ''));
        $locked = (bool)($input['locked'] ?? false);

        if ($ns !== 'team') {
            http_response_code(400);
            sendJson(['status' => 'error', 'message' => 'Slug locking is only supported for the team namespace.']);
        }
        if ($id === '') {
            http_response_code(400);
            sendJson(['status' => 'error', 'message' => 'Member ID is required.']);
        }

        $records = AppDB::read('team');
        if (!is_array($records)) {
            http_response_code(500);
            sendJson(['status' => 'error', 'message' => 'Could not read team data.']);
        }

        $found = false;
        foreach ($records as &$rec) {
            if (!is_array($rec)) continue;
            if ((string)($rec['id'] ?? '') === $id) {
                if ($locked) { $rec['slug_locked'] = true; } else { unset($rec['slug_locked']); }
                $found = true;
                break;
            }
        }
        unset($rec);

        if (!$found) {
            http_response_code(404);
            sendJson(['status' => 'error', 'message' => "Team member '{$id}' not found."]);
        }

        if (!AppDB::save('team', $records)) {
            http_response_code(500);
            sendJson(['status' => 'error', 'message' => 'Atomic save failed on team.json.']);
        }

        if (class_exists('AppLog')) {
            AppLog::info(($locked ? 'Slug LOCKED' : 'Slug UNLOCKED') . " for team member ID: {$id}");
        }

        sendJson([
            'status'  => 'success',
            'locked'  => $locked,
            'id'      => $id,
            'message' => $locked
                ? 'Slug locked — optimizer will skip this member.'
                : 'Slug unlocked — optimizer may normalise this member.',
        ]);
    }


        // Auto logo plate background from ink luminance (once at upload)
        if (!function_exists('rc_logo_mean_luminance')) {
            function rc_logo_mean_luminance(string $path): ?float {
                if (!is_file($path) || !function_exists('imagecreatefromstring')) {
                    return null;
                }
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['svg', 'svgz'], true)) {
                    return null; // treat SVG as dark-ink default via caller
                }
                $blob = @file_get_contents($path);
                if ($blob === false || $blob === '') {
                    return null;
                }
                $im = @imagecreatefromstring($blob);
                if (!$im) {
                    return null;
                }
                $w = imagesx($im);
                $h = imagesy($im);
                if ($w < 1 || $h < 1) {
                    imagedestroy($im);
                    return null;
                }
                $stepX = max(1, (int) floor($w / 48));
                $stepY = max(1, (int) floor($h / 48));
                $sum = 0.0;
                $n = 0;
                for ($y = 0; $y < $h; $y += $stepY) {
                    for ($x = 0; $x < $w; $x += $stepX) {
                        $rgb = imagecolorat($im, $x, $y);
                        if ($rgb === false) {
                            continue;
                        }
                        $a = ($rgb & 0x7F000000) >> 24;
                        // GD alpha 0=opaque 127=transparent
                        if ($a > 100) {
                            continue;
                        }
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $sum += (0.2126 * $r + 0.7152 * $g + 0.0722 * $b);
                        $n++;
                    }
                }
                imagedestroy($im);
                return $n > 0 ? ($sum / $n) : null;
            }
        }

    if ($action === 'save_company_brand') {
        if (empty($isAdmin) && empty($isSuperAdmin)) {
            http_response_code(403);
            sendJson(['status' => 'error', 'message' => 'Administrator required to update brand images']);
        }
        // Dedicated brand-image save — does not go through generic list/edit save.
        $co = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
        if (isset($co[0]) && is_array($co[0]) && !isset($co['name'])) {
            $co = $co[0];
        }
        if (!is_array($co)) {
            $co = [];
        }
        $destDir = defined('IMG_PATH') ? IMG_PATH : (DATA_PATH . '/media/images');
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        if (!is_dir($destDir) || !is_writable($destDir)) {
            sendJson(['status' => 'error', 'message' => 'Media folder not writable: ' . str_replace('\\', '/', (string)$destDir)]);
        }

        $lbg = strtolower(trim((string)($input['logo_bg'] ?? $_POST['logo_bg'] ?? $co['logo_bg'] ?? 'auto')));
        if (!in_array($lbg, ['auto', 'light', 'dark'], true)) {
            $lbg = 'auto';
        }

        $saved = [];
        $errors = [];
        $warnings = [];
        $map = [
            'logo' => ['stem' => 'company-logo', 'allow' => ['png','jpg','jpeg','webp','svg','svgz']],
            'favicon' => ['stem' => 'company-favicon', 'allow' => ['png','jpg','jpeg','webp','svg','svgz','ico']],
            'cover' => ['stem' => 'company-cover', 'allow' => ['png','jpg','jpeg','webp']],
        ];
        // Alias field names some clients send
        foreach (['brand_logo' => 'logo', 'brand_favicon' => 'favicon', 'brand_cover' => 'cover',
                  'company_logo' => 'logo', 'company_favicon' => 'favicon', 'company_cover' => 'cover'] as $alias => $canon) {
            if (!empty($_FILES[$alias]['name']) && empty($_FILES[$canon]['name'])) {
                $_FILES[$canon] = $_FILES[$alias];
            }
        }
        // Debug: which file keys arrived
        $receivedKeys = [];
        foreach ($_FILES as $fk => $fi) {
            if (!empty($fi['name'])) {
                $receivedKeys[] = $fk . ':' . (string)$fi['name'] . ':' . (int)($fi['error'] ?? -1) . ':' . (int)($fi['size'] ?? 0);
            }
        }
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

        
        // Empty multipart diagnostic (post_max_size / upload_max_filesize)
        if ($receivedKeys === [] && !empty($_SERVER['CONTENT_TYPE'])
            && stripos((string)$_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
            $cl = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
            $postMax = ini_get('post_max_size') ?: '?';
            $upMax = ini_get('upload_max_filesize') ?: '?';
            if ($cl > 0) {
                sendJson([
                    'status' => 'error',
                    'message' => 'No files reached PHP. CONTENT_LENGTH=' . $cl
                        . ' bytes but $_FILES is empty. Raise post_max_size (' . $postMax
                        . ') and upload_max_filesize (' . $upMax . ') above the total upload size, then retry.',
                    'received' => '',
                    'content_length' => $cl,
                ]);
            }
        }

        foreach ($map as $field => $cfg) {
            if (empty($_FILES[$field]['name'])) {
                continue;
            }
            $err = (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = $field . ': upload error ' . $err;
                continue;
            }
            $tmp = (string)$_FILES[$field]['tmp_name'];
            $orig = (string)$_FILES[$field]['name'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if ($ext === 'svgz') {
                $ext = 'svg';
            }
            if (!in_array($ext, $cfg['allow'], true)) {
                $errors[] = $field . ': unsupported type .' . $ext;
                continue;
            }
            $mime = $finfo ? (string)@finfo_file($finfo, $tmp) : '';
            $allowSvg = in_array($field, ['logo', 'favicon'], true) && $ext === 'svg';
            if ($ext !== 'ico' && class_exists('AppMedia') && AppMedia::isDangerousUpload($orig, $mime, $allowSvg)) {
                $errors[] = $field . ': blocked by security check';
                continue;
            }
            $filename = $cfg['stem'] . '.' . $ext;
            $destPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destDir), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . $filename;

            if ($ext === 'svg') {
                $stage = rtrim((string)$destDir, '/\\') . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(6)) . '.tmp';
                $raw = '';
                if (@move_uploaded_file($tmp, $stage)) {
                    $raw = (string)@file_get_contents($stage);
                    @unlink($stage);
                } else {
                    $raw = (string)@file_get_contents($tmp);
                }
                if ($raw === '') {
                    $errors[] = $field . ': could not read SVG (open_basedir?)';
                    continue;
                }
                $clean = class_exists('AppMedia') ? AppMedia::sanitizeSvg($raw) : $raw;
                if ($clean === '' || stripos($clean, '<svg') === false) {
                    $errors[] = $field . ': SVG failed sanitiser — re-export as plain SVG or use PNG';
                    continue;
                }
                if (!str_starts_with(ltrim($clean), '<?xml')) {
                    $clean = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . $clean;
                }
                if (@file_put_contents($destPath, $clean, LOCK_EX) === false) {
                    $errors[] = $field . ': could not write SVG';
                    continue;
                }
            } else {
                if (!@move_uploaded_file($tmp, $destPath) && !@copy($tmp, $destPath)) {
                    $errors[] = $field . ': could not store file';
                    continue;
                }
                // Optimise rasters only (not ico)
                if ($ext !== 'ico' && class_exists('SystemDataOptimizer') && is_file($destPath)) {
                    try {
                        $edge = ($field === 'favicon') ? 192 : (($field === 'cover') ? 1200 : 1600);
                        $opt = SystemDataOptimizer::optimizeImageFile($destPath, [
                            'max_edge' => $edge,
                            'quality' => ($field === 'logo') ? 82 : 80,
                            'prefer_webp' => ($field !== 'favicon' && $field !== 'cover'),
                            'max_bytes' => ($field === 'cover') ? 900000 : 450000,
                        ]);
                        if (!empty($opt['ok']) && !empty($opt['name'])) {
                            $filename = (string)$opt['name'];
                            $destPath = (string)($opt['path'] ?? $destPath);
                        }
                    } catch (Throwable $e) {
                        // Keep original upload if optimiser fails
                        // non-fatal: keep original file
                        $warnings[] = $field . ': optim skipped (' . $e->getMessage() . ')';
                    }
                }
            }
            @chmod($destPath, 0664);
            // Remove sibling extensions
            $stem = $cfg['stem'];
            foreach (['svg', 'png', 'webp', 'jpg', 'jpeg', 'gif', 'ico'] as $sx) {
                if (strtolower((string)pathinfo($filename, PATHINFO_EXTENSION)) === $sx) {
                    continue;
                }
                $sib = rtrim((string)$destDir, '/\\') . DIRECTORY_SEPARATOR . $stem . '.' . $sx;
                if (@is_file($sib)) {
                    @unlink($sib);
                }
            }
            $co[$field] = $filename;
            $saved[$field] = [
                'file' => $filename,
                'bytes' => @filesize($destPath) ?: 0,
            ];
        }
        if ($finfo) {
            @finfo_close($finfo);
        }

        // logo_sig from browser
        if (!empty($_FILES['logo_sig']['tmp_name']) && (int)($_FILES['logo_sig']['error'] ?? 0) === UPLOAD_ERR_OK) {
            $sigDest = rtrim((string)$destDir, '/\\') . DIRECTORY_SEPARATOR . 'company-logo.sig.png';
            if (@move_uploaded_file($_FILES['logo_sig']['tmp_name'], $sigDest) || @copy($_FILES['logo_sig']['tmp_name'], $sigDest)) {
                $co['logo_sig'] = 'company-logo.sig.png';
                $saved['logo_sig'] = ['file' => 'company-logo.sig.png', 'bytes' => @filesize($sigDest) ?: 0];
            }
        }

        if ($lbg === 'auto' && !empty($saved['logo'])) {
            $logoPath = rtrim((string)$destDir, '/\\') . DIRECTORY_SEPARATOR . basename((string)$co['logo']);
            if (function_exists('rc_logo_mean_luminance')) {
                $lum = rc_logo_mean_luminance($logoPath);
                $co['logo_bg'] = ($lum !== null && $lum > 150.0) ? 'dark' : 'light';
            } else {
                $co['logo_bg'] = 'light';
            }
        } else {
            $co['logo_bg'] = $lbg;
        }

        if ($saved === [] && $errors !== []) {
            sendJson(['status' => 'error', 'message' => implode('; ', $errors), 'errors' => $errors, 'received' => implode(', ', $receivedKeys ?? [])]);
        }
        if ($saved === []) {
            // No brand file was stored. Report clearly — do not claim success as "plate updated"
            // unless the client only sent logo_bg with zero file parts.
            $hasFilePart = false;
            foreach (['logo', 'favicon', 'cover', 'logo_sig'] as $__fk) {
                if (!empty($_FILES[$__fk]['name'])) { $hasFilePart = true; break; }
            }
            if ($hasFilePart || !empty($receivedKeys)) {
                sendJson([
                    'status' => 'error',
                    'message' => 'Files arrived but none could be stored. ' . implode('; ', $errors ?: ['Check file type and server permissions.']),
                    'errors' => $errors,
                    'received' => implode(', ', $receivedKeys ?? []),
                ]);
            }
            // True plate-only: still save logo_bg
            $co['logo_bg'] = $lbg;
        }

        $co['updated_at'] = date('c');
        if (!AppDB::save('company', $co)) {
            sendJson(['status' => 'error', 'message' => 'Images may be on disk but company record failed to save']);
        }
        if (class_exists('CardCache') && method_exists('CardCache', 'bust')) {
            try { CardCache::bust(); } catch (Throwable $e) {}
        }
        if (class_exists('AuditLog')) {
            AuditLog::write('save_company_brand', ['saved' => array_keys($saved)]);
        }
        $media_v = [];
        foreach (['logo', 'favicon', 'cover'] as $k) {
            $fn = basename((string)($co[$k] ?? ''));
            if ($fn === '') continue;
            $p = rtrim((string)$destDir, '/\\') . DIRECTORY_SEPARATOR . $fn;
            $media_v[$k] = @is_file($p) ? (int)@filemtime($p) : time();
        }
        $msg = $saved !== []
            ? ('Saved: ' . implode(', ', array_keys($saved)))
            : 'Logo plate updated';
        if ($errors !== []) {
            $msg .= ' · issues: ' . implode('; ', $errors);
        }
        if (!empty($warnings)) {
            $msg .= ' · notes: ' . implode('; ', $warnings);
        }
        sendJson([
            'status' => 'success',
            'message' => $msg,
            'saved' => $saved,
            'errors' => $errors,
            'warnings' => $warnings ?? [],
            'received' => implode(', ', $receivedKeys ?? []),
            'company' => [
                'logo' => $co['logo'] ?? '',
                'favicon' => $co['favicon'] ?? '',
                'cover' => $co['cover'] ?? '',
                'logo_bg' => $co['logo_bg'] ?? 'auto',
                'logo_sig' => $co['logo_sig'] ?? '',
            ],
            'media_v' => $media_v,
            'logo_sig' => (string)($co['logo_sig'] ?? ''),
        ]);
    }

    if ($action === 'save') {

        $ns     = trim((string)($input['ns'] ?? ''));
        $id     = trim((string)($input['id'] ?? ''));
        $isEdit = filter_var($input['__edit_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($ns === 'company') {
            if ($isEdit && empty($input['company_id'])) {
                $__co = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
                $cid = (string)($__co['id'] ?? '');
                if ($cid === '' && isset($__co[0]) && is_array($__co[0]) && isset($__co[0]['id'])) {
                    $cid = (string)$__co[0]['id'];
                }
                if ($cid === '' && defined('TENANT_ID')) {
                    $cid = (string)TENANT_ID;
                }
                if ($cid === '') {
                    $cid = 'company';
                }
                $input['company_id'] = $cid;
            }
        } else {
            if ($isEdit && empty($id)) {
                http_response_code(400); sendJson(['status' => 'error', 'message' => 'Invalid update request: Collection ID missing']);
            }
        }

        $payloadRaw = $input['payload'] ?? '{}';
        $newData    = is_array($payloadRaw) ? $payloadRaw : json_decode((string)$payloadRaw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400); sendJson(['status' => 'error', 'message' => 'Invalid JSON payload structure.']);
        }
        if ($ns === 'company' && isset($input['logo_bg']) && is_array($newData)) {
            $newData['logo_bg'] = strtolower(trim((string)$input['logo_bg']));
        }

        // Company is a singleton: image-only / partial saves must not wipe other fields
        if ($ns === 'company') {
            $__existing = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
            if (isset($__existing[0]) && is_array($__existing[0]) && !isset($__existing['name']) && !isset($__existing['id'])) {
                $__existing = $__existing[0];
            }
            if (!is_array($newData)) {
                $newData = [];
            }
            // Strip client-only preview blobs
            foreach (['_logoPreview', '_photoPreview', 'photoPreview'] as $__k) {
                unset($newData[$__k]);
            }
            foreach (['logo', 'favicon', 'cover', 'photo'] as $__ik) {
                if (isset($newData[$__ik]) && is_string($newData[$__ik]) && str_starts_with($newData[$__ik], 'data:')) {
                    unset($newData[$__ik]);
                }
            }
            // Keys ABSENT from the payload keep their stored value -- that alone is
            // what protects an image-only save ({logo_bg} + a file) from wiping
            // name/address/social. Do NOT also drop empty-string values: that made
            // it impossible to clear any company field (phone, tagline, website,
            // address...) because the blank was filtered out and the stored value
            // merged straight back in. Only JSON null is treated as "not supplied".
            // The organisation name cannot be blanked this way either way:
            // SavePrep rejects a name shorter than 2 characters after this merge.
            $newData = array_merge(
                is_array($__existing) ? $__existing : [],
                array_filter(
                    $newData,
                    static function ($v) {
                        return $v !== null;
                    }
                )
            );
        }

        // ── Field prep (Phase 8 SavePrep) ─────────────────────────────────────
        if (class_exists(\App\Rc\Http\Handlers\SavePrep::class, false) || class_exists('App\Rc\Http\Handlers\SavePrep')) {
            $prep = \App\Rc\Http\Handlers\SavePrep::prepare($ns, is_array($newData) ? $newData : [], $id, $isEdit);
            $newData = $prep['data'];
            if (!empty($prep['error'])) {
                sendJson(['status' => 'error', 'message' => $prep['error']]);
            }
        } else {
            // Fallback if Rc Handlers missing on disk
            if (!empty($newData['name'])) {
                $newData['name'] = mb_convert_case(mb_strtolower(trim((string)$newData['name']), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            }
            if (!empty($newData['email'])) {
                $newData['email'] = strtolower(trim((string)$newData['email']));
            }
            if ($ns === 'team' && !empty($newData['phone']) && class_exists('AppSlug')) {
                $newData['phone'] = AppSlug::normalizePhone((string)$newData['phone']);
            }
            if ($ns === 'team' && (class_exists(\App\Rc\Domain\Team\RecordNormalize::class, false) || class_exists('App\Rc\Domain\Team\RecordNormalize'))) {
                $newData = \App\Rc\Domain\Team\RecordNormalize::apply($newData);
            }
            if ($ns === 'bank' && (class_exists(\App\Rc\Domain\Bank\RecordNormalize::class, false) || class_exists('App\Rc\Domain\Bank\RecordNormalize'))) {
                $newData = \App\Rc\Domain\Bank\RecordNormalize::apply($newData);
            }
            if ($ns === 'company' && (empty($newData['name']) || strlen(trim((string)$newData['name'])) < 2)) {
                sendJson(['status' => 'error', 'message' => 'Organization Name is required.']);
            }
            if (isset($newData['slug'])) {
                $newData['slug'] = preg_replace('/[^a-z0-9\-]+/', '', strtolower(trim(basename(str_replace('\\', '/', (string)$newData['slug'])))));
                if ($newData['slug'] !== '') {
                    $existing = AppDB::read($ns);
                    if (is_array($existing) && $ns !== 'company') {
                        foreach ($existing as $row) {
                            if (($row['slug'] ?? '') === $newData['slug'] && (string)($row['id'] ?? '') !== (string)$id) {
                                sendJson(['status' => 'error', 'message' => 'Duplicate ID/Slug detected.']);
                            }
                        }
                    }
                }
            }
            if ($ns === 'team' && !$isEdit && class_exists('AppSlug')) {
                if (!empty($newData['phone'])) {
                    $newData['phone'] = AppSlug::normalizePhone((string)$newData['phone']);
                }
                if (empty($newData['slug'])) {
                    $newData['slug'] = AppSlug::generate(
                        (string)($newData['name']  ?? ''),
                        (string)($newData['dob']   ?? ''),
                        (string)($newData['phone'] ?? '')
                    );
                }
                $newData['slug'] = preg_replace('/[^a-z0-9\-]+/', '', strtolower(trim(basename(str_replace('\\', '/', (string)$newData['slug'])))));
            }
        }

        if (!empty($_FILES)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            // Map alternate client field names onto canonical keys
            if (!empty($_FILES['team_photo']['name']) && empty($_FILES['photo']['name'])) {
                $_FILES['photo'] = $_FILES['team_photo'];
            }
            // Email-signature PNG can arrive without a new primary logo file
            if ($ns === 'company' && !empty($_FILES['logo_sig']['tmp_name'])
                && (int)($_FILES['logo_sig']['error'] ?? 0) === UPLOAD_ERR_OK) {
                $__sigTmp = (string)$_FILES['logo_sig']['tmp_name'];
                $__sigMime = (string)@finfo_file($finfo, $__sigTmp);
                if ($__sigMime === '' || str_contains($__sigMime, 'png') || str_contains($__sigMime, 'octet')) {
                    $__sigDir = defined('IMG_PATH') ? IMG_PATH : (DATA_PATH . '/media/images');
                    if (!is_dir($__sigDir)) {
                        @mkdir($__sigDir, 0755, true);
                    }
                    $__sigDest = rtrim($__sigDir, '/\\') . DIRECTORY_SEPARATOR . 'company-logo.sig.png';
                    if (@move_uploaded_file($__sigTmp, $__sigDest) || @copy($__sigTmp, $__sigDest)) {
                        @chmod($__sigDest, 0664);
                        $newData['logo_sig'] = 'company-logo.sig.png';
                    }
                }
            }
            // Snapshot existing company brand paths so cover/favicon never wipe logo
            $__coSnap = ['logo' => '', 'favicon' => '', 'cover' => ''];
            if ($ns === 'company') {
                $__coPrev = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
                if (isset($__coPrev[0]) && is_array($__coPrev[0]) && !isset($__coPrev['name'])) {
                    $__coPrev = $__coPrev[0];
                }
                if (is_array($__coPrev)) {
                    foreach (['logo', 'favicon', 'cover'] as $__ck) {
                        $__coSnap[$__ck] = (string)($__coPrev[$__ck] ?? '');
                    }
                }
                // Only treat fields that actually have a real upload in this request
                $__coUploaded = [];
                foreach (['logo', 'favicon', 'cover'] as $__ck) {
                    if (!empty($_FILES[$__ck]['name']) && (int)($_FILES[$__ck]['error'] ?? 4) === UPLOAD_ERR_OK) {
                        $__coUploaded[$__ck] = true;
                    }
                }
            }

            foreach (['logo', 'favicon', 'cover', 'photo', 'doc_file', 'file'] as $field) {

                if (empty($_FILES[$field]['name'])) continue;

                // ── Report upload failures instead of skipping them ──────────
                // BUG (fixed 260906.22): this branch previously required
                // error === UPLOAD_ERR_OK and had no else. Any other outcome —
                // most commonly a file larger than PHP's upload_max_filesize,
                // which is often only 2 MB on shared hosting — was silently
                // ignored. The record then saved successfully WITHOUT the file
                // and the user was shown a success message, with nothing
                // anywhere to indicate the document had not been stored.
                $upErr = (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($upErr !== UPLOAD_ERR_OK) {
                    finfo_close($finfo);
                    $msg = (class_exists(\App\Rc\Support\UploadErrors::class, false) || class_exists('App\Rc\Support\UploadErrors'))
                        ? \App\Rc\Support\UploadErrors::message($upErr)
                        : ('Upload failed (error ' . $upErr . ').');
                    if (class_exists('AppLog')) AppLog::error('Upload failed', ['field' => $field, 'code' => $upErr]);
                    sendJson(['status' => 'error', 'message' => $msg]);
                }

                {
                    $tmp = $_FILES[$field]['tmp_name'];

                    $mime = (string)finfo_file($finfo, $tmp);

                    // FIX: previously only checked "contains .php" / mime substring
                    // "php"/"executable" — did not block .asp/.aspx/.phtml/etc.,
                    // and this shared check was never applied to doc_file at all.
                    // Now every upload field, including documents, goes through
                    // the same deny-list used by AppMedia::import().
                    $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $allowSvgField = in_array($field, ['logo', 'favicon'], true)
                        || ($ns === 'locations' && in_array($field, ['logo', 'photo'], true));
                    // Trust .svg extension for brand marks (IIS finfo often returns octet-stream)
                    $isLogoSvg = ($allowSvgField && in_array($ext, ['svg', 'svgz'], true));
                    $isFavIco = ($field === 'favicon' && $ext === 'ico');
                    // ICO is binary icon data — not dangerous; skip false positives on octet-stream
                    if ($isFavIco) {
                        // allow through
                    } elseif (class_exists('AppMedia') && AppMedia::isDangerousUpload((string)$_FILES[$field]['name'], $mime, $isLogoSvg)) {
                        finfo_close($finfo);
                        sendJson(['status' => 'error', 'message' => "Security Block: Executable or script files are prohibited."]);
                    }
                    if ($isLogoSvg && !class_exists('AppMedia')) {
                        finfo_close($finfo);
                        sendJson(['status' => 'error', 'message' => 'SVG logo requires AppMedia sanitiser.']);
                    }

                    if ($ns === 'company' && in_array($field, ['logo', 'favicon', 'cover'], true)) {
                        $map = ['logo' => 'company-logo', 'favicon' => 'company-favicon', 'cover' => 'company-cover'];
                        $filename = $map[$field] . '.' . ($ext === 'svgz' ? 'svg' : $ext);
                    } elseif ($field === 'photo' && $ns === 'team' && !empty($newData['slug'])) {
                        $safeSlug = preg_replace('/[^a-z0-9\-]+/', '', strtolower(basename(str_replace('\\', '/', (string)$newData['slug'])))) ?: 'member';
                        $filename = $safeSlug . '.' . $ext;
                    } elseif ($field === 'photo' && $ns === 'team') {
                        $filename = preg_replace('/[^a-z0-9]/i', '', strtolower((string)($newData['name'] ?? 'user'))) . '_' . str_replace('.', '', uniqid('', true)) . '.' . $ext;
                    } elseif ($ns === 'mediakit') {
                        $mkBase = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string)($newData['name'] ?? $newData['slug'] ?? 'media')));
                        $mkBase = trim($mkBase, '-') ?: 'media';
                        $filename = 'mk-' . $mkBase . '-' . substr(str_replace('.', '', uniqid('', true)), -6) . '.' . $ext;
                    } else {
                        $filename = preg_replace('/[^a-z0-9]/i', '_', strtolower((string)($newData['slug'] ?? 'file'))) . '_' . str_replace('.', '', uniqid('', true)) . '.' . $ext;
                    }

                    $destDir = (class_exists(\App\Rc\Support\UploadDest::class, false) || class_exists('App\Rc\Support\UploadDest'))
                        ? \App\Rc\Support\UploadDest::forField((string)$field)
                        : ((strpos($field, 'doc') !== false) ? DOC_PATH : IMG_PATH);
                    if (!is_dir($destDir)) {
                        @mkdir($destDir, 0775, true);
                    }
                    // A non-writable destination was also silent before: the
                    // move simply returned false and the save reported success.
                    if (!is_dir($destDir) || !is_writable($destDir)) {
                        finfo_close($finfo);
                        if (class_exists('AppLog')) AppLog::error('Upload destination not writable', ['dir' => $destDir]);
                        sendJson(['status' => 'error', 'message' => 'Server cannot write to the media folder (' . str_replace('\\', '/', (string)$destDir) . '). Grant write ACL / chmod 775.']);
                    }

                    $destPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
                    if ($isLogoSvg ?? false) {
                        // Read the upload via move_uploaded_file() into the media folder, NOT
                        // file_get_contents($tmp). On shared/Plesk hosts open_basedir often
                        // excludes PHP's upload temp dir: reading it directly then fails
                        // silently (the @ hid it) and the SVG was reported as "unsafe" and
                        // never saved, while PNG/WebP uploads -- which already use
                        // move_uploaded_file() -- kept working, so only SVGs seemed broken.
                        // Reproduced with open_basedir set and the temp dir outside it.
                        $upSize = (int)($_FILES[$field]['size'] ?? 0);
                        if ($upSize <= 0) {
                            finfo_close($finfo);
                            sendJson(['status' => 'error', 'message' => 'The uploaded SVG file is empty.']);
                        }
                        $stage = rtrim((string)$destDir, '/\\') . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(8)) . '.tmp';
                        $rawSvg = '';
                        if (@move_uploaded_file($tmp, $stage)) {
                            $rawSvg = (string)@file_get_contents($stage);
                            @unlink($stage);
                        } else {
                            $rawSvg = (string)@file_get_contents($tmp); // hosts where only a direct read works
                        }
                        if ($rawSvg === '') {
                            if (class_exists('AppLog')) {
                                AppLog::error('SVG upload could not be read', [
                                    'field' => $field,
                                    'size' => $upSize,
                                    'open_basedir' => (string)ini_get('open_basedir'),
                                    'upload_tmp_dir' => (string)(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()),
                                ]);
                            }
                            finfo_close($finfo);
                            sendJson(['status' => 'error', 'message' => 'The server could not read the uploaded SVG (PHP\'s upload temp folder is probably outside open_basedir). Ask your host to allow it, or upload a PNG/WebP instead.']);
                        }
                        $cleanSvg = class_exists('AppMedia') ? AppMedia::sanitizeSvg($rawSvg) : '';
                        if ($cleanSvg === '') {
                            finfo_close($finfo);
                            sendJson(['status' => 'error', 'message' => 'That SVG could not be accepted safely. It must be a well-formed SVG with no scripts, external references or undefined entities. Re-export it as plain/optimised SVG (CorelDRAW: Save as SVG; Illustrator: SVG Profile 1.1, entities off), or upload a PNG/WebP instead.']);
                        }
                        if ($ns !== 'company' || !in_array($field, ['logo', 'favicon', 'cover'], true)) {
                            $filename = preg_replace('/[^a-z0-9]/i', '_', strtolower((string)($newData['slug'] ?? 'company'))) . '_' . str_replace('.', '', uniqid('', true)) . '.svg';
                        } else {
                            $filename = pathinfo($filename, PATHINFO_FILENAME) . '.svg';
                        }
                        $destPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
                        // Ensure XML declaration for picky consumers; keep sanitised body
                        if (!str_starts_with(ltrim($cleanSvg), '<?xml')) {
                            $cleanSvg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $cleanSvg;
                        }
                        if (@file_put_contents($destPath, $cleanSvg, LOCK_EX) === false) {
                            finfo_close($finfo);
                            sendJson(['status' => 'error', 'message' => 'Could not save sanitised SVG logo.']);
                        }
                        @chmod($destPath, 0664);
                        if (!is_file($destPath) || filesize($destPath) < 32) {
                            finfo_close($finfo);
                            sendJson(['status' => 'error', 'message' => 'SVG logo write verification failed.']);
                        }
                        $newData[$field] = $filename;
                        // Drop sibling raster/vector so the new SVG is the only company-{field}.*
                        if ($ns === 'company' && in_array($field, ['logo', 'favicon', 'cover'], true)) {
                            $stem = pathinfo($filename, PATHINFO_FILENAME);
                            foreach (['png', 'webp', 'jpg', 'jpeg', 'gif'] as $__sx) {
                                $__sib = rtrim((string)$destDir, "/\\") . DIRECTORY_SEPARATOR . $stem . '.' . $__sx;
                                if (@is_file($__sib)) {
                                    @unlink($__sib);
                                }
                            }
                        }
                        if (class_exists('AppLog')) {
                            AppLog::info('SVG logo saved', ['field' => $field, 'file' => $filename, 'bytes' => filesize($destPath)]);
                        }
                    } elseif (move_uploaded_file($tmp, $destPath)) {
                        @chmod($destPath, 0664);
                        // Optimise every image at save time (incl. large WebP) — no deferred skip
                        $imageFields = ['logo', 'favicon', 'cover', 'photo'];
                        $__extNow = strtolower((string)pathinfo($destPath, PATHINFO_EXTENSION));
                        if (in_array($field, $imageFields, true) && class_exists('SystemDataOptimizer')
                            && !in_array($__extNow, ['svg', 'svgz', 'ico'], true)) {
                            $edge = ($field === 'favicon') ? 192 : (($field === 'cover') ? 1200 : 1600);
                            $opt = SystemDataOptimizer::optimizeImageFile($destPath, [
                                'max_edge' => $edge,
                                'quality' => ($field === 'logo') ? 82 : 80,
                                'prefer_webp' => ($field !== 'favicon'),
                                'max_bytes' => ($field === 'cover') ? 600000 : 450000,
                            ]);
                            if (!empty($opt['ok']) && !empty($opt['name'])) {
                                $filename = (string) $opt['name'];
                                $destPath = (string) ($opt['path'] ?? $destPath);
                            }
                        }
                        $newData[$field] = $filename;
                        // Company brand: drop sibling extensions so stale PNG does not shadow new SVG
                        if ($ns === 'company' && in_array($field, ['logo', 'favicon', 'cover'], true)) {
                            $stem = pathinfo($filename, PATHINFO_FILENAME);
                            $curExt = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
                            foreach (['svg', 'png', 'webp', 'jpg', 'jpeg', 'gif'] as $__sx) {
                                if ($curExt === $__sx) {
                                    continue;
                                }
                                $__sib = rtrim((string)$destDir, "/\\") . DIRECTORY_SEPARATOR . $stem . '.' . $__sx;
                                if (@is_file($__sib)) {
                                    @unlink($__sib);
                                }
                            }
                        }
                        // Locations: photo and logo are aliases of the same asset
                        if ($ns === 'locations' && in_array($field, ['photo', 'logo'], true)) {
                            $newData['logo'] = $filename;
                            $newData['photo'] = $filename;
                        }
                        // Company brand assets are independent — never copy cover/favicon onto logo
                        if ($ns === 'company' && in_array($field, ['cover', 'favicon'], true)) {
                            // Drop accidental logo key if payload sent the same file under logo
                            // (keep existing logo from merge; only this field changes)
                            if (isset($newData['logo']) && $newData['logo'] === $filename) {
                                $__existCo = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
                                if (isset($__existCo[0]) && is_array($__existCo[0])) {
                                    $__existCo = $__existCo[0];
                                }
                                $newData['logo'] = (string)($__existCo['logo'] ?? '');
                            }
                        }

                        if ($field === 'logo' && $ns === 'company') {
                            $__sigPng = function_exists('rc_write_logo_sig_png') ? rc_write_logo_sig_png($destPath) : null;
                            if (is_string($__sigPng) && $__sigPng !== '') {
                                $newData['logo_sig'] = $__sigPng;
                            }
                            // Browser-rasterised PNG (preferred on hosts without Imagick)
                            if (!empty($_FILES['logo_sig']['tmp_name']) && (int)($_FILES['logo_sig']['error'] ?? 0) === UPLOAD_ERR_OK) {
                                $__sigTmp = (string)$_FILES['logo_sig']['tmp_name'];
                                $__sigMime = (string)@finfo_file($finfo, $__sigTmp);
                                if (str_contains($__sigMime, 'png') || str_contains(strtolower((string)($_FILES['logo_sig']['name'] ?? '')), '.png')) {
                                    $__sigDest = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $destDir), DIRECTORY_SEPARATOR)
                                        . DIRECTORY_SEPARATOR . 'company-logo.sig.png';
                                    if (@move_uploaded_file($__sigTmp, $__sigDest) || @copy($__sigTmp, $__sigDest)) {
                                        @chmod($__sigDest, 0664);
                                        $newData['logo_sig'] = 'company-logo.sig.png';
                                        if (class_exists('AppLog')) {
                                            @AppLog::info('logo_sig PNG saved from browser', ['bytes' => @filesize($__sigDest)]);
                                        }
                                    }
                                }
                            }
                        }
                        if ($field === 'logo' && $ns === 'company') {
                            $bgMode = strtolower(trim((string)($newData['logo_bg'] ?? $input['logo_bg'] ?? 'auto')));
                            if ($bgMode === '' || $bgMode === 'auto') {
                                $lum = rc_logo_mean_luminance($destPath);
                                if ($lum === null) {
                                    $newData['logo_bg'] = 'light';
                                } else {
                                    $newData['logo_bg'] = ($lum > 150.0) ? 'dark' : 'light';
                                }
                            } else {
                                $newData['logo_bg'] = in_array($bgMode, ['light', 'dark'], true) ? $bgMode : 'light';
                            }
                        }
                        if ($ns === 'events' && $field === 'photo') {
                            $newData['photo'] = $filename;
                            $newData['image'] = $filename;
                        }
                        
                        if ($ns === 'mediakit' && in_array($field, ['photo', 'file', 'doc_file'], true)) {
                            $newData['file'] = $filename;
                            $newData['photo'] = $filename;
                            $newData['file_type'] = $ext;
                            if (empty($newData['name']) && !empty($newData['title'])) {
                                $newData['name'] = $newData['title'];
                            }
                            $__bytes = @filesize($destPath);
                            if ($__bytes) {
                                $newData['bytes'] = (int)$__bytes;
                            }
                            if (in_array($ext, ['jpg','jpeg','png','gif','webp','avif','bmp'], true)) {
                                $__info = @getimagesize($destPath);
                                if (is_array($__info)) {
                                    $newData['width'] = (int)($__info[0] ?? 0) ?: null;
                                    $newData['height'] = (int)($__info[1] ?? 0) ?: null;
                                    if (!empty($newData['width']) && !empty($newData['height'])) {
                                        $newData['size_hint'] = $newData['width'] . '×' . $newData['height'] . ' px';
                                    }
                                }
                            }
                        }
if ($ns === 'docs') {
                            $newData['file_type'] = strtoupper($ext === 'pdf' ? 'PDF' : $ext);
                            $newData['size']      = self_format_bytes((int)($_FILES[$field]['size'] ?? 0));
                            $newData['updated_at'] = date('d M Y');
                        }
                    } else {
                        finfo_close($finfo);
                        if (class_exists('AppLog')) AppLog::error('move_uploaded_file failed', ['field' => $field, 'dest' => $destDir]);
                        sendJson(['status' => 'error', 'message' => 'Could not save the uploaded file to disk. Please try again.']);
                    }
                }
            }
            finfo_close($finfo);
        }

        // Company: restore logo/favicon/cover that were NOT uploaded in this request
        if ($ns === 'company' && isset($__coSnap) && is_array($__coSnap)) {
            foreach (['logo', 'favicon', 'cover'] as $__ck) {
                $uploaded = !empty($__coUploaded[$__ck]);
                if (!$uploaded) {
                    // Keep previous path; strip any accidental assignment of another brand file
                    if ($__coSnap[$__ck] !== '') {
                        $newData[$__ck] = $__coSnap[$__ck];
                    } else {
                        unset($newData[$__ck]);
                    }
                } else {
                    // Uploaded this field — reject cross-contamination (cover path must not be logo)
                    $fn = basename((string)($newData[$__ck] ?? ''));
                    if ($__ck === 'logo' && preg_match('/^company-(cover|favicon)\./i', $fn)) {
                        $newData['logo'] = $__coSnap['logo'];
                    }
                    if ($__ck === 'cover' && preg_match('/^company-logo\./i', $fn)) {
                        $newData['cover'] = $__coSnap['cover'];
                    }
                    if ($__ck === 'favicon' && preg_match('/^company-logo\./i', $fn)) {
                        $newData['favicon'] = $__coSnap['favicon'];
                    }
                }
            }
            // Absolute: logo key must never equal cover/favicon file names from this request
            $logoFn = basename((string)($newData['logo'] ?? ''));
            $coverFn = basename((string)($newData['cover'] ?? ''));
            if ($logoFn !== '' && $coverFn !== '' && $logoFn === $coverFn) {
                $newData['logo'] = $__coSnap['logo'];
            }
        }

        $dbData = AppDB::read($ns);

        if ($ns === 'company') {
            if (empty($newData['location_id'])) {
                $newData['location_id'] = $newData['location'] ?? $newData['hq_location'] ?? $newData['hq'] ?? '';
            }
            unset($newData['location'], $newData['hq_location'], $newData['hq']);

            if (isset($newData['social']) && is_string($newData['social'])) {
                $decoded = json_decode($newData['social'], true);
                $newData['social'] = is_array($decoded) ? $decoded : [];
            }
            if (isset($newData['social']) && $newData['social'] === []) {
                unset($newData['social']);
            }

            $merged = array_merge(is_array($dbData) ? $dbData : [], $newData);
            // Force image keys ONLY when this request actually uploaded that field
            foreach (['logo', 'favicon', 'cover'] as $__ik) {
                $didUpload = !empty($__coUploaded[$__ik]);
                if ($didUpload && !empty($newData[$__ik]) && is_string($newData[$__ik]) && !str_starts_with($newData[$__ik], 'data:')) {
                    $merged[$__ik] = $newData[$__ik];
                } elseif (!$didUpload && isset($__coSnap[$__ik]) && $__coSnap[$__ik] !== '') {
                    $merged[$__ik] = $__coSnap[$__ik];
                }
            }
            foreach (['logo_sig', 'logo_png', 'logo_raster'] as $__ik) {
                if (!empty($newData[$__ik]) && is_string($newData[$__ik]) && !str_starts_with($newData[$__ik], 'data:')) {
                    // logo_sig only when logo was uploaded
                    if ($__ik === 'logo_sig' && empty($__coUploaded['logo'])) {
                        continue;
                    }
                    $merged[$__ik] = $newData[$__ik];
                }
            }
            if (!AppDB::save($ns, $merged)) sendJson(['status' => 'error', 'message' => 'Atomic Save Failed on Singleton.']);
            if (class_exists('AppDB')) {
                AppDB::clearCache();
            }
            if (class_exists('CardCache')) {
                CardCache::bust();
            }
            if (class_exists('AppDataCache') && method_exists('AppDataCache', 'flushAll')) {
                AppDataCache::flushAll();
            } elseif (class_exists('AppDataCache') && method_exists('AppDataCache', 'invalidate')) {
                AppDataCache::invalidate('company');
            }
            if (class_exists('AuditLog')) AuditLog::write('save', ['ns' => $ns, 'singleton' => true]);
            if (class_exists('AppLog')) AppLog::info("Saved Company configuration details", [
                'logo' => (string)($merged['logo'] ?? ''),
                'favicon' => (string)($merged['favicon'] ?? ''),
            ]);
            // Cache-bust tokens for the client (mtime of files on disk)
            $__v = [];
            foreach (['logo', 'favicon', 'cover'] as $__ik) {
                $__fn = basename((string)($merged[$__ik] ?? ''));
                if ($__fn === '') {
                    continue;
                }
                $__p = (defined('IMG_PATH') ? IMG_PATH : (DATA_PATH . '/media/images')) . DIRECTORY_SEPARATOR . $__fn;
                $__v[$__ik] = is_file($__p) ? (int) @filemtime($__p) : time();
            }
            sendJson(['status' => 'success', 'newData' => $merged, 'media_v' => $__v, 'logo_sig' => (string)($merged['logo_sig'] ?? '')], false);
        } else {
            if ($ns === 'team' && isset($newData['social']) && is_string($newData['social'])) {
                $decoded = json_decode($newData['social'], true);
                $newData['social'] = is_array($decoded) ? $decoded : [];
            }
            if ($ns === 'team' && isset($newData['social']) && $newData['social'] === []) {
                unset($newData['social']);
            }

            $list = is_array($dbData) ? $dbData : [];
            if ($isEdit) {
                $found = false;
                foreach ($list as &$item) {
                    if ((string)($item['id'] ?? '') === $id) {
                        $item = array_merge($item, $newData);
                        $found = true; break;
                    }
                }
                if (!$found) { http_response_code(404); sendJson(['status' => 'error', 'message' => "Update Failed: Identifier not found."]); }
            } else {
                if (empty($newData['id'])) $newData['id'] = uniqid();
                $list[] = $newData;
            }
            // Bank: pre-generate static UPI QR into images/ (no live generator links on share)
            if ($ns === 'bank') {
                if ($isEdit) {
                    foreach ($list as &$__b) {
                        if ((string)($__b['id'] ?? '') === (string)$id) {
                            $qrFile = generateBankQrPng($__b);
                            if ($qrFile) {
                                $__b['qr_image'] = $qrFile;
                            }
                            break;
                        }
                    }
                    unset($__b);
                } else {
                    $last = count($list) - 1;
                    if ($last >= 0) {
                        $qrFile = generateBankQrPng($list[$last]);
                        if ($qrFile) {
                            $list[$last]['qr_image'] = $qrFile;
                        }
                    }
                }
            }


            if ($ns === 'mediakit' && is_array($list)) {
                $__mkSeenId = [];
                $__mkSeenFp = [];
                $__mkOut = [];
                foreach ($list as $__row) {
                    if (!is_array($__row)) continue;
                    $__mid = (string)($__row['id'] ?? '');
                    if ($__mid === '') {
                        $__mid = 'mk' . substr(sha1(uniqid('', true)), 0, 12);
                        $__row['id'] = $__mid;
                    }
                    if (isset($__mkSeenId[$__mid])) continue;
                    $__fp = strtolower(trim((string)($__row['name'] ?? ''))) . '|' . strtolower(trim((string)($__row['file'] ?? $__row['photo'] ?? ''))) . '|' . strtolower(trim((string)($__row['caption'] ?? '')));
                    if ($__fp !== '||' && isset($__mkSeenFp[$__fp])) continue;
                    $__mkSeenId[$__mid] = true;
                    if ($__fp !== '||') $__mkSeenFp[$__fp] = true;
                    unset($__row['_preview'], $__row['photoFile']);
                    $__mkOut[] = $__row;
                }
                $list = array_values($__mkOut);
            }
            if (!AppDB::save($ns, $list)) sendJson(['status' => 'error', 'message' => 'Atomic Save Failed.']);
            if (class_exists('AuditLog')) AuditLog::write('save', ['ns' => $ns, 'count' => is_array($list) ? count($list) : 0, 'id' => (string)($input['id'] ?? '')]);
            if (class_exists('CardCache') && in_array($ns, ['team', 'company', 'locations', 'departments', 'designations'], true)) CardCache::bust();
            sendJson(['status' => 'success']);
        }
    }

    // ── XLSX / bulk import — namespace-isolated (never cross-write) ─────────
    if ($action === 'import') {
        try {
            if (!$isAdmin && empty($isSuperAdmin)) {
                http_response_code(403);
                sendJson(['status' => 'error', 'message' => 'Admin access required for import.']);
            }
            $ns = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($input['ns'] ?? '')));
            $allowed = ['team','bank','docs','mediakit','events','locations','departments','designations','cartags','statutory','cctv'];
            if ($ns === '' || !in_array($ns, $allowed, true)) {
                sendJson(['status' => 'error', 'message' => 'Import not allowed for this tab/namespace.']);
            }
            $rows = $input['data'] ?? [];
            if (!is_array($rows) || count($rows) === 0) {
                sendJson(['status' => 'error', 'message' => 'No rows to import.']);
            }
            if (count($rows) > 2000) {
                sendJson(['status' => 'error', 'message' => 'Too many rows (max 2000 per import).']);
            }

            // Detect spreadsheet shape — refuse writing bank sheets into team (and vice versa)
            $headerBlob = '';
            $sample = $rows[0] ?? [];
            if (is_array($sample)) {
                $headerBlob = strtolower(implode(' ', array_keys($sample)));
            }
            $looksBank = (bool) preg_match('/\b(bank_name|holder_name|acc_no|ifsc|upi_id|account\s*no|a\/c)\b/i', $headerBlob . ' ' . json_encode($sample));
            $looksTeam = (bool) preg_match('/\b(designation|department|dob|gotra|phone|employee)\b/i', $headerBlob);
            if ($looksBank && !$looksTeam && $ns !== 'bank') {
                sendJson([
                    'status' => 'error',
                    'message' => 'This spreadsheet looks like Treasury / Bank data. Open the Bank tab and import there — refused to write into "' . $ns . '".',
                ]);
            }
            if ($looksTeam && !$looksBank && $ns === 'bank') {
                sendJson([
                    'status' => 'error',
                    'message' => 'This spreadsheet looks like Team / HR data. Open the Team tab to import — refused to write into bank.',
                ]);
            }

            /** @return array<string,mixed> */
            $normalizeRow = static function (array $raw, string $ns): array {
                $row = [];
                foreach ($raw as $k => $v) {
                    $key = trim((string)$k);
                    $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
                    if ($key === '') continue;
                    $row[$key] = is_scalar($v) ? trim((string)$v) : $v;
                }
                // Lowercase alias map → canonical
                $aliases = [
                    'employee id' => 'slug', 'emp id' => 'slug', 'employee_id' => 'slug',
                    'full name' => 'name', 'employee name' => 'name',
                    'mobile' => 'phone', 'mobile number' => 'phone', 'phone number' => 'phone',
                    'e-mail' => 'email', 'email address' => 'email',
                    'designation' => 'designation_id', 'department' => 'department_id',
                    'location' => 'location_id', 'base location' => 'location_id',
                    'display rank' => 'rank', 'hierarchy' => 'rank',
                    'account holder' => 'holder_name', 'holder' => 'holder_name', 'a/c holder' => 'holder_name',
                    'a/c no' => 'acc_no', 'account no' => 'acc_no', 'account number' => 'acc_no',
                    'account_no' => 'acc_no', 'acc no' => 'acc_no',
                    'upi' => 'upi_id', 'upi id' => 'upi_id', 'ifsc code' => 'ifsc',
                    'bank' => 'bank_name', 'bank name' => 'bank_name',
                    'branch name' => 'branch',
                    'vehicle number' => 'reg_no', 'registration' => 'reg_no', 'reg no' => 'reg_no',
                ];
                // Also map exact case-insensitive keys to canonical when key differs only by case
                $lowerMap = [];
                foreach ($row as $k => $v) {
                    $lowerMap[strtolower(str_replace([' ', '-'], ['_', '_'], $k))] = $k;
                }
                foreach ($aliases as $from => $to) {
                    $fromKey = strtolower(str_replace([' ', '-'], ['_', '_'], $from));
                    if (isset($lowerMap[$fromKey]) && !isset($row[$to])) {
                        $row[$to] = $row[$lowerMap[$fromKey]];
                    }
                    foreach (array_keys($row) as $k) {
                        if (strtolower(trim($k)) === strtolower($from) && !isset($row[$to])) {
                            $row[$to] = $row[$k];
                        }
                    }
                }
                // Copy lowercase-identical canonical keys (holder_name already present etc.)
                foreach (['id','slug','holder_name','bank_name','acc_no','ifsc','branch','upi_id','name','phone','email'] as $ck) {
                    if (!isset($row[$ck])) {
                        foreach ($row as $k => $v) {
                            if (strtolower((string)$k) === $ck) {
                                $row[$ck] = $v;
                                break;
                            }
                        }
                    }
                }

                if ($ns === 'bank') {
                    // STRICT whitelist — never carry team fields into banking.json
                    $allowed = ['id','slug','holder_name','bank_name','acc_no','ifsc','branch','upi_id','qr_image'];
                    $clean = [];
                    foreach ($allowed as $f) {
                        if (isset($row[$f]) && $row[$f] !== '') {
                            $clean[$f] = is_scalar($row[$f]) ? trim((string)$row[$f]) : $row[$f];
                        }
                    }
                    if (empty($clean['holder_name']) && empty($clean['acc_no']) && empty($clean['bank_name'])) {
                        return []; // skip empty / non-bank rows
                    }
                    if (empty($clean['slug']) && !empty($clean['bank_name']) && !empty($clean['acc_no'])) {
                        $tail = preg_replace('/\D/', '', (string)$clean['acc_no']);
                        $tail = substr((string)$tail, -4);
                        $clean['slug'] = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)$clean['bank_name']) . '-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)($clean['holder_name'] ?? 'acct'))) . '-' . $tail);
                        $clean['slug'] = trim($clean['slug'], '-');
                    }
                    return $clean;
                }

                if ($ns === 'team') {
                    // Never accept bank-only columns as a person row
                    if (!empty($row['acc_no']) && empty($row['name']) && empty($row['phone'])) {
                        return [];
                    }
                }
                return $row;
            };

            $existing = AppDB::read($ns);
            if (!is_array($existing)) {
                $existing = [];
            }
            // Ensure list shape
            $existing = array_values(array_filter($existing, 'is_array'));
            $byId = [];
            $bySlug = [];
            $byAcc = []; // bank dedupe on account number
            foreach ($existing as $i => $row) {
                if (!empty($row['id'])) $byId[strtolower((string)$row['id'])] = $i;
                if (!empty($row['slug'])) $bySlug[strtolower((string)$row['slug'])] = $i;
                if ($ns === 'bank' && !empty($row['acc_no'])) {
                    $byAcc[preg_replace('/\s+/', '', strtolower((string)$row['acc_no']))] = $i;
                }
            }

            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            foreach ($rows as $raw) {
                if (!is_array($raw)) { $skipped++; continue; }
                $row = $normalizeRow($raw, $ns);
                if ($row === []) { $skipped++; continue; }

                $id = strtolower(trim((string)($row['id'] ?? '')));
                $slug = strtolower(trim((string)($row['slug'] ?? '')));
                $matchIdx = null;
                if ($id !== '' && isset($byId[$id])) $matchIdx = $byId[$id];
                elseif ($slug !== '' && isset($bySlug[$slug])) $matchIdx = $bySlug[$slug];
                elseif ($ns === 'bank' && !empty($row['acc_no'])) {
                    $ak = preg_replace('/\s+/', '', strtolower((string)$row['acc_no']));
                    if ($ak !== '' && isset($byAcc[$ak])) $matchIdx = $byAcc[$ak];
                }

                if ($matchIdx !== null) {
                    $merged = array_merge($existing[$matchIdx], $row);
                    $merged['id'] = $existing[$matchIdx]['id'] ?? ($row['id'] ?? uniqid($ns . '_', true));
                    if ($ns === 'bank') {
                        // Re-whitelist after merge
                        $allowed = ['id','slug','holder_name','bank_name','acc_no','ifsc','branch','upi_id','qr_image'];
                        $clean = [];
                        foreach ($allowed as $f) {
                            if (isset($merged[$f]) && $merged[$f] !== '') $clean[$f] = $merged[$f];
                        }
                        $merged = $clean;
                    }
                    $existing[$matchIdx] = $merged;
                    $updated++;
                } else {
                    if (empty($row['id'])) {
                        $row['id'] = uniqid($ns . '_', true);
                    }
                    if ($ns === 'team' && empty($row['slug']) && class_exists('AppSlug') && !empty($row['name'])) {
                        $row['slug'] = AppSlug::generate(
                            (string)($row['name'] ?? ''),
                            (string)($row['dob'] ?? ''),
                            (string)($row['phone'] ?? '')
                        );
                    }
                    $existing[] = $row;
                    $byId[strtolower((string)$row['id'])] = count($existing) - 1;
                    if (!empty($row['slug'])) $bySlug[strtolower((string)$row['slug'])] = count($existing) - 1;
                    if ($ns === 'bank' && !empty($row['acc_no'])) {
                        $byAcc[preg_replace('/\s+/', '', strtolower((string)$row['acc_no']))] = count($existing) - 1;
                    }
                    $inserted++;
                }
            }

            // CRITICAL: only write the requested namespace — never touch team when ns=bank
            if (!AppDB::save($ns, array_values($existing))) {
                sendJson(['status' => 'error', 'message' => 'Atomic save failed while importing into ' . $ns . '.']);
            }
            if (class_exists('AppLog')) {
                AppLog::info('XLSX import completed', ['ns' => $ns, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped]);
            }
            sendJson([
                'status'   => 'success',
                'message'  => "Imported into {$ns}: {$inserted} new, {$updated} updated" . ($skipped ? ", {$skipped} skipped" : '') . '.',
                'inserted' => $inserted,
                'updated'  => $updated,
                'skipped'  => $skipped,
                'ns'       => $ns,
            ]);
        } catch (Throwable $e) {
            if (class_exists('AppLog')) {
                AppLog::error('Import exception', ['error' => $e->getMessage()]);
            }
            http_response_code(500);
            sendJson(['status' => 'error', 'message' => 'Import failed. Details were logged.']);
        }
    }

    if ($action === 'import_template') {
        try {
            if (!$isAdmin) {
                http_response_code(403);
                sendJson(['status' => 'error', 'message' => 'Admin required.']);
            }
            $ns = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($input['ns'] ?? 'team')));
            $templates = [
                'team' => [
                    ['id','slug','name','phone','email','rank','designation_id','department_id','location_id','dob','gender','blood_group'],
                    ['','','Aakash Kumar','9876543210','aakash@example.com','1','MGR','OPS','HQ','1990-01-15','Male','B+'],
                ],
                'bank' => [
                    ['id','slug','holder_name','bank_name','acc_no','ifsc','branch','upi_id'],
                    ['','','Company Name','ICICI Bank','1234567890','ICIC0001234','Preet Vihar','company@icici'],
                ],
                'locations' => [
                    ['id','slug','name','address','city','state','pincode'],
                    ['','','Head Office','118 Shreshtha Vihar','Delhi','Delhi','110092'],
                ],
                'departments' => [
                    ['id','code','name'],
                    ['','OPS','Operations'],
                ],
                'designations' => [
                    ['id','code','name','rank'],
                    ['','MGR','Manager','3'],
                ],
                'cartags' => [
                    ['id','reg_no','make_model','colour','owner_name','status','phone'],
                    ['','DL01AB1234','Maruti Suzuki Swift','White','Owner Name','active','9876543210'],
                ],
                'docs' => [
                    ['id','slug','name','version','external_url'],
                    ['','policy-hr','HR Policy','1.0',''],
                ],
                'events' => [
                    ['id','title','start','end','location'],
                    ['','Townhall','2026-10-01 10:00','2026-10-01 12:00','HQ'],
                ],
            ];
            $tpl = $templates[$ns] ?? $templates['team'];
            $headers = $tpl[0];
            $sample = $tpl[1] ?? array_fill(0, count($headers), '');
            $rows = [array_combine($headers, $headers)];
            // Better: first row headers as keys for sheet_to_json compatibility when re-exported
            $rows = [array_combine($headers, $sample)];
            sendJson([
                'status'  => 'success',
                'ns'      => $ns,
                'headers' => $headers,
                'rows'    => $rows,
                'message' => 'Template for ' . $ns,
            ]);
        } catch (Throwable $e) {
            sendJson(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}

$dashboardFile = BASE_PATH . '/app/views/dashboard.php';
if (file_exists($dashboardFile)) {
    if (!$isLoggedIn) { if (ob_get_length()) ob_end_flush(); require BASE_PATH . '/app/views/login.php'; exit; }
    if (function_exists('rc_analytics_touch_visit')) { rc_analytics_touch_visit(); }
    // Never let a browser, CDN, or reverse proxy cache the authenticated
    // dashboard page. Added after diagnosing a case where an updated
    // dashboard.php's JavaScript wasn't reflected in the browser even
    // after redeploying — a caching layer serving a stale copy is
    // functionally indistinguishable from "the fix didn't work" from the
    // user's side, so this closes off that entire failure class.
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    $__tab = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['tab'] ?? 'team')) ?: 'team';
    $viewData = ['jsData' => class_exists('AppLookup') ? (AppLookup::all() ?: []) : [], 'company' => AppDB::read('company') ?? ['name' => 'Organization'], 'isAdmin' => $isAdmin, 'isSuperAdmin' => !empty($isSuperAdmin), 'isPublic' => !empty($isPublic), 'currentTab' => $__tab];
    // Perf (20261009.31): slim DASHBOARD_STATE — full data only for core + active tab.
    // Other namespaces contribute a COUNT only so nav badges stay correct without
    // embedding mediakit/docs/bank payloads into every HTML response.
    $__tab = (string)($viewData['currentTab'] ?? 'team');
    $__coreFull = ['team', 'locations', 'departments', 'designations', 'events'];
    // Active tab always gets its full dataset
    $__needFull = array_values(array_unique(array_merge($__coreFull, [$__tab])));
    // Map aliases
    if ($__tab === 'banking') $__needFull[] = 'bank';
    if ($__tab === 'fleet' || $__tab === 'assets') $__needFull[] = 'cartags';

    $viewData['jsData']['_counts'] = is_array($viewData['jsData']['_counts'] ?? null)
        ? $viewData['jsData']['_counts'] : [];

    foreach (['team', 'bank', 'docs', 'mediakit', 'events', 'statutory', 'locations', 'departments', 'designations', 'cartags', 'cctv'] as $k) {
        $rows = AppDB::read($k) ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }
        // Normalize list shape for count
        if ($rows !== [] && !isset($rows[0]) && !isset($rows['id']) && !isset($rows['name'])) {
            $rows = array_values(array_filter($rows, 'is_array'));
        } elseif (isset($rows['id']) || isset($rows['name']) || isset($rows['file'])) {
            // single object
            if ($k !== 'company') {
                $rows = [$rows];
            }
        }
        $cnt = 0;
        if (is_array($rows)) {
            if ($k === 'company') {
                $cnt = !empty($rows) ? 1 : 0;
            } else {
                foreach ($rows as $__r) {
                    if (is_array($__r)) {
                        $cnt++;
                    }
                }
            }
        }
        $viewData['jsData']['_counts'][$k] = $cnt;

        if (in_array($k, $__needFull, true)) {
            $viewData['jsData'][$k] = $rows;
        } else {
            // Empty array in Alpine — badge uses _counts
            $viewData['jsData'][$k] = [];
        }
    }

    // Bank logos — only on treasury tab
    if ($__tab === 'bank' || $__tab === 'banking') {
        if (is_file(BASE_PATH . '/app/MasterDirectory.php')) {
            require_once BASE_PATH . '/app/MasterDirectory.php';
        }
        if (!empty($viewData['jsData']['bank']) && is_array($viewData['jsData']['bank']) && class_exists('MasterDirectory')) {
            foreach ($viewData['jsData']['bank'] as &$_brow) {
                if (!is_array($_brow)) continue;
                $bn = trim((string)($_brow['bank_name'] ?? $_brow['bank'] ?? ''));
                if ($bn === '') continue;
                $logo = method_exists('MasterDirectory', 'bankLogoFor')
                    ? MasterDirectory::bankLogoFor($bn) : '';
                if ($logo === '' && method_exists('MasterDirectory', 'findBank')) {
                    $found = MasterDirectory::findBank($bn);
                    $dom = is_array($found) ? (string)($found['domain_name'] ?? '') : '';
                    $slug = is_array($found) ? (string)($found['slug'] ?? '') : '';
                    if ($dom === '' && method_exists('MasterDirectory', 'guessBankDomain')) {
                        $dom = MasterDirectory::guessBankDomain($bn);
                    }
                    $cands = method_exists('MasterDirectory', 'logoCandidates')
                        ? MasterDirectory::logoCandidates($dom, 'banks', $slug) : [];
                    if (!empty($cands[0])) {
                        $logo = $cands[0];
                    }
                }
                if ($logo !== '') {
                    $_brow['logo'] = $logo;
                    $_brow['bank_logo'] = $logo;
                }
            }
            unset($_brow);
        }
    }
    // OEM logos — only on fleet tab
    if (($__tab === 'cartags' || $__tab === 'fleet')
        && !empty($viewData['jsData']['cartags']) && is_array($viewData['jsData']['cartags'])) {
        if (is_file(BASE_PATH . '/app/VehicleCatalog.php')) {
            require_once BASE_PATH . '/app/VehicleCatalog.php';
        }
        if (is_file(BASE_PATH . '/app/MasterDirectory.php')) {
            require_once BASE_PATH . '/app/MasterDirectory.php';
        }
        foreach ($viewData['jsData']['cartags'] as &$_crow) {
            if (!is_array($_crow)) continue;
            $make = trim((string)($_crow['manufacturer'] ?? $_crow['make'] ?? ''));
            if ($make === '' && class_exists('VehicleCatalog')) {
                $mm = trim((string)($_crow['make_model'] ?? $_crow['model'] ?? ''));
                if ($mm !== '') {
                    $parts = preg_split('/\s+/', $mm) ?: [];
                    for ($n = min(3, count($parts)); $n >= 1; $n--) {
                        $try = implode(' ', array_slice($parts, 0, $n));
                        $key = VehicleCatalog::resolveMake($try);
                        if ($key) { $make = $key; break; }
                    }
                }
            }
            if ($make === '') continue;
            if (class_exists('VehicleCatalog')) {
                $key = VehicleCatalog::resolveMake($make) ?: $make;
                $_crow['manufacturer'] = $key;
                $_crow['oem_logo'] = VehicleCatalog::getVehicleLogo($key);
            }
        }
        unset($_crow);
    }

    // Release session lock BEFORE rendering HTML so parallel media_serve /
    // asset requests are not blocked on this long response.
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    require $dashboardFile;
} else {
    die("<div style='font-family:sans-serif; padding:20px; text-align:center; color:red; font-weight:bold;'>Critical Missing File: app/views/dashboard.php</div>");
}
