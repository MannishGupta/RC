<?php
declare(strict_types=1);

// Version: 260916.14
// CHANGELOG v1.3: previously had ZERO authentication and printed every team
// member's full name to any visitor. Now requires an active admin/crm
// session, matching the pattern used elsewhere (print.php, flush_cache.php).
// This script identifies team records with non-canonical location IDs.

$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
// BUG FIX: this file defined DATA_PATH/IMG_PATH/DOC_PATH directly from
// BASE_PATH, completely bypassing tenant resolution -- on a multi-tenant
// deployment (tenants/{id}/data/, resolved by host in
// app/tenant_bootstrap.php), every request to this file read and wrote
// the WRONG tenant's data regardless of which domain it was requested on.
// Fixed to require tenant_bootstrap.php first (as index.php and the
// correctly-wired standalone entry points already do) and guard every
// fallback define with if (!defined(...)) so tenant_bootstrap's
// resolution always wins when available, with these as the fallback for
// a genuine single-tenant install with no tenants/ folder at all.
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH'))  if (!defined('IMG_PATH')) define('IMG_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/images');
if (!defined('DOC_PATH'))  if (!defined('DOC_PATH')) define('DOC_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/documents');
if (!defined('SESSION_PATH')) define('SESSION_PATH', DATA_PATH . '/sessions');

if (!file_exists(SESSION_PATH)) @mkdir(SESSION_PATH, 0755, true);

if (!file_exists(BASE_PATH . '/app/bootstrap.php')) {
    die("Critical Error: app/bootstrap.php is missing. Please place this script in the root directory.");
}
require_once BASE_PATH . '/app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) AppAuth::initSession();
if (empty($_SESSION['user']) || ($_SESSION['user'] !== 'admin' && $_SESSION['user'] !== 'crm')) {
    http_response_code(403);
    die("<div style='padding:20px;font-family:sans-serif;color:red;font-weight:bold;'>Access Denied. Please log in via the main dashboard.</div>");
}

function runLocationAudit(): void {
    echo "<pre>--- Location Integrity Audit ---\n";

    try {
        if (!class_exists('AppDB')) {
            throw new Exception("AppDB class not found in app/bootstrap.php");
        }
        $team = AppDB::read('team') ?: [];
        $locations = AppDB::read('locations') ?: [];
    } catch (Throwable $e) {
        die("Fatal Error during DB Read: " . $e->getMessage());
    }

    $canonicalIds = array_map(fn($l) => (string)($l['id'] ?? ''), $locations);
    $canonicalIds = array_filter($canonicalIds);

    $mismatches = [];
    $totalRecords = count($team);

    foreach ($team as $member) {
        $locId = (string)($member['location_id'] ?? '');
        $name  = $member['name'] ?? 'Unknown Member';

        if ($locId === '') {
            $mismatches[] = ['name' => $name, 'issue' => 'Missing location_id', 'value' => 'NULL'];
            continue;
        }

        if (!in_array($locId, $canonicalIds, true)) {
            $mismatches[] = ['name' => $name, 'issue' => 'Non-canonical (Needs Normalization)', 'value' => $locId];
        }
    }

    if (empty($mismatches)) {
        echo "✅ Success: All {$totalRecords} records have valid, canonical location IDs.\n";
    } else {
        echo "⚠️ Found " . count($mismatches) . " issues out of {$totalRecords} records:\n\n";
        printf("%-25s | %-35s | %-20s\n", "Member Name", "Issue Type", "Current Value");
        echo str_repeat("-", 85) . "\n";
        foreach ($mismatches as $m) {
            printf("%-25s | %-35s | %-20s\n", htmlspecialchars((string)$m['name']), $m['issue'], htmlspecialchars((string)$m['value']));
        }
        echo "\n👉 Action: Run System Optimizer (from the Monitor/Optimise tab) to resolve these automatically.\n";
    }
    echo "</pre>";
}

runLocationAudit();
