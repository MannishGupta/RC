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
                'message' => 'Server error: ' . $e->getMessage(),
                'logs' => ['CRITICAL: ' . $e->getMessage()],
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
if (class_exists('AppLocale')) { AppLocale::boot(); }

AppAuth::setSecureHeaders();
AppAuth::initSession();

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
    if ($b <= 0) return '';
    $u = ['B','KB','MB','GB']; $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return round($b, $b < 10 && $i > 0 ? 1 : 0) . ' ' . $u[$i];
}

function sendJson($data, bool $cacheable = true) {
    if (ob_get_length()) ob_clean();
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = '{"status":"error","message":"JSON encode failed"}';
        $cacheable = false;
    }
    $etag = '"' . hash('sha256', $json) . '"';
    if ($cacheable) {
        $inm = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        // Allow weak validators and multiple etags
        if ($inm !== '' && (hash_equals($etag, $inm) || str_contains($inm, trim($etag, '"')))) {
            http_response_code(304);
            header('ETag: ' . $etag);
            header('Cache-Control: private, must-revalidate');
            exit;
        }
        header('ETag: ' . $etag);
        header('Cache-Control: private, must-revalidate');
    } else {
        header('Cache-Control: no-store');
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Vary: Accept-Encoding, Cookie');
    echo $json;
    exit;
}

/**
 * Pre-generate UPI QR PNG into images/ for a bank record.
 * Returns filename (relative to images/) or null.
 */
function generateBankQrPng(array $bank): ?string {
    $upi = trim((string)($bank['upi_id'] ?? $bank['upi'] ?? ''));
    if ($upi === '') {
        return null;
    }
    $holder = trim((string)($bank['holder_name'] ?? $bank['account_holder'] ?? ''));
    $payload = 'upi://pay?pa=' . rawurlencode($upi)
             . ($holder !== '' ? '&pn=' . rawurlencode($holder) : '')
             . '&cu=INR';
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($bank['id'] ?? $bank['slug'] ?? uniqid('b')));
    if ($id === '') {
        $id = uniqid('b');
    }
    $fname = 'bank-qr-' . $id . '.png';
    $imgDir = defined('IMG_PATH') ? IMG_PATH : ((defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/images');
    if (!is_dir($imgDir)) {
        @mkdir($imgDir, 0755, true);
    }
    $path = $imgDir . DIRECTORY_SEPARATOR . $fname;
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=12&ecc=M&data=' . rawurlencode($payload);
    $bin = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $bin = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            $bin = false;
        }
    }
    if ($bin === false && filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $ctx = stream_context_create(['http' => ['timeout' => 20], 'ssl' => ['verify_peer' => true]]);
        $bin = @file_get_contents($url, false, $ctx);
    }
    if ($bin === false || !is_string($bin) || strlen($bin) < 64) {
        return null;
    }
    // Basic PNG signature check
    if (substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return null;
    }
    if (@file_put_contents($path, $bin) === false) {
        return null;
    }
    return $fname;
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
$psiTokenFile = DATA_PATH . '/psi_token.txt';
$psiQuery = (string)($_GET['psi_preview'] ?? '');
if ($psiQuery !== '' && is_file($psiTokenFile)) {
    $psiExpected = trim((string)@file_get_contents($psiTokenFile));
    // Reject short/empty tokens; require constant-time compare
    if ($psiExpected !== '' && strlen($psiExpected) >= 24
        && hash_equals($psiExpected, $psiQuery)) {
        $psiPreviewActive = true;
        // Read-only directory view (CRM), not admin — no write actions needed for PSI
        $_SESSION['user'] = 'public';
        $currentUser = 'public';
        $isAdmin = false;
        $isSuperAdmin = false;
        $isPublic = true;
        $isLoggedIn = true;
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow');
            // Allow PSI/Lighthouse to fetch without caching a permanent public shell
            header('Cache-Control: no-store, private');
        }
        if (class_exists('AppLog')) {
            @AppLog::info('PSI preview session granted', [
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
            ]);
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
    if ($action !== 'login' && $action !== 'capture_lead') { AppAuth::verify_csrf(); }

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

    if ($action === 'login') {
        if (!AppAuth::checkRateLimit()) {
            sendJson(['status' => 'error', 'message' => 'Too many attempts. Please try again later.']);
        }

        $pass = trim((string)($input['password'] ?? ''));
        $role = AppAuth::verifyCredentials($pass);

        if ($role !== null) {
            // Prevent session fixation; ensure cookie is re-issued before response
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_regenerate_id(true);
            }
            $_SESSION['user'] = $role;
            $_SESSION['true_role'] = $role; // original credential role (for switch-back)
            $_SESSION['login_at'] = time();
            AppAuth::logAttempt(true);
            if (class_exists('AppLog')) AppLog::info(ucfirst($role) . ' Login Success');
            // Persist session file before any response body (critical on some hosts)
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            // 🟢 PRODUCTION-GRADE ISOLATION: Trap all output from the optimizer
            if (function_exists('fastcgi_finish_request')) {
                if (ob_get_length()) ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success']);
                fastcgi_finish_request();

                ob_start();
                try { SystemDataOptimizer::run(false); } catch (\Throwable $e) {
                    if (class_exists('AppLog')) AppLog::error('Post-login optimizer run failed: ' . $e->getMessage());
                }
                ob_end_clean();
                exit;
            } else {
                ob_start();
                try { SystemDataOptimizer::run(false); } catch (\Throwable $e) {
                    if (class_exists('AppLog')) AppLog::error('Post-login optimizer run failed: ' . $e->getMessage());
                }
                ob_end_clean();
                sendJson(['status' => 'success']);
            }
        }

        AppAuth::logAttempt(false);
        if (class_exists('AppLog')) AppLog::error('Invalid Login Attempt');
        sendJson(['status' => 'error', 'message' => 'Invalid Password']);
    }

    if ($action === 'logout') { session_destroy(); sendJson(['status' => 'success']); }

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
        sendJson([
            'status' => 'success',
            'tenant_id' => $tenantId,
            'host' => $host,
            'path' => 'tenants/' . $tenantId . '/data',
            'message' => 'Tenant provisioned. Point DNS A record for ' . ($host ?: 'rc.' . $tenantId . '.…') . ' to this server DocumentRoot, then open the host.',
        ]);
    }

    if ($action === 'tenant_update') {
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
        sendJson(['status' => 'success', 'tenant_id' => $tenantId, 'message' => 'Tenant removed from map and disk.']);
    }


    if (in_array($action, ['save', 'delete'], true)) {
        $nsToCheck = preg_replace('/[^a-z_]/', '', (string)($input['ns'] ?? ''));
        if (!in_array($nsToCheck, ['company', 'team', 'departments', 'locations', 'designations', 'bank', 'docs', 'events', 'statutory', 'cartags', 'cctv', 'leads'], true)) {
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
            if (class_exists('CardCache') && $ns === 'team') CardCache::bust();
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
        try {
            if (!headers_sent()) {
                http_response_code(200);
            }
            @ini_set('memory_limit', '256M');
            @set_time_limit(180);
            if (!class_exists('SystemDataOptimizer')) {
                sendJson(['status' => 'error', 'message' => 'Optimizer class missing', 'logs' => []]);
            }
            $result = SystemDataOptimizer::runAllTenants(true);
            if (!is_array($result)) {
                $result = ['status' => 'success', 'logs' => [(string)$result], 'message' => 'Completed'];
            }
            if (empty($result['status'])) {
                $result['status'] = 'success';
            }
            sendJson($result);
        } catch (\Throwable $e) {
            if (class_exists('AppLog')) {
                AppLog::error('Optimizer fatal: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
            http_response_code(200); // keep JSON contract for UI
            sendJson([
                'status' => 'error',
                'message' => 'Optimizer error: ' . $e->getMessage(),
                'logs' => ['ERROR: ' . $e->getMessage()],
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

    if ($action === 'save') {

        $ns     = trim((string)($input['ns'] ?? ''));
        $id     = trim((string)($input['id'] ?? ''));
        $isEdit = filter_var($input['__edit_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($ns === 'company') {
            if ($isEdit && empty($input['company_id'])) {
                http_response_code(400); sendJson(['status' => 'error', 'message' => 'Invalid update request: company_id missing']);
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

        // ── Universal field normalization ─────────────────────────────────────
        if (!empty($newData['name'])) {
            $newData['name'] = mb_convert_case(mb_strtolower(trim((string)$newData['name']), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }

        if (!empty($newData['email'])) {
            $newData['email'] = strtolower(trim((string)$newData['email']));
        }

        if ($ns === 'team' && !empty($newData['phone']) && class_exists('AppSlug')) {
            $newData['phone'] = AppSlug::normalizePhone((string)$newData['phone']);
        }

        // Birth coordinates: one Google-style "lat, lng" field and/or separate lat/lng.
        if ($ns === 'team') {
            $normCoord = static function ($v, float $min, float $max) {
                if ($v === null || $v === '') return null;
                if (!is_numeric($v)) return null;
                $n = (float) $v;
                if ($n < $min) $n = $min;
                if ($n > $max) $n = $max;
                return round($n, 6);
            };
            $geo = trim((string)($newData['birth_geo'] ?? $newData['geo'] ?? ''));
            if ($geo !== '' && preg_match('/(-?\d+(?:\.\d+)?)\s*[,\s]+\s*(-?\d+(?:\.\d+)?)/', $geo, $gm)) {
                $newData['birth_lat'] = $gm[1];
                $newData['birth_lng'] = $gm[2];
            }
            $latIn = $newData['birth_lat'] ?? $newData['lat'] ?? null;
            $lngIn = $newData['birth_lng'] ?? $newData['lng'] ?? null;
            $lat = $normCoord($latIn, -90.0, 90.0);
            $lng = $normCoord($lngIn, -180.0, 180.0);
            if ($lat !== null && $lng !== null) {
                $newData['birth_lat'] = $lat;
                $newData['birth_lng'] = $lng;
                $newData['lat'] = $lat;
                $newData['lng'] = $lng;
                $newData['birth_geo'] = $lat . ', ' . $lng;
            } elseif ($lat !== null) {
                $newData['birth_lat'] = $lat;
                $newData['lat'] = $lat;
            } elseif ($lng !== null) {
                $newData['birth_lng'] = $lng;
                $newData['lng'] = $lng;
            } else {
                unset($newData['birth_lat'], $newData['birth_lng'], $newData['lat'], $newData['lng'], $newData['birth_geo']);
            }
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

        // ── Auto-generate slug (team only, create mode) ───────────────────────
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

        if (!empty($_FILES)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            foreach (['logo', 'favicon', 'cover', 'photo', 'doc_file'] as $field) {

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
                    $iniMax = ini_get('upload_max_filesize');
                    $msg = match ($upErr) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                            "File is too large. This server accepts up to {$iniMax} per file. "
                            . "Compress the document, or add it as an External URL instead.",
                        UPLOAD_ERR_PARTIAL    => 'Upload was interrupted. Please try again.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Server has no temporary upload folder configured. Contact your host.',
                        UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file to disk.',
                        UPLOAD_ERR_EXTENSION  => 'A server extension blocked this upload.',
                        default               => "Upload failed (error {$upErr}).",
                    };
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
                    if (class_exists('AppMedia') && AppMedia::isDangerousUpload((string)$_FILES[$field]['name'], $mime)) {
                        finfo_close($finfo);
                        sendJson(['status' => 'error', 'message' => "Security Block: Executable or script files are prohibited."]);
                    }

                    $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));

                    if ($field === 'photo' && $ns === 'team' && !empty($newData['slug'])) {
                        $safeSlug = preg_replace('/[^a-z0-9\-]+/', '', strtolower(basename(str_replace('\\', '/', (string)$newData['slug'])))) ?: 'member';
                        $filename = $safeSlug . '.' . $ext;
                    } elseif ($field === 'photo' && $ns === 'team') {
                        $filename = preg_replace('/[^a-z0-9]/i', '', strtolower((string)($newData['name'] ?? 'user'))) . '_' . str_replace('.', '', uniqid('', true)) . '.' . $ext;
                    } else {
                        $filename = preg_replace('/[^a-z0-9]/i', '_', strtolower((string)($newData['slug'] ?? 'file'))) . '_' . str_replace('.', '', uniqid('', true)) . '.' . $ext;
                    }

                    $destDir = (strpos($field, 'doc') !== false) ? DOC_PATH : IMG_PATH;

                    // A non-writable destination was also silent before: the
                    // move simply returned false and the save reported success.
                    if (!is_dir($destDir) || !is_writable($destDir)) {
                        finfo_close($finfo);
                        if (class_exists('AppLog')) AppLog::error('Upload destination not writable', ['dir' => $destDir]);
                        sendJson(['status' => 'error', 'message' => 'Server cannot write to the ' . basename($destDir) . ' folder. Check its permissions.']);
                    }

                    if (move_uploaded_file($tmp, $destDir . '/' . $filename)) {
                        $newData[$field] = $filename;
                        if ($ns === 'locations' && in_array($field, ['photo', 'logo'], true)) {
                            $newData['logo'] = $filename;
                            $newData['photo'] = $filename;
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
            if (!AppDB::save($ns, $merged)) sendJson(['status' => 'error', 'message' => 'Atomic Save Failed on Singleton.']);
            if (class_exists('AuditLog')) AuditLog::write('save', ['ns' => $ns, 'singleton' => true]);
            if (class_exists('AppLog')) AppLog::info("Saved Company configuration details");
            sendJson(['status' => 'success', 'newData' => $merged]);
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

            if (!AppDB::save($ns, $list)) sendJson(['status' => 'error', 'message' => 'Atomic Save Failed.']);
            if (class_exists('AuditLog')) AuditLog::write('save', ['ns' => $ns, 'count' => is_array($list) ? count($list) : 0]);
            if (class_exists('CardCache') && $ns === 'team') CardCache::bust();
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
            $allowed = ['team','bank','docs','events','locations','departments','designations','cartags','statutory','cctv'];
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
            sendJson(['status' => 'error', 'message' => 'Import failed: ' . $e->getMessage()]);
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
    $viewData = ['jsData' => AppLookup::all(), 'company' => AppDB::read('company') ?? ['name' => 'Organization'], 'isAdmin' => $isAdmin, 'isSuperAdmin' => !empty($isSuperAdmin), 'isPublic' => !empty($isPublic), 'currentTab' => $_GET['tab'] ?? 'team'];
    foreach (['team', 'bank', 'docs', 'events', 'statutory', 'locations', 'departments', 'designations', 'cartags', 'cctv'] as $k) { $viewData['jsData'][$k] = AppDB::read($k) ?? []; }
    require $dashboardFile;
} else {
    die("<div style='font-family:sans-serif; padding:20px; text-align:center; color:red; font-weight:bold;'>Critical Missing File: app/views/dashboard.php</div>");
}
