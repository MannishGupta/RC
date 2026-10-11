<?php
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | Release v14.1.0 (2026-10-11)
   Single release version across all files. See CHANGELOG.md for changes.
   ───────────────────────────────────────────────────────────────────── */
// lib.php — Shared Core Library
// Centralized utilities: storage, security, image processing
declare(strict_types=1);

// ─── Constants ───────────────────────────────────────────────────────────────
/**
 * Storage layout
 * ──────────────
 * Multi-tenant RC (DATA_PATH / TENANT_ID already defined by app/tenant_bootstrap.php):
 *   DATA_DIR   = {DATA_PATH}/status/           e.g. tenants/fusionlimited/data/status/
 *   UPLOAD_DIR = {DATA_PATH}/status/uploads/
 * Standalone (no RC tenant context):
 *   DATA_DIR   = status/data/
 *   UPLOAD_DIR = status/uploads/
 * Explicit overrides (optional):
 *   define('STATUS_DATA_DIR', ...) / define('STATUS_UPLOAD_DIR', ...) before require lib.php
 *   or config integration.tenant_data = false to force legacy status/data
 */
if (!defined('DATA_DIR') || !defined('UPLOAD_DIR')) {
    $__stUseTenant = true;
    if (is_file(__DIR__ . '/config.php')) {
        $__stCfg = @include __DIR__ . '/config.php';
        if (is_array($__stCfg) && array_key_exists('integration', $__stCfg)
            && isset($__stCfg['integration']['tenant_data'])
            && $__stCfg['integration']['tenant_data'] === false) {
            $__stUseTenant = false;
        }
    }
    // Pull RC tenant bootstrap when status is hit standalone under an RC install
    if ($__stUseTenant && !defined('DATA_PATH')) {
        $__tb = dirname(__DIR__) . '/app/tenant_bootstrap.php';
        if (is_file($__tb)) {
            require_once $__tb;
        }
    }
    $__stData = null;
    $__stUp = null;
    if (defined('STATUS_DATA_DIR') && STATUS_DATA_DIR !== '') {
        $__stData = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)STATUS_DATA_DIR), DIRECTORY_SEPARATOR);
    } elseif ($__stUseTenant && defined('DATA_PATH') && DATA_PATH !== '') {
        $__stData = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)DATA_PATH), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'status';
    }
    if (defined('STATUS_UPLOAD_DIR') && STATUS_UPLOAD_DIR !== '') {
        $__stUp = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)STATUS_UPLOAD_DIR), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;
    } elseif ($__stData !== null) {
        $__stUp = $__stData . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    }
    if ($__stData === null) {
        $__stData = __DIR__ . DIRECTORY_SEPARATOR . 'data';
        $__stUp = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    }
    if (!defined('DATA_DIR')) {
        define('DATA_DIR', $__stData);
    }
    if (!defined('UPLOAD_DIR')) {
        define('UPLOAD_DIR', $__stUp);
    }
    // Ensure writable dirs exist (tenant first-run)
    foreach ([DATA_DIR, UPLOAD_DIR] as $__stMk) {
        if (!is_dir($__stMk)) {
            @mkdir($__stMk, 0775, true);
        }
    }
    foreach (['projects.json', 'components.json', 'updates.json'] as $__stSeed) {
        $__stSeedPath = DATA_DIR . DIRECTORY_SEPARATOR . $__stSeed;
        if (!is_file($__stSeedPath)) {
            @file_put_contents($__stSeedPath, "[]\n");
        }
    }
    // One-time copy from legacy status/data when tenant store is still empty
    $__legacy = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if ($__stUseTenant && defined('DATA_PATH') && is_dir($__legacy)
        && realpath($__legacy) !== realpath(DATA_DIR)) {
        $__proj = DATA_DIR . DIRECTORY_SEPARATOR . 'projects.json';
        $__legP = $__legacy . DIRECTORY_SEPARATOR . 'projects.json';
        $__empty = !is_file($__proj) || trim((string)@file_get_contents($__proj)) === '[]';
        if ($__empty && is_file($__legP) && trim((string)@file_get_contents($__legP)) !== '[]') {
            foreach (['projects.json', 'components.json', 'updates.json', 'site_config.json', 'visitor_logs.json'] as $__copy) {
                $__src = $__legacy . DIRECTORY_SEPARATOR . $__copy;
                $__dst = DATA_DIR . DIRECTORY_SEPARATOR . $__copy;
                if (is_file($__src) && !is_file($__dst)) {
                    @copy($__src, $__dst);
                }
            }
        }
    }
}
define('MAX_FILE_SIZE', 2097152);  // 2 MB

define('PROJ_FILE',      DATA_DIR . DIRECTORY_SEPARATOR . 'projects.json');
define('COMP_FILE',      DATA_DIR . DIRECTORY_SEPARATOR . 'components.json');
define('UPD_FILE',       DATA_DIR . DIRECTORY_SEPARATOR . 'updates.json');
define('ANALYTICS_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'visitor_logs.json');
define('ANALYTICS_CAP',  10000); // Maximum analytics log entries (FIFO rotation)

/** True when construction JSON lives under a tenant DATA_PATH/status tree. */
function statusUsesTenantData(): bool {
    return defined('DATA_PATH') && defined('DATA_DIR')
        && str_contains(str_replace('\\', '/', DATA_DIR), '/status');
}

/** Public URL path for a stored relative upload (uploads/…). */
function statusPublicUploadUrl(string $rel): string {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if (!str_starts_with($rel, 'uploads/')) {
        return $rel;
    }
    // Tenant uploads are outside the web status/ folder — serve via serve.php
    if (statusUsesTenantData()
        || (defined('UPLOAD_DIR') && !str_starts_with(
            str_replace('\\', '/', realpath(UPLOAD_DIR) ?: UPLOAD_DIR),
            str_replace('\\', '/', realpath(__DIR__) ?: __DIR__)
        ))) {
        return 'serve.php?f=' . rawurlencode($rel);
    }
    return $rel;
}

// ─── Configuration ────────────────────────────────────────────────────────────

/**
 * Load config.php once. Falls back to an empty set so a missing config file
 * degrades to the built-in defaults rather than a fatal error.
 */

/**
 * Read a status JSON file; if tenant store is empty, fall back to legacy status/data/.
 */
function statusLoadJson(string $basename): array {
    $primary = DATA_DIR . DIRECTORY_SEPARATOR . $basename;
    $data = [];
    if (is_file($primary)) {
        $j = json_decode((string)@file_get_contents($primary), true);
        if (is_array($j)) {
            $data = $j;
        }
    }
    $empty = ($data === [] || $data === null);
    if ($empty) {
        $legacy = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $basename;
        if (is_file($legacy) && realpath($legacy) !== realpath($primary)) {
            $j = json_decode((string)@file_get_contents($legacy), true);
            if (is_array($j) && $j !== []) {
                $data = $j;
                // Persist into tenant store so next load is local
                if (is_dir(DATA_DIR) && is_writable(DATA_DIR)) {
                    @file_put_contents($primary, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX);
                }
            }
        }
    }
    return is_array($data) ? $data : [];
}

function appConfig(): array {
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
        $cfg  = is_file($file) ? (require $file) : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }
    return $cfg;
}

/**
 * Read a dot-path out of the config: cfg('brand.company', 'Fallback').
 */
function cfg(string $path, mixed $default = null): mixed {
    $node = appConfig();
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}

/**
 * Is this request HTTPS?
 *
 * IIS sets $_SERVER['HTTPS'] to the literal string 'off' on plain HTTP, which
 * is truthy in PHP — so the isset() check that works on Apache is ALWAYS true
 * on IIS. This is the single most common Apache-to-IIS porting bug.
 */
function isHttps(): bool {
    $https = $_SERVER['HTTPS'] ?? '';
    if (is_string($https) && strcasecmp($https, 'on') === 0) {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;   // Plesk / ARR / load balancer in front
    }
    if (($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '') === 'on') {
        return true;
    }
    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/**
 * Absolute base URL of this installation, with a trailing slash.
 *
 * Detected from the request so the portal works at any host and any folder
 * depth without a code change — https://fusionlimited.in/status/ and
 * https://rc.arthsathi.com/status/ both resolve correctly from the same files.
 *
 * The Host header is attacker-controlled, so it is validated against
 * site.allowed_hosts when that list is populated, and otherwise reduced to a
 * safe character set. Without this, a poisoned Host header ends up inside
 * <link rel="canonical"> and og:url.
 */
function appBaseUrl(): string {
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $pinned = cfg('site.base_url');
    if (is_string($pinned) && $pinned !== '') {
        return $base = rtrim($pinned, '/') . '/';
    }

    $https = isHttps();

    /* ── Host ──────────────────────────────────────────────────────────────
       The Host header is attacker-controlled, so it is validated against
       site.allowed_hosts when that list is populated, and otherwise reduced to
       a safe character set. A poisoned Host otherwise lands in canonical/og:. */
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $host) ?? '';

    $allowed = (array)cfg('site.allowed_hosts', []);
    if ($allowed !== []) {
        $match = false;
        foreach ($allowed as $ok) {
            if (strcasecmp($host, (string)$ok) === 0) {
                $match = true;
                break;
            }
        }
        if (!$match) {
            $host = (string)$allowed[0];
        }
    } elseif ($host === '') {
        $host = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
    }

    // Drop the port when it is the default for the scheme. IIS commonly reports
    // "example.com:443" on HTTPS, and ":443" in a canonical URL is wrong.
    if (str_contains($host, ':')) {
        [$hostName, $port] = explode(':', $host, 2);
        $port = preg_replace('/\D/', '', $port) ?? '';
        $host = ($port === '' || ($https && $port === '443') || (!$https && $port === '80'))
            ? $hostName
            : $hostName . ':' . $port;
    }

    /* ── Folder ────────────────────────────────────────────────────────────
       Derived from where THIS FILE lives relative to the document root, not
       from the running script's path. dirname(SCRIPT_NAME) is wrong whenever
       the executing script sits in a subfolder (tools/, or an include), which
       would silently produce a base URL one level too deep.                  */
    $path    = null;
    $docRoot = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $docReal = $docRoot !== '' ? realpath($docRoot) : false;
    $appReal = realpath(__DIR__);

    if ($docReal !== false && $appReal !== false) {
        $d = rtrim(str_replace('\\', '/', $docReal), '/');
        $a = rtrim(str_replace('\\', '/', $appReal), '/');
        // Case-insensitive: Windows paths differ in case between IIS and PHP.
        if ($a === $d) {
            $path = '';
        } elseif (stripos($a . '/', $d . '/') === 0) {
            $path = '/' . trim(substr($a, strlen($d)), '/');
        }
    }

    if ($path === null) {
        // Fallback for hosts that do not expose a usable DOCUMENT_ROOT.
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/');
        $path   = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($path === '.' || $path === '/') {
            $path = '';
        }
    }

    return $base = ($https ? 'https' : 'http') . '://' . $host . $path . '/';
}

/**
 * Build an absolute URL for a path relative to the installation root.
 */
function appUrl(string $relative = ''): string {
    return appBaseUrl() . ltrim($relative, '/');
}

// ─── AppLocale — en-IN presentation layer ─────────────────────────────────────

/**
 * Locale-aware formatting for the portal.
 *
 * Storage formats never change; this class governs presentation only. Dates are
 * stored as 'd-M-Y' (config locale.date_store) and displayed as 'd M Y'
 * (locale.date_display), so the JSON stays stable while the UI follows the
 * en-IN convention.
 */
final class AppLocale
{
    private static bool $booted = false;

    /** Apply timezone and encoding. Called automatically by every entry point. */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        date_default_timezone_set((string)cfg('locale.timezone', 'Asia/Kolkata'));
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }
        ini_set('default_charset', 'UTF-8');
    }

    public static function lang(): string
    {
        return (string)cfg('locale.lang', 'en-IN');
    }

    /** Parse a date written in the stored format. Accepts Y-m-d as a fallback. */
    public static function parse(string $stored): ?DateTimeImmutable
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }
        foreach ([(string)cfg('locale.date_store', 'd-M-Y'), 'Y-m-d', 'd-m-Y'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat($fmt, $stored);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }
        return null;
    }

    /** Format a stored date for display: "08-Mar-2026" → "08 Mar 2026". */
    public static function date(string $stored): string
    {
        $dt = self::parse($stored);
        return $dt ? $dt->format((string)cfg('locale.date_display', 'd M Y')) : $stored;
    }

    /** Write a date back in the stored format. */
    public static function store(DateTimeInterface $dt): string
    {
        return $dt->format((string)cfg('locale.date_store', 'd-M-Y'));
    }

    /** "18 Sep 2026, 4:05 PM IST" */
    public static function now(): string
    {
        return date((string)cfg('locale.datetime_display', 'd M Y, g:i A')) . ' IST';
    }

    /**
     * Indian digit grouping — ₹12,34,567.00, not ₹1,234,567.00.
     * The last three digits group normally, everything above pairs off.
     */
    public static function money(float $amount, bool $paise = false): string
    {
        $symbol = (string)cfg('locale.currency_symbol', '₹');
        $neg    = $amount < 0;
        $amount = abs($amount);

        $whole = (string)(int)floor($amount);
        $frac  = $paise ? '.' . str_pad((string)(int)round(($amount - floor($amount)) * 100), 2, '0', STR_PAD_LEFT) : '';

        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest  = substr($whole, 0, -3);
            $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;
            $whole = $rest . ',' . $last3;
        }

        return ($neg ? '-' : '') . $symbol . $whole . $frac;
    }

    /** "+91 98765 43210" from any reasonable input. */
    public static function phone(string $raw): string
    {
        $cc     = (string)cfg('locale.phone_cc', '+91');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        $bare = ltrim($cc, '+');
        if ($bare !== '' && str_starts_with($digits, $bare) && strlen($digits) > 10) {
            $digits = substr($digits, strlen($bare));
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) !== 10) {
            return trim($raw);   // not an Indian mobile — leave it alone
        }

        return $cc . ' ' . substr($digits, 0, 5) . ' ' . substr($digits, 5);
    }
}

// ─── JSON Storage ─────────────────────────────────────────────────────────────

/**
 * Read a JSON file safely with a shared lock to prevent read-during-write corruption.
 *
 * $strict distinguishes "this file is legitimately empty" from "this file is damaged".
 * Read-modify-write callers MUST pass true: without it a corrupt or unreadable store
 * decodes to [] and the subsequent write silently destroys every remaining record.
 *
 * @throws RuntimeException when $strict and the file exists but cannot be parsed.
 */
function safeReadJson(string $file, bool $strict = false): array {
    if (!file_exists($file)) {
        return [];
    }

    $fp = fopen($file, 'r');
    if (!$fp) {
        if ($strict) {
            throw new RuntimeException("Cannot open data store for reading: " . basename($file));
        }
        return [];
    }

    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if ($content === false) {
        if ($strict) {
            throw new RuntimeException("Cannot read data store: " . basename($file));
        }
        return [];
    }

    $trimmed = trim($content);
    if ($trimmed === '') {
        return [];   // genuinely empty file — not an error
    }

    $data = json_decode($trimmed, true);
    if (!is_array($data)) {
        if ($strict) {
            throw new RuntimeException(
                "Data store is corrupt and was NOT overwritten: " . basename($file)
                . " (" . json_last_error_msg() . "). Restore it from the most recent backup"
                . " or from the .bak file beside it."
            );
        }
        error_log("safeReadJson: corrupt JSON in {$file} — " . json_last_error_msg());
        return [];
    }

    return $data;
}

/**
 * Read a store for a read-modify-write cycle. Always strict — see safeReadJson().
 */
function readStoreForWrite(string $file): array {
    return safeReadJson($file, true);
}

/**
 * Write JSON atomically via temp-file + rename to prevent data loss on crash.
 * Refuses to write when json_encode() fails (a single invalid UTF-8 byte returns
 * false, which would otherwise truncate the store to an empty file), and keeps
 * one generation of the previous file as <name>.bak.
 *
 * @throws RuntimeException when the payload cannot be encoded.
 */
function atomicWriteJson(string $file, array $data): void {
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $payload = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($payload === false) {
        throw new RuntimeException(
            "Refusing to write " . basename($file) . ": " . json_last_error_msg()
            . ". The existing file is unchanged."
        );
    }

    // Keep one generation back, so a bad write is always recoverable in place.
    if (is_file($file) && filesize($file) > 0) {
        @copy($file, $file . '.bak');
    }

    $tmp = tempnam($dir, 'json_');

    if ($tmp === false) {
        // Fallback: non-atomic write with exclusive lock (often needed on Windows)
        file_put_contents($file, $payload, LOCK_EX);
        return;
    }

    file_put_contents($tmp, $payload);
    chmod($tmp, 0644);

    // Attempt atomic rename. Suppress warnings for Windows file lock edge cases.
    if (!@rename($tmp, $file)) {
        file_put_contents($file, $payload, LOCK_EX);
    }

    // Garbage collection if temp file was left stranded
    if (file_exists($tmp)) {
        @unlink($tmp);
    }
}

/**
 * Read JSON with a static in-request cache to prevent redundant I/O calls.
 */
function cachedJson(string $file): array {
    static $cache = [];
    if (!isset($cache[$file])) {
        $cache[$file] = safeReadJson($file);
    }
    return $cache[$file];
}

// ─── Security ─────────────────────────────────────────────────────────────────

/**
 * Sanitize an image path for safe HTML output and path traversal prevention.
 */
function sanitizeImagePath(string $path): string {
    return htmlspecialchars(statusMediaUrl($path), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Resolve stored media (uploads/…, assets/…) to a browser-usable URL.
 *
 * Order of preference (permanent, with fallback):
 *  1) Absolute / data / already-resolved URLs — unchanged
 *  2) File exists under status/uploads or status/assets (legacy web tree) → /status/…
 *  3) File exists under tenant UPLOAD_DIR → /status/serve.php?f=…
 *  4) Heuristic fallback: try legacy public URL first (most deploys still have files there)
 */
function statusMediaUrl(string $path): string {
    $path = trim(str_replace(["\r", "\n", "\0"], '', (string)$path));
    if ($path === '') {
        return '';
    }
    // Already a full URL, data URI, or root-absolute app path
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }
    if (str_starts_with($path, '/status/') || str_starts_with($path, '/media_serve.php') || str_starts_with($path, '/assets/')) {
        return $path;
    }
    if (str_starts_with($path, 'serve.php')) {
        return '/status/' . ltrim($path, '/');
    }

    $clean = $path;
    do {
        $before = $clean;
        $clean = str_replace(['../', '..\\', '..'], '', $clean);
    } while ($clean !== $before);
    $clean = ltrim(str_replace('\\', '/', $clean), '/');

    $statusRoot = str_replace('\\', '/', __DIR__);

    // assets/* shipped with the portal
    if (str_starts_with($clean, 'assets/')) {
        $abs = $statusRoot . '/' . $clean;
        if (is_file($abs)) {
            return '/status/' . $clean;
        }
        return '/status/' . $clean; // still point at public tree
    }

    if (str_starts_with($clean, 'uploads/')) {
        $sub = substr($clean, strlen('uploads/'));
        $legacyAbs = $statusRoot . '/uploads/' . $sub;
        $tenantAbs = '';
        if (defined('UPLOAD_DIR') && UPLOAD_DIR !== '') {
            $tenantAbs = rtrim(str_replace('\\', '/', UPLOAD_DIR), '/') . '/' . $sub;
        }

        $legacyOk = is_file($legacyAbs);
        $tenantOk = $tenantAbs !== '' && is_file($tenantAbs);

        if ($legacyOk) {
            return '/status/uploads/' . str_replace(' ', '%20', $sub);
        }
        if ($tenantOk) {
            return '/status/serve.php?f=' . rawurlencode('uploads/' . $sub);
        }
        // Neither found — still prefer public path (older FTP layouts), browser 404 is clearer than wrong serve root
        if (is_dir($statusRoot . '/uploads')) {
            return '/status/uploads/' . str_replace(' ', '%20', $sub);
        }
        if (defined('UPLOAD_DIR')) {
            return '/status/serve.php?f=' . rawurlencode('uploads/' . $sub);
        }
        return '/status/uploads/' . str_replace(' ', '%20', $sub);
    }

    // Bare filename → try uploads/
    if (!str_contains($clean, '/')) {
        return statusMediaUrl('uploads/' . $clean);
    }

    return '/status/' . $clean;
}

/**
 * Logo markup on sand-dune plate. Never emits broken leftover attributes.
 * Dual-src fallback: primary URL + onerror swap to alternate (legacy ↔ serve).
 */
function statusLogoImg(string $path, string $alt = '', string $class = ''): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    $url = statusMediaUrl($path);
    if ($url === '') {
        return '';
    }
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Build fallback alternate
    $altUrl = '';
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    if (str_starts_with($clean, 'uploads/')) {
        $sub = substr($clean, strlen('uploads/'));
        if (str_starts_with($url, '/status/serve.php')) {
            $altUrl = '/status/uploads/' . str_replace(' ', '%20', $sub);
        } elseif (str_starts_with($url, '/status/uploads/')) {
            $altUrl = '/status/serve.php?f=' . rawurlencode('uploads/' . $sub);
        }
    }

    $isSvg = (bool)preg_match('/\.svg(\?|$)/i', $path . ' ' . $url);
    $cls = trim('st-logo-img ' . $class);
    $onerr = $altUrl !== ''
        ? 'this.onerror=null;this.src=\'' . $e($altUrl) . '\';'
        : 'this.closest(\'.st-logo-plate\')&&this.closest(\'.st-logo-plate\').classList.add(\'is-broken\')';

    return '<span class="project-logo-plate st-logo-plate' . ($isSvg ? ' st-logo-plate--svg' : '') . '">'
        . '<img src="' . $e($url) . '" alt="' . $e($alt) . '" class="' . $e($cls) . '" '
        . 'loading="lazy" decoding="async" onerror="' . $onerr . '">'
        . '</span>';
}

/**
 * Plain <img> for site photos (thumbs) with the same URL + fallback logic.
 */
function statusPhotoImg(string $path, string $alt = '', string $class = 'update-thumb'): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    $url = statusMediaUrl($path);
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $altUrl = '';
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    if (str_starts_with($clean, 'uploads/')) {
        $sub = substr($clean, strlen('uploads/'));
        if (str_starts_with($url, '/status/serve.php')) {
            $altUrl = '/status/uploads/' . str_replace(' ', '%20', $sub);
        } elseif (str_starts_with($url, '/status/uploads/')) {
            $altUrl = '/status/serve.php?f=' . rawurlencode('uploads/' . $sub);
        }
    }
    $onerr = $altUrl !== ''
        ? 'this.onerror=null;this.src=\'' . $e($altUrl) . '\';'
        : 'this.style.opacity=\'0.3\'';
    return '<img src="' . $e($url) . '" alt="' . $e($alt) . '" class="' . $e($class) . '" '
        . 'loading="lazy" decoding="async" onerror="' . $onerr . '">';
}

function isPathInsideUploads(string $relativePath): bool {
    $absUpload = realpath(UPLOAD_DIR);
    if ($absUpload === false) return false;
    
    $cleanPath = ltrim(str_replace(['..', '\\'], '', $relativePath), '/');
    $absFile = realpath(__DIR__ . DIRECTORY_SEPARATOR . $cleanPath);
    
    if ($absFile === false) return false;
    return strncmp($absFile, $absUpload, strlen($absUpload)) === 0;
}

/**
 * Get the real client IP address, verifying against spoofed proxy headers.
 */
function getRealIp(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                // Validate IP and explicitly reject private/reserved ranges from proxies
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    
    // Fallback to strict remote address if proxy headers are spoofed/private
    $fallback = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    return filter_var($fallback, FILTER_VALIDATE_IP) ? $fallback : 'UNKNOWN';
}

// ─── Image Processing ─────────────────────────────────────────────────────────

/**
 * Compress, resize, and EXIF-rotate an uploaded image using GD.
 * Returns true on success, false if GD unavailable or image unreadable.
 */
function compressAndSaveImage(string $sourcePath, string $targetPath, string $mime): bool {
    if (!extension_loaded('gd')) return false;

    $info = @getimagesize($sourcePath);
    if (!$info) return false;
    [$origWidth, $origHeight] = $info;

    // Guard against zero-dimension images (corrupted/truncated files) to avoid division-by-zero
    if ($origWidth <= 0 || $origHeight <= 0) return false;

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png'  => @imagecreatefrompng($sourcePath),
        'image/webp' => @imagecreatefromwebp($sourcePath),
        'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($sourcePath) : false,
        default      => false,
    };
    if (!$source) return false;

    // EXIF auto-rotation for JPEG to fix orientation issues
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($sourcePath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3: 
                    $source = imagerotate($source, 180, 0); 
                    break;
                case 6: 
                    $source = imagerotate($source, -90, 0); 
                    [$origWidth, $origHeight] = [$origHeight, $origWidth]; 
                    break;
                case 8: 
                    $source = imagerotate($source, 90, 0);  
                    [$origWidth, $origHeight] = [$origHeight, $origWidth]; 
                    break;
            }
        }
    }

    // Proportional resize to max 1600×1600 constraint
    $maxW = 1600;
    $maxH = 1600;
    $ratio = $origWidth / $origHeight;
    
    if ($maxW / $maxH > $ratio) { 
        $maxW = (int)round($maxH * $ratio); 
    } else { 
        $maxH = (int)round($maxW / $ratio); 
    }

    $canvas = imagecreatetruecolor($maxW, $maxH);
    
    // Alpha channel preservation for modern formats
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $maxW, $maxH, $transparent);
    } else {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
    }
    
    // Resample with anti-aliasing
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $maxW, $maxH, $origWidth, $origHeight);

    // Always save as WebP for best compression/quality ratio
    $success = imagewebp($canvas, $targetPath, 82);

    imagedestroy($canvas);
    imagedestroy($source);
    
    return $success;
}

/**
 * Upload engine: validate, compress, and save multiple images.
 * Returns [array $savedPaths, array $errors]
 */
function uploadEngine(array $filesArray, bool $isSingle = false): array {
    $images = [];
    $errors = [];

    // Guard against entirely empty or invalid $_FILES payloads
    if (empty($filesArray) || empty($filesArray['name'])) {
        return $isSingle ? ['', []] : [[], []];
    }

    $names    = is_array($filesArray['name'])     ? $filesArray['name']     : [$filesArray['name']];
    $tmpNames = is_array($filesArray['tmp_name']) ? $filesArray['tmp_name'] : [$filesArray['tmp_name']];
    $sizes    = is_array($filesArray['size'])     ? $filesArray['size']     : [$filesArray['size']];
    $errCodes = is_array($filesArray['error'])    ? $filesArray['error']    : [$filesArray['error']];

    if (empty($names[0])) {
        return $isSingle ? ['', []] : [[], []];
    }

    $subDir    = date('Y/m/');
    $targetDir = UPLOAD_DIR . $subDir;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Armor the upload directory against script execution (IIS)
    $armor = UPLOAD_DIR . 'web.config';
    if (!file_exists($armor)) {
        $configData = '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><security><requestFiltering><fileExtensions><add fileExtension=".php" allowed="false" /><add fileExtension=".aspx" allowed="false" /><add fileExtension=".asp" allowed="false" /><add fileExtension=".jsp" allowed="false" /></fileExtensions></requestFiltering></security></system.webServer></configuration>';
        file_put_contents($armor, $configData);
    }

    // Accepted input formats. All raster types are converted to WebP on save.
    // SVG and GIF are stored as-is (GD cannot safely re-encode them).
    $extMap = [
        'image/jpeg'    => 'webp',
        'image/png'     => 'webp',
        'image/webp'    => 'webp',
        'image/avif'    => 'webp',
        'image/svg+xml' => 'svg',
        'image/gif'     => 'gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    
    foreach ($tmpNames as $key => $tmp) {
        if (($errCodes[$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        if (!is_uploaded_file($tmp)) continue;

        $fileName = $names[$key] ?? 'file';

        if (($sizes[$key] ?? 0) > MAX_FILE_SIZE) {
            $errors[] = "{$fileName} exceeds the 2 MB size limit. Please reduce the file size before uploading.";
            continue;
        }

        $mime = finfo_file($finfo, $tmp);
        if (!isset($extMap[$mime])) {
            $errors[] = "{$fileName} is not a valid image format.";
            continue;
        }

        // ── Decompression-bomb guard ─────────────────────────────────────
        if (!in_array($mime, ['image/svg+xml', 'image/gif'], true)) {
            $imgInfo = @getimagesize($tmp);
            if ($imgInfo !== false) {
                $megapixels = (($imgInfo[0] ?? 0) * ($imgInfo[1] ?? 0)) / 1_000_000;
                if ($megapixels > 40) {
                    $errors[] = "{$fileName} was rejected: image dimensions exceed 40 megapixels (" . number_format($megapixels, 1) . " MP). Please resize before uploading.";
                    continue;
                }
            }
        }

        $safeExt  = $extMap[$mime];
        $newName  = bin2hex(random_bytes(16)) . '.' . $safeExt;
        $destPath = $targetDir . $newName;
        $relPath  = 'uploads/' . $subDir . $newName;

        if (compressAndSaveImage($tmp, $destPath, $mime)) {
            // Saved as WebP by compressAndSaveImage
            $images[] = $relPath;
        } elseif (in_array($mime, ['image/svg+xml', 'image/gif'], true)) {
            // SVG and GIF: GD cannot re-encode these — safe raw move only
            // SVG: sanitize to strip inline scripts and event-handler attributes before saving
            if ($mime === 'image/svg+xml') {
                $rawSvg = file_get_contents($tmp);
                if ($rawSvg === false) { $errors[] = "{$fileName} could not be read."; continue; }
                // Strip <script> blocks
                $rawSvg = preg_replace('#<script[\s\S]*?</script\s*>#i', '', $rawSvg);
                // Strip on* event handler attributes (e.g. onclick, onload, onmouseover)
                $rawSvg = preg_replace('/\bon\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $rawSvg);
                // Strip javascript: hrefs/xlink:hrefs
                $rawSvg = preg_replace('/\b(href|xlink:href)\s*=\s*"javascript:[^"]*"/i', '', $rawSvg);
                if (file_put_contents($destPath, $rawSvg) !== false) {
                    $images[] = $relPath;
                } else {
                    $errors[] = "{$fileName} could not be saved.";
                }
            } elseif (move_uploaded_file($tmp, $destPath)) {
                $images[] = $relPath;
            } else {
                $errors[] = "{$fileName} could not be saved.";
            }
        } else {
            $errors[] = "{$fileName} could not be processed. Ensure GD is enabled on the server.";
        }
    }
    
    finfo_close($finfo);

    return $isSingle
        ? [(isset($images[0]) ? $images[0] : ''), $errors]
        : [$images, $errors];
}

// ─── Rich-text sanitisation ───────────────────────────────────────────────────

/**
 * Sanitise Quill-authored HTML for safe output.
 *
 * strip_tags() is NOT sufficient here: it removes disallowed *tags* but preserves
 * every *attribute* on the ones it keeps, so <span onmouseover="…"> and
 * <a href="javascript:…"> pass straight through. This parses the fragment and
 * rebuilds it, dropping every attribute that is not explicitly allowed.
 *
 * Apply at save time AND at render time — stored data predating this function
 * has not been through it.
 */
function sanitizeRichText(?string $html): string {
    $html = trim((string)$html);
    if ($html === '') {
        return '';
    }

    // Tag => attributes permitted on it. Everything else is dropped.
    $allowed = [
        'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [],
        'br' => [], 'p' => ['class'], 'ul' => [], 'ol' => [], 'li' => ['class'],
        'span' => ['class'], 'a' => ['href', 'rel', 'target'],
    ];
    // Removed entirely, contents and all.
    $purge = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math',
              'form', 'input', 'button', 'template', 'noscript', 'link', 'meta'];
    // Quill's own formatting classes are the only ones kept.
    $classOk = '/^ql-(align-(center|right|justify)|indent-[1-8])$/';

    if (!class_exists('DOMDocument')) {
        // Fallback when ext-dom is unavailable: keep the tags, drop ALL attributes.
        $out = strip_tags($html, '<b><strong><i><em><u><s><br><p><ul><ol><li><span>');
        return (string)preg_replace('#<\s*([a-zA-Z0-9]+)(?:\s[^>]*?)?(/?)\s*>#', '<$1$2>', $out);
    }

    $doc  = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $ok   = $doc->loadHTML(
        '<?xml encoding="utf-8" ?><div id="fx-sanitise-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$ok) {
        return '';
    }

    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root instanceof DOMElement) {
        return '';
    }

    $walk = function (DOMNode $node) use (&$walk, $allowed, $purge, $classOk): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $child->parentNode?->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue;   // text nodes are kept as-is and escaped on output
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, $purge, true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            if (!isset($allowed[$tag])) {
                // Not allowed, but not dangerous in itself: keep the text, drop the tag.
                $walk($child);
                while ($child->firstChild) {
                    $child->parentNode?->insertBefore($child->firstChild, $child);
                }
                $child->parentNode?->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);

                if (!in_array($name, $allowed[$tag], true)) {
                    $child->removeAttribute($attr->nodeName);
                    continue;
                }

                if ($name === 'href') {
                    $href = trim($attr->nodeValue ?? '');
                    if (!preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $href)) {
                        $child->removeAttribute('href');   // javascript:, data:, vbscript:, …
                    } else {
                        $child->setAttribute('href', $href);
                        if (stripos($href, 'http') === 0) {
                            $child->setAttribute('rel', 'noopener noreferrer nofollow');
                            $child->setAttribute('target', '_blank');
                        }
                    }
                    continue;
                }

                if ($name === 'class') {
                    $keep = array_values(array_filter(
                        preg_split('/\s+/', trim($attr->nodeValue ?? '')) ?: [],
                        static fn($c) => $c !== '' && preg_match($classOk, $c) === 1
                    ));
                    if ($keep) {
                        $child->setAttribute('class', implode(' ', $keep));
                    } else {
                        $child->removeAttribute('class');
                    }
                }
            }

            $walk($child);
        }
    };

    $walk($root);

    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }

    return trim($out);
}

// ─── Media filename normalisation ─────────────────────────────────────────────

/**
 * Normalise one filename for safe, predictable URLs.
 *
 * The site-root web.config contains LowerCaseRule1, which 301-redirects any URL
 * containing an uppercase letter. Combined with spaces and '&' in legacy asset
 * names, that produced hard 500s for images. Everything is folded to lowercase
 * and reduced to [a-z0-9._-], so no rewrite rule ever fires on a media URL.
 */
function mediaSlug(string $filename): string {
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    $base = strtolower($base);
    $base = str_replace('&', 'and', $base);
    $base = preg_replace('/[^a-z0-9._-]+/', '-', $base) ?? $base;
    $base = trim(preg_replace('/-+/', '-', $base) ?? $base, '-._');

    if ($base === '') {
        $base = 'file';
    }

    return $ext !== '' ? $base . '.' . strtolower($ext) : $base;
}

/**
 * Resolve an asset path that may or may not have been normalised yet.
 *
 * Lets the hardcoded paths in the PHP sources keep working both before and
 * after the Optimise run renames the files, so there is never a window where
 * a logo 404s.
 */
function assetPath(string $rel): string {
    $exists = static function (string $p): bool {
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
        if (is_file(__DIR__ . DIRECTORY_SEPARATOR . $p)) {
            return true;
        }
        if (defined('UPLOAD_DIR') && str_starts_with(str_replace('\\', '/', $p), 'uploads/')) {
            $sub = substr(str_replace('\\', '/', $p), strlen('uploads/'));
            return is_file(rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub));
        }
        if (defined('DATA_DIR') && is_file(DATA_DIR . DIRECTORY_SEPARATOR . $p)) {
            return true;
        }
        return false;
    };

    if ($exists($rel)) {
        return statusMediaUrl($rel);
    }

    // Optimise may have renamed it …
    $dir  = str_contains($rel, '/') ? substr($rel, 0, strrpos($rel, '/') + 1) : '';
    $slug = $dir . mediaSlug(basename($rel));
    if ($exists($slug)) {
        return $slug;
    }

    // … and may also have re-encoded it, changing the extension.
    foreach (['webp', 'avif'] as $ext) {
        foreach ([$slug, $rel] as $candidate) {
            $alt = preg_replace('/\.[^.]+$/', '.' . $ext, $candidate);
            if ($alt !== null && $alt !== $candidate && $exists($alt)) {
                return $alt;
            }
        }
    }

    return statusMediaUrl($rel);
}

/**
 * Rename every file under the given folders to its normalised form.
 *
 * @param  list<string> $folders  Relative to the app root, e.g. ['uploads','assets']
 * @return array{0: array<string,string>, 1: list<string>}  [relOld => relNew], skipped
 */
function normaliseMediaNames(array $folders): array {
    $map     = [];
    $skipped = [];

    foreach ($folders as $folder) {
        $base = __DIR__ . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($base)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }

            $name = $f->getFilename();
            if ($name === 'web.config') {
                continue;   // the uploads script-execution guard
            }
            if ($name !== '' && $name[0] === '.') {
                /* Dotfiles are configuration, not media. SKIP_DOTS only drops
                   '.' and '..', so without this a .htaccess, .user.ini or
                   .gitkeep would be renamed to something inert. */
                continue;
            }

            $slug = mediaSlug($name);
            if ($slug === $name) {
                continue;
            }

            $dir = $f->getPath();
            $old = $dir . DIRECTORY_SEPARATOR . $name;
            $new = $dir . DIRECTORY_SEPARATOR . $slug;

            // A different file already occupies the target name — leave both alone.
            if (strcasecmp($old, $new) !== 0 && file_exists($new)) {
                $skipped[] = $name . ' (target "' . $slug . '" already exists)';
                continue;
            }

            // Case-only renames are a no-op on NTFS, so go via a temp name.
            $tmp = $dir . DIRECTORY_SEPARATOR . '_rn_' . bin2hex(random_bytes(4)) . '_' . $slug;
            if (!@rename($old, $tmp)) {
                $skipped[] = $name . ' (rename failed — file locked?)';
                continue;
            }
            if (!@rename($tmp, $new)) {
                @rename($tmp, $old);
                $skipped[] = $name . ' (rename failed on second step)';
                continue;
            }

            $rel  = str_replace('\\', '/', substr($old, strlen(__DIR__) + 1));
            $relN = str_replace('\\', '/', substr($new, strlen(__DIR__) + 1));
            $map[$rel] = $relN;
        }
    }

    return [$map, $skipped];
}

/** File extensions treated as playable media. */
const VIDEO_EXTS = ['mp4', 'webm', 'ogv', 'ogg', 'mov', 'm4v'];

/**
 * Every video file on disk, as app-relative paths, for the Featured Media picker.
 *
 * @return list<array{path:string,bytes:int}>
 */
function listVideoFiles(): array {
    $out = [];
    foreach (['assets', 'uploads'] as $folder) {
        $base = __DIR__ . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            if (!in_array(strtolower($f->getExtension()), VIDEO_EXTS, true)) {
                continue;
            }
            $out[] = [
                'path'  => str_replace('\\', '/', substr($f->getPathname(), strlen(__DIR__) + 1)),
                'bytes' => (int)$f->getSize(),
            ];
        }
    }
    usort($out, fn($a, $b) => strcmp($a['path'], $b['path']));
    return $out;
}

/**
 * MIME type for a video path, so <source type> matches the actual file.
 */
function videoMimeFor(string $path): string {
    $ext = strtolower(pathinfo((string)(parse_url($path, PHP_URL_PATH) ?: $path), PATHINFO_EXTENSION));
    return [
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm',
        'ogv' => 'video/ogg', 'ogg' => 'video/ogg', 'mov' => 'video/quicktime',
    ][$ext] ?? 'video/mp4';
}

// ─── Housekeeping ─────────────────────────────────────────────────────────────

/**
 * Find files that nothing in the application refers to.
 *
 * Deliberately conservative and REPORT-ONLY — the caller decides what to delete,
 * and the core runtime files can never appear in the result.
 *
 * @return array{code: list<array{path:string,bytes:int,why:string}>,
 *               media: list<array{path:string,bytes:int,why:string}>}
 */
function housekeepingScan(): array {
    $core = [
        'index.php', 'admin.php', 'admin_backend.php', 'lib.php', 'report.php',
        'actions.php', 'backup.php', 'reminder.php', 'analytics.php',
        'web.config', 'VERSION', 'error.php', 'favicon.ico',
    ];

    // Everything the running code mentions by name.
    $haystack = '';
    foreach ($core as $f) {
        $p = __DIR__ . DIRECTORY_SEPARATOR . $f;
        if (is_file($p)) {
            $haystack .= (string)file_get_contents($p);
        }
    }
    foreach (['app.css'] as $c) {
        $p = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $c;
        if (is_file($p)) {
            $haystack .= (string)file_get_contents($p);
        }
    }

    // Media referenced from the data stores.
    $referenced = [];
    foreach (cachedJson(PROJ_FILE) as $p) {
        if (!empty($p['logo'])) {
            $referenced[strtolower($p['logo'])] = true;
        }
    }
    foreach (cachedJson(UPD_FILE) as $u) {
        foreach ((array)($u['images'] ?? []) as $img) {
            $referenced[strtolower((string)$img)] = true;
        }
    }
    $cfg = cachedJson(DATA_DIR . '/site_config.json');
    if (!empty($cfg['video_url'])) {
        $referenced[strtolower((string)$cfg['video_url'])] = true;
    }

    $code = [];
    $media = [];

    // 1. Root-level files that are not core and are not named anywhere in the code.
    foreach ((array)scandir(__DIR__) as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $abs = __DIR__ . DIRECTORY_SEPARATOR . $name;
        if (!is_file($abs) || in_array($name, $core, true)) {
            continue;
        }
        if (str_contains($haystack, $name)) {
            continue;   // referenced somewhere — leave it
        }
        $code[] = [
            'path'  => $name,
            'bytes' => (int)filesize($abs),
            'why'   => 'not referenced by any application file',
        ];
    }

    // 2. Assets that neither the code nor the data stores mention.
    $adir = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
    if (is_dir($adir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($adir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(__DIR__) + 1));
            if (isset($referenced[strtolower($rel)])) {
                continue;
            }
            if (str_contains($haystack, $f->getFilename())) {
                continue;
            }
            // Videos are content, not clutter. A featured video that is not
            // currently selected would otherwise be listed as "unused" and
            // could be deleted by mistake.
            if (in_array(strtolower($f->getExtension()), VIDEO_EXTS, true)) {
                continue;
            }
            $media[] = [
                'path'  => $rel,
                'bytes' => (int)$f->getSize(),
                'why'   => 'in assets/ but referenced by neither the code nor the database',
            ];
        }
    }

    usort($code,  fn($a, $b) => $b['bytes'] <=> $a['bytes']);
    usort($media, fn($a, $b) => $b['bytes'] <=> $a['bytes']);

    return ['code' => $code, 'media' => $media];
}

/**
 * Delete a caller-supplied list of paths, re-checking each against a fresh scan
 * so a stale form can never remove something that is now in use.
 *
 * @param  list<string> $paths
 * @return array{0:int, 1:int, 2:list<string>}  [deleted, bytesFreed, refused]
 */
function housekeepingDelete(array $paths): array {
    $scan    = housekeepingScan();
    $allowed = [];
    foreach ([...$scan['code'], ...$scan['media']] as $e) {
        $allowed[$e['path']] = true;
    }

    $deleted = 0;
    $freed   = 0;
    $refused = [];

    foreach ($paths as $rel) {
        $rel = str_replace('\\', '/', trim((string)$rel));

        if (!isset($allowed[$rel])) {
            $refused[] = $rel . ' — no longer listed as unused';
            continue;
        }

        $abs = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
        $root = realpath(__DIR__);

        if ($abs === false || $root === false
            || strncasecmp($abs, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0) {
            $refused[] = $rel . ' — outside the application folder';
            continue;
        }

        $size = (int)@filesize($abs);
        if (@unlink($abs)) {
            $deleted++;
            $freed += $size;
        } else {
            $refused[] = $rel . ' — delete failed (permissions?)';
        }
    }

    return [$deleted, $freed, $refused];
}

// ─── Login throttle — persistent, keyed on IP ─────────────────────────────────

/**
 * Brute-force throttle for the admin login.
 *
 * The previous implementation counted failures in $_SESSION. That is not a
 * throttle: the counter lives in the attacker's own session, so clearing the
 * cookie — or simply not sending one — resets it to zero. The lockout could be
 * bypassed indefinitely with unlimited password guesses.
 *
 * State now lives server-side in data/login_throttle.json, keyed on a hash of
 * the client IP, so it survives cookie clearing and is shared across sessions.
 * Entries expire and are pruned on write, so the file cannot grow unbounded.
 */
function throttleFile(): string {
    return DATA_DIR . DIRECTORY_SEPARATOR . 'login_throttle.json';
}

function throttleKey(): string {
    return substr(hash('sha256', 'login|' . getRealIp()), 0, 32);
}

/**
 * @return array{locked:bool, remaining:int, attempts:int}
 */
function throttleStatus(): array {
    $max    = max(1, (int)cfg('admin.login_attempts', 5));
    $window = max(30, (int)cfg('admin.login_lockout', 900));
    $all    = safeReadJson(throttleFile());
    $row    = $all[throttleKey()] ?? null;

    if (!is_array($row)) {
        return ['locked' => false, 'remaining' => 0, 'attempts' => 0];
    }

    $attempts = (int)($row['attempts'] ?? 0);
    $lockedAt = (int)($row['locked_at'] ?? 0);

    if ($lockedAt > 0 && (time() - $lockedAt) < $window) {
        return ['locked' => true, 'remaining' => $window - (time() - $lockedAt), 'attempts' => $attempts];
    }

    // Window elapsed — treat as clean.
    if ($lockedAt > 0) {
        return ['locked' => false, 'remaining' => 0, 'attempts' => 0];
    }

    return ['locked' => $attempts >= $max, 'remaining' => 0, 'attempts' => $attempts];
}

/** Record a failed attempt and lock out once the ceiling is reached. */
function throttleRegisterFailure(): void {
    $max    = max(1, (int)cfg('admin.login_attempts', 5));
    $window = max(30, (int)cfg('admin.login_lockout', 900));
    $file   = throttleFile();
    $key    = throttleKey();
    $all    = safeReadJson($file);

    $row      = is_array($all[$key] ?? null) ? $all[$key] : [];
    $lockedAt = (int)($row['locked_at'] ?? 0);
    $attempts = (int)($row['attempts'] ?? 0);

    if ($lockedAt > 0 && (time() - $lockedAt) >= $window) {
        $attempts = 0;
        $lockedAt = 0;
    }

    $attempts++;
    if ($attempts >= $max && $lockedAt === 0) {
        $lockedAt = time();
    }

    $all[$key] = ['attempts' => $attempts, 'locked_at' => $lockedAt, 'seen' => time()];

    // Prune anything older than twice the window so the file stays small.
    $cutoff = time() - ($window * 2);
    foreach ($all as $k => $v) {
        if (!is_array($v) || (int)($v['seen'] ?? 0) < $cutoff) {
            unset($all[$k]);
        }
    }

    try {
        atomicWriteJson($file, $all);
    } catch (Throwable $e) {
        error_log('throttle write failed: ' . $e->getMessage());
    }
}

/** Clear the throttle for this IP after a successful login. */
function throttleClear(): void {
    $file = throttleFile();
    $all  = safeReadJson($file);
    unset($all[throttleKey()]);
    try {
        atomicWriteJson($file, $all);
    } catch (Throwable $e) {
        error_log('throttle clear failed: ' . $e->getMessage());
    }
}

// ─── Optimistic locking for the JSON stores ───────────────────────────────────

/**
 * A cheap fingerprint of a store's current state.
 *
 * Two admins editing at once previously meant silent data loss: both loaded the
 * same updates.json, both saved, and the second write discarded the first one's
 * record with no warning. The edit forms now carry the fingerprint they loaded,
 * and a save is rejected if the store has moved on since.
 */
function storeFingerprint(string $file): string {
    if (!is_file($file)) {
        return 'absent';
    }
    clearstatcache(true, $file);
    return substr(hash('sha256', (string)filemtime($file) . '|' . (string)filesize($file)), 0, 16);
}

/**
 * Throws when the store changed after the form was rendered.
 *
 * @throws RuntimeException
 */
function assertStoreUnchanged(string $file, ?string $submitted): void {
    if ($submitted === null || $submitted === '') {
        return;   // form predates this check — allow, so an old open tab still works
    }
    $now = storeFingerprint($file);
    if (!hash_equals($now, $submitted)) {
        throw new RuntimeException(
            'Someone else saved a change while this form was open, so nothing was written. '
            . 'Reload the page and re-apply your edit.'
        );
    }
}

// ─── DPDP Act 2023 — consent and retention ────────────────────────────────────

/**
 * Has the visitor granted consent for non-essential (analytics) processing?
 *
 * DPDP requires free, specific, informed, unconditional and unambiguous
 * consent, given by a clear affirmative action, before personal data is
 * processed — and the portal's analytics records online identifiers, which are
 * personal data. So nothing is tracked until this returns true.
 *
 * Consent is held in a first-party cookie rather than the session, so it
 * survives a session expiring and can be withdrawn independently.
 */
function consentCookieName(): string {
    return cfg('site.cookie_prefix', 'fusion') . '_consent';
}

function analyticsConsentGiven(): bool {
    if (!cfg('dpdp.require_consent', true)) {
        return true;   // explicitly disabled for internal builds
    }
    return ($_COOKIE[consentCookieName()] ?? '') === 'analytics';
}

/** True when the visitor has made any choice at all (so hide the banner). */
function consentDecided(): bool {
    $v = $_COOKIE[consentCookieName()] ?? '';
    return $v === 'analytics' || $v === 'essential';
}

/**
 * Record the visitor's choice. "analytics" grants, anything else refuses.
 * Returns the value stored.
 */
function consentStore(string $choice): string {
    $value = $choice === 'analytics' ? 'analytics' : 'essential';
    setcookie(consentCookieName(), $value, [
        'expires'  => time() + (180 * 86400),
        'path'     => '/',
        'secure'   => isHttps(),
        'httponly' => false,   // the page reads it to decide whether to emit events
        'samesite' => 'Lax',
    ]);
    $_COOKIE[consentCookieName()] = $value;
    return $value;
}

/**
 * Purge analytics rows older than the configured retention period.
 *
 * Storage limitation: traffic data is kept only as long as the purpose needs.
 * Called by Optimise and by a lightweight daily tick, so it happens without
 * anyone remembering to do it.
 *
 * @return int rows removed
 */
function analyticsPurgeExpired(): int {
    $days = (int)cfg('dpdp.retention_days', 180);
    if ($days <= 0) {
        return 0;
    }

    $logs = safeReadJson(ANALYTICS_FILE);
    if ($logs === []) {
        return 0;
    }

    $cutoff = time() - ($days * 86400);
    $kept   = [];
    foreach ($logs as $row) {
        $ts = isset($row['timestamp']) ? strtotime((string)$row['timestamp']) : false;
        if ($ts === false || $ts >= $cutoff) {
            $kept[] = $row;
        }
    }

    $removed = count($logs) - count($kept);
    if ($removed > 0) {
        try {
            atomicWriteJson(ANALYTICS_FILE, $kept);
        } catch (Throwable $e) {
            error_log('retention purge failed: ' . $e->getMessage());
            return 0;
        }
    }
    return $removed;
}

/**
 * Run the retention purge at most once a day, cheaply.
 * Safe to call on every public request.
 */
function analyticsRetentionTick(): void {
    $stamp = DATA_DIR . DIRECTORY_SEPARATOR . 'retention_last_run.txt';
    $last  = is_file($stamp) ? (int)trim((string)file_get_contents($stamp)) : 0;
    if ((time() - $last) < 86400) {
        return;
    }
    @file_put_contents($stamp, (string)time(), LOCK_EX);
    analyticsPurgeExpired();
}

/**
 * Erase every analytics row belonging to one visitor id.
 * This is the Right to Erasure in practice — the visitor id is the only handle
 * a data principal can quote, since IPs are stored hashed.
 *
 * @return int rows removed
 */
function analyticsEraseVisitor(string $visitorId): int {
    $visitorId = trim($visitorId);
    if ($visitorId === '') {
        return 0;
    }
    $logs = safeReadJson(ANALYTICS_FILE);
    $kept = array_values(array_filter(
        $logs,
        static fn($r) => (string)($r['visitor_id'] ?? '') !== $visitorId
    ));
    $removed = count($logs) - count($kept);
    if ($removed > 0) {
        atomicWriteJson(ANALYTICS_FILE, $kept);
    }
    return $removed;
}

// ─── SEO — canonical host, meta, structured data ───────────────────────────────

/**
 * The address search engines should index, with a trailing slash.
 * Falls back to the detected base when seo.canonical_base is null.
 */
function canonicalBase(): string {
    $pinned = cfg('seo.canonical_base');
    if (is_string($pinned) && $pinned !== '') {
        return rtrim($pinned, '/') . '/';
    }
    return appBaseUrl();
}

/** Absolute canonical URL for a path relative to the install root. */
function canonicalUrl(string $relative = ''): string {
    return canonicalBase() . ltrim($relative, '/');
}

/**
 * Is the host serving this request the canonical one?
 *
 * When it is not, the page still works but must not be indexed, or the two
 * hosts compete as duplicate content and Google chooses a canonical for you.
 */
function isCanonicalHost(): bool {
    $a = parse_url(canonicalBase(), PHP_URL_HOST);
    $b = parse_url(appBaseUrl(),    PHP_URL_HOST);
    return $a === null || $b === null || strcasecmp((string)$a, (string)$b) === 0;
}

/**
 * The robots directive for this request.
 * Non-canonical hosts and non-indexable deployments get noindex, follow —
 * "follow" so link equity still flows to the canonical copy.
 */
function robotsDirective(): string {
    if (!cfg('seo.indexable', true) || !isCanonicalHost()) {
        return 'noindex, follow';
    }
    return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
}

/**
 * Emit the full head block: robots, canonical, Open Graph, Twitter, icons.
 *
 * @param array{title:string,description:string,path:string,image?:string,type?:string} $meta
 */
function seoHead(array $meta): string {
    $e      = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $title  = (string)($meta['title'] ?? '');
    $desc   = (string)($meta['description'] ?? '');
    $path   = (string)($meta['path'] ?? '');
    $canon  = canonicalUrl($path);
    $type   = (string)($meta['type'] ?? 'website');
    $site   = (string)cfg('brand.company', '');
    $lang   = AppLocale::lang();

    // Prefer the generated card: it carries the latest milestone date, so a
    // share preview is never stale. Falls back to the configured artwork.
    $imgRel = (string)($meta['image'] ?? '');
    if ($imgRel === '') {
        $imgRel = is_file(__DIR__ . '/assets/og/og-home.png')
            ? 'assets/og/og-home.png'
            : (string)cfg('seo.og_image', 'assets/og.jpg');
    }
    $image  = preg_match('#^https?://#i', $imgRel) ? $imgRel : canonicalUrl(assetPath($imgRel));

    $out  = '<meta name="robots" content="' . $e(robotsDirective()) . '">' . "\n";
    $out .= '    <meta name="description" content="' . $e($desc) . '">' . "\n";
    $out .= '    <link rel="canonical" href="' . $e($canon) . '">' . "\n";
    $out .= '    <link rel="alternate" hreflang="' . $e($lang) . '" href="' . $e($canon) . '">' . "\n";
    $out .= '    <link rel="alternate" hreflang="x-default" href="' . $e($canon) . '">' . "\n";

    // Open Graph
    $out .= '    <meta property="og:type" content="' . $e($type) . '">' . "\n";
    $out .= '    <meta property="og:site_name" content="' . $e($site) . '">' . "\n";
    $out .= '    <meta property="og:title" content="' . $e($title) . '">' . "\n";
    $out .= '    <meta property="og:description" content="' . $e($desc) . '">' . "\n";
    $out .= '    <meta property="og:url" content="' . $e($canon) . '">' . "\n";
    $out .= '    <meta property="og:image" content="' . $e($image) . '">' . "\n";
    $out .= '    <meta property="og:image:secure_url" content="' . $e($image) . '">' . "\n";
    $out .= '    <meta property="og:image:type" content="image/' . ($e(pathinfo($image, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpeg') . '">' . "\n";
    $out .= '    <meta property="og:image:width" content="1200">' . "\n";
    $out .= '    <meta property="og:image:height" content="630">' . "\n";
    $out .= '    <meta property="og:image:alt" content="' . $e($title) . '">' . "\n";
    $out .= '    <meta property="og:locale" content="' . $e(str_replace('-', '_', $lang)) . '">' . "\n";

    // Twitter / X
    $out .= '    <meta name="twitter:card" content="summary_large_image">' . "\n";
    $out .= '    <meta name="twitter:title" content="' . $e($title) . '">' . "\n";
    $out .= '    <meta name="twitter:description" content="' . $e($desc) . '">' . "\n";
    $out .= '    <meta name="twitter:image" content="' . $e($image) . '">' . "\n";
    $out .= '    <meta name="twitter:image:alt" content="' . $e($title) . '">' . "\n";
    if ($tw = (string)cfg('seo.twitter_site', '')) {
        $out .= '    <meta name="twitter:site" content="' . $e($tw) . '">' . "\n";
    }

    // Icons, theme, PWA
    $out .= '    <link rel="icon" href="' . $e(assetPath('assets/favicon.ico')) . '" sizes="any">' . "\n";
    $out .= '    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#0f6cbd">' . "\n";
    $out .= '    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#292929">' . "\n";
    $out .= '    <meta name="format-detection" content="telephone=no">' . "\n";
    $out .= '    <meta name="author" content="' . $e($site) . '">' . "\n";
    $out .= '    <meta name="generator" content="Construction Progress Portal ' . $e(STATUS_RELEASE_SAFE()) . '">';

    return $out;
}

/** Release string, tolerant of version.php not being loaded. */
function STATUS_RELEASE_SAFE(): string {
    return defined('STATUS_RELEASE') ? (string)constant('STATUS_RELEASE') : '';
}

/**
 * JSON-LD graph. Written for AI answer engines as much as for Google: the
 * entities are explicit and linked by @id, so a retrieval model can state who
 * published the data, when, and for which tower, and cite it.
 *
 * @param list<array<string,mixed>> $updates already sorted, newest first
 */
function seoJsonLd(array $projects, array $components, array $updates, array $compMap, array $projMap): string {
    $base    = canonicalBase();
    $company = (string)cfg('brand.company', '');
    $graph   = [];

    $graph[] = [
        '@type'  => 'Organization',
        '@id'    => $base . '#organization',
        'name'   => $company,
        'url'    => (string)cfg('brand.company_url', $base),
        'logo'   => [
            '@type' => 'ImageObject',
            'url'   => canonicalUrl(assetPath('assets/ag-image.png')),
        ],
        'address' => [
            '@type'           => 'PostalAddress',
            'addressLocality' => (string)cfg('brand.locality', ''),
            'addressCountry'  => 'IN',
        ],
    ];

    $graph[] = [
        '@type'      => 'WebSite',
        '@id'        => $base . '#website',
        'url'        => $base,
        'name'       => $company . ' — Construction Progress Dashboard',
        'inLanguage' => AppLocale::lang(),
        'publisher'  => ['@id' => $base . '#organization'],
    ];

    $graph[] = [
        '@type'           => 'BreadcrumbList',
        '@id'             => $base . '#breadcrumbs',
        'itemListElement' => [[
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => 'Construction Progress',
            'item'     => $base,
        ]],
    ];

    // Each project as an ApartmentComplex, with its RERA registration stated.
    foreach ($projects as $p) {
        $pid = (string)($p['id'] ?? '');
        if ($pid === '') { continue; }
        $node = [
            '@type' => 'ApartmentComplex',
            '@id'   => $base . '#project-' . $pid,
            'name'  => (string)($p['name'] ?? ''),
            'url'   => $base,
        ];
        if (!empty($p['rera'])) {
            $node['identifier'] = [
                '@type'                => 'PropertyValue',
                'propertyID'           => 'RERA',
                'value'                => (string)$p['rera'],
                'additionalProperty'   => ['@type' => 'PropertyValue', 'name' => 'regulator', 'value' => 'UP RERA'],
            ];
        }
        if (!empty($p['logo'])) {
            $node['image'] = canonicalUrl(assetPath((string)$p['logo']));
        }
        $graph[] = $node;
    }

    // The updates themselves, newest first and capped: an AI answer needs the
    // latest few milestones, not the whole archive.
    $items = [];
    $pos   = 0;
    foreach (array_slice($updates, 0, 25) as $u) {
        $c    = $compMap[$u['component_id'] ?? ''] ?? null;
        $pname = $c ? (string)($projMap[$c['project_id']]['name'] ?? '') : '';
        $job   = $c ? (string)($c['job'] ?? '') : (string)($u['legacy_title'] ?? 'Update');
        $dt    = AppLocale::parse((string)($u['date'] ?? ''));
        $text  = trim(preg_replace('/\s+/', ' ', strip_tags((string)($u['description'] ?? ''))) ?? '');

        $article = [
            '@type'            => 'Article',
            'headline'         => trim(($pname !== '' ? $pname . ' — ' : '') . $job . ' construction update'),
            'articleSection'   => $pname,
            'about'            => $job,
            'inLanguage'       => AppLocale::lang(),
            'publisher'        => ['@id' => $base . '#organization'],
            'isPartOf'         => ['@id' => $base . '#website'],
        ];
        if ($dt) {
            $article['datePublished'] = $dt->format('Y-m-d');
            $article['dateModified']  = $dt->format('Y-m-d');
        }
        if ($text !== '') {
            $article['description'] = mb_substr($text, 0, 300);
        }
        $imgs = [];
        foreach (array_slice((array)($u['images'] ?? []), 0, 4) as $img) {
            $imgs[] = canonicalUrl(assetPath((string)$img));
        }
        if ($imgs) { $article['image'] = $imgs; }

        $items[] = ['@type' => 'ListItem', 'position' => ++$pos, 'item' => $article];
    }

    if ($items) {
        $graph[] = [
            '@type'           => 'ItemList',
            '@id'             => $base . '#updates',
            'name'            => 'Construction progress updates',
            'itemListOrder'   => 'https://schema.org/ItemListOrderDescending',
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ];
    }

    return json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?: '{}';
}

/** The shared developer footer, matching the resource centre's convention. */
function developerFooter(): string {
    $e    = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $dev  = (string)cfg('footer.developer', '');
    $url  = (string)cfg('footer.developer_url', '');
    $bits = [];

    $bits[] = '<a href="' . $e(canonicalUrl('privacy.php')) . '">Privacy</a>';
    if ($mail = (string)cfg('footer.support_email', '')) {
        $bits[] = '<a href="mailto:' . $e($mail) . '">Support</a>';
    }
    if (cfg('footer.show_version', true) && defined('APP_VERSION')) {
        $bits[] = '<span class="dev-footer-version">' . $e(statusVersionLabel()) . '</span>';
    }
    if ($dev !== '') {
        $credit = $url !== ''
            ? '<a href="' . $e($url) . '" rel="noopener">' . $e($dev) . '</a>'
            : $e($dev);
        $bits[] = '<span>Powered by ' . $credit . '</span>';
    }

    return '<p class="dev-footer">' . implode('<span class="dev-footer-sep" aria-hidden="true">·</span>', $bits) . '</p>';
}

// ─── Open Graph image generation ───────────────────────────────────────────────

/**
 * Locate a bold TrueType face for OG text.
 *
 * Windows/IIS hosts almost always have Segoe UI, which is also the portal's
 * Fluent typeface, so the generated card matches the site. Returns null when no
 * TTF is available and the caller falls back to GD's bitmap font.
 */
function ogFontPath(): ?string {
    $candidates = [
        __DIR__ . '/assets/fonts/og.ttf',
        'C:/Windows/Fonts/seguisb.ttf',   // Segoe UI Semibold
        'C:/Windows/Fonts/segoeuib.ttf',  // Segoe UI Bold
        'C:/Windows/Fonts/arialbd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    ];
    foreach ($candidates as $f) {
        if (is_file($f) && is_readable($f)) {
            return $f;
        }
    }
    return null;
}

/**
 * Render a 1200x630 Open Graph card.
 *
 * Deliberately text-forward rather than a photograph with an overlay. Social
 * previews are read at thumbnail size, and AI answer engines increasingly read
 * the image itself — so the project name, the latest milestone date and the
 * RERA number are set large, in high contrast, as the primary content. The
 * same facts are in the JSON-LD, so image and markup agree.
 *
 * @return bool true when written
 */
function ogRenderCard(string $destAbs, string $heading, string $subheading, string $footnote): bool {
    if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
        return false;
    }

    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    if (!$im) { return false; }

    // Fluent surfaces: near-white card on the brand ramp, with a brand bar.
    $bg    = imagecolorallocate($im, 255, 255, 255);
    $ink   = imagecolorallocate($im, 36, 36, 36);
    $ink2  = imagecolorallocate($im, 66, 66, 66);
    $ink3  = imagecolorallocate($im, 97, 97, 97);
    $brand = imagecolorallocate($im, 15, 108, 189);
    $rule  = imagecolorallocate($im, 224, 224, 224);

    imagefilledrectangle($im, 0, 0, $W, $H, $bg);
    imagefilledrectangle($im, 0, 0, $W, 14, $brand);           // top brand bar
    imagefilledrectangle($im, 72, $H - 118, $W - 72, $H - 117, $rule);

    $font = ogFontPath();

    if ($font !== null) {
        // Wrap the heading to the card width.
        $wrap = static function (string $text, int $size, int $maxW) use ($font): array {
            $words = preg_split('/\s+/', trim($text)) ?: [];
            $lines = []; $cur = '';
            foreach ($words as $w) {
                $try = $cur === '' ? $w : $cur . ' ' . $w;
                $box = imagettfbbox($size, 0, $font, $try);
                if ($box && ($box[2] - $box[0]) > $maxW && $cur !== '') {
                    $lines[] = $cur; $cur = $w;
                } else {
                    $cur = $try;
                }
            }
            if ($cur !== '') { $lines[] = $cur; }
            return array_slice($lines, 0, 3);
        };

        imagettftext($im, 22, 0, 72, 108, $brand, $font, mb_strtoupper($subheading));

        $y = 210;
        foreach ($wrap($heading, 58, $W - 144) as $line) {
            imagettftext($im, 58, 0, 72, $y, $ink, $font, $line);
            $y += 78;
        }

        imagettftext($im, 24, 0, 72, $H - 70, $ink2, $font, $footnote);
        imagettftext($im, 18, 0, 72, $H - 32, $ink3, $font, (string)cfg('brand.company', ''));
    } else {
        // No TTF: GD's bitmap font, rendered small then scaled so it stays
        // legible at preview size. Lower quality, but never a blank card.
        $tmp = imagecreatetruecolor(400, 210);
        $tbg = imagecolorallocate($tmp, 255, 255, 255);
        $tin = imagecolorallocate($tmp, 36, 36, 36);
        $tbr = imagecolorallocate($tmp, 15, 108, 189);
        imagefilledrectangle($tmp, 0, 0, 400, 210, $tbg);
        imagestring($tmp, 5, 24, 28, substr(strtoupper($subheading), 0, 46), $tbr);
        imagestring($tmp, 5, 24, 70, substr($heading, 0, 46), $tin);
        imagestring($tmp, 5, 24, 92, substr($heading, 46, 46), $tin);
        imagestring($tmp, 5, 24, 150, substr($footnote, 0, 46), $tin);
        imagecopyresampled($im, $tmp, 72, 150, 0, 0, $W - 144, 330, 400, 210);
        imagedestroy($tmp);
    }

    $dir = dirname($destAbs);
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    $ok = imagepng($im, $destAbs, 6);
    imagedestroy($im);
    return (bool)$ok;
}

/**
 * Build the social cards. Returns the number written.
 * Called by Optimise so the date on the card follows the newest update.
 */
function ogBuildImages(): int {
    $projects   = safeReadJson(PROJ_FILE);
    $components = safeReadJson(COMP_FILE);
    $updates    = safeReadJson(UPD_FILE);
    [$projMap, $compMap] = buildMaps($projects, $components);
    sortUpdates($updates, $compMap, $projMap);

    $newest = '';
    foreach ($updates as $u) {
        $d = AppLocale::parse((string)($u['date'] ?? ''));
        if ($d) { $newest = $d->format((string)cfg('locale.date_display', 'd M Y')); break; }
    }

    $names = [];
    foreach ($projects as $p) {
        if (!empty($p['name'])) { $names[] = (string)$p['name']; }
    }

    $heading = (string)cfg('brand.project', '') ?: implode(' · ', array_slice($names, 0, 2));
    $foot    = $newest !== '' ? 'Latest site update ' . $newest : 'Official construction progress';
    $reras   = [];
    foreach ($projects as $p) {
        if (!empty($p['rera'])) { $reras[] = (string)$p['rera']; }
    }
    if ($reras) {
        $foot .= '   ·   RERA ' . implode(', ', array_slice($reras, 0, 2));
    }

    $written = 0;
    if (ogRenderCard(__DIR__ . '/assets/og/og-home.png', $heading, 'Construction Progress Dashboard', $foot)) {
        $written++;
    }
    return $written;
}

// ─── Sorting ──────────────────────────────────────────────────────────────────

/**
 * Parse a date string in the format "dd-Mon-YYYY" (e.g. "15-Jan-2024") safely.
 */
function parseUpdateDate(string $date): int {
    $dt = DateTime::createFromFormat('d-M-Y', $date);
    return $dt ? (int)$dt->format('U') : 0;
}

/**
 * Canonical multi-key sort for updates: non-legacy first, then by project → type → job → date desc.
 */
function sortUpdates(array &$updates, array $compMap, array $projMap): void {
    usort($updates, function (array $a, array $b) use ($compMap, $projMap): int {
        $legA = ($a['component_id'] ?? '') === 'legacy';
        $legB = ($b['component_id'] ?? '') === 'legacy';
        
        if ($legA !== $legB) return $legA ? 1 : -1;
        if ($legA && $legB)  return parseUpdateDate($b['date'] ?? '') <=> parseUpdateDate($a['date'] ?? '');

        $cA = $compMap[$a['component_id']] ?? null;
        $cB = $compMap[$b['component_id']] ?? null;

        $pA = $cA ? ($projMap[$cA['project_id']]['name'] ?? '') : '';
        $pB = $cB ? ($projMap[$cB['project_id']]['name'] ?? '') : '';
        
        if (($r = strcasecmp($pA, $pB)) !== 0) return $r;
        if (($r = strcasecmp($cA['type'] ?? '', $cB['type'] ?? '')) !== 0) return $r;
        if (($r = strcasecmp($cA['job']  ?? '', $cB['job']  ?? '')) !== 0) return $r;

        return parseUpdateDate($b['date'] ?? '') <=> parseUpdateDate($a['date'] ?? '');
    });
}

/**
 * Build fast-lookup maps from flat arrays to avoid O(n^2) loops during rendering.
 */
function buildMaps(array $projects, array $components): array {
    $projMap = [];
    $compMap = [];
    
    foreach ($projects as $p) {
        $projMap[(string)$p['id']] = $p;
    }
    
    foreach ($components as $c) {
        $compMap[(string)$c['id']] = $c;
    }
    
    return [$projMap, $compMap];
}