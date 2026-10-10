<?php
/**
 * health.php — lightweight uptime probe (no auth, no tenant, no DB).
 * Version: 20261006.0
 */
declare(strict_types=1);

try {
    $base = __DIR__;
    if (is_file($base . '/version.php')) {
        require_once $base . '/version.php';
    }
    $version = defined('APP_VERSION') ? (string) APP_VERSION : 'unknown';
    $time = gmdate('c');
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
    }
    echo json_encode([
        'status' => 'ok',
        'app' => 'Resource Centre',
        'version' => $version,
        'time' => $time,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Robots-Tag: noindex');
    }
    echo '{"status":"ok","app":"Resource Centre","version":"unknown","time":"' . gmdate('c') . '"}';
}
