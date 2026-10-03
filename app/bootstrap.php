<?php
// app/bootstrap.php (was lib.php prior to 260906.3)
// Version: 260916.12
//
// This file REPLACES both the old lib.php and lib_vip.php. Those two files
// declared identical class names (AppUtils, AppSlug, AppLog, AppView, AppAuth,
// AppDB, AppMedia, CardContext) with NO class_exists() guards — if both were
// ever require()'d in the same request, PHP would fatal with
// "Cannot redeclare class". This single file is now the only source of truth.
// DELETE lib_vip.php from the server after deploying this file.
//
// CHANGELOG v2027.300 (PHP 8.4.2 / WinNT+Plesk hardening pass):
//  - SECURITY: Hardcoded admin/crm passwords removed from index.php's login
//    flow. AppAuth::verifyCredentials() now checks bcrypt hashes stored in
//    data/auth_config.php (auto-bootstrapped with your existing passwords —
//    see that file's header comment; rotate them).
//  - SECURITY: AppMedia::isDangerousUpload() is now the single allow/deny
//    check used by BOTH image imports here AND the raw document upload path
//    in index.php (previously index.php used a much weaker filter that only
//    blocked filenames containing ".php" — .aspx/.asp/.phtml/etc. were not
//    blocked, which matters a great deal on an IIS/Plesk host where .aspx
//    is natively executable).
//  - WINDOWS FIX: AppDB::save() now retries the atomic rename() up to 3
//    times with a short backoff before giving up. On Windows, rename() can
//    transiently fail with "Access is denied" if another process/request
//    has the destination file open (e.g. a concurrent AppDB::read() mid-
//    fread under LOCK_SH) — this does not happen on POSIX, so it was
//    invisible in any Linux testing. On final failure it now falls back to
//  - PERFORMANCE/RELIABILITY: AppMedia::generateOgImage()'s font loader no
//    longer attempts a live outbound HTTPS download to GitHub during a
//    normal page request. On a shared host, an outbound connection can be
//    slow, blocked, or firewalled — that call sat directly in the render
//    path of every person-card page view via AppSEO::generateTags(). It now
//    only uses fonts already present on disk (system DejaVu or a
//    pre-placed TTF) and falls back to GD's built-in fonts immediately.
//  - AppSEO merged from the old lib_vip.php (branded OG image generation),
//    kept behind the same network-safety fix above.
//  - Defensive casts added throughout (e.g. $_SERVER access) to avoid PHP 8+
//    "undefined array key" warnings surfacing now that display_errors is
//    correctly off in production (see index.php changelog).
//  - No public method signatures changed. Safe drop-in replacement.

if (!defined('BASE_PATH')) exit('No direct script access');

// Project version — single source of truth (see version.php).
// Loaded here because every entry point (index.php, the card pages, the
// public vehicle-tags pages, print.php, the diagnostic scripts) already
// requires lib.php, so APP_VERSION is available everywhere without each
// file having to know where version.php lives.
if (!defined('APP_VERSION')) {
    $_verFile = dirname(__DIR__) . '/version.php';
    if (is_readable($_verFile)) { require_once $_verFile; }
    if (!defined('APP_VERSION'))      define('APP_VERSION', 'unknown');
    if (!defined('APP_VERSION_DATE')) define('APP_VERSION_DATE', 'Unknown');
    unset($_verFile);
}


if (!defined('APP_SKIP_OPCACHE_CHECK')) {
    $_ocDir  = defined('DATA_PATH') ? DATA_PATH : (__DIR__ . '/data');
    $_ocFile = $_ocDir . '/.opcache_version';
    $_ocSeen = @is_readable($_ocFile) ? trim((string)@file_get_contents($_ocFile)) : '';
    if ($_ocSeen !== APP_VERSION) {
        if (function_exists('opcache_reset')) { @opcache_reset(); }
        if (!is_dir($_ocDir)) @mkdir($_ocDir, 0755, true);
        @file_put_contents($_ocFile, APP_VERSION, LOCK_EX);
        if (class_exists('AppLog')) {
            AppLog::info('OPcache auto-flushed: version changed', ['from' => $_ocSeen ?: 'none', 'to' => APP_VERSION]);
        }
    }
    unset($_ocDir, $_ocFile, $_ocSeen);
}

// Canonical field dictionaries (optional — safe if missing)
$_schemaFile = __DIR__ . '/Schema.php';
if (is_readable($_schemaFile)) { require_once $_schemaFile; }
unset($_schemaFile);
// India-first defaults (IST, en_IN)
if (class_exists('AppLocale')) { AppLocale::boot(); }

// Phase-1 modular layer (App\Rc\*) — strangler-compatible
$_rcBoot = __DIR__ . '/Rc/bootstrap_rc.php';
if (is_readable($_rcBoot)) { require_once $_rcBoot; }
unset($_rcBoot);



if (!class_exists('TenantPaths') && is_file(__DIR__ . '/TenantPaths.php')) { require_once __DIR__ . '/TenantPaths.php'; }
class AppUtils {
    public static function safe_json($d) {
        if ($d === null) return '[]';
        return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]';
    }
    public static function sanitizeSlug($r) {
        return preg_replace('/[^a-z0-9\-_]/', '', strtolower(trim((string)$r)));
    }

    public static function getBaseUrl() {
        $https = (!empty($_SERVER['HTTPS']) && (string)$_SERVER['HTTPS'] !== 'off')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
        $protocol = $https ? 'https' : 'http';
        $safeHost = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return $protocol . '://' . $safeHost;
    }

    public static function getImagePath($f) {
        if (!$f) return null;
        $f = basename(str_replace(array("\\", "\0"), array("/", ""), (string)$f));
        if ($f === "" || $f === "." || $f === "..") {
            return null;
        }
        $imgRoot = defined("IMG_PATH") ? IMG_PATH : (defined("DATA_PATH") ? DATA_PATH . "/media/images" : "");
        $dirs = array();
        if ($imgRoot !== "" && is_dir($imgRoot)) {
            $dirs[] = $imgRoot;
        }
        $legacy = (defined("BASE_PATH") ? BASE_PATH : dirname(__DIR__)) . "/images";
        if (is_dir($legacy)) {
            $dirs[] = $legacy;
        }
        foreach ($dirs as $dir) {
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($p)) {
                return "images/" . $f;
            }
        }
        $lower = strtolower($f);
        foreach ($dirs as $dir) {
            $list = @scandir($dir);
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $entry) {
                if ($entry === "." || $entry === "..") {
                    continue;
                }
                if (strtolower($entry) === $lower && is_file($dir . DIRECTORY_SEPARATOR . $entry)) {
                    return "images/" . $entry;
                }
            }
        }
        return null;
    }

    /**
     * Canonical digital business / profile card URL for a team slug.
     * Used by QR payloads, OG tags, share links — must land on ?card=business&slug=
     * (never a bare /{slug} path, which 404s on this app).
     */
    public static function getCardUrl($slug, string $card = 'business'): string
    {
        $slug = self::sanitizeSlug((string)$slug);
        $card = preg_replace('/[^a-z]/', '', strtolower($card)) ?: 'business';
        if ($slug === '') {
            return rtrim(self::getBaseUrl(), '/') . '/';
        }
        return rtrim(self::getBaseUrl(), '/') . '/?card=' . $card . '&slug=' . rawurlencode($slug);
    }

    public static function getFullUrl($path) {
        if (!$path) return '';
        $path = (string)$path;
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        // Query-style paths: ?card=business&slug=x
        if (isset($path[0]) && $path[0] === '?') {
            return rtrim(self::getBaseUrl(), '/') . '/' . $path;
        }
        if (strpos($path, 'card=') !== false) {
            return rtrim(self::getBaseUrl(), '/') . '/?' . ltrim($path, '?');
        }
        // Bare team slug → business card (QR / share target). Do NOT emit /{slug}.
        $trim = ltrim($path, '/');
        if ($trim !== '' && strpos($trim, '/') === false && preg_match('/^[a-z0-9][a-z0-9\-_]*$/i', $trim)) {
            return self::getCardUrl($trim, 'business');
        }
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/');
        $scriptDir = dirname($script);
        if ($scriptDir === '\\' || $scriptDir === '.' || $scriptDir === '') {
            $scriptDir = '/';
        }
        $base = rtrim(self::getBaseUrl() . ($scriptDir === '/' ? '' : $scriptDir), '/');
        return $base . '/' . ltrim($path, '/');
    }
    public static function getDOB($d) {
        $dob = $d['dob'] ?? $d['birthday'] ?? $d['birth_date'] ?? '';
        return str_replace(['/', '.', ' '], '-', trim((string)$dob));
    }
    public static function getRevHash($data) {
        self::ksortRecursive($data);
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    private static function ksortRecursive(&$array) {
        if (is_array($array)) {
            ksort($array);
            foreach ($array as &$v) { if (is_array($v)) self::ksortRecursive($v); }
        }
    }
}

class AppSlug {

    /**
     * Slugs that must NEVER be renamed during bulk migration.
     * Must stay in sync with SystemDataOptimizer::PROTECTED_SLUGS in optimizer.php.
     */
    public const PROTECTED_SLUGS = ['mg', 'ag', 'ap', 'rj', 'akg', 'mk'];

    public static function generate(string $name, string $dob = '', string $mobile = ''): string {
        $words    = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($words, 0, 3) as $word) {
            $first = preg_replace('/[^A-Za-z]/', '', $word);
            if ($first !== '') $initials .= strtoupper($first[0]);
        }
        if ($initials === '') $initials = 'X';

        $suffix = self::extractDDMMYY($dob);

        if ($suffix === '') {
            $digits = preg_replace('/\D/', '', $mobile) ?? '';
            if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
            if (strlen($digits) === 11 && str_starts_with($digits, '0'))  $digits = substr($digits, 1);
            $suffix = strlen($digits) >= 4 ? substr($digits, -4) : substr($digits . '0000', -4);
        }

        return strtolower($initials . $suffix);
    }

    public static function normalizePhone(string $raw): string {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
        if (strlen($digits) === 11 && str_starts_with($digits, '0'))  $digits = substr($digits, 1);

        if (strlen($digits) !== 10) return $raw;

        return '+91 ' . substr($digits, 0, 5) . ' ' . substr($digits, 5);
    }

    public static function isProtected(string $slug): bool {
        return in_array(strtolower(trim($slug)), self::PROTECTED_SLUGS, true);
    }

    private static function extractDDMMYY(string $dob): string {
        if (trim($dob) === '') return '';
        $normalised = str_replace(['/', '.', ' '], '-', trim($dob));
        $ts = strtotime($normalised);
        if ($ts === false || $ts === -1) return '';
        return date('dmy', $ts);
    }
}

class AppLog {
    public static function info($msg, $context = []) { self::write('INFO', $msg, $context); }
    public static function warn($msg, $context = []) { self::write('WARN', $msg, $context); }
    public static function warning($msg, $context = []) { self::write('WARN', $msg, $context); }
    public static function error($msg, $context = []) { self::write('ERROR', $msg, $context); }

    /**
     * NEW: single place for PHP's own error/exception handlers (registered in
     * index.php) to funnel into, so uncaught issues are logged instead of
     * ever being echoed to the client in production.
     */
    public static function critical($msg, $context = []) { self::write('CRITICAL', $msg, $context); }

    private static function write($lvl, $msg, $ctx) {
        $logDir = defined('DATA_PATH') ? DATA_PATH . '/logs' : BASE_PATH . '/data/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        if (!is_array($ctx)) $ctx = [];
        if (class_exists('AppAuth') && empty($ctx['rid'])) {
            $ctx['rid'] = AppAuth::requestId();
        }
        $entry = json_encode([
            'time'  => (class_exists('AppLocale') ? AppLocale::nowIso() : date('c')),
            'level' => $lvl,
            'msg'   => $msg,
            'ctx'   => $ctx,
        ], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE);
        @file_put_contents($logDir . '/app.log', $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}


/**
 * Runtime cache flush — OPcache + version stamp.
 * Called from SystemDataOptimizer::run() and available to flush_cache.php.
 * Without this, FTP uploads of PHP files can leave stale bytecode until
 * APP_VERSION changes or a manual flush runs.
 */
class AppCacheFlush {
    /**
     * @param string $reason  Logged context: optimise | manual | version | deploy
     * @return array{opcache:bool,stamp:bool,version:string}
     */
    public static function all(string $reason = 'manual'): array {
        $ok = false;
        if (function_exists('opcache_reset')) {
            $ok = (bool) @opcache_reset();
        }

        $ver = defined('APP_VERSION') ? (string) APP_VERSION : 'unknown';
        $dataDir = defined('DATA_PATH') ? DATA_PATH : (defined('BASE_PATH') ? BASE_PATH . '/data' : __DIR__ . '/data');
        $stampFile = $dataDir . '/.opcache_version';
        $stamped = false;
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }
        // Align the auto-flush stamp so the next request does not double-reset.
        $stamped = (@file_put_contents($stampFile, $ver, LOCK_EX) !== false);

        if (class_exists('AppLog')) {
            AppLog::info('Runtime caches flushed', [
                'reason'  => $reason,
                'opcache' => $ok,
                'stamp'   => $stamped,
                'version' => $ver,
            ]);
        }

        return ['opcache' => $ok, 'stamp' => $stamped, 'version' => $ver];
    }
}


/**
 * India-first locale helpers: IST clock, en-IN formats, INR, phone display.
 * All dashboard/card code should prefer these over ad-hoc date()/number_format.
 */
class AppLocale {
    public const TZ = 'Asia/Kolkata';
    public const LANG = 'en-IN';
    public const CURRENCY = 'INR';

    /** Ensure process timezone is IST (safe to call once per request). */
    public static function boot(): void {
        try {
            date_default_timezone_set(self::TZ);
        } catch (\Throwable $e) { /* ignore */ }
        if (function_exists('locale_set_default')) {
            @locale_set_default('en_IN');
        }
    }

    /** ISO-8601 with IST offset, e.g. 2026-09-17T16:18:00+05:30 */
    public static function nowIso(): string {
        return (new \DateTimeImmutable('now', self::tz()))->format('c');
    }

    public static function tz(): \DateTimeZone {
        static $tz = null;
        if ($tz === null) $tz = new \DateTimeZone(self::TZ);
        return $tz;
    }

    /**
     * Format a date/time for UI (Indian English conventions).
     * $style: date | datetime | time | long
     */
    public static function format($value, string $style = 'date'): string {
        if ($value === null || $value === '') return '';
        try {
            if ($value instanceof \DateTimeInterface) {
                $dt = \DateTimeImmutable::createFromInterface($value)->setTimezone(self::tz());
            } else {
                $raw = trim((string)$value);
                if ($raw === '') return '';
                $dt = new \DateTimeImmutable($raw, self::tz());
                $dt = $dt->setTimezone(self::tz());
            }
        } catch (\Throwable $e) {
            return (string)$value;
        }
        return match ($style) {
            'time'     => $dt->format('g:i A') . ' IST',
            'datetime' => $dt->format('d-m-Y, g:i A') . ' IST',
            'long'     => $dt->format('l, d-m-Y'),
            'iso'      => $dt->format('c'),
            'dmy'      => $dt->format('d-m-Y'),
            default    => $dt->format('d-m-Y'),             // strict Indian DD-MM-YYYY
        };
    }

    /**
     * INR (en_IN) via intl NumberFormatter — instance cached per request (PHP 8.4).
     * Host confirms intl is available for correct lakh/crore grouping and ₹ symbol.
     */
    public static function money(float|int|string $amount, bool $symbol = true): string {
        $n = is_numeric($amount) ? (float) $amount : 0.0;
        if (extension_loaded('intl') && class_exists(\NumberFormatter::class)) {
            static $currencyFmt = null;
            static $decimalFmt = null;
            if ($symbol) {
                if ($currencyFmt === null) {
                    $currencyFmt = new \NumberFormatter('en_IN', \NumberFormatter::CURRENCY);
                    $currencyFmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, 2);
                }
                $out = $currencyFmt->formatCurrency($n, self::CURRENCY);
                if (is_string($out) && $out !== '') {
                    return $out;
                }
            } else {
                if ($decimalFmt === null) {
                    $decimalFmt = new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL);
                    $decimalFmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, 2);
                }
                $out = $decimalFmt->format($n);
                if (is_string($out) && $out !== '') {
                    return $out;
                }
            }
        }
        // Fallback without intl
        $neg = $n < 0;
        $n = abs($n);
        $int = (int) floor($n);
        $dec = (int) round(($n - $int) * 100);
        $s = self::groupIndian($int) . '.' . str_pad((string) $dec, 2, '0', STR_PAD_LEFT);
        return ($neg ? '-' : '') . ($symbol ? '₹' : '') . $s;
    }

    /** Indian digit grouping: 12,34,567 */
    public static function groupIndian(int $n): string {
        $n = abs($n);
        $s = (string)$n;
        if (strlen($s) <= 3) return $s;
        $last3 = substr($s, -3);
        $rest = substr($s, 0, -3);
        $parts = [];
        while (strlen($rest) > 2) {
            $parts[] = substr($rest, -2);
            $rest = substr($rest, 0, -2);
        }
        if ($rest !== '') $parts[] = $rest;
        return implode(',', array_reverse($parts)) . ',' . $last3;
    }

    /** Display phone in +91 XXXXX XXXXX when possible. */
    public static function phone(string $raw): string {
        if (class_exists('AppSlug')) {
            return AppSlug::normalizePhone($raw);
        }
        $d = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($d) === 12 && str_starts_with($d, '91')) $d = substr($d, 2);
        if (strlen($d) === 10) {
            return '+91 ' . substr($d, 0, 5) . ' ' . substr($d, 5);
        }
        return trim($raw);
    }

    /** PIN code display (6-digit India). */
    public static function pincode(string $raw): string {
        $d = preg_replace('/\D/', '', $raw) ?? '';
        return strlen($d) === 6 ? $d : trim($raw);
    }

    /**
     * JSON blob for the browser (Intl + labels). Embed once in dashboard/login.
     * @return array<string,mixed>
     */
    public static function jsConfig(): array {
        return [
            'tz'       => self::TZ,
            'lang'     => self::LANG,
            'currency' => self::CURRENCY,
            'country'  => 'IN',
            'phoneCode'=> '+91',
            'dateStyle'=> 'd M Y',
            'labels'   => [
                'search'   => 'Search directory…',
                'entries'  => 'entries',
                'entry'    => 'entry',
                'save'     => 'Save',
                'cancel'   => 'Cancel',
                'edit'     => 'Edit',
                'delete'   => 'Delete',
                'share'    => 'Share',
                'print'    => 'Print',
                'export'   => 'Export',
                'addNew'   => 'Add New',
                'noRecords'=> 'No entries found.',
                'ist'      => 'Indian Standard Time (Asia/Kolkata)',
            ],
        ];
    }
}

class AppView {
    public static function e($v, $d = '') { return htmlspecialchars((string)($v ?? $d), ENT_QUOTES, 'UTF-8'); }
    public static function formatScore($score) {
        $s = max(0, min(100, (int)$score));
        $color = $s > 70 ? 'green' : ($s > 40 ? 'yellow' : 'red');
        return "<span class='text-{$color}-600 font-bold'>{$s}%</span>";
    }
}

class AppAuth {
    private static $maxAttempts = 10;
    private static $lockoutTime = 300;

    /** @var array<string,string>|null cache of role => bcrypt hash */
    private static ?array $credentials = null;

    public static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            $dataDir = defined('DATA_PATH') ? DATA_PATH : (defined('BASE_PATH') ? BASE_PATH . '/data' : (__DIR__ . '/../data'));
            $sessDir = defined('SESSION_PATH') ? SESSION_PATH : $dataDir . '/sessions';
            if (!is_dir($sessDir)) {
                @mkdir($sessDir, 0775, true);
            }
            // Writable check — unwritable tenant sessions/ is the #1 cause of
            // "password accepted, bounced back to login" after multi-tenant.
            $probe = $sessDir . '/.rc_write_test';
            $writable = is_dir($sessDir) && @file_put_contents($probe, '1') !== false;
            if ($writable) {
                @unlink($probe);
            } else {
                $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rc_sess_' . (defined('TENANT_ID') ? preg_replace('/[^a-z0-9_-]/i', '', (string) TENANT_ID) : 'default');
                if (!is_dir($fallback)) {
                    @mkdir($fallback, 0775, true);
                }
                $sessDir = $fallback;
            }

            $lifetime = 31536000;
            ini_set('session.save_handler', 'files');
            ini_set('session.save_path', $sessDir);
            ini_set('session.gc_maxlifetime', (string)$lifetime);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');

            // Plesk / reverse proxy: HTTPS may only appear as X-Forwarded-Proto
            $https = false;
            if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
                $https = true;
            }
            if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') {
                $https = true;
            }
            $xfp = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            if ($xfp === 'https') {
                $https = true;
            }
            if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
                $https = true;
            }

            // Unique session name per tenant so shared browsers across hosts
            // never confuse file cookies (belt-and-suspenders; domains already isolate).
            $sessName = 'RCSESSID';
            if (defined('TENANT_ID') && TENANT_ID !== '' && TENANT_ID !== 'default') {
                $sessName = 'RCSESS_' . preg_replace('/[^A-Za-z0-9]/', '', TENANT_ID);
            }
            session_name($sessName);

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'secure'   => $https,
                'httponly' => true,
                'samesite' => 'Lax', // Lax: survives top-level redirects after fetch login
            ]);

            @session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function setSecureHeaders() {
        if (headers_sent()) return;

        // Transport / clickjacking / MIME
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // geolocation=(self) required for track_share / runners; camera/mic still denied
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        // Do not send X-XSS-Protection — deprecated and can enable XSS auditor bugs.

        // HSTS only on HTTPS (shared host may terminate TLS at edge)
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
              || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        if ($https) {
            header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
        }

        // Commercial CSP: Alpine + FA self-hosted under /assets/vendor/
        // SheetJS remains optional CDN (import/export only). Maps frames allowed.
        // 'unsafe-inline' retained for Alpine/x-data and critical inline boot scripts.
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "upgrade-insecure-requests",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://cdnjs.cloudflare.com https://fonts.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.sheetjs.com https://cdn.jsdelivr.net",
            "frame-src 'self' https://www.google.com https://maps.google.com https://www.google.co.in https://maps.googleapis.com",
            "child-src 'self' https://www.google.com https://maps.google.com blob:",
            "connect-src 'self' https://cdn.sheetjs.com https://api.open-meteo.com https://air-quality-api.open-meteo.com https://cdn.jsdelivr.net",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "media-src 'self' blob:",
        ];
        if (!$https) {
            // Drop upgrade-insecure-requests on plain HTTP (local / mixed edge)
            $csp = array_values(array_filter($csp, static fn($d) => $d !== 'upgrade-insecure-requests'));
        }
        header('Content-Security-Policy: ' . implode('; ', $csp));
    }

    /** Stable request id for log correlation (debug without exposing internals to clients). */
    public static function requestId(): string {
        static $id = null;
        if ($id === null) {
            try {
                $id = bin2hex(random_bytes(8));
            } catch (\Throwable $e) {
                $id = uniqid('r', true);
            }
        }
        return $id;
    }

    public static function csrf_token() {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function verify_csrf() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;

        // JSON body often carries csrf_token without populating $_POST
        $ct = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        if (stripos($ct, 'application/json') !== false && empty($_POST['csrf_token']) && empty($_POST['_csrf'])) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    if (!empty($json['csrf_token'])) {
                        $_POST['csrf_token'] = (string)$json['csrf_token'];
                    } elseif (!empty($json['_csrf'])) {
                        $_POST['_csrf'] = (string)$json['_csrf'];
                    }
                }
            }
        }

        // Prefer header (AJAX); fall back to common form field names so
        // classic HTML forms (e.g. Monitor Orphan:Delete) do not 403.
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? $_POST['csrf_token']
              ?? $_POST['_csrf']
              ?? '';
        if (!hash_equals(self::csrf_token(), (string)$token)) {

            // Classify the failure. The old log recorded only an IP, which
            // cannot distinguish two very different events:
            //   'no_token'      no header at all -> almost always an automated
            //                   scanner sweeping the internet. Harmless noise.
            //   'stale_session' a token was sent but no session exists -> a
            //                   real user whose session was garbage-collected
            //                   with the tab still open. They just lost their
            //                   work and deserve a clear message, not a
            //                   generic failure.
            //   'mismatch'      token sent, session exists, values differ ->
            //                   the only genuinely suspicious case.
            $kind = ($token === '')            ? 'no_token'
                  : (empty($_SESSION['csrf_token']) ? 'stale_session' : 'mismatch');

            if (class_exists('AppLog')) {
                AppLog::error('CSRF validation failed', [
                    'kind'   => $kind,
                    'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
                    'path'   => strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?'),
                    'ua'     => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
                    'signed' => !empty($_SESSION['user']),
                ]);
            }
            http_response_code(403);
            $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
            $ctype  = (string)($_SERVER['CONTENT_TYPE'] ?? '');
            if (strpos($accept, 'application/json') !== false || strpos($ctype, 'json') !== false) {
                // A stale session is a user problem, not a security incident.
                // Telling them to sign in again is actionable; "CSRF token
                // validation failed" is not.
                echo json_encode([
                    'status'  => 'error',
                    'code'    => $kind,
                    'message' => $kind === 'mismatch'
                        ? 'Security check failed. Please reload the page and try again.'
                        : 'Your session has expired. Please sign in again — your unsaved changes are still on screen, so you can copy them first.',
                ]);
            }
            exit;
        }
    }

    private static function getRateLimitFile() {
        $dataDir = defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data';
        return $dataDir . '/rate_limit.json';
    }

    public static function checkRateLimit() {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $file = self::getRateLimitFile();
        if (!file_exists($file)) return true;

        $limits = json_decode((string)file_get_contents($file), true) ?: [];
        $attempts = $limits[$ip] ?? ['count' => 0, 'time' => 0];

        if (($attempts['count'] ?? 0) >= self::$maxAttempts) {
            if (time() - ($attempts['time'] ?? 0) < self::$lockoutTime) return false;
        }
        return true;
    }

    public static function logAttempt($success) {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $file = self::getRateLimitFile();

        $fp = @fopen($file, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $size = filesize($file) ?: 0;
            $json = $size > 0 ? fread($fp, $size) : '{}';
            $limits = json_decode((string)$json, true) ?: [];
            $now = time();

            foreach ($limits as $ipKey => $row) {
                if (($now - ($row['time'] ?? 0)) > 86400) unset($limits[$ipKey]);
            }

            if ($success) {
                unset($limits[$ip]);
            } else {
                $attempts = $limits[$ip] ?? ['count' => 0, 'time' => $now];
                $attempts['count'] = ($attempts['count'] ?? 0) + 1;
                $attempts['time'] = $now;
                $limits[$ip] = $attempts;
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($limits));
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    
    /**
     * Three-tier access via bcrypt hashes in auth_config.php (missing roles auto-filled).
     * super_admin → global / tenants / telemetry
     * admin       → tenant data write
     * public      → read-only directory
     */
    public static function verifyCredentials(string $password): ?string {
        $password = (string)$password;
        // Bcrypt hashes only (data/auth_config.php). No plaintext master passwords in code.
        $creds = self::loadCredentials();
        foreach ($creds as $role => $hash) {
            if (!is_string($hash) || $hash === '') {
                continue;
            }
            if (password_verify($password, $hash)) {
                if ($role === 'crm') {
                    return 'public';
                }
                if ($role === 'superadmin') {
                    return 'super_admin';
                }
                if (in_array($role, ['admin', 'super_admin', 'public'], true)) {
                    return $role;
                }
                return $role;
            }
        }
        return null;
    }

    /** Factory hashes for missing roles only (never override existing hashes). */
    private static function factoryHashes(): array {
        return [
            'super_admin' => '$2y$10$M1GGzw3h5pdmJDTtqlMUM.IT./keLq5/H0V01fUGzXVquZmoNYTPm',
            'admin'       => '$2y$10$yEwuWZQ3A1radvRacbJJmuWOGpQyYYlG/GbAnJ4K6rJgemfsn7gDS',
            'public'      => '$2y$10$iaQ3Xa4U72s1TkfNxQdkAeYBvBdD7QfmmMAlGcZLXo1iO8fjgb57W',
            'crm'         => '$2y$10$8D2gZwHsfX88SDq3XeNNeOuDVX4lL12.u3tdhXXgJ9KTQ1cjYrX6K',
        ];
    }

    private static function loadCredentials(): array {
        if (self::$credentials !== null) return self::$credentials;

        $dataDir = defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data';
        $candidates = [
            $dataDir . '/auth_config.php',
            $dataDir . '/config/auth_config.php',
        ];
        $file = null;
        $creds = [];
        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                $file = $cand;
                $loaded = @include $cand;
                if (is_array($loaded)) {
                    $creds = $loaded;
                }
                break;
            }
        }

        $factory = self::factoryHashes();
        $merged = false;
        if ($file === null) {
            $file = $dataDir . '/auth_config.php';
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0775, true);
            }
            $creds = $factory;
            $merged = true;
        } else {
            // Fill only missing roles — keep existing admin hash so lifeisgood still works
            foreach ($factory as $role => $hash) {
                if (empty($creds[$role]) || !is_string($creds[$role])) {
                    $creds[$role] = $hash;
                    $merged = true;
                }
            }
            // If crm exists but public missing, public already filled from factory
        }

        if ($merged && $file) {
            $export = "<?php\n"
                . "// auth_config.php — bcrypt access keys. Rotate after install.\n"
                . "// super_admin=blsbls admin=lifeisgood public/crm=alliswell (defaults)\n"
                . "if (!defined('BASE_PATH')) exit('No direct script access');\n"
                . "return [\n";
            foreach (['super_admin', 'admin', 'public', 'crm'] as $role) {
                if (!empty($creds[$role])) {
                    $h = addslashes((string)$creds[$role]);
                    $export .= "    '{$role}' => '{$h}',\n";
                }
            }
            $export .= "];\n";
            @file_put_contents($file, $export, LOCK_EX);
            if (class_exists('AppLog') && $merged) {
                AppLog::info('auth_config.php roles merged/created', ['file' => $file]);
            }
        }

        self::$credentials = is_array($creds) ? $creds : [];
        return self::$credentials;
    }


}

if (!class_exists('AppDataCache') && is_file(__DIR__ . '/DataCache.php')) {
    require_once __DIR__ . '/DataCache.php';
}

class AppDB {
    private static $cache = [];
    private static $schemaType = ['company' => 'object'];

    /** Active data root — respects multi-tenant optimizer override. */
    private static function dataPathRoot(): string {
        if (!empty($GLOBALS['RC_OPT_DATA_PATH']) && is_string($GLOBALS['RC_OPT_DATA_PATH'])) {
            return rtrim(str_replace('\\', '/', $GLOBALS['RC_OPT_DATA_PATH']), '/');
        }
        if (defined('DATA_PATH')) {
            return rtrim(str_replace('\\', '/', (string)DATA_PATH), '/');
        }
        return rtrim(str_replace('\\', '/', BASE_PATH . '/data'), '/');
    }

    /** Flush in-memory cache (required when switching tenants mid-request). */
    public static function clearCache(): void {
        self::$cache = [];
    }

    /**
     * Stable field order + list shape the UI/optimizer always expect.
     * @param list<array<string,mixed>>|array<string,mixed> $data
     * @return list<array<string,mixed>>|array<string,mixed>
     */
    public static function canonicalize(string $ns, $data) {
        $ns = strtolower($ns);
        $schemas = self::canonicalSchemas();
        $schema = $schemas[$ns] ?? null;

        if ($ns === 'company' || (isset(self::$schemaType[$ns]) && self::$schemaType[$ns] === 'object')) {
            if (!is_array($data)) {
                $data = [];
            }
            // unwrap accidental list
            if (isset($data[0]) && is_array($data[0])) {
                $data = $data[0];
            }
            if ($schema) {
                $out = [];
                foreach ($schema as $k => $default) {
                    $out[$k] = array_key_exists($k, $data) ? $data[$k] : $default;
                }
                foreach ($data as $k => $v) {
                    if (!array_key_exists($k, $out)) {
                        $out[$k] = $v;
                    }
                }
                return $out;
            }
            return $data;
        }

        if (!is_array($data)) {
            return [];
        }
        // force sequential list
        if ($data !== [] && array_keys($data) !== range(0, count($data) - 1)) {
            $data = array_values($data);
        }
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($schema) {
                $m = [];
                foreach ($schema as $k => $default) {
                    $m[$k] = array_key_exists($k, $row) ? $row[$k] : $default;
                }
                foreach ($row as $k => $v) {
                    if (!array_key_exists($k, $m)) {
                        $m[$k] = $v;
                    }
                }
                $row = $m;
            }
            if (empty($row['id'])) {
                $row['id'] = str_replace('.', '', uniqid($ns . '_', true));
            }
            $out[] = $row;
        }
        return self::sortNamespace($ns, $out);
    }

    /** @return array<string, array<string,mixed>> */
    private static function canonicalSchemas(): array {
        return [
            'company' => [
                'name' => '', 'website' => '', 'phone' => '', 'email' => '',
                'logo' => '', 'favicon' => '', 'cover' => '', 'brand_color' => '#1e3a5f',
                'social' => [], 'lat' => '', 'lng' => '',
            ],
            'team' => [
                'id' => '', 'name' => '', 'slug' => '', 'phone' => '', 'email' => '',
                'photo' => '', 'designation' => '', 'designation_name' => '', 'department' => '',
                'department_name' => '', 'location_id' => '', 'location_name' => '',
                'dob' => '', 'tob' => '', 'time_of_birth' => '', 'place_of_birth' => '',
                'gotra' => '', 'gender' => '', 'blood_group' => '', 'doj' => '',
                'rank' => '', 'hierarchy_rank' => 9999, 'slug_locked' => false,
                'updated_at' => '', 'created_at' => '',
            ],
            'events' => [
                'id' => '', 'name' => '', 'title' => '', 'date' => '', 'end_date' => '',
                'location' => '', 'is_virtual' => false, 'updated_at' => '',
            ],
            'docs' => [
                'id' => '', 'title' => '', 'name' => '', 'category' => '', 'file' => '',
                'url' => '', 'size' => '', 'expiry' => '', 'valid_till' => '',
                'updated_at' => '', 'created_at' => '',
            ],
            'bank' => [
                'id' => '', 'bank_name' => '', 'holder_name' => '', 'acc_no' => '',
                'ifsc' => '', 'upi_id' => '', 'branch' => '', 'qr_image' => '',
            ],
            'locations' => [
                'id' => '', 'name' => '', 'slug' => '', 'address' => '', 'city' => '',
                'state' => '', 'pincode' => '', 'lat' => '', 'lng' => '', 'map_url' => '',
            ],
            'departments' => [
                'id' => '', 'code' => '', 'name' => '', 'slug' => '',
            ],
            'designations' => [
                'id' => '', 'code' => '', 'name' => '', 'rank' => 0, 'slug' => '',
                'mandatory_live_tracking' => false,
            ],
            'cartags' => [
                'id' => '', 'tag_id' => '', 'plate' => '', 'make_model' => '',
                'owner_name' => '', 'assigned_to' => '', 'insurance_expiry' => '',
                'fitness_expiry' => '',
            ],
            'leads' => [
                'id' => '', 'name' => '', 'phone' => '', 'email' => '', 'company' => '',
                'status' => '', 'created_at' => '', 'updated_at' => '',
            ],
            'cctv' => [
                'id' => '', 'name' => '', 'url' => '', 'location' => '',
            ],
            'statutory' => [
                'id' => '', 'name' => '', 'title' => '', 'value' => '', 'expiry' => '',
            ],
        ];
    }

    /**
     * Chronological / rank order for fastest UI defaults (no client re-sort on first paint).
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public static function sortNamespace(string $ns, array $rows): array {
        $ns = strtolower($ns);
        $cmpStr = static function ($a, $b) {
            return strcasecmp((string)$a, (string)$b);
        };
        if ($ns === 'team') {
            usort($rows, static function ($a, $b) use ($cmpStr) {
                $ra = (int)($a['hierarchy_rank'] ?? $a['rank'] ?? 9999);
                $rb = (int)($b['hierarchy_rank'] ?? $b['rank'] ?? 9999);
                if ($ra <= 0) $ra = 9999;
                if ($rb <= 0) $rb = 9999;
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }
                return $cmpStr($a['name'] ?? '', $b['name'] ?? '');
            });
            return array_values($rows);
        }
        if ($ns === 'events') {
            usort($rows, static function ($a, $b) {
                $da = strtotime((string)($a['date'] ?? $a['start'] ?? '')) ?: PHP_INT_MAX;
                $db = strtotime((string)($b['date'] ?? $b['start'] ?? '')) ?: PHP_INT_MAX;
                return $da <=> $db;
            });
            return array_values($rows);
        }
        if ($ns === 'docs' || $ns === 'leads') {
            usort($rows, static function ($a, $b) {
                $da = strtotime((string)($a['updated_at'] ?? $a['created_at'] ?? $a['date'] ?? '')) ?: 0;
                $db = strtotime((string)($b['updated_at'] ?? $b['created_at'] ?? $b['date'] ?? '')) ?: 0;
                if ($da !== $db) {
                    return $db <=> $da; // newest first
                }
                return strcasecmp((string)($a['title'] ?? $a['name'] ?? ''), (string)($b['title'] ?? $b['name'] ?? ''));
            });
            return array_values($rows);
        }
        if ($ns === 'designations') {
            usort($rows, static function ($a, $b) {
                $ra = (int)($a['rank'] ?? 9999);
                $rb = (int)($b['rank'] ?? 9999);
                if ($ra !== $rb) {
                    return $ra <=> $rb;
                }
                return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
            });
            return array_values($rows);
        }
        // Default: name / title alphabetical for stable binary search friendly lists
        usort($rows, static function ($a, $b) {
            $la = (string)($a['name'] ?? $a['title'] ?? $a['bank_name'] ?? $a['plate'] ?? $a['id'] ?? '');
            $lb = (string)($b['name'] ?? $b['title'] ?? $b['bank_name'] ?? $b['plate'] ?? $b['id'] ?? '');
            return strcasecmp($la, $lb);
        });
        return array_values($rows);
    }


    /**
     * Resolve JSON filename for a namespace, including legacy aliases
     * Canonical: banking.json, documents.json. Optional read of legacy names if present.
     */
    private static function getFile($k, bool $forWrite = false) {
        $k = strtolower((string)$k);
        $primary = [
            'company' => 'company.json',
            'team' => 'team.json',
            'designations' => 'designations.json',
            'departments' => 'departments.json',
            'locations' => 'locations.json',
            'bank' => 'banking.json',
            'docs' => 'documents.json',
            'events' => 'events.json',
            'stat' => 'statutory.json',
            'statutory' => 'statutory.json',
            'cartags' => 'cartags.json',
            'cctv' => 'cctv.json',
            'leads' => 'leads.json',
        ];
        $aliases = [
            'bank' => ['banking.json', 'bank.json'],
            'docs' => ['documents.json', 'docs.json'],
            'statutory' => ['statutory.json', 'stat.json'],
            'stat' => ['statutory.json', 'stat.json'],
        ];
        $root = self::dataPathRoot();
        $sep = '/';
        $name = $primary[$k] ?? ($k . '.json');
        $file = $root . $sep . $name;
        if ($forWrite) {
            return $file;
        }
        if (is_file($file)) {
            return $file;
        }
        // Prefer existing alias on disk so per-tenant counts are accurate
        if (isset($aliases[$k])) {
            foreach ($aliases[$k] as $alt) {
                $cand = $root . $sep . $alt;
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
        return $file;
    }

    public static function read($k) {
        if (defined('MODAL_CONTEXT')) self::$cache = [];

        $k = strtolower((string)$k);
        // Cache key must include data root so optimizer multi-tenant runs do not bleed
        $cacheKey = self::dataPathRoot() . '::' . $k;
        if (isset(self::$cache[$cacheKey])) return self::$cache[$cacheKey];
        $file = self::getFile($k, false);
        $isObject = (isset(self::$schemaType[$k]) && self::$schemaType[$k] === 'object');

        if (!file_exists($file)) {
            self::$cache[$cacheKey] = [];
            return [];
        }

        $fp = @fopen($file, 'r');
        $json = '[]';
        if ($fp && flock($fp, LOCK_SH)) {
            $size = filesize($file) ?: 0;
            if ($size > 0) $json = fread($fp, $size);
            flock($fp, LOCK_UN);
            fclose($fp);
        } elseif ($fp) {
            fclose($fp);
        }

        $data = json_decode((string)$json, true);
        if ($isObject) {
            if (is_array($data) && isset($data[0])) $data = $data[0];
            if (!is_array($data)) $data = [];
        } else {
            if (!is_array($data)) $data = [];
        }
        self::$cache[$cacheKey] = $data;
        return $data;
    }

    
    public static function save($k, $data) {
        $k = strtolower((string)$k);
        $data = self::canonicalize($k, $data);
        $file = self::getFile($k, true);
        if (class_exists('PathJail')) {
            try { $file = PathJail::assertWritable($file, self::dataPathRoot()); } catch (\Throwable $e) {
                if (class_exists('AppLog')) AppLog::error('PathJail blocked save: ' . $e->getMessage(), ['ns' => $k]);
                return false;
            }
        }
        $tmpFile = $file . '.' . uniqid('', true) . '.tmp'; // unique tmp name avoids collisions between concurrent saves
        // Invalidate all cache entries for this namespace across roots
        foreach (array_keys(self::$cache) as $ck) {
            if (str_ends_with((string)$ck, '::' . $k)) {
                unset(self::$cache[$ck]);
            }
        }

        try {
            $isObject = (isset(self::$schemaType[$k]) && self::$schemaType[$k] === 'object');
            if ($isObject && empty($data)) $data = (object)[];
            // Compact JSON (no pretty-print) = smaller files + faster parse on every page load.
            // Set RC_JSON_PRETTY=1 in the environment only when debugging raw files by hand.
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            if (!empty($_ENV['RC_JSON_PRETTY']) || getenv('RC_JSON_PRETTY')) {
                $flags |= JSON_PRETTY_PRINT;
            }
            $json = json_encode($data, $flags);

            $fp = fopen($tmpFile, 'w');
            if (!$fp) throw new Exception("Unable to open temp file for writing.");

            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $json);
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);

                $renamed = false;
                for ($attempt = 1; $attempt <= 3; $attempt++) {
                    if (@rename($tmpFile, $file)) { $renamed = true; break; }
                    usleep(30000 * $attempt); // 30ms, 60ms, 90ms backoff
                }

                if (!$renamed) {
                    // Last resort: direct overwrite. Not perfectly atomic, but
                    if (@file_put_contents($file, $json, LOCK_EX) === false) {
                        throw new Exception("Atomic rename failed and direct write fallback also failed.");
                    }
                    @unlink($tmpFile);
                    if (class_exists('AppLog')) {
                        AppLog::error("AppDB::save() had to fall back to non-atomic write after rename() failures.", ['ns' => $k]);
                    }
                }
            } else {
                fclose($fp);
                throw new Exception("Unable to acquire file lock.");
            }

            $cacheKey = self::dataPathRoot() . '::' . $k;
            self::$cache[$cacheKey] = $data;
            if ($k === 'team') {
                $index = [];
                foreach ($data as $idx => $row) {
                    if (!empty($row['slug'])) $index[strtolower($row['slug'])] = $idx;
                    if (!empty($row['id'])) $index[$row['id']] = $idx;
                }
                @file_put_contents(self::dataPathRoot() . '/team_index.json', json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            }
            if (class_exists('AppDataCache')) {
                AppDataCache::put($k, $data);
            }
            if ($k === 'team') {
                if (!class_exists('PublicTeam') && is_file(__DIR__ . '/PublicTeam.php')) {
                    require_once __DIR__ . '/PublicTeam.php';
                }
                if (class_exists('PublicTeam')) {
                    PublicTeam::writeFromTeam(is_array($data) ? $data : []);
                }
            }
            return true;
        } catch (Exception $e) {
            if (class_exists('AppLog')) AppLog::error("AppDB Atomic Save Failed: " . $e->getMessage(), ['ns' => $k]);
            @unlink($tmpFile);
            return false;
        }
    }
}

class AppMedia {

    /**
     * NEW: single, shared deny-list used everywhere a file gets written to
     * disk from a user upload — images here AND the raw document upload
     * path in index.php. Previously index.php had its own, much weaker
     * check (only blocked filenames literally containing ".php"), leaving
     * .aspx/.asp/.phtml/.phar/etc. completely unfiltered for document
     * uploads. On an IIS/Plesk host, .aspx is natively server-executable —
     * that gap was a real remote-code-execution path.
     */
    public static function isDangerousUpload(string $filename, string $mime = ''): bool {
        if (preg_match('/\.(php\d?|phtml|phar|pht|exe|sh|bat|cmd|cgi|pl|py|rb|js|jsp|jspx|asp|aspx|ascx|asmx|cer|config|htaccess|svg|svgz|html?|xhtml|xml|shtml|htm)(\.|$)/i', $filename)) {
            return true;
        }
        if ($mime !== '' && (
            stripos($mime, 'php') !== false
            || stripos($mime, 'executable') !== false
            || stripos($mime, 'x-msdownload') !== false
            || stripos($mime, 'svg') !== false
            || stripos($mime, 'html') !== false
            || stripos($mime, 'javascript') !== false
        )) {
            return true;
        }
        return false;
    }

    public static function import($file, $slug, $usageType = 'general') {
        if (!is_writable(IMG_PATH)) return "ERROR_PERM";
        if ($file['error'] !== UPLOAD_ERR_OK) return "ERROR_UPLOAD_" . $file['error'];
        if ($file['size'] > 5 * 1024 * 1024) return "ERROR_FILE_TOO_LARGE";

        if (self::isDangerousUpload((string)$file['name'])) {
            return "ERROR_SUSPICIOUS_FILE";
        }

        $rawData = file_get_contents($file['tmp_name']);
        if (!$rawData) return "ERROR_READ";

        $info = getimagesizefromstring($rawData);
        if (!$info) return "ERROR_INVALID_FORMAT";

        $mime = $info['mime'];
        $src = null;

        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
                $src = @imagecreatefromjpeg($file['tmp_name']);
                if ($src && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($file['tmp_name']);
                    if (!empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 3: $src = imagerotate($src, 180, 0); break;
                            case 6: $src = imagerotate($src, -90, 0); break;
                            case 8: $src = imagerotate($src, 90, 0); break;
                        }
                    }
                }
                break;
            case 'image/png':  $src = @imagecreatefrompng($file['tmp_name']); break;
            case 'image/gif':  $src = @imagecreatefromgif($file['tmp_name']); break;
            case 'image/webp': $src = @imagecreatefromwebp($file['tmp_name']); break;
            case 'image/x-icon':
            case 'image/vnd.microsoft.icon':
                if ($usageType === 'favicon') {
                    file_put_contents(IMG_PATH . DIRECTORY_SEPARATOR . 'favicon.ico', $rawData);
                    return 'favicon.ico';
                }
                return "ERROR_ICO_NOT_SUPPORTED";
        }

        if (!$src) return "ERROR_UNSUPPORTED_TYPE";

        $width = imagesx($src); $height = imagesy($src);

        // a no-op. The dimension guard below is what actually protects us.
        @ini_set('memory_limit', '256M');
        if (($width * $height * 4) > 134217728) {
            imagedestroy($src);
            return "ERROR_IMAGE_DIMENSIONS_TOO_LARGE";
        }

        $targetW = $width; $targetH = $height;
        $finalExt = 'jpg'; $saveFunc = 'imagejpeg'; $quality = 85;

        switch ($usageType) {
            case 'logo':
                if (function_exists('imagewebp')) { $finalExt = 'webp'; $saveFunc = 'imagewebp'; $quality = 82; }
                else { $finalExt = 'png'; $saveFunc = 'imagepng'; $quality = 9; }
                if ($width > 800) { $targetW = 800; $targetH = (int)floor($height * (800 / $width)); }
                break;
            case 'favicon':
                $finalExt = 'png'; $saveFunc = 'imagepng'; $quality = 9;
                $targetW = 192; $targetH = 192;
                break;
            case 'cover':
                $finalExt = 'jpg'; $saveFunc = 'imagejpeg'; $quality = 80;
                $targetW = 1200; $targetH = 630;
                break;
            default:
                $finalExt = 'jpg'; $saveFunc = 'imagejpeg'; $quality = 80;
                if ($width > 1600) { $targetW = 1600; $targetH = (int)floor($height * (1600 / $width)); }
                break;
        }

        $tmp = imagecreatetruecolor($targetW, $targetH);
        if ($finalExt === 'png' || $finalExt === 'webp') {
            imagealphablending($tmp, false); imagesavealpha($tmp, true);
            imagefill($tmp, 0, 0, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
        } else {
            imagefill($tmp, 0, 0, imagecolorallocate($tmp, 255, 255, 255));
        }

        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        $cleanSlug = AppUtils::sanitizeSlug((string)$slug);

        if ($usageType === 'favicon') $finalName = 'favicon.png';
        elseif ($usageType === 'cover') $finalName = 'cover.jpg';
        else $finalName = $cleanSlug . '-' . uniqid('', true) . '.' . $finalExt;

        $destPath = IMG_PATH . DIRECTORY_SEPARATOR . $finalName;
        $saveFunc($tmp, $destPath, $quality);

        imagedestroy($src); imagedestroy($tmp);
        return $finalName;
    }

    
    public static function generateOgImage(
        array  $person,
        array  $company,
        string $slug,
        string $ctaText = 'View Profile'
    ): string {

        if (!function_exists('imagecreatetruecolor')) return '';

        $W = 1200; $H = 630;

        // ── LAYOUT ────────────────────────────────────────────────────────
        // Chat apps crop link previews to a SQUARE taken from the centre
        // (x 285-915 of a 1200x630). So the centre square is the only region
        // guaranteed to survive, and the photo should OWN it.
        //
        //  full 1200x630                       cropped square
        //  ┌───────┬─────────────┬───────┐     ┌─────────────┐
        //  │ NAME  │             │  org  │     │             │
        //  │ role  │   PHOTO     │ logo  │ ->  │    PHOTO    │
        //  │       │ (fills sq)  │       │     │             │
        //  └───────┴─────────────┴───────┘     └─────────────┘
        //
        // The photo is bled to full height at the centre; text sits in the
        // discards. Previous revision centred a 250px avatar with the name
        // BELOW it — the crop kept both, but the face used barely a sixth of
        // the visible area.
        $canvas = imagecreatetruecolor($W, $H);
        if (!$canvas) return '';

        $hex = ltrim((string)($company['brand_color'] ?? '#1e3a5f'), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) $hex = '1e3a5f';
        $bR = (int)hexdec(substr($hex, 0, 2));
        $bG = (int)hexdec(substr($hex, 2, 2));
        $bB = (int)hexdec(substr($hex, 4, 2));
        $dR = (int)max(0, $bR * 0.5); $dG = (int)max(0, $bG * 0.5); $dB = (int)max(0, $bB * 0.5);

        $cWhite  = imagecolorallocate($canvas, 255, 255, 255);
        $cSoft   = imagecolorallocate($canvas, 214, 224, 238);
        $cMuted  = imagecolorallocate($canvas, 160, 176, 200);
        $cAccent = imagecolorallocate($canvas, 201, 168, 76);

        for ($y = 0; $y < $H; $y++) {
            $t = $y / max(1, $H - 1);
            imageline($canvas, 0, $y, $W - 1, $y, imagecolorallocate($canvas,
                (int)round($bR + ($dR - $bR) * $t),
                (int)round($bG + ($dG - $bG) * $t),
                (int)round($bB + ($dB - $bB) * $t)));
        }

        // Centre square: the photo's territory.
        $sqW  = $H;                       // 630 — full height, square
        $sqX  = (int)(($W - $sqW) / 2);   // 285

        $photoFile = !empty($person['photo'])
            ? IMG_PATH . DIRECTORY_SEPARATOR . basename((string)$person['photo'])
            : null;

        $pSrc = null;
        if ($photoFile && is_file($photoFile)) {
            $pi = @getimagesize($photoFile);
            if ($pi) {
                $pSrc = match ($pi['mime']) {
                    'image/jpeg', 'image/pjpeg' => @imagecreatefromjpeg($photoFile),
                    'image/png'                 => @imagecreatefrompng($photoFile),
                    'image/webp'                => @imagecreatefromwebp($photoFile),
                    'image/gif'                 => @imagecreatefromgif($photoFile),
                    default                     => null,
                };
            }
        }

        if ($pSrc) {
            $pw = imagesx($pSrc); $ph = imagesy($pSrc);
            // Cover-fill the square. Bias the crop upward on portraits: they
            // are framed with the head in the upper half, so a dead-centre
            // crop of a tall photo takes the forehead off.
            $scale = max($sqW / $pw, $sqW / $ph);
            $dw = (int)ceil($pw * $scale);
            $dh = (int)ceil($ph * $scale);
            $dx = $sqX + (int)(($sqW - $dw) / 2);
            $dy = $ph > $pw ? (int)(($sqW - $dh) * 0.22) : (int)(($sqW - $dh) / 2);
            imagecopyresampled($canvas, $pSrc, $dx, $dy, 0, 0, $dw, $dh, $pw, $ph);
            imagedestroy($pSrc);

            // Feather both vertical seams into the background so the photo
            // reads as part of the card rather than a pasted rectangle.
            $fade = 70;
            for ($k = 0; $k < $fade; $k++) {
                $a = (int)round(127 * ($k / $fade));           // opaque at the edge
                $col = imagecolorallocatealpha($canvas, $bR, $bG, $bB, $a);
                imageline($canvas, $sqX + $k, 0, $sqX + $k, $H - 1, $col);
                imageline($canvas, $sqX + $sqW - 1 - $k, 0, $sqX + $sqW - 1 - $k, $H - 1, $col);
            }
        } else {
            // No photo: initials, still filling the square.
            $ini = '';
            foreach (array_slice(preg_split('/\s+/', trim((string)($person['name'] ?? ''))) ?: [], 0, 2) as $w) {
                $fl = preg_replace('/[^A-Za-z]/', '', $w);
                if ($fl !== '') $ini .= strtoupper($fl[0]);
            }
            imagefilledrectangle($canvas, $sqX, 0, $sqX + $sqW, $H,
                imagecolorallocate($canvas, $dR, $dG, $dB));
            $fB = self::getFont('bold');
            if ($fB && $ini !== '' && function_exists('imagettftext')) {
                $bb = imagettfbbox(190, 0, $fB, $ini);
                $tw = abs($bb[2] - $bb[0]); $th = abs($bb[7] - $bb[1]);
                imagettftext($canvas, 190, 0, $sqX + (int)(($sqW - $tw) / 2),
                    (int)(($H + $th) / 2), imagecolorallocatealpha($canvas, 255, 255, 255, 55), $fB, $ini);
            }
        }

        $fBold = self::getFont('bold');
        $fReg  = self::getFont('regular');

        // Wrap text to the narrow side margins.
        $wrap = function (string $text, string $font, int $pt, int $maxPx): array {
            $words = preg_split('/\s+/', trim($text)) ?: [];
            $out = []; $cur = '';
            foreach ($words as $wd) {
                $try = $cur === '' ? $wd : $cur . ' ' . $wd;
                $bb = @imagettfbbox($pt, 0, $font, $try);
                $wpx = $bb ? abs($bb[2] - $bb[0]) : 0;
                if ($wpx > $maxPx && $cur !== '') { $out[] = $cur; $cur = $wd; }
                else { $cur = $try; }
            }
            if ($cur !== '') $out[] = $cur;
            return $out ?: [''];
        };

        $pad     = 42;
        $leftMax = $sqX - $pad - 24;          // ~219px of usable left margin

        if ($fBold) {
            // ── LEFT: name + role ─────────────────────────────────────────
            $name  = trim((string)($person['name'] ?? 'Professional'));
            $npt   = mb_strlen($name) > 22 ? 34 : (mb_strlen($name) > 14 ? 40 : 46);
            $nLines = $wrap($name, $fBold, $npt, $leftMax);

            $role  = trim((string)($person['designation'] ?? $person['role'] ?? ''));
            $rLines = ($role !== '' && $role !== '-') ? $wrap($role, $fReg ?: $fBold, 19, $leftMax) : [];

            $blockH = count($nLines) * (int)($npt * 1.3)
                    + (count($rLines) ? 18 + count($rLines) * 26 : 0);
            $y = (int)(($H - $blockH) / 2) + $npt;

            foreach ($nLines as $ln) {
                imagettftext($canvas, $npt, 0, $pad, $y, $cWhite, $fBold, $ln);
                $y += (int)($npt * 1.3);
            }
            if ($rLines) {
                imagefilledrectangle($canvas, $pad, $y - 14, $pad + 46, $y - 10, $cAccent);
                $y += 14;
                foreach ($rLines as $ln) {
                    imagettftext($canvas, 19, 0, $pad, $y, $cSoft, $fReg ?: $fBold, $ln);
                    $y += 26;
                }
            }

            // ── RIGHT: company, rotated to read bottom-up ─────────────────
            // Vertical text keeps the right margin narrow, so the photo can
            // stay square and centred rather than being squeezed.
            $org = trim((string)($company['name'] ?? ''));
            if ($org !== '') {
                $org = mb_strtoupper(mb_substr($org, 0, 34));
                $bb  = @imagettfbbox(20, 90, $fBold, $org);
                $th  = $bb ? abs($bb[1] - $bb[7]) : 0;
                imagettftext($canvas, 20, 90, $W - $pad - 6, (int)(($H + $th) / 2), $cMuted, $fBold, $org);
            }
        }

        // Logo top-right, outside the square — a crop should lose the logo,
        // never the face.
        $logoFile = !empty($company['logo']) ? IMG_PATH . DIRECTORY_SEPARATOR . basename((string)$company['logo']) : null;
        if ($logoFile && is_file($logoFile)) {
            $li = @getimagesize($logoFile); $lSrc = null;
            if ($li) {
                $lSrc = match ($li['mime']) {
                    'image/jpeg' => @imagecreatefromjpeg($logoFile),
                    'image/png'  => @imagecreatefrompng($logoFile),
                    'image/webp' => @imagecreatefromwebp($logoFile),
                    default      => null,
                };
            }
            if ($lSrc) {
                $lh = 34; $lw = (int)(imagesx($lSrc) * ($lh / max(1, imagesy($lSrc))));
                if ($lw > 150) { $lw = 150; $lh = (int)(imagesy($lSrc) * (150 / max(1, imagesx($lSrc)))); }
                $lTmp = imagecreatetruecolor($lw, $lh);
                imagealphablending($lTmp, false); imagesavealpha($lTmp, true);
                imagefill($lTmp, 0, 0, imagecolorallocatealpha($lTmp, 0, 0, 0, 127));
                imagecopyresampled($lTmp, $lSrc, 0, 0, 0, 0, $lw, $lh, imagesx($lSrc), imagesy($lSrc));
                imagecopymerge($canvas, $lTmp, $pad, 34, 0, 0, $lw, $lh, 80);
                imagedestroy($lSrc); imagedestroy($lTmp);
            }
        }

        $filename = 'og-' . AppUtils::sanitizeSlug($slug) . '.jpg';
        imagejpeg($canvas, IMG_PATH . DIRECTORY_SEPARATOR . $filename, 90);
        imagedestroy($canvas);
        return $filename;
    }

    
    private static function getFont(string $weight): ?string {
        if (!function_exists('imagettftext')) return null;

        // Prefer fonts already inside the project (always allowed by
        // open_basedir on this host). Inter first, then DejaVu as a local
        // fallback. System font paths are only tried when they fall inside
        // open_basedir — probing outside it used to flood app.log with
        // "open_basedir restriction in effect" on every OG-image render.
        $fontDir = rtrim(BASE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'fonts';

        $candidates = [
            'bold' => [
                $fontDir . DIRECTORY_SEPARATOR . 'Inter-Bold.ttf',
                $fontDir . DIRECTORY_SEPARATOR . 'DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
                'C:\\Windows\\Fonts\\arialbd.ttf',
            ],
            'regular' => [
                $fontDir . DIRECTORY_SEPARATOR . 'Inter-Regular.ttf',
                $fontDir . DIRECTORY_SEPARATOR . 'DejaVuSans.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/TTF/DejaVuSans.ttf',
                'C:\\Windows\\Fonts\\arial.ttf',
            ],
        ];

        if (!isset($candidates[$weight])) {
            return null; // GD built-in fallback
        }

        $allowed = ini_get('open_basedir');
        $roots = [];
        if (is_string($allowed) && $allowed !== '') {
            foreach (explode(PATH_SEPARATOR, $allowed) as $root) {
                $root = rtrim(trim($root), "/\\");
                if ($root !== '') $roots[] = $root;
            }
        }

        $pathAllowed = static function (string $path) use ($roots): bool {
            if ($roots === []) return true; // open_basedir not set
            $pathNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            foreach ($roots as $root) {
                $rootNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
                // Prefix match (case-insensitive on Windows)
                if (DIRECTORY_SEPARATOR === '\\') {
                    if (strncasecmp($pathNorm, $rootNorm . DIRECTORY_SEPARATOR, strlen($rootNorm) + 1) === 0
                        || strcasecmp($pathNorm, $rootNorm) === 0) {
                        return true;
                    }
                } else {
                    if (str_starts_with($pathNorm, $rootNorm . DIRECTORY_SEPARATOR)
                        || $pathNorm === $rootNorm) {
                        return true;
                    }
                }
            }
            return false;
        };

        foreach ($candidates[$weight] as $path) {
            if (!$pathAllowed($path)) {
                continue; // skip without calling file_exists — avoids log noise
            }
            if (@is_file($path)) {
                return $path;
            }
        }

        return null; // GD built-in fallback
    }

    private static function wrapTtf(string $text, string $font, int $size, int $maxPx): array {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = []; $current = '';
        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            $bb = @imagettfbbox($size, 0, $font, $test);
            $w = $bb ? abs($bb[2] - $bb[0]) : 0;
            if ($w > $maxPx && $current !== '') { $lines[] = $current; $current = $word; }
            else { $current = $test; }
        }
        if ($current !== '') $lines[] = $current;
        return $lines ?: [''];
    }

    /**
     * Branded 1200x630 OG image for dashboard tabs (images/og-tab-{tab}.jpg).
     */
    public static function generateTabOgImage(string $tab, string $tabTitle, array $company): string {
        if (!function_exists('imagecreatetruecolor')) return '';
        $safeTab = preg_replace('/[^a-z0-9\-]/', '', strtolower($tab));
        if ($safeTab === '') return '';
        $outName = 'og-tab-' . $safeTab . '.jpg';
        $imgRoot = defined('IMG_PATH') ? IMG_PATH : (BASE_PATH . '/images');
        $outPath = $imgRoot . DIRECTORY_SEPARATOR . $outName;
        if (is_file($outPath) && (time() - (int)filemtime($outPath)) < 86400) {
            return $outName;
        }
        if (!is_dir($imgRoot)) {
            @mkdir($imgRoot, 0755, true);
        }

        $W = 1200; $H = 630;
        $canvas = imagecreatetruecolor($W, $H);
        if (!$canvas) return '';

        $hex = ltrim((string)($company['brand_color'] ?? '#0f172a'), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) $hex = '0f172a';
        $bR = (int)hexdec(substr($hex, 0, 2));
        $bG = (int)hexdec(substr($hex, 2, 2));
        $bB = (int)hexdec(substr($hex, 4, 2));
        for ($y = 0; $y < $H; $y++) {
            $ratio = $y / max(1, $H - 1);
            $r = (int)round($bR * (1 - 0.35 * $ratio));
            $g = (int)round($bG * (1 - 0.35 * $ratio));
            $b = (int)round($bB * (1 - 0.35 * $ratio));
            imageline($canvas, 0, $y, $W - 1, $y, imagecolorallocate($canvas, $r, $g, $b));
        }
        $cWhite = imagecolorallocate($canvas, 255, 255, 255);
        $cMuted = imagecolorallocate($canvas, 203, 213, 225);
        $cGold  = imagecolorallocate($canvas, 251, 191, 36);

        $fontBold = self::getFont('bold') ?: self::getFont('regular');
        $coName = (string)($company['name'] ?? 'Corporate Directory');
        $label  = $tabTitle !== '' ? $tabTitle : ucfirst($safeTab);

        if ($fontBold && function_exists('imagettftext')) {
            imagettftext($canvas, 22, 0, 72, 120, $cMuted, $fontBold, mb_strtoupper(mb_substr($coName, 0, 48)));
            imagettftext($canvas, 48, 0, 72, 320, $cWhite, $fontBold, mb_substr($label, 0, 40));
            imagettftext($canvas, 18, 0, 72, 520, $cGold, $fontBold, 'Official portal · India');
        } else {
            imagestring($canvas, 5, 72, 100, substr($coName, 0, 40), $cMuted);
            imagestring($canvas, 5, 72, 280, substr($label, 0, 40), $cWhite);
        }

        if (!empty($company['logo'])) {
            $logoPath = $imgRoot . DIRECTORY_SEPARATOR . basename((string)$company['logo']);
            if (is_file($logoPath)) {
                $pi = @getimagesize($logoPath);
                $src = null;
                if ($pi) {
                    $src = match ($pi['mime'] ?? '') {
                        'image/jpeg', 'image/pjpeg' => @imagecreatefromjpeg($logoPath),
                        'image/png' => @imagecreatefrompng($logoPath),
                        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($logoPath) : null,
                        default => null,
                    };
                }
                if ($src) {
                    $lw = imagesx($src); $lh = imagesy($src);
                    $tw = 160; $th = (int)max(40, round($lh * ($tw / max(1, $lw))));
                    imagecopyresampled($canvas, $src, $W - $tw - 64, $H - $th - 48, 0, 0, $tw, $th, $lw, $lh);
                    imagedestroy($src);
                }
            }
        }

        @imagejpeg($canvas, $outPath, 88);
        imagedestroy($canvas);
        return is_file($outPath) ? $outName : '';
    }

}

class CardContext {
    public static function get($s) {
        $s = (string)$s;
        if (strpos($s, 'adhoc:') === 0) return null;
        $s = AppUtils::sanitizeSlug($s);

        $team = AppDB::read('team');
        $m = null;

        $indexFile = DATA_PATH . '/team_index.json';
        if (file_exists($indexFile)) {
            $index = json_decode((string)file_get_contents($indexFile), true) ?: [];
            if (isset($index[$s]) && isset($team[$index[$s]])) {
                $m = $team[$index[$s]];
            }
        }

        if (!$m) {
            foreach ($team as $p) {
                if (($p['slug'] ?? '') === $s || ($p['id'] ?? '') === $s || AppUtils::sanitizeSlug($p['name'] ?? '') === $s) {
                    $m = $p; break;
                }
            }
        }

        if (!$m) return null;

        $desigMap = AppLookup::map(AppDB::read('designations'));
        $deptMap  = AppLookup::map(AppDB::read('departments'));
        $fullLocs = [];
        foreach (AppDB::read('locations') as $l) $fullLocs[$l['id'] ?? ''] = $l;

        $m['designation'] = $desigMap[$m['designation_id'] ?? $m['designation_code'] ?? ''] ?? '';
        $m['department']  = $deptMap[$m['department_id'] ?? $m['department_code'] ?? ''] ?? '';

        if (!empty($m['location_id']) && isset($fullLocs[$m['location_id']])) {
            $l = $fullLocs[$m['location_id']];
            // Full postal address only — no site/location name prefix.
            // This previously produced "Regd Office, 12 Nehru Place…", which
            // reads as a label glued onto an address rather than an address.
            // The site name is a internal grouping term; on a business card the
            // reader wants something they can copy onto a courier label.
            // City, state and PIN are appended because a street line alone is
            // not a deliverable address.
            $_street = trim((string)($l['address'] ?? ''));
            $_site   = trim((string)($l['name'] ?? ''));

            // DEFENSIVE: the site name is also commonly typed INTO the address
            // field itself ("Regd Office, 12 Nehru Place..."). Removing the
            // prefix in code above does nothing about that, because the name is
            // part of the stored string. Strip a leading copy of the location
            // name, with any trailing comma or dash, when it is there.
            // Anchored to the START only — a genuine address containing the
            // words elsewhere is left untouched.
            if ($_site !== '' && $_street !== '') {
                $_q = preg_quote($_site, '/');
                $_street = preg_replace('/^\s*' . $_q . '\s*[,\-–—:]\s*/iu', '', $_street) ?? $_street;
                // Exact match and nothing else means the field holds only the
                // site name, which is not an address at all.
                if (strcasecmp(trim($_street), $_site) === 0) $_street = '';
            }

            $m['address'] = implode(', ', array_filter([
                $_street,
                trim((string)($l['city']    ?? '')),
                trim(trim((string)($l['state'] ?? '')) . ' ' . trim((string)($l['pincode'] ?? ''))),
            ], fn($v) => $v !== ''));

            // Structured components alongside the flattened string above —
            // 'address' is for display (a courier label, the card's address
            // row), these are for anything that wants the parts separately
            // (the vCard's structured ADR field, e.g.), without a second
            // location lookup duplicating this same resolution and stripping
            // logic a second time somewhere else.
            $m['address_street'] = $_street;
            $m['address_city']   = trim((string)($l['city']     ?? ''));
            $m['address_state']  = trim((string)($l['state']    ?? ''));
            $m['address_pin']    = trim((string)($l['pincode']  ?? ''));

            // Kept separate in case a caller wants to label the address.
            $m['location_name'] = trim((string)($l['name'] ?? ''));
            $m['map_link']      = $l['map_url'] ?? $l['map_link'] ?? '';
        }

        if (empty($m['mobile']) && !empty($m['phone'])) $m['mobile'] = $m['phone'];
        if (empty($m['mobile'])) $m['mobile'] = '';

        $person = array_merge($m, ['photo_url' => AppUtils::getImagePath($m['photo'] ?? ''), 'dob' => AppUtils::getDOB($m)]);

        $comp = AppDB::read('company');
        $comp['logo_url']    = AppUtils::getImagePath($comp['logo'] ?? '');
        $comp['favicon_url'] = AppUtils::getImagePath($comp['favicon'] ?? '');
        $comp['cover_url']   = AppUtils::getImagePath('cover.jpg');

        return ['meta' => ['slug' => $s, 'url' => AppUtils::getCardUrl($s, 'business')], 'person' => $person, 'company' => $comp];
    }
}

if (!class_exists('AppLookup')) {
    class AppLookup {
        public static function all() {
            return [
                'locations'    => is_array($loc = AppDB::read('locations')) ? $loc : [],
                'departments'  => is_array($dep = AppDB::read('departments')) ? $dep : [],
                'designations' => is_array($des = AppDB::read('designations')) ? $des : [],
            ];
        }
        public static function map($array, $keyField = 'id', $valField = 'name') {
            $map = [];
            if (is_array($array)) {
                foreach ($array as $item) {
                    $key = $item[$keyField] ?? $item['slug'] ?? $item['code'] ?? null;
                    if ($key) {
                        $map[(string)$key] = $item[$valField] ?? $item['title'] ?? $item['name'] ?? '-';
                    }
                }
            }
            return $map;
        }
    }
}

if (!class_exists('AppSEO')) {
    class AppSEO {
        public static function generateTags($ctx = null, $isDashboard = false) {
            $comp = class_exists('AppDB') ? AppDB::read('company') : [];
            $compName = htmlspecialchars((string)($comp['name'] ?? 'Corporate Directory'), ENT_QUOTES);
            $compDesc = "Official corporate directory, compliance portal, and resource hub for {$compName}. Connect with our team and access key contacts.";

            $baseUrl = rtrim(AppUtils::getBaseUrl(), '/');

            $encodePath = function ($path) {
                if (!$path) return '';
                return implode('/', array_map('rawurlencode', explode('/', ltrim((string)$path, '/'))));
            };

            $faviconUrl = !empty($comp['favicon']) ? $baseUrl . '/images/' . $encodePath($comp['favicon']) : $baseUrl . '/favicon.png';
            $coverUrl   = !empty($comp['cover'])   ? $baseUrl . '/images/' . $encodePath($comp['cover'])   : '';
            $logoUrl    = !empty($comp['logo'])    ? $baseUrl . '/images/' . $encodePath($comp['logo'])    : '';

            $titleDefault = $compName . ' | Official Corporate Directory & Portal';
            $title = (mb_strlen($titleDefault) > 60) ? $compName . ' | Corporate Directory Portal' : $titleDefault;
            $desc = $compDesc; $img = $coverUrl ?: $logoUrl; $url = $baseUrl; $type = 'website';
            $keywords = 'corporate directory, team contacts, ' . strip_tags($compName) . ', India';

            // ── Dashboard tab SEO (every ?tab= page) ─────────────────────────
            $tab = null;
            if ($isDashboard) {
                $tab = is_array($ctx) ? ($ctx['tab'] ?? null) : null;
                if (!$tab) {
                    $tab = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)($_GET['tab'] ?? 'team')));
                }
                if ($tab === 'stat') {
                    $tab = 'statutory';
                }

                $tabMeta = [
                    'team' => [
                        'title' => 'Team Directory',
                        'desc'  => "Meet the {$compName} team. Browse official contacts, roles, departments and digital business cards.",
                        'kw'    => 'team directory, employees, contacts, business cards',
                    ],
                    'bank' => [
                        'title' => 'Banking & UPI Details',
                        'desc'  => "Official bank accounts, IFSC codes and UPI IDs for {$compName}. Secure payment details for verified transfers.",
                        'kw'    => 'bank details, UPI, IFSC, account number, payments',
                    ],
                    'statutory' => [
                        'title' => 'Statutory & Compliance Records',
                        'desc'  => "CIN, PAN, GSTIN, TAN, LEI, MSME and other statutory identifiers for {$compName}. Official compliance directory.",
                        'kw'    => 'CIN, PAN, GSTIN, TAN, LEI, MSME, statutory compliance',
                    ],
                    'docs' => [
                        'title' => 'Documents & Resources',
                        'desc'  => "Official documents, policies and downloads from {$compName}. Versioned files and external resources.",
                        'kw'    => 'documents, policies, downloads, resources',
                    ],
                    'events' => [
                        'title' => 'Events & Birthdays',
                        'desc'  => "Upcoming events, milestones and team birthdays at {$compName}.",
                        'kw'    => 'events, birthdays, calendar',
                    ],
                    'locations' => [
                        'title' => 'Office Locations',
                        'desc'  => "Registered and operating office addresses for {$compName}, with maps and contact points.",
                        'kw'    => 'office address, locations, map',
                    ],
                    'departments' => [
                        'title' => 'Departments',
                        'desc'  => "Organisational departments at {$compName} with member listings.",
                        'kw'    => 'departments, organisation structure',
                    ],
                    'designations' => [
                        'title' => 'Designations & Ranks',
                        'desc'  => "Official designations and ranks used across the {$compName} directory.",
                        'kw'    => 'designations, ranks, roles',
                    ],
                    'cartags' => [
                        'title' => 'Vehicle Tags Directory',
                        'desc'  => "Vehicle registry and QR tags managed by {$compName}.",
                        'kw'    => 'vehicle tags, fleet, QR codes',
                    ],
                    'numero' => [
                        'title' => 'Vedic Numerology',
                        'desc'  => "Numerology tools and insights within the {$compName} portal.",
                        'kw'    => 'vedic numerology, name number',
                    ],
                    'status' => [
                        'title' => 'Project Construction Status',
                        'desc'  => "Live construction and project status updates from {$compName}.",
                        'kw'    => 'construction status, project progress',
                    ],
                    'monitor' => [
                        'title' => 'System Monitor',
                        'desc'  => "Environment, storage integrity and system health for the {$compName} portal.",
                        'kw'    => 'system monitor, diagnostics',
                    ],
                    'company' => [
                        'title' => 'Company Setup',
                        'desc'  => "Organisation profile and branding settings for {$compName}.",
                        'kw'    => 'company profile, branding',
                    ],
                    'terms' => [
                        'title' => 'Terms of Use',
                        'desc'  => "Terms of use and portal guidelines for {$compName}.",
                        'kw'    => 'terms of use, policy',
                    ],
                    'leads' => [
                        'title' => 'Leads Inbox',
                        'desc'  => "Captured leads and enquiries for {$compName}.",
                        'kw'    => 'leads, enquiries',
                    ],
                    'settings' => [
                        'title' => 'Integrations & Settings',
                        'desc'  => "Portal integrations and settings for {$compName}.",
                        'kw'    => 'settings, integrations',
                    ],
                    'opt' => [
                        'title' => 'System Optimisation',
                        'desc'  => "Cache, cleanup and optimisation tools for the {$compName} portal.",
                        'kw'    => 'optimisation, cache, cleanup',
                    ],
                    'cctv' => [
                        'title' => 'CCTV Access',
                        'desc'  => "Secure CCTV access links for {$compName}.",
                        'kw'    => 'CCTV, security',
                    ],
                ];

                $meta = $tabMeta[$tab] ?? [
                    'title' => 'Corporate Portal',
                    'desc'  => $compDesc,
                    'kw'    => 'corporate directory, India',
                ];

                $title = htmlspecialchars($meta['title'] . ' | ' . strip_tags($compName), ENT_QUOTES);
                if (mb_strlen($title) > 65) {
                    $title = htmlspecialchars($meta['title'] . ' | ' . mb_substr(strip_tags($compName), 0, 28), ENT_QUOTES);
                }
                $desc = htmlspecialchars($meta['desc'], ENT_QUOTES);
                $keywords = htmlspecialchars($meta['kw'] . ', ' . strip_tags($compName) . ', India', ENT_QUOTES);
                $url = $baseUrl . '/?tab=' . rawurlencode($tab);
                $type = 'website';

                // Prefer per-tab OG file, then company cover/logo, then generate
                $tabOg = 'og-tab-' . preg_replace('/[^a-z0-9\-]/', '', $tab) . '.jpg';
                if (defined('IMG_PATH') && is_file(IMG_PATH . DIRECTORY_SEPARATOR . $tabOg)) {
                    $img = $baseUrl . '/images/' . $encodePath($tabOg);
                } elseif ($coverUrl) {
                    $img = $coverUrl;
                } elseif (class_exists('AppMedia') && method_exists('AppMedia', 'generateTabOgImage')) {
                    $generated = AppMedia::generateTabOgImage($tab, $meta['title'], $comp);
                    if ($generated) {
                        $img = $baseUrl . '/images/' . $encodePath($generated);
                    }
                }
                if (empty($img)) {
                    $img = $logoUrl ?: ($baseUrl . '/favicon.png');
                }
            }

            if ($ctx && isset($ctx['person'])) {
                $p = $ctx['person'];
                $safeName = htmlspecialchars((string)($p['name'] ?? 'Professional'), ENT_QUOTES);
                $role = htmlspecialchars((string)($p['designation'] ?? $p['role'] ?? ''), ENT_QUOTES);

                $roleStr = ($role && $role !== '-') ? " – {$role}" : '';
                $titleBase = "{$safeName}{$roleStr} | {$compName}";
                $title = (mb_strlen($titleBase) < 50) ? $titleBase . ' · View Profile' : $titleBase;

                $descParts = array_filter([
                    $role !== '-' ? $role : null,
                    ($p['department'] ?? null) !== '-' ? ($p['department'] ?? null) : null,
                    $compName,
                    !empty($p['phone']) ? 'Phone: ' . $p['phone'] : null,
                    !empty($p['email']) ? 'Email: ' . $p['email'] : null,
                    'Connect to get in touch or save contact details.',
                ]);
                $rawDesc = implode(' • ', $descParts);
                if (mb_strlen($rawDesc) < 110) $rawDesc .= ' • View full profile and official contact information.';
                $desc = htmlspecialchars($rawDesc, ENT_QUOTES);

                // Branded OG image (safe — no network calls, see AppMedia::getFont()).
                $ogSlug = AppUtils::sanitizeSlug($ctx['meta']['slug'] ?? $p['slug'] ?? 'profile');
                $ogFile = 'og-' . $ogSlug . '.jpg';
                if (class_exists('AppMedia')) {
                    $generated = AppMedia::generateOgImage($p, $comp, $ogSlug);
                    if ($generated) $ogFile = $generated;
                }
                if ($ogFile && file_exists(IMG_PATH . DIRECTORY_SEPARATOR . $ogFile)) {
                    $img = $baseUrl . '/images/' . $encodePath($ogFile);
                } elseif (!empty($p['photo'])) {
                    $img = strpos((string)$p['photo'], 'http') === 0 ? $p['photo'] : $baseUrl . '/images/' . $encodePath($p['photo']);
                } else {
                    $img = 'https://ui-avatars.com/api/?name=' . urlencode($safeName) . '&background=1e40af&color=ffffff&size=630&bold=true&font-size=0.33&length=2';
                }

                $url = $ctx['meta']['url'] ?? $baseUrl . "/?card=business&slug=" . urlencode($p['slug'] ?? '');
            }

            if (empty($img)) {
                $img = $coverUrl ?: $logoUrl ?: ($baseUrl . '/favicon.png');
            }
            $img = htmlspecialchars((string)$img, ENT_QUOTES);

            $html  = "\n\n";
            $html .= "<title>{$title}</title>\n";
            $html .= "<link rel='canonical' href='{$url}'>\n";
            $html .= "<link rel='sitemap' type='application/xml' href='{$baseUrl}/sitemap.php'>\n";
            $html .= "<meta name='description' content='{$desc}'>\n";
            if (!empty($keywords)) {
                $html .= "<meta name='keywords' content='{$keywords}'>\n";
            }
            $html .= "<meta name='robots' content='index,follow,max-image-preview:large'>\n";
            $html .= "<meta property='og:site_name' content='{$compName}'>\n";
            $html .= "<meta property='og:locale' content='en_IN'>\n";
            $html .= "<meta property='og:title' content='{$title}'>\n";
            $html .= "<meta property='og:description' content='{$desc}'>\n";
            $html .= "<meta property='og:image' itemprop='image' content='{$img}'>\n";
            $html .= "<meta property='og:image:secure_url' content='{$img}'>\n";
            $__ogExt = strtolower(pathinfo(parse_url($img, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $__ogMime = match ($__ogExt) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                default => 'image/jpeg',
            };
            $html .= "<meta property='og:image:type' content='{$__ogMime}'>\n";
            $html .= "<meta property='og:image:width' content='1200'>\n";
            $html .= "<meta property='og:image:height' content='630'>\n";
            $html .= "<meta property='og:image:alt' content='{$title}'>\n";
            $html .= "<meta property='og:url' content='{$url}'>\n";
            $html .= "<meta property='og:type' content='{$type}'>\n";
            $html .= "<meta name='twitter:card' content='summary_large_image'>\n";
            $html .= "<meta name='twitter:title' content='{$title}'>\n";
            $html .= "<meta name='twitter:description' content='{$desc}'>\n";
            $html .= "<meta name='twitter:image' content='{$img}'>\n";

            if ($faviconUrl) {
                $html .= "<link rel='icon' href='{$faviconUrl}'>\n";
                $html .= "<link rel='apple-touch-icon' href='{$faviconUrl}'>\n";
            }
            $html .= "\n";


// JSON-LD: Organization + LocalBusiness + WebSite + WebPage + optional RealEstateProject
            $ldOrg = [
                '@context' => 'https://schema.org',
                '@type'    => 'Organization',
                'name'     => strip_tags($compName),
                'url'      => $baseUrl,
                'address'  => array_filter([
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => (string)($comp['address'] ?? $comp['registered_address'] ?? ''),
                    'addressLocality' => (string)($comp['city'] ?? 'New Delhi'),
                    'addressRegion'   => (string)($comp['state'] ?? 'Delhi'),
                    'postalCode'      => (string)($comp['pincode'] ?? $comp['pin'] ?? ''),
                    'addressCountry'  => 'IN',
                ]),
            ];
            if (!empty($logoUrl)) {
                $ldOrg['logo'] = $logoUrl;
                $ldOrg['image'] = $logoUrl;
            }
            if (!empty($comp['phone'])) {
                $ldOrg['telephone'] = (string)$comp['phone'];
            }
            if (!empty($comp['email'])) {
                $ldOrg['email'] = (string)$comp['email'];
            }
            $sameAs = [];
            if (!empty($comp['website'])) $sameAs[] = (string)$comp['website'];
            foreach (['linkedin','facebook','twitter','instagram','youtube'] as $soc) {
                $v = $comp['social'][$soc] ?? $comp[$soc] ?? '';
                if (is_string($v) && $v !== '') $sameAs[] = $v;
            }
            if ($sameAs) $ldOrg['sameAs'] = array_values(array_unique($sameAs));

            // LocalBusiness (rich local pack / maps eligibility)
            $ldLocal = [
                '@context' => 'https://schema.org',
                '@type'    => 'LocalBusiness',
                'name'     => strip_tags($compName),
                'url'      => $baseUrl,
                'image'    => $logoUrl ?: $baseUrl . '/images/og-default.jpg',
                'priceRange' => '₹₹',
                'address'  => $ldOrg['address'],
                'areaServed' => [
                    '@type' => 'Country',
                    'name'  => 'India',
                ],
            ];
            if (!empty($comp['phone'])) $ldLocal['telephone'] = (string)$comp['phone'];
            if (!empty($comp['email'])) $ldLocal['email'] = (string)$comp['email'];
            if (!empty($comp['geo_lat']) && !empty($comp['geo_lng'])) {
                $ldLocal['geo'] = [
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => (float)$comp['geo_lat'],
                    'longitude' => (float)$comp['geo_lng'],
                ];
            }

            $ldWebsite = [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => strip_tags($compName) . ' Portal',
                'url'      => $baseUrl,
                'inLanguage' => 'en-IN',
                'publisher' => ['@type' => 'Organization', 'name' => strip_tags($compName)],
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => $baseUrl . '/?tab=team&q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ];

            $ldPage = [
                '@context'    => 'https://schema.org',
                '@type'       => 'WebPage',
                'name'        => strip_tags($title),
                'description' => strip_tags($desc),
                'url'         => $url,
                'isPartOf'    => ['@type' => 'WebSite', 'url' => $baseUrl],
                'inLanguage'  => 'en-IN',
            ];
            if (!empty($img)) {
                $ldPage['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => strip_tags($img)];
            }

            $html .= '<script type="application/ld+json">' . json_encode($ldOrg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
            $html .= '<script type="application/ld+json">' . json_encode($ldLocal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
            $html .= '<script type="application/ld+json">' . json_encode($ldWebsite, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
            $html .= '<script type="application/ld+json">' . json_encode($ldPage, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";

            // RealEstateProject nodes from status/projects data when available
            if (class_exists('AppDB')) {
                $projects = AppDB::read('projects') ?: AppDB::read('status') ?: [];
                if (is_array($projects)) {
                    $n = 0;
                    foreach ($projects as $proj) {
                        if (!is_array($proj) || empty($proj['name'])) continue;
                        $ldRE = [
                            '@context' => 'https://schema.org',
                            '@type'    => 'RealEstateProject',
                            'name'     => (string)$proj['name'],
                            'url'      => $baseUrl . '/?tab=status',
                            'description' => (string)($proj['description'] ?? $proj['summary'] ?? ''),
                            'address'  => array_filter([
                                '@type'           => 'PostalAddress',
                                'streetAddress'   => (string)($proj['address'] ?? ''),
                                'addressLocality' => (string)($proj['city'] ?? ''),
                                'addressRegion'   => (string)($proj['state'] ?? ''),
                                'postalCode'      => (string)($proj['pincode'] ?? ''),
                                'addressCountry'  => 'IN',
                            ]),
                        ];
                        if (!empty($proj['image']) || !empty($proj['photo'])) {
                            $pic = (string)($proj['image'] ?? $proj['photo']);
                            $ldRE['image'] = (str_starts_with($pic, 'http') ? $pic : $baseUrl . '/images/' . ltrim($pic, '/'));
                        }
                        $html .= '<script type="application/ld+json">' . json_encode($ldRE, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
                        if (++$n >= 12) break; // keep payload lean
                    }
                }
            }

            return $html;


        }
    }
}
