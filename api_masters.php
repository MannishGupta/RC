<?php
declare(strict_types=1);
/**
 * Master directory API — banks & vehicle OEMs (JSON file backed).
 * GET ?kind=banks|oems
 * Version: 20260929.35
 */
define('BASE_PATH', str_replace('\\', '/', dirname(__FILE__)));
require_once BASE_PATH . '/app/tenant_bootstrap.php';
require_once BASE_PATH . '/app/bootstrap.php';
if (is_file(BASE_PATH . '/app/MasterDirectory.php')) {
    require_once BASE_PATH . '/app/MasterDirectory.php';
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
$kind = strtolower((string)($_GET['kind'] ?? 'banks'));
if ($kind === 'oem' || $kind === 'vehicles' || $kind === 'vehicle_oems') {
    $kind = 'oems';
}
if (!class_exists('MasterDirectory')) {
    echo json_encode(['status' => 'error', 'message' => 'MasterDirectory unavailable']);
    exit;
}
$data = MasterDirectory::options($kind === 'oems' ? 'oems' : 'banks');
echo json_encode(['status' => 'ok', 'kind' => $kind, 'items' => $data], JSON_UNESCAPED_UNICODE);
