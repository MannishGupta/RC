<?php

declare(strict_types=1);

const STATUS_VERSION      = '20261011.1';
const STATUS_VERSION_DATE = '11 Oct 2026';
const STATUS_RELEASE      = '14.1.0';

if (!defined('APP_VERSION'))      { define('APP_VERSION', STATUS_VERSION); }
if (!defined('APP_VERSION_DATE')) { define('APP_VERSION_DATE', STATUS_VERSION_DATE); }

 
function statusVersionIsInherited(): bool {
    return APP_VERSION !== STATUS_VERSION;
}

 
function statusVersionLabel(): string {
    return 'v' . APP_VERSION . ' · ' . APP_VERSION_DATE;
}

 
if (PHP_SAPI !== 'cli' && realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'module'            => 'status',
        'release'           => STATUS_RELEASE,
        'version'           => STATUS_VERSION,
        'version_date'      => STATUS_VERSION_DATE,
        'effective_version' => APP_VERSION,
        'inherited'         => statusVersionIsInherited(),
        'php'               => PHP_VERSION,
        'endpoints'         => [
            'dashboard' => 'index.php',
            'report'    => 'report.php',
            'privacy'   => 'privacy.php',
            'sitemap'   => 'sitemap.php',
            'fragment'  => 'status-tab.php',
            'health'    => 'version.php',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
