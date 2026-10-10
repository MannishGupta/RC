<?php
/**
 * Version: 260926.25
 * Multi-tenant bootstrap — domain / subdomain wildcard → DATA_PATH
 *
 * Load BEFORE defining DATA_PATH (or it sets DATA_PATH for you).
 *
 * Layout:
 *   tenants/{tenantId}/data/     ← writable JSON, media, sessions…
 *   tenants/{tenantId}/config.json
 *   tenants/map.json             ← optional host overrides
 *
 * Wildcard examples (second label of rc.{tenant}.tld):
 *   rc.fusionlimited.in  → fusionlimited
 *   rc.arthsathi.com     → arthsathi
 *   rc.digisofts.com     → digisofts
 *   rc.simcoauto.com    → simcoauto  (no external redirect)
 * Also: {tenant}.rc.example.com → tenant from first label
 *
 * Single-site installs without tenants/ keep using BASE_PATH/data.
 */
declare(strict_types=1);

if (is_file(__DIR__ . '/TenantTombstone.php')) {
    require_once __DIR__ . '/TenantTombstone.php';
}

if (defined('TENANT_BOOTSTRAPPED')) {
    return;
}
define('TENANT_BOOTSTRAPPED', true);
@date_default_timezone_set('Asia/Kolkata');

if (!defined('BASE_PATH')) {
    define('BASE_PATH', str_replace('\\', '/', dirname(__DIR__)));
}

/**
 * Resolve HTTP host → tenant id.
 * Never issues HTTP redirects — the public host stays whatever the client requested.
 */
function rc_tenant_resolve_host_raw(): string
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost')));
    if (str_contains($host, ':')) {
        $host = explode(':', $host, 2)[0];
    }
    return $host;
}

function rc_tenant_resolve_host(): string
{
    // Prefer the host the client actually used (address bar). Ignore X-Forwarded-Host
    // unless explicitly trusted, to avoid cross-tenant confusion on shared edges.
    $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = strtolower(trim($host));
    // strip port
    if (str_contains($host, ':')) {
        $host = explode(':', $host, 2)[0];
    }
    $host = rtrim($host, '.');
    // strip accidental trailing path fragments some proxies inject
    $host = preg_replace('/[^a-z0-9.\-]/', '', $host) ?? $host;

    // Optional map file (exact + extra rules)
    $mapFile = BASE_PATH . '/tenants/map.json';
    $map = [];
    if (is_readable($mapFile)) {
        $decoded = json_decode((string)file_get_contents($mapFile), true);
        if (is_array($decoded)) {
            $map = $decoded;
        }
    }

    // 1) Exact host match (rc.simcoauto.com → simcoauto, etc.)
    $exact = $map['exact'] ?? [];
    if (is_array($exact) && isset($exact[$host]) && is_string($exact[$host]) && $exact[$host] !== '') {
        return rc_tenant_sanitize_id($exact[$host]);
    }
    // www. prefix fallback to apex exact
    if (str_starts_with($host, 'www.')) {
        $apex = substr($host, 4);
        if (isset($exact[$apex]) && is_string($exact[$apex]) && $exact[$apex] !== '') {
            return rc_tenant_sanitize_id($exact[$apex]);
        }
    }

    // 2) Custom regex rules from map.json
    $rules = $map['rules'] ?? [];
    if (is_array($rules)) {
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $pattern = (string)($rule['match'] ?? '');
            $group = (int)($rule['group'] ?? 1);
            if ($pattern === '') {
                continue;
            }
            if (@preg_match($pattern, $host, $m) && !empty($m[$group])) {
                return rc_tenant_sanitize_id((string)$m[$group]);
            }
        }
    }

    // 3) Built-in wildcards: rc.{tenant}.tld → tenant
    if (preg_match('/^rc\.([a-z0-9][a-z0-9-]{0,62})\./i', $host, $m)) {
        return rc_tenant_sanitize_id((string)$m[1]);
    }
    // {tenant}.rc.tld
    if (preg_match('/^([a-z0-9][a-z0-9-]{0,62})\.rc\./i', $host, $m)) {
        return rc_tenant_sanitize_id((string)$m[1]);
    }
    // {tenant}.localhost for local dev
    if (preg_match('/^([a-z0-9][a-z0-9-]{0,62})\.localhost$/i', $host, $m)) {
        return rc_tenant_sanitize_id((string)$m[1]);
    }

    $fallback = (string)($map['fallback'] ?? 'default');
    return rc_tenant_sanitize_id($fallback !== '' ? $fallback : 'default');
}


function rc_tenant_sanitize_id(string $id): string
{
    $id = strtolower(trim($id));
    $id = preg_replace('/[^a-z0-9_-]+/', '', $id) ?? '';
    if ($id === '' || $id === '.' || $id === '..') {
        return 'default';
    }
    return substr($id, 0, 64);
}

/**
 * Ensure tenant data directories exist; migrate legacy BASE_PATH/data on first run for default tenant.
 */
function rc_tenant_ensure_dirs(string $tenantRoot, string $dataPath): void
{
    $dirs = [
        $tenantRoot,
        $dataPath,
        $dataPath . '/media/images',
        $dataPath . '/media/documents',
        $dataPath . '/sessions',
        $dataPath . '/logs',
        $dataPath . '/backups',
        $dataPath . '/runners',
        $dataPath . '/value',
        $dataPath . '/dispatch',
        $dataPath . '/janam',
        $dataPath . '/config',
        $dataPath . '/cache',
    ];
    foreach ($dirs as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
    }
}

/**
 * Define TENANT_* and DATA_PATH family constants.
 * Safe to call only once; subsequent requires are no-ops via TENANT_BOOTSTRAPPED.
 */
function rc_tenant_boot(): void
{
    if (defined('DATA_PATH') && defined('TENANT_ID')) {
        return;
    }

    $host = rc_tenant_resolve_host_raw();
    $mapFile = BASE_PATH . '/tenants/map.json';
    $map = is_file($mapFile) ? (json_decode((string)@file_get_contents($mapFile), true) ?: []) : [];
    $exact = is_array($map['exact'] ?? null) ? $map['exact'] : [];
    $strict = !empty($map['strict_hosts']);

    if ($strict) {
        $h = strtolower(trim($host));
        if (str_contains($h, ':')) {
            $h = explode(':', $h, 2)[0];
        }
        $apex = preg_replace('/^www\./', '', $h) ?? $h;
        if (!isset($exact[$h]) && !isset($exact[$apex]) && !isset($exact['www.' . $apex])) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Robots-Tag: noindex');
            echo 'Host not authorised for this Resource Centre (strict_hosts).';
            exit;
        }
    }

    $tenantId = rc_tenant_resolve_host();
    $knownIds = array_values(array_unique(array_map('strval', array_values($exact))));
    $tenantRoot = BASE_PATH . '/tenants/' . $tenantId;
    // Do not auto-provision unknown tenant trees (Host-header junk)
    if (!is_dir($tenantRoot) && $tenantId !== 'default' && !in_array($tenantId, $knownIds, true)) {
        $tenantId = rc_tenant_sanitize_id((string)($map['fallback'] ?? 'default'));
        $tenantRoot = BASE_PATH . '/tenants/' . $tenantId;
    }
    $tenantData = $tenantRoot . '/data';

    // Prefer dedicated tenant data when folder exists OR multi-tenant mode is on
    $multi = is_dir(BASE_PATH . '/tenants') || is_file(BASE_PATH . '/tenants/map.json');
    $legacyData = BASE_PATH . '/data';

    if ($multi) {
        // Use tenant path; seed from legacy only for fallback tenant if empty
        if (!is_dir($tenantData) && is_dir($legacyData) && $tenantId === 'default') {
            // Keep serving legacy until admin migrates
            $dataPath = $legacyData;
        } else {
            rc_tenant_ensure_dirs($tenantRoot, $tenantData);
            $dataPath = $tenantData;
            // One-time soft seed: if team.json missing and legacy has it, copy JSON files only
            if (!is_file($dataPath . '/team.json') && is_file($legacyData . '/team.json')) {
                rc_tenant_seed_from_legacy($legacyData, $dataPath);
            }
        }
    } else {
        // Classic single-tenant
        $dataPath = $legacyData;
        if (!is_dir($dataPath)) {
            @mkdir($dataPath, 0775, true);
        }
    }

    if (!defined('TENANT_ID')) {
        if (class_exists('TenantTombstone') && TenantTombstone::isDeleted((string)$tenantId) && $tenantId !== 'default') {
    $tenantId = 'default';
}
define('TENANT_ID', $tenantId);
    }
    if (!defined('TENANT_ROOT')) {
        define('TENANT_ROOT', $tenantRoot);
    }
    if (!defined('DATA_PATH')) {
        define('DATA_PATH', $dataPath);
    }

    // Dependent paths
    if (!defined('IMG_PATH')) {
        define('IMG_PATH', DATA_PATH . '/media/images');
    }
    if (!defined('DOC_PATH')) {
        define('DOC_PATH', DATA_PATH . '/media/documents');
    }
    if (!defined('SESSION_PATH')) {
        define('SESSION_PATH', DATA_PATH . '/sessions');
    }
    if (!defined('LOG_PATH')) {
        define('LOG_PATH', DATA_PATH . '/logs');
    }
    if (!defined('BACKUP_PATH')) {
        define('BACKUP_PATH', DATA_PATH . '/backups');
    }
    if (!defined('DISPATCH_DATA_PATH')) {
        define('DISPATCH_DATA_PATH', DATA_PATH . '/dispatch');
    }
    if (!defined('JANAM_DATA_PATH')) {
        define('JANAM_DATA_PATH', DATA_PATH . '/janam');
    }
    if (!defined('VALUE_DATA_PATH')) {
        define('VALUE_DATA_PATH', DATA_PATH . '/value');
    }
    if (!defined('RUNNERS_DATA_PATH')) {
        define('RUNNERS_DATA_PATH', DATA_PATH . '/runners');
    }
    if (!defined('CONFIG_DATA_PATH')) {
        define('CONFIG_DATA_PATH', DATA_PATH . '/config');
    }
    if (!defined('TENANT_CONFIG_FILE')) {
        define('TENANT_CONFIG_FILE', TENANT_ROOT . '/config.json');
    }

    foreach ([
        DATA_PATH, IMG_PATH, DOC_PATH, SESSION_PATH, LOG_PATH, BACKUP_PATH,
        DISPATCH_DATA_PATH, JANAM_DATA_PATH, VALUE_DATA_PATH, RUNNERS_DATA_PATH, CONFIG_DATA_PATH,
    ] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0775);
        }
    }
    if (function_exists('rc_storage_self_heal')) {
        rc_storage_self_heal(DATA_PATH);
    }
    $__ts = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/app/TenantStorage.php';
    $__md = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/app/MasterDirectory.php';
    if (is_file($__md)) require_once $__md;
    if (is_file($__ts)) { require_once $__ts; if (class_exists('TenantStorage')) TenantStorage::ensureBaseline(DATA_PATH); }
    $__mr = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/app/ModuleRegistry.php';
    if (is_file($__mr)) { require_once $__mr; }


    // Session path is applied in AppAuth::initSession (with writability fallback).
}



/**
 * Runtime self-healing: ensure required dirs + seed JSON exist (0775).
 * Silent — no migration UI prompts.
 */

/**
 * Lightweight per-tenant analytics (visits / share / print).
 */
function rc_analytics_path(): string
{
    $base = defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data');
    return rtrim($base, '/\\') . '/analytics.json';
}

function rc_analytics_read(): array
{
    $def = ['visits' => 0, 'share_clicks' => 0, 'print_clicks' => 0, 'last_visit_at' => null, 'daily_visits' => []];
    $p = rc_analytics_path();
    if (!is_file($p)) {
        return $def;
    }
    $j = json_decode((string)@file_get_contents($p), true);
    return is_array($j) ? array_merge($def, $j) : $def;
}

function rc_analytics_write(array $data): void
{
    $p = rc_analytics_path();
    $dir = dirname($p);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(
        $p,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

/** Once per PHP session: count a visit */
function rc_analytics_touch_visit(): void
{
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['rc_visit_counted'])) {
        return;
    }
    $a = rc_analytics_read();
    $a['visits'] = (int)($a['visits'] ?? 0) + 1;
    $a['last_visit_at'] = date('c');
    $day = date('Y-m-d'); // Asia/Kolkata set globally
    if (!isset($a['daily_visits']) || !is_array($a['daily_visits'])) {
        $a['daily_visits'] = [];
    }
    $a['daily_visits'][$day] = (int)($a['daily_visits'][$day] ?? 0) + 1;
    // Keep ~90 days of daily series
    if (count($a['daily_visits']) > 90) {
        ksort($a['daily_visits']);
        $a['daily_visits'] = array_slice($a['daily_visits'], -90, null, true);
    }
    rc_analytics_write($a);
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['rc_visit_counted'] = 1;
    }
}

function rc_analytics_inc(string $key): void
{
    $a = rc_analytics_read();
    if (!isset($a[$key])) {
        $a[$key] = 0;
    }
    $a[$key] = (int)$a[$key] + 1;
    rc_analytics_write($a);
}

function rc_storage_self_heal(string $dataPath): void
{
    $dirs = [
        $dataPath,
        $dataPath . '/media',
        $dataPath . '/media/images',
        $dataPath . '/media/documents',
        $dataPath . '/sessions',
        $dataPath . '/logs',
        $dataPath . '/backups',
        $dataPath . '/runners',
        $dataPath . '/dispatch',
        $dataPath . '/janam',
        $dataPath . '/value',
        $dataPath . '/config',
        $dataPath . '/analytics',
    ];
    // Legacy-friendly aliases under data/
    $dirs[] = $dataPath . '/images';
    $dirs[] = $dataPath . '/docs';
    foreach ($dirs as $d) {
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        if (is_dir($d) && !is_writable($d)) {
            @chmod($d, 0775);
        }
    }

    $jsonDefaults = [
        'policy_settings.json' => [
            'master_tracking_email' => '',
            'working_hours' => ['start' => '09:00', 'end' => '19:00', 'timezone' => 'Asia/Kolkata'],
            'mandatory_designations' => [],
            'mandatory_departments' => [],
            'heartbeat_timeout_minutes' => 10,
        ],
        'runners_state.json' => [],
        'location_violations_log.json' => [],
        'analytics.json' => [
            'visits' => 0,
            'share_clicks' => 0,
            'print_clicks' => 0,
            'last_visit_at' => null,
        ],
    ];
    foreach ($jsonDefaults as $file => $default) {
        $path = $dataPath . '/' . $file;
        if (!is_file($path)) {
            @file_put_contents(
                $path,
                json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
            @chmod($path, 0664);
        }
    }
}

/**
 * Copy top-level JSON + common subfolders from legacy data/ into tenant data/ (non-destructive).
 */
function rc_tenant_seed_from_legacy(string $from, string $to): void
{
    if (!is_dir($from) || !is_dir($to)) {
        return;
    }
    foreach (glob($from . '/*.json') ?: [] as $f) {
        $dest = $to . '/' . basename($f);
        if (!is_file($dest)) {
            @copy($f, $dest);
        }
    }
}

/** Load optional tenants/{id}/config.json */
function rc_tenant_config(): array
{
    if (!defined('TENANT_CONFIG_FILE') || !is_readable(TENANT_CONFIG_FILE)) {
        return [];
    }
    $j = json_decode((string)file_get_contents(TENANT_CONFIG_FILE), true);
    return is_array($j) ? $j : [];
}

// Auto-boot when included
rc_tenant_boot();
