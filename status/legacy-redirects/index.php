// Version: 1.0
<?php
declare(strict_types=1);

/**
 * Fallback permanent redirect handler for https://fusionlimited.in/status/
 * Environment: Windows Server / IIS / PHP 8.4
 */

$targetHost = 'https://rc.fusionlimited.in/status/';
$queryString = $_SERVER['QUERY_STRING'] ?? '';

$destination = $targetHost . ($queryString !== '' ? '?' . $queryString : '');

header('X-Robots-Tag: noindex, nofollow', true);
header('Location: ' . $destination, true, 301);
exit;