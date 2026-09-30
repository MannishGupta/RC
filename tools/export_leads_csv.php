<?php // Version: 260916.14
declare(strict_types=1);
/**
 * tools/export_leads_csv.php — Export captured leads as CSV.
 *
 * CSV, not vCard — leads are prospects to follow up with in a spreadsheet or
 * CRM import, not contacts to save to a phone.
 */

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
require_once BASE_PATH . '/app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) AppAuth::initSession();
if (empty($_SESSION['user']) || ($_SESSION['user'] !== 'admin' && $_SESSION['user'] !== 'crm')) {
    http_response_code(403);
    die("<div style='padding:20px;font-family:sans-serif;color:red;font-weight:bold;'>Access Denied.</div>");
}

$leads = AppDB::read('leads') ?: [];
$team  = AppDB::read('team')  ?: [];
$teamById = [];
foreach ($team as $m) { if (!empty($m['id'])) $teamById[(string)$m['id']] = (string)($m['name'] ?? ''); }

usort($leads, fn($a, $b) => strtotime((string)($b['created_at'] ?? '')) <=> strtotime((string)($a['created_at'] ?? '')));

if (ob_get_length()) ob_end_clean();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . date('ymd') . '_leads.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel does not mangle non-ASCII names
fputcsv($out, ['Date', 'Name', 'Email', 'Phone', 'Company', 'Note', 'Card Owner', 'Status']);
foreach ($leads as $l) {
    fputcsv($out, [
        (string)($l['created_at'] ?? ''),
        (string)($l['name'] ?? ''),
        (string)($l['email'] ?? ''),
        (string)($l['phone'] ?? ''),
        (string)($l['company'] ?? ''),
        (string)($l['note'] ?? ''),
        $teamById[(string)($l['card_owner_id'] ?? '')] ?? '',
        (string)($l['status'] ?? 'new'),
    ]);
}
fclose($out);

if (class_exists('AppLog')) AppLog::info('Leads exported to CSV', ['count' => count($leads), 'by' => $_SESSION['user']]);
