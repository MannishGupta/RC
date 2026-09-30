<?php
/**
 * Version: 1.0
 * Delivery Routing & Dispatch — JSON flat-file module for Resource Center
 *
 * Drop at site root (or /dispatch/) and open:
 *   dispatch.php              — Admin map + assign + dispatch console
 *   dispatch.php?view=mobile  — Field staff mobile page (Roman Hindi)
 *   dispatch.php?action=...   — JSON API
 *
 * Data dir: __DIR__ . '/data/' (routes, assignments, dispatches, settings)
 * Optional: reads ../data/team.json and ../data/cartags.json if present.
 */
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');

define('DISPATCH_ROOT', __DIR__);
// Unified writable root: {site}/data/dispatch (legacy: dispatch/data)
$_dd = dirname(DISPATCH_ROOT) . '/data/dispatch';
$_ddLegacy = DISPATCH_ROOT . '/data';
if (!is_dir($_dd) && is_dir($_ddLegacy)) {
    @mkdir($_dd, 0775, true);
    foreach (glob($_ddLegacy . '/*.json') ?: [] as $_f) {
        $_dest = $_dd . '/' . basename($_f);
        if (!is_file($_dest)) {
            @copy($_f, $_dest);
        }
    }
}
define('DISPATCH_DATA', is_dir($_dd) || @mkdir($_dd, 0775, true) ? $_dd : $_ddLegacy);

// ── Helpers ──────────────────────────────────────────────────────────────────

function d_json_path(string $name): string
{
    return DISPATCH_DATA . '/' . $name . '.json';
}

/** @return array<mixed> */
function d_read(string $name): array
{
    $path = d_json_path($name);
    if (!is_file($path)) {
        return [];
    }
    $raw = json_decode((string) @file_get_contents($path), true);
    return is_array($raw) ? $raw : [];
}

/** @param array<mixed> $data */
function d_write(string $name, array $data): bool
{
    if (!is_dir(DISPATCH_DATA)) {
        @mkdir(DISPATCH_DATA, 0775, true);
    }
    $path = d_json_path($name);
    $tmp = $path . '.' . getmypid() . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $fp = @fopen($tmp, 'cb');
    if (!$fp) {
        return false;
    }
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        @unlink($tmp);
        return false;
    }
    ftruncate($fp, 0);
    $ok = fwrite($fp, $json) !== false;
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    if (!$ok) {
        @unlink($tmp);
        return false;
    }
    return @rename($tmp, $path);
}

function d_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function d_id(string $prefix = 'ID'): string
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/** @return array<string, mixed> */
function d_input(): array
{
    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($ct, 'application/json') !== false) {
        $j = json_decode((string) file_get_contents('php://input'), true);
        return is_array($j) ? $j : [];
    }
    return array_merge($_GET, $_POST);
}

function d_send(array $payload, int $code = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function d_truthy($v): bool
{
    return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
}

/** Designation keys flagged for mandatory live tracking */
function d_tracking_desig_keys(): array
{
    $candidates = [
        dirname(DISPATCH_ROOT) . '/data/designations.json',
        DISPATCH_ROOT . '/../data/designations.json',
        DISPATCH_DATA . '/designations.json',
    ];
    $keys = [];
    foreach ($candidates as $p) {
        if (!is_file($p)) {
            continue;
        }
        $raw = json_decode((string) @file_get_contents($p), true);
        if (!is_array($raw)) {
            continue;
        }
        if (isset($raw['items']) && is_array($raw['items'])) {
            $raw = $raw['items'];
        }
        foreach ($raw as $d) {
            if (!is_array($d)) {
                continue;
            }
            if (!d_truthy($d['mandatory_live_tracking'] ?? $d['require_live_tracking'] ?? $d['live_tracking'] ?? false)) {
                continue;
            }
            foreach (['id', 'code', 'slug', 'name'] as $f) {
                $k = strtolower(trim((string) ($d[$f] ?? '')));
                if ($k !== '') {
                    $keys[$k] = true;
                }
            }
        }
        if ($keys !== []) {
            break;
        }
    }
    return $keys;
}

/**
 * Field staff only: designation has Mandatory live tracking, or member flag is on.
 */
function d_team(): array
{
    $trackKeys = d_tracking_desig_keys();
    $candidates = [
        dirname(DISPATCH_ROOT) . '/data/team.json',
        DISPATCH_ROOT . '/../data/team.json',
        DISPATCH_DATA . '/team.json',
    ];
    foreach ($candidates as $p) {
        if (!is_file($p)) {
            continue;
        }
        $raw = json_decode((string) @file_get_contents($p), true);
        if (!is_array($raw)) {
            continue;
        }
        if (isset($raw['items']) && is_array($raw['items'])) {
            $raw = $raw['items'];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? $row['slug'] ?? '');
            if ($id === '') {
                continue;
            }
            $memberTrack = d_truthy($row['is_tracking_enabled'] ?? $row['mandatory_live_tracking'] ?? false);
            $desigHit = false;
            foreach (['designation_id', 'designation_code', 'designation', 'designation_name', 'labels'] as $f) {
                $k = strtolower(trim((string) ($row[$f] ?? '')));
                if ($k !== '' && isset($trackKeys[$k])) {
                    $desigHit = true;
                    break;
                }
            }
            // If no designation flags exist in data, fall back to common field roles by name
            if ($trackKeys === []) {
                $dn = strtolower((string) ($row['designation_name'] ?? $row['designation'] ?? ''));
                if (preg_match('/\b(runner|driver|field\s*boy|delivery|rider|logistics)\b/i', $dn)) {
                    $desigHit = true;
                }
            }
            if (!$memberTrack && !$desigHit) {
                continue;
            }
            $out[] = [
                'id' => $id,
                'name' => (string) ($row['name'] ?? 'Staff'),
                'designation' => (string) ($row['designation_name'] ?? $row['designation'] ?? ''),
                'phone' => (string) ($row['phone'] ?? $row['mobile'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'tracking' => true,
            ];
        }
        if ($out !== []) {
            return $out;
        }
    }
    return [
        ['id' => 'RUN-01', 'name' => 'Rajesh Kumar', 'designation' => 'Office Runner', 'phone' => '', 'slug' => 'rajesh', 'tracking' => true],
        ['id' => 'DRV-01', 'name' => 'Suresh Yadav', 'designation' => 'Driver', 'phone' => '', 'slug' => 'suresh', 'tracking' => true],
        ['id' => 'FLD-01', 'name' => 'Amit Singh', 'designation' => 'Field Boy', 'phone' => '', 'slug' => 'amit', 'tracking' => true],
    ];
}

/** Standard locations from Premises Registry (locations.json) */
function d_locations(): array
{
    $candidates = [
        dirname(DISPATCH_ROOT) . '/data/locations.json',
        DISPATCH_ROOT . '/../data/locations.json',
        DISPATCH_DATA . '/locations.json',
    ];
    foreach ($candidates as $p) {
        if (!is_file($p)) {
            continue;
        }
        $raw = json_decode((string) @file_get_contents($p), true);
        if (!is_array($raw)) {
            continue;
        }
        if (isset($raw['items']) && is_array($raw['items'])) {
            $raw = $raw['items'];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? $row['slug'] ?? '');
            $name = trim((string) ($row['name'] ?? $row['title'] ?? $row['label'] ?? ''));
            if ($name === '' && $id === '') {
                continue;
            }
            if ($name === '') {
                $name = $id;
            }
            $addr = trim((string) ($row['address'] ?? $row['full_address'] ?? $row['line1'] ?? ''));
            $city = trim((string) ($row['city'] ?? ''));
            $label = $name;
            if ($city !== '') {
                $label .= ' · ' . $city;
            }
            if ($addr !== '') {
                $label .= ' — ' . $addr;
            }
            $lat = (float) ($row['lat'] ?? $row['latitude'] ?? 0);
            $lng = (float) ($row['lng'] ?? $row['longitude'] ?? 0);
            $out[] = [
                'id' => $id !== '' ? $id : ('LOC-' . md5($name)),
                'name' => $name,
                'label' => $label,
                'address' => $addr,
                'city' => $city,
                'lat' => $lat,
                'lng' => $lng,
            ];
        }
        if ($out !== []) {
            usort($out, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
            return $out;
        }
    }
    return [];
}

function d_vehicles(): array
{
    $candidates = [
        dirname(DISPATCH_ROOT) . '/data/cartags.json',
        DISPATCH_ROOT . '/../data/cartags.json',
        DISPATCH_DATA . '/cartags.json',
    ];
    foreach ($candidates as $p) {
        if (!is_file($p)) {
            continue;
        }
        $raw = json_decode((string) @file_get_contents($p), true);
        if (!is_array($raw)) {
            continue;
        }
        if (isset($raw['items']) && is_array($raw['items'])) {
            $raw = $raw['items'];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? $row['tag_id'] ?? $row['registration_number'] ?? '');
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'tag_id' => (string) ($row['tag_id'] ?? $id),
                'registration' => (string) ($row['registration_number'] ?? $row['plate'] ?? ''),
                'make_model' => (string) ($row['make_model'] ?? ''),
                'colour' => (string) ($row['colour'] ?? $row['color'] ?? ''),
                'member_id' => (string) ($row['member_id'] ?? ''),
            ];
        }
        if ($out !== []) {
            return $out;
        }
    }
    return [
        ['id' => 'VEH-01', 'tag_id' => 'TAG-101', 'registration' => 'DL01AB1234', 'make_model' => 'Maruti Eeco', 'colour' => 'White', 'member_id' => ''],
        ['id' => 'VEH-02', 'tag_id' => 'TAG-102', 'registration' => 'DL02CD5678', 'make_model' => 'Honda Activa', 'colour' => 'Blue', 'member_id' => ''],
        ['id' => 'VEH-03', 'tag_id' => 'TAG-103', 'registration' => 'DL03EF9012', 'make_model' => 'Tata Ace', 'colour' => 'Silver', 'member_id' => ''],
    ];
}

function d_settings(): array
{
    $s = d_read('settings');
    if ($s === [] || !isset($s['default_lat'])) {
        $s = [
            'google_maps_api_key' => '',
            'default_city' => 'Delhi',
            'default_lat' => 28.6139,
            'default_lng' => 77.2090,
            'dispatch_language' => 'hinglish',
        ];
    }
    // Prefer parent company key if present
    $companyPaths = [
        dirname(DISPATCH_ROOT) . '/data/company.json',
        DISPATCH_ROOT . '/../data/company.json',
    ];
    foreach ($companyPaths as $cp) {
        if (!is_file($cp)) {
            continue;
        }
        $c = json_decode((string) @file_get_contents($cp), true);
        if (isset($c[0]) && is_array($c[0])) {
            $c = $c[0];
        }
        if (is_array($c)) {
            $k = trim((string) ($c['google_maps_api_key'] ?? $c['maps_api_key'] ?? ''));
            if ($k !== '') {
                $s['google_maps_api_key'] = $k;
            }
        }
        break;
    }
    return $s;
}

/**
 * Build simple conversational Roman Hindi (Hinglish) dispatch message.
 */
function d_hinglish_message(array $job): string
{
    $name = trim((string) ($job['runner_name'] ?? 'Bhai'));
    $first = explode(' ', $name)[0] ?: 'Bhai';
    $to = trim((string) ($job['destination'] ?? $job['address'] ?? 'location'));
    $from = trim((string) ($job['origin'] ?? ''));
    $item = trim((string) ($job['item'] ?? $job['parcel'] ?? 'samaan'));
    $note = trim((string) ($job['notes'] ?? $job['instructions'] ?? ''));
    $vehicle = trim((string) ($job['vehicle_label'] ?? ''));
    $contact = trim((string) ($job['contact_name'] ?? ''));
    $phone = trim((string) ($job['contact_phone'] ?? ''));

    $lines = [];
    $lines[] = "Namaste {$first} ji 👋";
    $lines[] = '';
    $lines[] = "Aapka naya delivery task ready hai.";
    $lines[] = '';
    if ($item !== '') {
        $lines[] = "📦 Kya le jaana hai: {$item}";
    }
    if ($from !== '') {
        $lines[] = "📍 Uthana kahan se: {$from}";
    }
    $lines[] = "🏁 Pahunchana kahan: {$to}";
    if ($contact !== '' || $phone !== '') {
        $who = trim($contact . ($phone !== '' ? " ({$phone})" : ''));
        $lines[] = "👤 Milega: {$who}";
    }
    if ($vehicle !== '') {
        $lines[] = "🚗 Gaadi: {$vehicle}";
    }
    if ($note !== '') {
        $lines[] = '';
        $lines[] = "⚠️ Extra baat: {$note}";
    }
    $lines[] = '';
    $lines[] = "Map link neeche hai — open karke seedha chale jao.";
    $lines[] = "Pahunch ke baad status update kar dena. Shukriya!";
    return implode("\n", $lines);
}

function d_maps_link(float $lat, float $lng, string $label = ''): string
{
    $q = $lat . ',' . $lng;
    if ($label !== '') {
        $q = rawurlencode($label) . '/@' . $lat . ',' . $lng . ',16z';
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($label . ' ' . $lat . ',' . $lng);
    }
    return 'https://www.google.com/maps?q=' . rawurlencode((string) $lat . ',' . (string) $lng);
}

// ── API actions ──────────────────────────────────────────────────────────────

$action = (string) ($_GET['action'] ?? ($_POST['action'] ?? ''));
$input = d_input();
if ($action === '' && isset($input['action'])) {
    $action = (string) $input['action'];
}

if ($action !== '') {
    switch ($action) {
        case 'list_all':
            d_send([
                'ok' => true,
                'routes' => d_read('routes'),
                'assignments' => d_read('assignments'),
                'dispatches' => d_read('dispatches'),
                'team' => d_team(),
                'vehicles' => d_vehicles(),
                'locations' => d_locations(),
                'settings' => d_settings(),
            ]);

        case 'save_settings':
            $cur = d_settings();
            if (isset($input['google_maps_api_key'])) {
                $cur['google_maps_api_key'] = trim((string) $input['google_maps_api_key']);
            }
            if (isset($input['default_lat'])) {
                $cur['default_lat'] = (float) $input['default_lat'];
            }
            if (isset($input['default_lng'])) {
                $cur['default_lng'] = (float) $input['default_lng'];
            }
            if (isset($input['default_city'])) {
                $cur['default_city'] = trim((string) $input['default_city']);
            }
            d_write('settings', $cur);
            d_send(['ok' => true, 'settings' => $cur]);

        case 'assign_vehicle':
            $runnerId = trim((string) ($input['runner_id'] ?? ''));
            $vehicleId = trim((string) ($input['vehicle_id'] ?? ''));
            if ($runnerId === '' || $vehicleId === '') {
                d_send(['ok' => false, 'error' => 'runner_id and vehicle_id required'], 400);
            }
            $team = d_team();
            $vehs = d_vehicles();
            $runner = null;
            $vehicle = null;
            foreach ($team as $t) {
                if ($t['id'] === $runnerId) {
                    $runner = $t;
                    break;
                }
            }
            foreach ($vehs as $v) {
                if ($v['id'] === $vehicleId || $v['tag_id'] === $vehicleId) {
                    $vehicle = $v;
                    break;
                }
            }
            if (!$runner || !$vehicle) {
                d_send(['ok' => false, 'error' => 'Runner or vehicle not found'], 404);
            }
            $rows = d_read('assignments');
            // One active vehicle per runner; one runner per vehicle
            $rows = array_values(array_filter($rows, static function ($r) use ($runnerId, $vehicleId) {
                if (!is_array($r)) {
                    return false;
                }
                if (($r['status'] ?? '') === 'inactive') {
                    return true;
                }
                if ((string) ($r['runner_id'] ?? '') === $runnerId) {
                    return false;
                }
                if ((string) ($r['vehicle_id'] ?? '') === $vehicleId) {
                    return false;
                }
                return true;
            }));
            $row = [
                'id' => d_id('ASN'),
                'runner_id' => $runnerId,
                'runner_name' => $runner['name'],
                'vehicle_id' => $vehicle['id'],
                'vehicle_tag' => $vehicle['tag_id'],
                'registration' => $vehicle['registration'],
                'make_model' => $vehicle['make_model'],
                'status' => 'active',
                'assigned_at' => date('c'),
            ];
            $rows[] = $row;
            d_write('assignments', $rows);
            d_send(['ok' => true, 'assignment' => $row, 'assignments' => $rows]);

        case 'unassign_vehicle':
            $asnId = trim((string) ($input['id'] ?? ''));
            $rows = d_read('assignments');
            $found = false;
            foreach ($rows as &$r) {
                if (!is_array($r)) {
                    continue;
                }
                if ((string) ($r['id'] ?? '') === $asnId || (string) ($r['runner_id'] ?? '') === $asnId) {
                    $r['status'] = 'inactive';
                    $r['ended_at'] = date('c');
                    $found = true;
                }
            }
            unset($r);
            d_write('assignments', $rows);
            d_send(['ok' => true, 'updated' => $found, 'assignments' => $rows]);

        case 'create_route':
            $title = trim((string) ($input['title'] ?? 'Delivery'));
            $runnerId = trim((string) ($input['runner_id'] ?? ''));
            $origin = trim((string) ($input['origin'] ?? ''));
            $destination = trim((string) ($input['destination'] ?? ''));
            $olat = (float) ($input['origin_lat'] ?? 0);
            $olng = (float) ($input['origin_lng'] ?? 0);
            $dlat = (float) ($input['dest_lat'] ?? 0);
            $dlng = (float) ($input['dest_lng'] ?? 0);
            $item = trim((string) ($input['item'] ?? ''));
            $notes = trim((string) ($input['notes'] ?? ''));
            $contactName = trim((string) ($input['contact_name'] ?? ''));
            $contactPhone = trim((string) ($input['contact_phone'] ?? ''));

            if ($destination === '' && ($dlat == 0.0 || $dlng == 0.0)) {
                d_send(['ok' => false, 'error' => 'Destination required'], 400);
            }

            $runnerName = '';
            $vehicleLabel = '';
            foreach (d_team() as $t) {
                if ($t['id'] === $runnerId) {
                    $runnerName = $t['name'];
                    break;
                }
            }
            foreach (d_read('assignments') as $a) {
                if (!is_array($a) || ($a['status'] ?? '') !== 'active') {
                    continue;
                }
                if ((string) ($a['runner_id'] ?? '') === $runnerId) {
                    $vehicleLabel = trim(($a['registration'] ?? '') . ' ' . ($a['make_model'] ?? ''));
                    break;
                }
            }

            $settings = d_settings();
            if ($dlat == 0.0 && $dlng == 0.0) {
                $dlat = (float) $settings['default_lat'];
                $dlng = (float) $settings['default_lng'];
            }
            if ($olat == 0.0 && $olng == 0.0) {
                $olat = (float) $settings['default_lat'];
                $olng = (float) $settings['default_lng'];
            }

            $route = [
                'id' => d_id('RTE'),
                'title' => $title !== '' ? $title : 'Delivery',
                'runner_id' => $runnerId,
                'runner_name' => $runnerName,
                'vehicle_label' => $vehicleLabel,
                'origin' => $origin,
                'origin_lat' => $olat,
                'origin_lng' => $olng,
                'destination' => $destination,
                'dest_lat' => $dlat,
                'dest_lng' => $dlng,
                'item' => $item,
                'notes' => $notes,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'status' => 'active', // active | completed | cancelled
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];
            $routes = d_read('routes');
            $routes[] = $route;
            d_write('routes', $routes);
            d_send(['ok' => true, 'route' => $route, 'routes' => $routes]);

        case 'update_route_status':
            $id = trim((string) ($input['id'] ?? ''));
            $status = trim((string) ($input['status'] ?? ''));
            if (!in_array($status, ['active', 'completed', 'cancelled'], true)) {
                d_send(['ok' => false, 'error' => 'Invalid status'], 400);
            }
            $routes = d_read('routes');
            $found = null;
            foreach ($routes as &$r) {
                if (!is_array($r)) {
                    continue;
                }
                if ((string) ($r['id'] ?? '') === $id) {
                    $r['status'] = $status;
                    $r['updated_at'] = date('c');
                    $found = $r;
                    break;
                }
            }
            unset($r);
            d_write('routes', $routes);
            d_send(['ok' => true, 'route' => $found, 'routes' => $routes]);

        case 'dispatch':
            $routeId = trim((string) ($input['route_id'] ?? ''));
            $routes = d_read('routes');
            $route = null;
            foreach ($routes as $r) {
                if (is_array($r) && (string) ($r['id'] ?? '') === $routeId) {
                    $route = $r;
                    break;
                }
            }
            if (!$route) {
                d_send(['ok' => false, 'error' => 'Route not found'], 404);
            }
            $msg = d_hinglish_message($route);
            $mapUrl = d_maps_link((float) $route['dest_lat'], (float) $route['dest_lng'], (string) $route['destination']);
            $fullMsg = $msg . "\n\n🗺️ Map: " . $mapUrl;

            $dispatch = [
                'id' => d_id('DSP'),
                'route_id' => $routeId,
                'runner_id' => (string) ($route['runner_id'] ?? ''),
                'runner_name' => (string) ($route['runner_name'] ?? ''),
                'message' => $fullMsg,
                'message_plain' => $msg,
                'map_url' => $mapUrl,
                'channel' => trim((string) ($input['channel'] ?? 'copy')), // copy | whatsapp | sms
                'created_at' => date('c'),
                'status' => 'sent',
            ];
            $list = d_read('dispatches');
            $list[] = $dispatch;
            // keep last 200
            if (count($list) > 200) {
                $list = array_slice($list, -200);
            }
            d_write('dispatches', $list);

            // WhatsApp deep link if phone known
            $waUrl = '';
            $phone = '';
            foreach (d_team() as $t) {
                if ($t['id'] === ($route['runner_id'] ?? '')) {
                    $phone = preg_replace('/\D+/', '', $t['phone']);
                    break;
                }
            }
            if ($phone !== '') {
                if (strlen($phone) === 10) {
                    $phone = '91' . $phone;
                }
                $waUrl = 'https://wa.me/' . $phone . '?text=' . rawurlencode($fullMsg);
            } else {
                $waUrl = 'https://wa.me/?text=' . rawurlencode($fullMsg);
            }

            d_send([
                'ok' => true,
                'dispatch' => $dispatch,
                'whatsapp_url' => $waUrl,
                'message' => $fullMsg,
            ]);

        case 'runner_jobs':
            $rid = trim((string) ($input['runner_id'] ?? $_GET['runner_id'] ?? ''));
            $routes = array_values(array_filter(d_read('routes'), static function ($r) use ($rid) {
                if (!is_array($r)) {
                    return false;
                }
                if (($r['status'] ?? '') !== 'active') {
                    return false;
                }
                if ($rid === '') {
                    return true;
                }
                return (string) ($r['runner_id'] ?? '') === $rid;
            }));
            d_send(['ok' => true, 'routes' => $routes]);

        default:
            d_send(['ok' => false, 'error' => 'Unknown action'], 400);
    }
}

// ── Views ────────────────────────────────────────────────────────────────────

$view = (string) ($_GET['view'] ?? 'admin');
if ($view === 'mobile') {
    require DISPATCH_ROOT . '/views/dispatch_mobile.php';
    exit;
}

require DISPATCH_ROOT . '/views/dispatch_ui.php';
