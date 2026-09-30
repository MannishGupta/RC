<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) { define('BASE_PATH', __DIR__); }
require_once BASE_PATH . '/app/tenant_bootstrap.php';

// Version: 1.5
/**
 * Live Location, Travel History & HR Policy Violation Audit
 * Native JSON only — atomic flock writes. No MySQL / PDO.
 *
 * Data: __DIR__/data/runners/
 *   policy_settings.json | location_violations_log.json
 *   runners_state.json | runner_history_YYYY-MM-DD.json
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

define('RT_ROOT', __DIR__);
define('RT_DATA', RT_ROOT . '/data/runners');
define('RT_TZ_DEFAULT', 'Asia/Kolkata');

function rtEnsureDir(): void
{
    if (!is_dir(RT_DATA)) {
        @mkdir(RT_DATA, 0755, true);
    }
}

/** @return array<mixed> */
function readJsonFile(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $fp = @fopen($path, 'rb');
    if ($fp === false) {
        return [];
    }
    try {
        if (!flock($fp, LOCK_SH)) {
            return [];
        }
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
    } finally {
        fclose($fp);
    }
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** @param array<mixed> $data */
function writeJsonFile(string $path, array $data): bool
{
    rtEnsureDir();
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $fp = @fopen($tmp, 'cb');
    if ($fp === false) {
        return false;
    }
    $ok = false;
    try {
        if (!flock($fp, LOCK_EX)) {
            return false;
        }
        ftruncate($fp, 0);
        rewind($fp);
        $w = fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = ($w !== false && $w === strlen($json));
    } finally {
        fclose($fp);
    }
    if (!$ok) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($path);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
    }
    return true;
}

function pathPolicy(): string { return RT_DATA . '/policy_settings.json'; }
function pathViolations(): string { return RT_DATA . '/location_violations_log.json'; }
function pathState(): string { return RT_DATA . '/runners_state.json'; }
function pathHistory(string $date): string
{
    $date = preg_replace('/[^0-9\-]/', '', $date) ?: rtToday();
    return RT_DATA . '/runner_history_' . $date . '.json';
}

/** @return array<string,mixed> */
function defaultPolicy(): array
{
    return [
        'master_tracking_email' => 'admin.logistics@company.com',
        // Optional link to a Team Directory person acting as Logistics Admin
        'logistics_admin_id' => '',
        'logistics_admin_name' => '',
        'working_hours' => ['start' => '09:00', 'end' => '19:00', 'timezone' => RT_TZ_DEFAULT],
        'mandatory_designations' => ['Driver', 'Office Runner'],
        'mandatory_departments' => ['Logistics', 'Operations', 'Administration'],
        'heartbeat_timeout_minutes' => 10,
    ];
}

/** @return array<string,mixed> */
function loadPolicy(): array
{
    $raw = readJsonFile(pathPolicy());
    $d = defaultPolicy();
    if ($raw === []) {
        writeJsonFile(pathPolicy(), $d);
        return $d;
    }
    if (isset($raw['master_tracking_email'])) {
        $d['master_tracking_email'] = (string)$raw['master_tracking_email'];
    }
    if (isset($raw['logistics_admin_id'])) {
        $d['logistics_admin_id'] = (string)$raw['logistics_admin_id'];
    }
    if (isset($raw['logistics_admin_name'])) {
        $d['logistics_admin_name'] = (string)$raw['logistics_admin_name'];
    }
    if (isset($raw['working_hours']) && is_array($raw['working_hours'])) {
        $d['working_hours'] = array_merge($d['working_hours'], $raw['working_hours']);
    }
    if (isset($raw['mandatory_designations']) && is_array($raw['mandatory_designations'])) {
        $d['mandatory_designations'] = array_values(array_map('strval', $raw['mandatory_designations']));
    }
    if (isset($raw['mandatory_departments']) && is_array($raw['mandatory_departments'])) {
        $d['mandatory_departments'] = array_values(array_map('strval', $raw['mandatory_departments']));
    }
    if (isset($raw['heartbeat_timeout_minutes'])) {
        $d['heartbeat_timeout_minutes'] = max(1, (int)$raw['heartbeat_timeout_minutes']);
    }
    return $d;
}

function rtTimezone(array $policy): DateTimeZone
{
    try {
        return new DateTimeZone((string)($policy['working_hours']['timezone'] ?? RT_TZ_DEFAULT));
    } catch (Throwable $e) {
        return new DateTimeZone(RT_TZ_DEFAULT);
    }
}

function rtNow(array $policy): DateTimeImmutable
{
    return new DateTimeImmutable('now', rtTimezone($policy));
}

function rtToday(?array $policy = null): string
{
    return rtNow($policy ?? loadPolicy())->format('Y-m-d');
}

function rtNowStr(array $policy): string
{
    return rtNow($policy)->format('Y-m-d H:i:s');
}

/** @return list<array<string,mixed>> */
function loadViolations(): array
{
    $rows = readJsonFile(pathViolations());
    return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
}

/** @param list<array<string,mixed>> $rows */
function saveViolations(array $rows): bool
{
    return writeJsonFile(pathViolations(), array_values($rows));
}

/** @param array<string,mixed> $a @param array<string,mixed> $b */
function rtSortByRank(array $a, array $b): int
{
    $ra = isset($a['rank']) && (int)$a['rank'] > 0 ? (int)$a['rank'] : 9999;
    $rb = isset($b['rank']) && (int)$b['rank'] > 0 ? (int)$b['rank'] : 9999;
    $aNull = $ra >= 9999 ? 1 : 0;
    $bNull = $rb >= 9999 ? 1 : 0;
    if ($aNull !== $bNull) {
        return $aNull - $bNull;
    }
    if ($ra !== $rb) {
        return $ra - $rb;
    }
    return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
}

/**
 * Mandatory tracking is driven primarily by designation.mandatory_live_tracking
 * (ticked in Resource Center → Designations). Policy name lists remain a fallback
 * for legacy setups.
 *
 * @param array<string,mixed> $emp
 * @param array<string,mixed> $policy
 */
function isMandatoryEmployee(array $emp, array $policy): bool
{
    // 1) Per-designation flag from designations.json (preferred)
    $desigKeys = [];
    foreach (['designation_id', 'designation_code', 'designation', 'designation_name', 'labels'] as $fld) {
        $v = strtolower(trim((string)($emp[$fld] ?? '')));
        if ($v !== '') {
            $desigKeys[$v] = true;
        }
    }
    $desigFile = RT_ROOT . '/data/designations.json';
    if (is_file($desigFile) && $desigKeys !== []) {
        $rows = readJsonFile($desigFile);
        if (isset($rows['items']) && is_array($rows['items'])) {
            $rows = $rows['items'];
        }
        if (is_array($rows)) {
            foreach ($rows as $d) {
                if (!is_array($d)) {
                    continue;
                }
                $keys = [];
                foreach (['id', 'code', 'slug', 'name'] as $fld) {
                    $k = strtolower(trim((string)($d[$fld] ?? '')));
                    if ($k !== '') {
                        $keys[$k] = true;
                    }
                }
                $match = false;
                foreach ($desigKeys as $ek => $_) {
                    if (isset($keys[$ek])) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    continue;
                }
                $flag = $d['mandatory_live_tracking'] ?? $d['require_live_tracking'] ?? $d['live_tracking'] ?? false;
                if ($flag === true || $flag === 1 || $flag === '1' || $flag === 'true' || $flag === 'yes' || $flag === 'on') {
                    return true;
                }
                // Matched designation explicitly opted out — still allow policy name fallback below
            }
        }
    }

    // 2) Legacy / optional: policy mandatory designation names
    $desig = strtolower(trim((string)($emp['designation'] ?? $emp['designation_name'] ?? '')));
    $dept = strtolower(trim((string)($emp['department'] ?? $emp['department_name'] ?? '')));
    foreach ($policy['mandatory_designations'] as $d) {
        if ($desig !== '' && $desig === strtolower(trim((string)$d))) {
            return true;
        }
    }
    // 3) Policy mandatory departments
    foreach ($policy['mandatory_departments'] as $d) {
        if ($dept !== '' && $dept === strtolower(trim((string)$d))) {
            return true;
        }
    }
    return false;
}

/** @param array<string,mixed> $policy */
function isWorkingHours(array $policy, ?DateTimeImmutable $now = null): bool
{
    $now = $now ?? rtNow($policy);
    $start = (string)($policy['working_hours']['start'] ?? '09:00');
    $end = (string)($policy['working_hours']['end'] ?? '19:00');
    $hm = $now->format('H:i');
    if ($start <= $end) {
        return $hm >= $start && $hm <= $end;
    }
    return $hm >= $start || $hm <= $end;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $s = '';
    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        $s .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($s) >= 2) {
            break;
        }
    }
    return $s !== '' ? $s : '?';
}

/** @return list<array<string,mixed>> */
function seedFromTeam(): array
{
    $out = [];
    $teamFile = RT_ROOT . '/data/team.json';
    if (!is_file($teamFile)) {
        return $out;
    }
    $team = readJsonFile($teamFile);
    if (isset($team['items']) && is_array($team['items'])) {
        $team = $team['items'];
    }
    if (!is_array($team)) {
        return $out;
    }
    $policy = loadPolicy();
    foreach ($team as $m) {
        if (!is_array($m) || !isMandatoryEmployee($m, $policy)) {
            continue;
        }
        $id = (string)($m['id'] ?? $m['slug'] ?? '');
        if ($id === '') {
            continue;
        }
        $out[] = [
            'id' => $id,
            'name' => (string)($m['name'] ?? 'Employee'),
            'designation' => (string)($m['designation_name'] ?? $m['designation'] ?? ''),
            'department' => (string)($m['department_name'] ?? $m['department'] ?? ''),
            'rank' => isset($m['rank']) ? (int)$m['rank'] : 9999,
            'is_tracking_enabled' => true,
            'location_sharing' => true,
            'last_lat' => null,
            'last_lng' => null,
            'last_known_address' => null,
            'speed_kmh' => null,
            'battery_percentage' => null,
            'last_ping' => null,
            'status' => 'OFFLINE',
            'compliance' => 'OFF_DUTY',
        ];
    }
    usort($out, 'rtSortByRank');
    return $out;
}

/** @return list<array<string,mixed>> */
function loadState(): array
{
    $rows = readJsonFile(pathState());
    if ($rows === []) {
        $seeded = seedFromTeam();
        if ($seeded !== []) {
            writeJsonFile(pathState(), $seeded);
        }
        return $seeded;
    }
    $list = array_values(array_filter($rows, 'is_array'));
    usort($list, 'rtSortByRank');
    return $list;
}

/** @param list<array<string,mixed>> $rows */
function saveState(array $rows): bool
{
    usort($rows, 'rtSortByRank');
    return writeJsonFile(pathState(), array_values($rows));
}

function nextIncidentId(array $violations, array $policy): string
{
    $day = rtToday($policy);
    $prefix = 'VIOL-' . str_replace('-', '', $day) . '-';
    $max = 0;
    foreach ($violations as $v) {
        $id = (string)($v['incident_id'] ?? '');
        if (strpos($id, $prefix) === 0) {
            $n = (int)substr($id, strlen($prefix));
            if ($n > $max) {
                $max = $n;
            }
        }
    }
    return $prefix . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

/**
 * @param array<string,mixed> $emp
 * @param array<string,mixed> $policy
 * @return array{created:bool,incident:?array<string,mixed>}
 */
function openViolation(array $emp, string $type, array $policy, ?string $address = null): array
{
    $violations = loadViolations();
    $eid = (string)($emp['id'] ?? '');
    foreach ($violations as $v) {
        if ((string)($v['employee_id'] ?? '') === $eid && ($v['status'] ?? '') === 'OPEN') {
            return ['created' => false, 'incident' => $v];
        }
    }
    $incident = [
        'incident_id' => nextIncidentId($violations, $policy),
        'employee_id' => $eid,
        'name' => (string)($emp['name'] ?? ''),
        'designation' => (string)($emp['designation'] ?? ''),
        'department' => (string)($emp['department'] ?? ''),
        'violation_type' => $type,
        'detected_at' => rtNowStr($policy),
        'resolved_at' => null,
        'duration_minutes' => null,
        'last_known_lat' => $emp['last_lat'] ?? null,
        'last_known_lng' => $emp['last_lng'] ?? null,
        'last_known_address' => $address ?? ($emp['last_known_address'] ?? null),
        'status' => 'OPEN',
    ];
    $violations[] = $incident;
    saveViolations($violations);
    return ['created' => true, 'incident' => $incident];
}

/**
 * @param list<array<string,mixed>> $violations
 * @return list<array<string,mixed>>
 */
function resolveOpenViolations(array &$violations, string $employeeId, array $policy): array
{
    $resolved = [];
    $now = rtNow($policy);
    foreach ($violations as &$v) {
        if ((string)($v['employee_id'] ?? '') !== $employeeId || ($v['status'] ?? '') !== 'OPEN') {
            continue;
        }
        $detected = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)($v['detected_at'] ?? ''), rtTimezone($policy));
        $mins = 0;
        if ($detected instanceof DateTimeImmutable) {
            $mins = (int)max(0, floor(($now->getTimestamp() - $detected->getTimestamp()) / 60));
        }
        $v['resolved_at'] = $now->format('Y-m-d H:i:s');
        $v['duration_minutes'] = $mins;
        $v['status'] = 'RESOLVED';
        $resolved[] = $v;
    }
    unset($v);
    if ($resolved !== []) {
        saveViolations($violations);
    }
    return $resolved;
}

/**
 * @param list<array<string,mixed>> $state
 * @return list<array<string,mixed>>
 */
function runHeartbeatCompliance(array $state, array $policy): array
{
    $onDuty = isWorkingHours($policy);
    $timeout = max(1, (int)$policy['heartbeat_timeout_minutes']) * 60;
    $nowTs = rtNow($policy)->getTimestamp();
    $changed = false;
    foreach ($state as &$emp) {
        if (!isMandatoryEmployee($emp, $policy)) {
            $emp['compliance'] = 'OFF_DUTY';
            continue;
        }
        if (!$onDuty) {
            $emp['compliance'] = 'OFF_DUTY';
            continue;
        }
        $explicitOff = isset($emp['location_sharing']) && $emp['location_sharing'] === false;
        $ping = (string)($emp['last_ping'] ?? '');
        $age = null;
        if ($ping !== '') {
            $ts = strtotime($ping);
            if ($ts !== false) {
                $age = $nowTs - $ts;
            }
        }
        if ($explicitOff) {
            $emp['compliance'] = 'IN_VIOLATION';
            $emp['status'] = 'OFFLINE';
            openViolation($emp, 'LOCATION_DISABLED_MANUAL', $policy);
            $changed = true;
            continue;
        }
        if ($age === null || $age > $timeout) {
            $emp['compliance'] = 'IN_VIOLATION';
            $emp['status'] = 'OFFLINE';
            openViolation($emp, 'SIGNAL_LOST_HEARTBEAT_TIMEOUT', $policy);
            $changed = true;
        } else {
            $speed = isset($emp['speed_kmh']) && $emp['speed_kmh'] !== null ? (float)$emp['speed_kmh'] : null;
            $emp['status'] = ($speed !== null && $speed >= 1.5) ? 'MOVING' : 'IDLE';
            $emp['compliance'] = 'COMPLIANT';
        }
    }
    unset($emp);
    if ($changed) {
        saveState($state);
    }
    return $state;
}

function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $R = 6371.0;
    $φ1 = deg2rad($lat1);
    $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1);
    $Δλ = deg2rad($lon2 - $lon1);
    $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;
    return 2 * $R * asin(min(1.0, sqrt($a)));
}

/** @param list<array<string,mixed>> $points @return array<string,mixed> */
function summarizeTrail(array $points): array
{
    $n = count($points);
    if ($n === 0) {
        return ['distance_km' => 0.0, 'active_seconds' => 0, 'idle_seconds' => 0, 'start' => null, 'end' => null, 'point_count' => 0];
    }
    $dist = 0.0;
    $active = 0;
    $idle = 0;
    for ($i = 1; $i < $n; $i++) {
        $a = $points[$i - 1];
        $b = $points[$i];
        $seg = haversineKm((float)$a['lat'], (float)$a['lng'], (float)$b['lat'], (float)$b['lng']);
        $dist += $seg;
        $t0 = strtotime((string)($a['timestamp'] ?? ''));
        $t1 = strtotime((string)($b['timestamp'] ?? ''));
        if ($t0 === false || $t1 === false) {
            continue;
        }
        $dt = max(0, $t1 - $t0);
        $spd = isset($b['speed']) ? (float)$b['speed'] : null;
        $moving = ($spd !== null && $spd >= 1.5) || ($dt > 0 && ($seg / max($dt / 3600.0, 0.0001)) >= 1.5);
        if ($moving) {
            $active += $dt;
        } else {
            $idle += $dt;
        }
    }
    return [
        'distance_km' => round($dist, 3),
        'active_seconds' => $active,
        'idle_seconds' => $idle,
        'start' => (string)($points[0]['timestamp'] ?? null),
        'end' => (string)($points[$n - 1]['timestamp'] ?? null),
        'point_count' => $n,
    ];
}

function jsonOut(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requestBody(): array
{
    $body = $_POST;
    $ct = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($ct, 'application/json') !== false) {
        $j = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (is_array($j)) {
            $body = $j;
        }
    }
    return is_array($body) ? $body : [];
}

// ─── API ───────────────────────────────────────────────────────────────────

function actionGetPolicy(): void
{
    jsonOut(['ok' => true, 'policy' => loadPolicy()]);
}

function actionSavePolicy(array $body): void
{
    $p = loadPolicy();
    if (isset($body['logistics_admin_id'])) {
        $p['logistics_admin_id'] = trim((string)$body['logistics_admin_id']);
    }
    if (isset($body['logistics_admin_name'])) {
        $p['logistics_admin_name'] = trim((string)$body['logistics_admin_name']);
    }
    if (isset($body['master_tracking_email'])) {
        $email = trim((string)$body['master_tracking_email']);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['ok' => false, 'error' => 'Invalid master tracking email'], 400);
        }
        $p['master_tracking_email'] = $email;
    }
    if (isset($body['working_hours']) && is_array($body['working_hours'])) {
        $p['working_hours'] = array_merge($p['working_hours'], $body['working_hours']);
    }
    if (isset($body['mandatory_designations']) && is_array($body['mandatory_designations'])) {
        $p['mandatory_designations'] = array_values(array_filter(array_map('strval', $body['mandatory_designations'])));
    }
    if (isset($body['mandatory_departments']) && is_array($body['mandatory_departments'])) {
        $p['mandatory_departments'] = array_values(array_filter(array_map('strval', $body['mandatory_departments'])));
    }
    if (isset($body['heartbeat_timeout_minutes'])) {
        $p['heartbeat_timeout_minutes'] = max(1, (int)$body['heartbeat_timeout_minutes']);
    }
    writeJsonFile(pathPolicy(), $p);
    jsonOut(['ok' => true, 'policy' => $p]);
}

function actionGetRunners(): void
{
    $policy = loadPolicy();
    $state = runHeartbeatCompliance(loadState(), $policy);
    usort($state, 'rtSortByRank');
    $out = [];
    $counts = ['total' => 0, 'moving' => 0, 'idle' => 0, 'offline' => 0, 'violations' => 0, 'compliant' => 0];
    foreach ($state as $r) {
        $counts['total']++;
        $status = (string)($r['status'] ?? 'OFFLINE');
        $compliance = (string)($r['compliance'] ?? 'OFF_DUTY');
        if ($status === 'MOVING') {
            $counts['moving']++;
        } elseif ($status === 'IDLE') {
            $counts['idle']++;
        } else {
            $counts['offline']++;
        }
        if ($compliance === 'IN_VIOLATION') {
            $counts['violations']++;
        }
        if ($compliance === 'COMPLIANT') {
            $counts['compliant']++;
        }
        $out[] = [
            'id' => (string)($r['id'] ?? ''),
            'name' => (string)($r['name'] ?? ''),
            'designation' => (string)($r['designation'] ?? ''),
            'department' => (string)($r['department'] ?? ''),
            'rank' => isset($r['rank']) ? (int)$r['rank'] : 9999,
            'last_lat' => isset($r['last_lat']) && $r['last_lat'] !== null ? (float)$r['last_lat'] : null,
            'last_lng' => isset($r['last_lng']) && $r['last_lng'] !== null ? (float)$r['last_lng'] : null,
            'last_known_address' => $r['last_known_address'] ?? null,
            'speed_kmh' => isset($r['speed_kmh']) && $r['speed_kmh'] !== null ? (float)$r['speed_kmh'] : null,
            'battery_percentage' => isset($r['battery_percentage']) && $r['battery_percentage'] !== null ? (int)$r['battery_percentage'] : null,
            'last_ping' => $r['last_ping'] ?? null,
            'status' => $status,
            'compliance' => $compliance,
            'location_sharing' => !isset($r['location_sharing']) || $r['location_sharing'] !== false,
            'initials' => initials((string)($r['name'] ?? '')),
        ];
    }
    $open = array_values(array_filter(loadViolations(), static fn($v) => ($v['status'] ?? '') === 'OPEN'));
    jsonOut([
        'ok' => true,
        'runners' => $out,
        'counts' => $counts,
        'open_violations' => $open,
        'open_violation_count' => count($open),
        'policy' => $policy,
        'working_hours_active' => isWorkingHours($policy),
    ]);
}

function actionUpdateLocation(array $body): void
{
    $policy = loadPolicy();
    $runnerId = trim((string)($body['runner_id'] ?? $body['id'] ?? ''));
    $lat = isset($body['lat']) ? (float)$body['lat'] : (isset($body['latitude']) ? (float)$body['latitude'] : null);
    $lng = isset($body['lng']) ? (float)$body['lng'] : (isset($body['longitude']) ? (float)$body['longitude'] : null);
    $speed = isset($body['speed']) ? (float)$body['speed'] : (isset($body['speed_kmh']) ? (float)$body['speed_kmh'] : null);
    $battery = isset($body['battery']) ? (int)$body['battery'] : (isset($body['battery_percentage']) ? (int)$body['battery_percentage'] : null);
    $address = isset($body['address']) ? trim((string)$body['address']) : null;
    if ($runnerId === '' || $lat === null || $lng === null) {
        jsonOut(['ok' => false, 'error' => 'runner_id, lat, lng required'], 400);
    }
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        jsonOut(['ok' => false, 'error' => 'Invalid coordinates'], 400);
    }
    if ($battery !== null) {
        $battery = max(0, min(100, $battery));
    }
    if ($speed !== null && $speed < 0) {
        $speed = 0.0;
    }
    $now = rtNowStr($policy);
    $today = rtToday($policy);
    $timeOnly = rtNow($policy)->format('H:i:s');
    $state = loadState();
    $found = false;
    $emp = null;
    foreach ($state as &$r) {
        if ((string)($r['id'] ?? '') !== $runnerId) {
            continue;
        }
        $found = true;
        $r['last_lat'] = round($lat, 8);
        $r['last_lng'] = round($lng, 8);
        $r['speed_kmh'] = $speed;
        $r['battery_percentage'] = $battery;
        $r['last_ping'] = $now;
        $r['is_tracking_enabled'] = true;
        $r['location_sharing'] = true;
        if ($address) {
            $r['last_known_address'] = $address;
        }
        $r['status'] = ($speed !== null && $speed >= 1.5) ? 'MOVING' : 'IDLE';
        $r['compliance'] = (isMandatoryEmployee($r, $policy) && isWorkingHours($policy)) ? 'COMPLIANT' : 'OFF_DUTY';
        $emp = $r;
        break;
    }
    unset($r);
    if (!$found) {
        $emp = [
            'id' => $runnerId,
            'name' => (string)($body['name'] ?? $runnerId),
            'designation' => (string)($body['designation'] ?? 'Office Runner'),
            'department' => (string)($body['department'] ?? ''),
            'rank' => isset($body['rank']) ? (int)$body['rank'] : 9999,
            'is_tracking_enabled' => true,
            'location_sharing' => true,
            'last_lat' => round($lat, 8),
            'last_lng' => round($lng, 8),
            'last_known_address' => $address,
            'speed_kmh' => $speed,
            'battery_percentage' => $battery,
            'last_ping' => $now,
            'status' => ($speed !== null && $speed >= 1.5) ? 'MOVING' : 'IDLE',
            'compliance' => 'COMPLIANT',
        ];
        $state[] = $emp;
    }
    saveState($state);
    $violations = loadViolations();
    $resolved = resolveOpenViolations($violations, $runnerId, $policy);
    $history = readJsonFile(pathHistory($today));
    if ($history !== [] && !isset($history[0])) {
        $history = [];
    }
    $history[] = [
        'runner_id' => $runnerId,
        'lat' => round($lat, 8),
        'lng' => round($lng, 8),
        'speed' => $speed,
        'battery' => $battery,
        'timestamp' => $timeOnly,
        'recorded_at' => $now,
        'address' => $address,
    ];
    writeJsonFile(pathHistory($today), $history);
    jsonOut([
        'ok' => true,
        'runner_id' => $runnerId,
        'recorded_at' => $now,
        'status' => $emp['status'] ?? 'IDLE',
        'compliance' => $emp['compliance'] ?? 'COMPLIANT',
        'resolved_incidents' => array_map(static fn($v) => $v['incident_id'], $resolved),
    ]);
}

function actionReportTurnOff(array $body): void
{
    $policy = loadPolicy();
    $runnerId = trim((string)($body['runner_id'] ?? $body['id'] ?? ''));
    if ($runnerId === '') {
        jsonOut(['ok' => false, 'error' => 'runner_id required'], 400);
    }
    $state = loadState();
    $emp = null;
    foreach ($state as &$r) {
        if ((string)($r['id'] ?? '') !== $runnerId) {
            continue;
        }
        $r['location_sharing'] = false;
        $r['status'] = 'OFFLINE';
        $r['compliance'] = (isMandatoryEmployee($r, $policy) && isWorkingHours($policy)) ? 'IN_VIOLATION' : 'OFF_DUTY';
        $emp = $r;
        break;
    }
    unset($r);
    if ($emp === null) {
        $emp = [
            'id' => $runnerId,
            'name' => (string)($body['name'] ?? $runnerId),
            'designation' => (string)($body['designation'] ?? 'Office Runner'),
            'department' => (string)($body['department'] ?? ''),
            'rank' => 9999,
            'location_sharing' => false,
            'last_lat' => isset($body['lat']) ? (float)$body['lat'] : null,
            'last_lng' => isset($body['lng']) ? (float)$body['lng'] : null,
            'last_known_address' => $body['address'] ?? null,
            'last_ping' => null,
            'status' => 'OFFLINE',
            'compliance' => 'IN_VIOLATION',
        ];
        $state[] = $emp;
    }
    $result = ['created' => false, 'incident' => null];
    if (isMandatoryEmployee($emp, $policy) && isWorkingHours($policy)) {
        $result = openViolation($emp, 'LOCATION_DISABLED_MANUAL', $policy, isset($body['address']) ? (string)$body['address'] : null);
    }
    saveState($state);
    jsonOut(['ok' => true, 'runner_id' => $runnerId, 'compliance' => $emp['compliance'], 'incident_created' => $result['created'], 'incident' => $result['incident']]);
}

function actionGetHistory(array $query): void
{
    $runnerId = trim((string)($query['runner_id'] ?? ''));
    $date = trim((string)($query['date'] ?? rtToday()));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = rtToday();
    }
    if ($runnerId === '') {
        jsonOut(['ok' => false, 'error' => 'runner_id required'], 400);
    }
    $points = [];
    foreach (readJsonFile(pathHistory($date)) as $p) {
        if (!is_array($p) || (string)($p['runner_id'] ?? '') !== $runnerId) {
            continue;
        }
        $ts = (string)($p['recorded_at'] ?? '');
        if ($ts === '' && isset($p['timestamp'])) {
            $ts = $date . ' ' . $p['timestamp'];
        }
        $points[] = [
            'lat' => (float)($p['lat'] ?? 0),
            'lng' => (float)($p['lng'] ?? 0),
            'speed' => isset($p['speed']) ? (float)$p['speed'] : null,
            'battery' => isset($p['battery']) ? (int)$p['battery'] : null,
            'timestamp' => $ts,
            'time' => (string)($p['timestamp'] ?? ''),
            'address' => $p['address'] ?? null,
        ];
    }
    usort($points, static fn($a, $b) => strcmp((string)$a['timestamp'], (string)$b['timestamp']));
    jsonOut(['ok' => true, 'runner_id' => $runnerId, 'date' => $date, 'points' => $points, 'stats' => summarizeTrail($points)]);
}

function actionGetViolations(array $query): void
{
    $status = strtoupper(trim((string)($query['status'] ?? 'ALL')));
    $rows = loadViolations();
    if ($status === 'OPEN' || $status === 'RESOLVED') {
        $rows = array_values(array_filter($rows, static fn($v) => ($v['status'] ?? '') === $status));
    }
    usort($rows, static fn($a, $b) => strcmp((string)($b['detected_at'] ?? ''), (string)($a['detected_at'] ?? '')));
    jsonOut(['ok' => true, 'violations' => $rows, 'open_count' => count(array_filter(loadViolations(), static fn($v) => ($v['status'] ?? '') === 'OPEN'))]);
}

function actionResolveViolation(array $body): void
{
    $policy = loadPolicy();
    $id = trim((string)($body['incident_id'] ?? ''));
    if ($id === '') {
        jsonOut(['ok' => false, 'error' => 'incident_id required'], 400);
    }
    $violations = loadViolations();
    $found = false;
    $now = rtNow($policy);
    foreach ($violations as &$v) {
        if ((string)($v['incident_id'] ?? '') !== $id) {
            continue;
        }
        $found = true;
        if (($v['status'] ?? '') === 'OPEN') {
            $detected = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)($v['detected_at'] ?? ''), rtTimezone($policy));
            $mins = 0;
            if ($detected instanceof DateTimeImmutable) {
                $mins = (int)max(0, floor(($now->getTimestamp() - $detected->getTimestamp()) / 60));
            }
            $v['resolved_at'] = $now->format('Y-m-d H:i:s');
            $v['duration_minutes'] = $mins;
            $v['status'] = 'RESOLVED';
        }
        break;
    }
    unset($v);
    if (!$found) {
        jsonOut(['ok' => false, 'error' => 'Not found'], 404);
    }
    saveViolations($violations);
    jsonOut(['ok' => true, 'incident_id' => $id]);
}

function actionExportViolationsCsv(array $query): void
{
    $rows = loadViolations();
    $status = strtoupper(trim((string)($query['status'] ?? 'ALL')));
    if ($status === 'OPEN' || $status === 'RESOLVED') {
        $rows = array_values(array_filter($rows, static fn($v) => ($v['status'] ?? '') === $status));
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hr_policy_violation_report.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Incident ID', 'Date', 'Employee Name', 'Designation', 'Department', 'Violation Type', 'Disconnected At', 'Reconnected At', 'Total Offline Duration (mins)', 'Last Known Lat', 'Last Known Lng', 'Last Known Location', 'Status']);
    foreach ($rows as $v) {
        fputcsv($out, [
            $v['incident_id'] ?? '', substr((string)($v['detected_at'] ?? ''), 0, 10), $v['name'] ?? '', $v['designation'] ?? '', $v['department'] ?? '',
            $v['violation_type'] ?? '', $v['detected_at'] ?? '', $v['resolved_at'] ?? '', $v['duration_minutes'] ?? '',
            $v['last_known_lat'] ?? '', $v['last_known_lng'] ?? '', $v['last_known_address'] ?? '', $v['status'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

function actionExportViolationsPrint(array $query): void
{
    $policy = loadPolicy();
    $rows = loadViolations();
    $status = strtoupper(trim((string)($query['status'] ?? 'ALL')));
    if ($status === 'OPEN' || $status === 'RESOLVED') {
        $rows = array_values(array_filter($rows, static fn($v) => ($v['status'] ?? '') === $status));
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>HR Policy Violation Report</title><style>body{font-family:system-ui;margin:24px;font-size:12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #e2e8f0;padding:7px;text-align:left}th{background:#f1f5f9;font-size:10px;text-transform:uppercase}</style></head><body>';
    echo '<button onclick="window.print()">Print</button><h1>HR Policy Violation &amp; Disconnect Report</h1>';
    echo '<p>Master: ' . htmlspecialchars((string)$policy['master_tracking_email']) . ' · ' . htmlspecialchars(rtNowStr($policy)) . '</p><table><tr><th>ID</th><th>Name</th><th>Type</th><th>Disconnected</th><th>Reconnected</th><th>Mins</th><th>Location</th><th>Status</th></tr>';
    foreach ($rows as $v) {
        echo '<tr><td>' . htmlspecialchars((string)($v['incident_id'] ?? '')) . '</td><td>' . htmlspecialchars((string)($v['name'] ?? '')) . '</td><td>' . htmlspecialchars((string)($v['violation_type'] ?? '')) . '</td><td>' . htmlspecialchars((string)($v['detected_at'] ?? '')) . '</td><td>' . htmlspecialchars((string)($v['resolved_at'] ?? '')) . '</td><td>' . htmlspecialchars((string)($v['duration_minutes'] ?? '')) . '</td><td>' . htmlspecialchars((string)($v['last_known_address'] ?? '')) . '</td><td>' . htmlspecialchars((string)($v['status'] ?? '')) . '</td></tr>';
    }
    echo '</table></body></html>';
    exit;
}

/** @return list<array<string,mixed>> */
function buildTravelAudit(string $from, string $to, ?string $runnerId): array
{
    $fromTs = strtotime($from);
    $toTs = strtotime($to);
    if ($fromTs === false || $toTs === false || $fromTs > $toTs) {
        return [];
    }
    $names = [];
    foreach (loadState() as $r) {
        $names[(string)($r['id'] ?? '')] = (string)($r['name'] ?? '');
    }
    $rows = [];
    for ($t = $fromTs; $t <= $toTs; $t += 86400) {
        $date = date('Y-m-d', $t);
        $by = [];
        foreach (readJsonFile(pathHistory($date)) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $rid = (string)($p['runner_id'] ?? '');
            if ($rid === '' || ($runnerId && $rid !== $runnerId)) {
                continue;
            }
            $ts = (string)($p['recorded_at'] ?? '');
            if ($ts === '' && isset($p['timestamp'])) {
                $ts = $date . ' ' . $p['timestamp'];
            }
            $by[$rid][] = ['lat' => (float)$p['lat'], 'lng' => (float)$p['lng'], 'speed' => $p['speed'] ?? null, 'timestamp' => $ts];
        }
        foreach ($by as $rid => $pts) {
            usort($pts, static fn($a, $b) => strcmp($a['timestamp'], $b['timestamp']));
            $stats = summarizeTrail($pts);
            $rows[] = ['date' => $date, 'runner_id' => $rid, 'name' => $names[$rid] ?? $rid, 'start' => $stats['start'], 'end' => $stats['end'], 'distance_km' => $stats['distance_km'], 'active_hours' => round($stats['active_seconds'] / 3600, 2), 'idle_hours' => round($stats['idle_seconds'] / 3600, 2), 'point_count' => $stats['point_count']];
        }
    }
    return $rows;
}

function actionAudit(array $query): void
{
    $from = (string)($query['from'] ?? date('Y-m-d', strtotime('-14 days')));
    $to = (string)($query['to'] ?? rtToday());
    $rid = isset($query['runner_id']) ? trim((string)$query['runner_id']) : null;
    jsonOut(['ok' => true, 'rows' => buildTravelAudit($from, $to, $rid !== '' ? $rid : null)]);
}

function actionExportCsv(array $query): void
{
    $from = (string)($query['from'] ?? date('Y-m-d', strtotime('-14 days')));
    $to = (string)($query['to'] ?? rtToday());
    $rid = isset($query['runner_id']) ? trim((string)$query['runner_id']) : null;
    $rows = buildTravelAudit($from, $to, $rid !== '' ? $rid : null);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="runner_travel_audit.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Date', 'Runner ID', 'Name', 'Start', 'End', 'Distance km', 'Active h', 'Idle h', 'Points']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['date'], $r['runner_id'], $r['name'], $r['start'], $r['end'], $r['distance_km'], $r['active_hours'], $r['idle_hours'], $r['point_count']]);
    }
    fclose($out);
    exit;
}

// Router
rtEnsureDir();
if (!is_file(pathPolicy())) {
    writeJsonFile(pathPolicy(), defaultPolicy());
}
if (!is_file(pathViolations())) {
    writeJsonFile(pathViolations(), []);
}
if (!is_file(pathState())) {
    writeJsonFile(pathState(), seedFromTeam());
}

$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? 'ui')));
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$body = requestBody();

if ($action !== 'ui') {
    try {
        switch ($action) {
            case 'get_runners': actionGetRunners(); break;
            case 'update_location':
                if ($method !== 'POST') { jsonOut(['ok' => false, 'error' => 'POST required'], 405); }
                actionUpdateLocation($body); break;
            case 'report_turn_off':
                if ($method !== 'POST') { jsonOut(['ok' => false, 'error' => 'POST required'], 405); }
                actionReportTurnOff($body); break;
            case 'get_history': actionGetHistory($_GET); break;
            case 'get_policy': actionGetPolicy(); break;
            case 'save_policy':
                if ($method !== 'POST') { jsonOut(['ok' => false, 'error' => 'POST required'], 405); }
                actionSavePolicy($body); break;
            case 'get_violations': actionGetViolations($_GET); break;
            case 'resolve_violation':
                if ($method !== 'POST') { jsonOut(['ok' => false, 'error' => 'POST required'], 405); }
                actionResolveViolation($body); break;
            case 'export_violations_csv': actionExportViolationsCsv($_GET); break;
            case 'export_violations_print': actionExportViolationsPrint($_GET); break;
            case 'audit': actionAudit($_GET); break;
            case 'export_csv': actionExportCsv($_GET); break;
            default: jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
        }
    } catch (Throwable $e) {
        jsonOut(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

$mapsKey = '';
if (is_file(RT_ROOT . '/data/company.json')) {
    $co = readJsonFile(RT_ROOT . '/data/company.json');
    $c0 = isset($co[0]) && is_array($co[0]) ? $co[0] : $co;
    $mapsKey = (string)($c0['google_maps_api_key'] ?? $c0['maps_api_key'] ?? '');
}
if ($mapsKey === '' && defined('GOOGLE_MAPS_API_KEY')) {
    $mapsKey = (string)GOOGLE_MAPS_API_KEY;
}
$self = htmlspecialchars((string)($_SERVER['SCRIPT_NAME'] ?? '/runners.php'), ENT_QUOTES, 'UTF-8');
require __DIR__ . '/runners_ui.php';
