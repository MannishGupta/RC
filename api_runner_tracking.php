<?php
// Version: 1.2 — tenant bootstrap always; auth via $_SESSION[user]
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

$base = __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';

$boot = $base . '/app/bootstrap.php';
if (is_readable($boot)) {
    require_once $boot;
}

require_once $base . '/app/RunnerTracking.php';

if (class_exists('AppAuth') && method_exists('AppAuth', 'initSession')) {
    AppAuth::initSession();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$currentUser = (string)($_SESSION['user'] ?? '');
$isAdmin = in_array($currentUser, ['admin', 'super_admin'], true);
if (!$isAdmin) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized — admin session required']);
    exit;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$query = $_GET;
$body = $_POST;
$ct = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if ($method === 'POST' && stripos($ct, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '[]', true);
    if (is_array($json)) {
        $body = $json;
    }
}

$actor = $currentUser !== '' ? $currentUser : null;

try {
    if (class_exists('App\RunnerTracking')) {
        $svc = \App\RunnerTracking::fromEnv();
    } else {
        $svc = RunnerTracking::fromEnv();
    }
    $svc->handleRequest($method, $query, $body, $actor);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
