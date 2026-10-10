<?php
declare(strict_types=1);
/**
 * Value Pack API — attendance, visits, assets, expiry, audit, backup meta, analytics.
 * POST/GET action via index-compatible session. Include from index or call standalone.
 * Version: 20260929.20
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', str_replace('\\', '/', dirname(__FILE__)));
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (is_file(BASE_PATH . '/app/bootstrap.php')) {
    require_once BASE_PATH . '/app/bootstrap.php';
}
require_once BASE_PATH . '/app/ValueStore.php';
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}


header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$currentUser = $_SESSION['user'] ?? null;
$isAdmin = ($currentUser === 'admin');
$loggedIn = ($isAdmin || $currentUser === 'crm' || !empty($currentUser));

$input = $_POST;
if (empty($input) && str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'json')) {
    $raw = file_get_contents('php://input');
    $j = json_decode((string)$raw, true);
    if (is_array($j)) {
        $input = $j;
    }
}
$action = (string)($input['action'] ?? $_GET['action'] ?? '');

function vp_json($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function vp_require_auth(bool $loggedIn): void
{
    if (!$loggedIn) {
        vp_json(['status' => 'error', 'message' => 'Authentication required'], 401);
    }
}

function vp_id(string $prefix): string
{
    return $prefix . '-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
}

// Public endpoints (no auth) — ONLY explicitly public locations (no "all" fallback)
if ($action === 'public_locations') {
    $locs = [];
    $dataRoot = defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data');
    $path = $dataRoot . '/locations.json';
    if (!is_file($path)) {
        $path = $dataRoot . '/location.json';
    }
    // Prefer AppDB when available (tenant-correct path)
    $raw = null;
    if (class_exists('AppDB') && method_exists('AppDB', 'read')) {
        $try = AppDB::read('locations');
        if (is_array($try)) {
            $raw = $try;
        }
    }
    if ($raw === null && is_file($path)) {
        $decoded = json_decode((string)@file_get_contents($path), true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    foreach ($raw as $L) {
        if (!is_array($L)) {
            continue;
        }
        // Strict: only rows explicitly flagged public
        $isPublic = !empty($L['public']) || !empty($L['show_public']) || !empty($L['is_public']);
        if (!$isPublic) {
            continue;
        }
        $locs[] = [
            'name' => htmlspecialchars(strip_tags((string)($L['name'] ?? $L['title'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'address' => htmlspecialchars(strip_tags((string)($L['address'] ?? $L['full_address'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'lat' => isset($L['lat']) ? (float)$L['lat'] : (isset($L['latitude']) ? (float)$L['latitude'] : null),
            'lng' => isset($L['lng']) ? (float)$L['lng'] : (isset($L['longitude']) ? (float)$L['longitude'] : null),
            // Phone omitted from public payload by default (privacy)
        ];
    }
    vp_json(['status' => 'ok', 'locations' => $locs]);
}

if ($action === 'card_hit') {
    $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($input['slug'] ?? $_GET['slug'] ?? ''));
    if ($slug === '') {
        vp_json(['status' => 'error', 'message' => 'slug required'], 400);
    }
    // Verify slug exists in team directory (prevent arbitrary analytics inflation)
    $exists = false;
    if (class_exists('AppDB') && method_exists('AppDB', 'read')) {
        $team = AppDB::read('team');
        if (is_array($team)) {
            foreach ($team as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $s = (string)($m['slug'] ?? $m['id'] ?? '');
                if ($s !== '' && strcasecmp($s, $slug) === 0) {
                    $exists = true;
                    break;
                }
            }
        }
    }
    if (!$exists) {
        $teamPath = (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/team.json';
        if (is_file($teamPath)) {
            $team = json_decode((string)@file_get_contents($teamPath), true);
            if (is_array($team)) {
                foreach ($team as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    $s = (string)($m['slug'] ?? $m['id'] ?? '');
                    if ($s !== '' && strcasecmp($s, $slug) === 0) {
                        $exists = true;
                        break;
                    }
                }
            }
        }
    }
    if (!$exists) {
        vp_json(['status' => 'error', 'message' => 'unknown slug'], 404);
    }
    // Light rate limit: same slug from same session at most once per 10s
    $rlKey = '_card_hit_' . $slug;
    $now = time();
    $last = (int)($_SESSION[$rlKey] ?? 0);
    if ($last > 0 && ($now - $last) < 10) {
        $stats = ValueStore::read('card_analytics', []);
        $opens = (int)(($stats[$slug]['opens'] ?? 0));
        vp_json(['status' => 'ok', 'opens' => $opens, 'throttled' => true]);
    }
    $_SESSION[$rlKey] = $now;

    $stats = ValueStore::read('card_analytics', []);
    if (!is_array($stats)) {
        $stats = [];
    }
    if (!isset($stats[$slug]) || !is_array($stats[$slug])) {
        $stats[$slug] = ['opens' => 0, 'last' => null];
    }
    $stats[$slug]['opens'] = (int)($stats[$slug]['opens'] ?? 0) + 1;
    $stats[$slug]['last'] = date('c');
    ValueStore::write('card_analytics', $stats);
    vp_json(['status' => 'ok', 'opens' => $stats[$slug]['opens']]);
}

vp_require_auth($loggedIn);

switch ($action) {
    case 'vp_list':
        $type = (string)($input['type'] ?? $_GET['type'] ?? '');
        $allowed = ['attendance', 'visits', 'assets', 'geofences', 'audit_log', 'card_analytics', 'roles'];
        if (!in_array($type, $allowed, true)) {
            vp_json(['status' => 'error', 'message' => 'Invalid type'], 400);
        }
        vp_json(['status' => 'ok', 'data' => ValueStore::read($type, $type === 'card_analytics' || $type === 'roles' ? new stdClass() : [])]);

    case 'attendance_check':
        $kind = (string)($input['kind'] ?? 'in'); // in|out
        $emp = trim((string)($input['employee_id'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $lat = isset($input['lat']) ? (float)$input['lat'] : null;
        $lng = isset($input['lng']) ? (float)$input['lng'] : null;
        $row = [
            'id' => vp_id('ATT'),
            'kind' => $kind === 'out' ? 'out' : 'in',
            'employee_id' => $emp,
            'name' => $name,
            'at' => date('c'),
            'lat' => $lat,
            'lng' => $lng,
            'note' => trim((string)($input['note'] ?? '')),
        ];
        ValueStore::append('attendance', $row);
        ValueStore::audit('attendance_' . $row['kind'], $emp ?: $name, $row);
        vp_json(['status' => 'ok', 'record' => $row]);

    case 'dispatch_list':
        $paths = [(defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : (BASE_PATH . '/data/dispatch')) . '/dispatches.json', (defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : (BASE_PATH . '/data/dispatch')) . '/routes.json'];
        $rows = [];
        foreach ($paths as $path) {
            if (!is_file($path)) continue;
            $raw = json_decode((string)@file_get_contents($path), true);
            if (!is_array($raw)) continue;
            foreach ($raw as $d) {
                if (!is_array($d)) continue;
                $rows[] = ['id'=>(string)($d['id']??$d['dispatch_id']??''),'title'=>(string)($d['title']??$d['label']??$d['destination']??$d['to']??'Dispatch'),'status'=>(string)($d['status']??'open'),'assignee'=>(string)($d['assignee']??$d['runner']??$d['runner_name']??''),'eta'=>(string)($d['eta']??$d['sla_due']??'')];
            }
            if ($rows) break;
        }
        vp_json(['status'=>'ok','dispatches'=>$rows]);

    case 'visit_log':
        $row = [
            'id' => vp_id('VIS'),
            'employee_id' => trim((string)($input['employee_id'] ?? '')),
            'name' => trim((string)($input['name'] ?? '')),
            'dispatch_id' => trim((string)($input['dispatch_id'] ?? '')),
            'status' => in_array(($input['status'] ?? ''), ['reached', 'delivered', 'failed', 'partial'], true)
                ? $input['status'] : 'reached',
            'note' => trim((string)($input['note'] ?? '')),
            'photo' => trim((string)($input['photo'] ?? '')),
            'lat' => isset($input['lat']) ? (float)$input['lat'] : null,
            'lng' => isset($input['lng']) ? (float)$input['lng'] : null,
            'at' => date('c'),
        ];
        ValueStore::append('visits', $row);
        // Link: update matching dispatch status when id provided
        if ($row['dispatch_id'] !== '') {
            $dpath = (defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : (BASE_PATH . '/data/dispatch')) . '/dispatches.json';
            if (is_file($dpath)) {
                $list = json_decode((string)@file_get_contents($dpath), true);
                if (is_array($list)) {
                    $mapStatus = [
                        'reached' => 'in_progress',
                        'delivered' => 'completed',
                        'partial' => 'in_progress',
                        'failed' => 'failed',
                    ];
                    $ns = $mapStatus[$row['status']] ?? 'in_progress';
                    foreach ($list as $i => $d) {
                        if (!is_array($d)) {
                            continue;
                        }
                        $did = (string)($d['id'] ?? $d['dispatch_id'] ?? '');
                        if ($did === $row['dispatch_id']) {
                            $list[$i]['status'] = $ns;
                            $list[$i]['last_visit_id'] = $row['id'];
                            $list[$i]['last_visit_at'] = $row['at'];
                            $list[$i]['updated_at'] = date('c');
                            break;
                        }
                    }
                    $tmp = $dpath . '.tmp';
                    @file_put_contents($tmp, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    @rename($tmp, $dpath);
                }
            }
        }
        ValueStore::audit('visit_' . $row['status'], $row['employee_id'] ?: $row['name'], $row);
        vp_json(['status' => 'ok', 'record' => $row]);

    case 'asset_save':
        if (!$isAdmin) {
            vp_json(['status' => 'error', 'message' => 'Admin only'], 403);
        }
        $assets = ValueStore::read('assets', []);
        if (!is_array($assets)) {
            $assets = [];
        }
        $id = trim((string)($input['id'] ?? ''));
        $rec = [
            'id' => $id !== '' ? $id : vp_id('AST'),
            'name' => trim((string)($input['name'] ?? 'Asset')),
            'category' => trim((string)($input['category'] ?? 'General')),
            'serial' => trim((string)($input['serial'] ?? '')),
            'status' => in_array(($input['status'] ?? ''), ['available', 'checked_out', 'maintenance', 'retired'], true)
                ? $input['status'] : 'available',
            'assigned_to' => trim((string)($input['assigned_to'] ?? '')),
            'assigned_name' => trim((string)($input['assigned_name'] ?? '')),
            'due_date' => trim((string)($input['due_date'] ?? '')),
            'notes' => trim((string)($input['notes'] ?? '')),
        ];
        $found = false;
        foreach ($assets as $i => $a) {
            if (($a['id'] ?? '') === $rec['id']) {
                $assets[$i] = $rec;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $assets[] = $rec;
        }
        ValueStore::write('assets', $assets);
        ValueStore::audit('asset_save', $rec['id'], $rec);
        vp_json(['status' => 'ok', 'record' => $rec]);

    case 'geofence_save':
        if (!$isAdmin) {
            vp_json(['status' => 'error', 'message' => 'Admin only'], 403);
        }
        $list = ValueStore::read('geofences', []);
        if (!is_array($list)) {
            $list = [];
        }
        $rec = [
            'id' => trim((string)($input['id'] ?? '')) ?: vp_id('GF'),
            'name' => trim((string)($input['name'] ?? 'Zone')),
            'lat' => (float)($input['lat'] ?? 0),
            'lng' => (float)($input['lng'] ?? 0),
            'radius_m' => max(50, (int)($input['radius_m'] ?? 200)),
            'active' => !empty($input['active']),
        ];
        $found = false;
        foreach ($list as $i => $g) {
            if (($g['id'] ?? '') === $rec['id']) {
                $list[$i] = $rec;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $list[] = $rec;
        }
        ValueStore::write('geofences', $list);
        ValueStore::audit('geofence_save', $rec['id'], $rec);
        vp_json(['status' => 'ok', 'record' => $rec]);

    case 'health':
        $checks = [];
        $writable = [
            'data' => BASE_PATH . '/data',
            'data/media/images' => defined('IMG_PATH') ? IMG_PATH : (BASE_PATH . '/data/media/images'),
            'data/media/documents' => defined('DOC_PATH') ? DOC_PATH : (BASE_PATH . '/data/media/documents'),
            'data/value' => defined('VALUE_DATA_PATH') ? VALUE_DATA_PATH : (BASE_PATH . '/data/value'),
            'data/runners' => defined('RUNNERS_DATA_PATH') ? RUNNERS_DATA_PATH : (BASE_PATH . '/data/runners'),
            'data/dispatch' => defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : (BASE_PATH . '/data/dispatch'),
            'data/janam' => defined('JANAM_DATA_PATH') ? JANAM_DATA_PATH : (BASE_PATH . '/data/janam'),
            'data/sessions' => defined('SESSION_PATH') ? SESSION_PATH : (BASE_PATH . '/data/sessions'),
            'data/logs' => defined('LOG_PATH') ? LOG_PATH : (BASE_PATH . '/data/logs'),
            'data/backups' => defined('BACKUP_PATH') ? BACKUP_PATH : (BASE_PATH . '/data/backups'),
        ];
        foreach ($writable as $label => $path) {
            $ok = is_dir($path) && is_writable($path);
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
                $ok = is_dir($path) && is_writable($path);
            }
            $checks[] = ['name' => $label, 'ok' => $ok, 'path' => $path];
        }
        $checks[] = ['name' => 'php_version', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'value' => PHP_VERSION, 'recommended' => '8.2+ (production target 8.4.x)'];
        $checks[] = ['name' => 'ext_intl', 'ok' => class_exists('NumberFormatter'), 'value' => extension_loaded('intl') ? 'intl' : 'missing'];
        $mapKey = '';
        foreach ([BASE_PATH . '/data/registry_config.php', BASE_PATH . '/data/auth_config.php'] as $cf) {
            if (is_file($cf)) {
                $c = @file_get_contents($cf);
                if ($c && (str_contains($c, 'GOOGLE_MAPS') || str_contains($c, 'maps_api'))) {
                    $mapKey = 'configured_or_present';
                }
            }
        }
        $checks[] = ['name' => 'maps_key_hint', 'ok' => true, 'value' => $mapKey ?: 'check settings'];
        $att = ValueStore::read('attendance', []);
        $checks[] = ['name' => 'attendance_rows', 'ok' => true, 'value' => is_array($att) ? count($att) : 0];
        vp_json(['status' => 'ok', 'checks' => $checks, 'time' => date('c'), 'version' => defined('APP_VERSION') ? APP_VERSION : '']);

    case 'backup_create':
        if (!$isAdmin) {
            vp_json(['status' => 'error', 'message' => 'Admin only'], 403);
        }
        $backupDir = defined('BACKUP_PATH') ? BACKUP_PATH : (BASE_PATH . '/data/backups');
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        $ver = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)(defined('APP_VERSION') ? APP_VERSION : date('ymd')));
        $name = 'RC-' . $ver . '-' . date('Ymd-His') . '.zip';
        $zipPath = $backupDir . '/' . $name;
        if (!class_exists('ZipArchive')) {
            vp_json(['status' => 'error', 'message' => 'ZipArchive not available on this host'], 500);
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            vp_json(['status' => 'error', 'message' => 'Cannot create zip'], 500);
        }

        // Durable *data* backup only — not application PHP (app/, cards/, index.php).
        // Includes entity JSON, module stores, media, and data/config secrets.
        // Excludes sessions, logs, nested backups, tmp, and cache stamps.
        $dataRoot = defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data');
        $dataRootNorm = rtrim(str_replace('\\', '/', (string)(realpath($dataRoot) ?: $dataRoot)), '/');

        /** @var array<string,string> $files archive path => absolute path */
        $files = [];

        $shouldSkip = static function (string $rel, string $basename): bool {
            $rel = str_replace('\\', '/', $rel);
            if (str_contains($rel, '/sessions/') || str_starts_with($rel, 'sessions/')) {
                return true;
            }
            if (str_contains($rel, '/logs/') || str_starts_with($rel, 'logs/')) {
                return true;
            }
            if (str_contains($rel, '/backups/') || str_starts_with($rel, 'backups/')) {
                return true;
            }
            if (str_contains($rel, '/tmp/') || str_starts_with($rel, 'tmp/')) {
                return true;
            }
            if (preg_match('/\.tmp$/i', $basename)) {
                return true;
            }
            if (in_array($basename, ['.opcache_version', '.numero_meanings_cache.php', '.gitkeep', '.DS_Store'], true)) {
                return true;
            }
            return false;
        };

        $collectDir = static function (string $absDir, string $prefix) use (&$files, $shouldSkip): void {
            if (!is_dir($absDir)) {
                return;
            }
            $absDir = rtrim(str_replace('\\', '/', $absDir), '/');
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $abs = str_replace('\\', '/', $file->getPathname());
                $rel = str_replace('\\', '/', $prefix . '/' . substr($abs, strlen($absDir) + 1));
                if ($shouldSkip($rel, $file->getFilename())) {
                    continue;
                }
                if (!isset($files[$rel])) {
                    $files[$rel] = $abs;
                }
            }
        };

        $collectDir($dataRoot, 'data');

        $legacyPairs = []; // retired: no action on obsolete root folders
        foreach ($legacyPairs as [$src, $prefix]) {
            $srcReal = realpath($src);
            if ($srcReal === false) {
                continue;
            }
            $srcNorm = str_replace('\\', '/', $srcReal);
            if (str_starts_with($srcNorm, $dataRootNorm . '/') || $srcNorm === $dataRootNorm) {
                continue;
            }
            $collectDir($srcReal, $prefix);
        }

        // Legacy root docs/: upload binaries only (skip markdown/docs site files)
        $legacyDocs = false; // retired BASE_PATH/docs
        if ($legacyDocs !== false) {
            $legacyDocsNorm = str_replace('\\', '/', $legacyDocs);
            if (!str_starts_with($legacyDocsNorm, $dataRootNorm)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($legacyDocs, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $file) {
                    if (!$file->isFile()) {
                        continue;
                    }
                    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                    if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip', 'txt'], true)) {
                        continue;
                    }
                    $abs = str_replace('\\', '/', $file->getPathname());
                    $rel = 'data/media/documents/' . substr($abs, strlen($legacyDocsNorm) + 1);
                    if (!isset($files[$rel])) {
                        $files[$rel] = $abs;
                    }
                }
            }
        }

        $added = 0;
        $bytes = 0;
        $byKind = ['json' => 0, 'config_php' => 0, 'media' => 0, 'other' => 0];
        foreach ($files as $rel => $abs) {
            if (!$zip->addFile($abs, $rel)) {
                continue;
            }
            $added++;
            $bytes += (int) @filesize($abs);
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            if ($ext === 'json') {
                $byKind['json']++;
            } elseif ($ext === 'php') {
                $byKind['config_php']++;
            } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip'], true)) {
                $byKind['media']++;
            } else {
                $byKind['other']++;
            }
        }

        $stamp = "ARTHSATHI / FUSION RC — DATA BACKUP\n"
            . 'Created: ' . date('c') . "\n"
            . 'Version: ' . (defined('APP_VERSION') ? APP_VERSION : '') . "\n"
            . 'By: ' . (string)($_SESSION['user'] ?? '') . "\n"
            . 'Files: ' . $added . '  Bytes: ' . $bytes . "\n"
            . 'JSON: ' . $byKind['json'] . '  Config PHP: ' . $byKind['config_php']
            . '  Media: ' . $byKind['media'] . '  Other: ' . $byKind['other'] . "\n"
            . "Excluded: sessions/, logs/, backups/, tmp/, *.tmp, cache stamps\n"
            . "Application code (app/, index.php, cards/) is NOT included.\n"
            . "Restore: extract so paths land under data/ (do not wipe code first).\n";
        $zip->addFromString('VERSION-STAMP.txt', $stamp);
        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            vp_json(['status' => 'error', 'message' => 'Nothing to back up under data/'], 404);
        }

        ValueStore::audit('backup_create', $name, [
            'bytes' => @filesize($zipPath),
            'files' => $added,
            'kinds' => $byKind,
        ]);
        vp_json([
            'status' => 'ok',
            'file' => $name,
            'path' => 'data/backups/' . $name,
            'bytes' => @filesize($zipPath),
            'files' => $added,
            'kinds' => $byKind,
        ]);

    case 'expiry_scan':
        // Scan docs collection for date-like fields
        $docsPath = (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/documents.json';
        if (!is_file($docsPath)) {
            $docsPath = BASE_PATH . '/data/documents.json';
        }
        $docs = is_file($docsPath) ? (json_decode((string)@file_get_contents($docsPath), true) ?: []) : [];
        $today = new DateTimeImmutable('today');
        $out = [];
        foreach ($docs as $d) {
            if (!is_array($d)) {
                continue;
            }
            $exp = $d['expiry'] ?? $d['expiry_date'] ?? $d['expires_on'] ?? $d['valid_till'] ?? '';
            if ($exp === '' || $exp === null) {
                continue;
            }
            try {
                $dt = new DateTimeImmutable((string)$exp);
            } catch (\Throwable $e) {
                continue;
            }
            $days = (int)$today->diff($dt)->format('%r%a');
            $out[] = [
                'id' => $d['id'] ?? $d['slug'] ?? '',
                'title' => $d['title'] ?? $d['name'] ?? 'Document',
                'expiry' => $dt->format('Y-m-d'),
                'days_left' => $days,
                'urgency' => $days < 0 ? 'expired' : ($days <= 7 ? 'critical' : ($days <= 30 ? 'warning' : 'ok')),
            ];
        }
        usort($out, static fn($a, $b) => $a['days_left'] <=> $b['days_left']);
        vp_json(['status' => 'ok', 'items' => $out]);

    case 'org_tree':
        $teamPath = (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/team.json';
        if (!is_file($teamPath)) {
            $teamPath = BASE_PATH . '/data/members.json';
        }
        $team = is_file($teamPath) ? (json_decode((string)@file_get_contents($teamPath), true) ?: []) : [];
        // Also try bootstrap data patterns
        if (!$team && is_file(BASE_PATH . '/data/directory.json')) {
            $team = json_decode((string)@file_get_contents(BASE_PATH . '/data/directory.json'), true) ?: [];
        }
        $nodes = [];
        foreach ($team as $m) {
            if (!is_array($m)) {
                continue;
            }
            $nodes[] = [
                'id' => (string)($m['id'] ?? $m['slug'] ?? $m['emp_id'] ?? ''),
                'name' => (string)($m['name'] ?? ''),
                'designation' => (string)($m['designation_name'] ?? $m['designation'] ?? ''),
                'department' => (string)($m['department_name'] ?? $m['department'] ?? ''),
                'manager_id' => (string)($m['manager_id'] ?? $m['reports_to'] ?? ''),
                'rank' => (int)($m['rank'] ?? $m['sort_rank'] ?? 9999),
                'photo' => (string)($m['photo'] ?? ''),
            ];
        }
        usort($nodes, static fn($a, $b) => $a['rank'] <=> $b['rank']);
        vp_json(['status' => 'ok', 'people' => $nodes]);

    case 'dispatch_templates':
        vp_json(['status' => 'ok', 'templates' => [
            ['id' => 'out', 'hi' => 'Bhai, aapki delivery ab nikal gayi hai. Location share karta hoon.', 'en' => 'Your delivery is on the way.'],
            ['id' => 'reached', 'hi' => 'Main location par aa gaya hoon. Kripya milne ke liye ready rahein.', 'en' => 'I have reached the location.'],
            ['id' => 'done', 'hi' => 'Delivery complete ho gayi. Dhanyavaad!', 'en' => 'Delivery completed. Thank you!'],
            ['id' => 'need_address', 'hi' => 'Sahi address / landmark bhej dijiye, please.', 'en' => 'Please share the correct address or landmark.'],
            ['id' => 'delay', 'hi' => 'Thoda late ho sakta hai traffic ki wajah se. Jaldi pahunchunga.', 'en' => 'Slight delay due to traffic. Arriving soon.'],
        ]]);

    case 'reminders_scan':
        // Build reminder queue from document expiry + optional open dispatches past ETA
        $today = new DateTimeImmutable('today');
        $reminders = [];
        $docsPath = (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/documents.json';
        $docs = is_file($docsPath) ? (json_decode((string)@file_get_contents($docsPath), true) ?: []) : [];
        foreach ($docs as $d) {
            if (!is_array($d)) continue;
            $exp = $d['expiry'] ?? $d['expiry_date'] ?? $d['expires_on'] ?? $d['valid_till'] ?? '';
            if ($exp === '' || $exp === null) continue;
            try { $dt = new DateTimeImmutable((string)$exp); } catch (\Throwable $e) { continue; }
            $days = (int)$today->diff($dt)->format('%r%a');
            if ($days > 30) continue;
            $title = (string)($d['title'] ?? $d['name'] ?? 'Document');
            $reminders[] = [
                'type' => 'doc_expiry',
                'id' => (string)($d['id'] ?? ''),
                'title' => $title,
                'when' => $dt->format('Y-m-d'),
                'days_left' => $days,
                'wa_hi' => $days < 0
                    ? ("Document expire ho chuka hai: {$title}. Kripya turant renew karein.")
                    : ("Document {$days} din mein expire hoga: {$title}. Please renew plan karein."),
                'wa_en' => $days < 0
                    ? ("Document expired: {$title}. Please renew immediately.")
                    : ("Document expires in {$days} day(s): {$title}. Please plan renewal."),
            ];
        }
        $dpath = (defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : (BASE_PATH . '/data/dispatch')) . '/dispatches.json';
        if (is_file($dpath)) {
            $list = json_decode((string)@file_get_contents($dpath), true) ?: [];
            foreach ($list as $d) {
                if (!is_array($d)) continue;
                $st = strtolower((string)($d['status'] ?? ''));
                if (in_array($st, ['completed', 'done', 'cancelled'], true)) continue;
                $eta = (string)($d['eta'] ?? $d['sla_due'] ?? '');
                if ($eta === '') continue;
                try { $edt = new DateTimeImmutable($eta); } catch (\Throwable $e) { continue; }
                if ($edt >= new DateTimeImmutable('now')) continue;
                $title = (string)($d['title'] ?? $d['id'] ?? 'Dispatch');
                $reminders[] = [
                    'type' => 'dispatch_sla',
                    'id' => (string)($d['id'] ?? ''),
                    'title' => $title,
                    'when' => $edt->format('c'),
                    'days_left' => 0,
                    'wa_hi' => "Dispatch late hai: {$title}. Status update bhejein.",
                    'wa_en' => "Dispatch is past ETA: {$title}. Please send a status update.",
                ];
            }
        }
        ValueStore::write('reminders_last', ['at' => date('c'), 'items' => $reminders]);
        ValueStore::audit('reminders_scan', 'system', ['count' => count($reminders)]);
        vp_json(['status' => 'ok', 'items' => $reminders, 'count' => count($reminders)]);

    case 'role_set_crm_pack':
        if (!$isAdmin) {
            vp_json(['status' => 'error', 'message' => 'Admin only'], 403);
        }
        $pack = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)($input['pack'] ?? 'logistics')));
        $cfg = ValueStore::read('roles', []);
        if (!is_array($cfg)) {
            $cfg = [];
        }
        // roles stored as roles.json via ValueStore name "roles"
        $cfg['crm_pack'] = $pack ?: 'logistics';
        if (empty($cfg['packs'])) {
            $cfg = json_decode((string)@file_get_contents(BASE_PATH . '/data/value/roles.json'), true) ?: $cfg;
            $cfg['crm_pack'] = $pack ?: 'logistics';
        }
        ValueStore::write('roles', $cfg);
        ValueStore::audit('role_set_crm_pack', $pack, []);
        vp_json(['status' => 'ok', 'crm_pack' => $cfg['crm_pack']]);

    default:
        vp_json(['status' => 'error', 'message' => 'Unknown action: ' . $action], 400);
}
