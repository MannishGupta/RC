<?php
declare(strict_types=1);
/**
 * tools/migrate_tenants.php — Idempotent seed of new schema keys for every tenant.
 *
 * Ensures:
 *  - company.dpdp (grievance officer / contact) structure
 *  - config/mediakit_connections.json skeleton
 * Does NOT overwrite existing non-empty values.
 *
 * CLI: php tools/migrate_tenants.php
 * Web: super-admin only
 */
$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

if (!$isCli) {
    require_once BASE_PATH . '/app/tenant_bootstrap.php';
    require_once BASE_PATH . '/app/bootstrap.php';
    if (session_status() === PHP_SESSION_NONE) {
        AppAuth::initSession();
    }
    $user = (string)($_SESSION['user'] ?? '');
    $true = (string)($_SESSION['true_role'] ?? $user);
    if (!in_array($user, ['super_admin'], true) && !in_array($true, ['super_admin'], true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Super Admin only']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('verify_csrf')) {
        verify_csrf();
    }
}

$tenantsDir = BASE_PATH . '/tenants';
$ids = [];
$mapFile = $tenantsDir . '/map.json';
if (is_file($mapFile)) {
    $map = json_decode((string)@file_get_contents($mapFile), true);
    if (is_array($map)) {
        // map shapes: {exact:{host:id}} or {tenants:[]} or flat id list
        if (isset($map['exact']) && is_array($map['exact'])) {
            foreach ($map['exact'] as $host => $id) {
                $ids[] = (string)$id;
            }
        }
        if (isset($map['tenants']) && is_array($map['tenants'])) {
            foreach ($map['tenants'] as $t) {
                if (is_string($t)) {
                    $ids[] = $t;
                } elseif (is_array($t) && isset($t['id'])) {
                    $ids[] = (string)$t['id'];
                }
            }
        }
        foreach ($map as $k => $v) {
            if (is_string($v) && is_dir($tenantsDir . '/' . $v)) {
                $ids[] = $v;
            }
            if (is_string($k) && is_dir($tenantsDir . '/' . $k) && $k !== 'exact') {
                $ids[] = $k;
            }
        }
    }
}
foreach (scandir($tenantsDir) ?: [] as $d) {
    if ($d === '.' || $d === '..' || str_starts_with($d, '.')) {
        continue;
    }
    if (is_dir($tenantsDir . '/' . $d) && is_dir($tenantsDir . '/' . $d . '/data')) {
        $ids[] = $d;
    }
}
$ids = array_values(array_unique(array_filter($ids)));

$report = [];

foreach ($ids as $tid) {
    $dataPath = $tenantsDir . '/' . $tid . '/data';
    if (!is_dir($dataPath)) {
        $report[$tid] = ['skipped' => 'no data dir'];
        continue;
    }
    $added = [];
    $present = [];

    // company.dpdp
    $coFile = $dataPath . '/company.json';
    $co = [];
    if (is_file($coFile)) {
        $co = json_decode((string)@file_get_contents($coFile), true);
        if (!is_array($co)) {
            $co = [];
        }
    }
    // singleton or list
    $isList = isset($co[0]) && is_array($co[0]);
    $rec = $isList ? $co[0] : $co;
    if (!is_array($rec)) {
        $rec = [];
    }
    if (!isset($rec['dpdp']) || !is_array($rec['dpdp'])) {
        $rec['dpdp'] = [
            'grievance_officer_name' => '',
            'grievance_officer_email' => '',
            'grievance_officer_phone' => '',
            'privacy_contact_email' => '',
            'retention_note' => '',
        ];
        $added[] = 'company.dpdp';
    } else {
        foreach (['grievance_officer_name', 'grievance_officer_email', 'grievance_officer_phone', 'privacy_contact_email', 'retention_note'] as $k) {
            if (!array_key_exists($k, $rec['dpdp'])) {
                $rec['dpdp'][$k] = '';
                $added[] = 'company.dpdp.' . $k;
            } else {
                $present[] = 'company.dpdp.' . $k;
            }
        }
    }
    if ($isList) {
        $co[0] = $rec;
    } else {
        $co = $rec;
    }
    if ($added !== []) {
        @file_put_contents($coFile, json_encode($co, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }

    // mediakit_connections
    $mkDir = $dataPath . '/config';
    $mkFile = $mkDir . '/mediakit_connections.json';
    if (!is_file($mkFile)) {
        if (!is_dir($mkDir)) {
            @mkdir($mkDir, 0775, true);
        }
        $skeleton = [
            'meta' => ['access_token' => '', 'ig_user_id' => '', 'page_id' => '', 'enabled' => false],
            'youtube' => ['api_key' => '', 'channel_id' => '', 'enabled' => false],
            'linkedin' => ['access_token' => '', 'org_id' => '', 'enabled' => false],
            'x' => ['bearer_token' => '', 'user_id' => '', 'enabled' => false],
            'rss' => ['feed_url' => '', 'enabled' => false],
        ];
        @file_put_contents($mkFile, json_encode($skeleton, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
        $added[] = 'config/mediakit_connections.json';
    } else {
        $present[] = 'config/mediakit_connections.json';
    }

    $report[$tid] = ['added' => $added, 'already' => $present];
}

if (class_exists('AuditLog')) {
    try {
        AuditLog::write('migrate_tenants', ['tenants' => count($ids)]);
    } catch (Throwable $e) {
        // ignore
    }
}

$payload = ['status' => 'success', 'tenants' => count($ids), 'report' => $report];
if ($isCli) {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}
header('Content-Type: application/json');
echo json_encode($payload);
exit;
