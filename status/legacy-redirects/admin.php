// Version: 1.2
<?php
declare(strict_types=1);

/**
 * Fallback permanent redirect handler for fusionlimited.in/status/admin.php
 * Target: https://rc.fusionlimited.in/status/admin.php
 */

$destination = 'https://rc.fusionlimited.in/status/admin.php';

if (!empty($_SERVER['QUERY_STRING'])) {
    $destination .= '?' . $_SERVER['QUERY_STRING'];
}

header('HTTP/1.1 301 Moved Permanently', true, 301);
header('Location: ' . $destination, true, 301);
header('X-Robots-Tag: noindex, nofollow', true);
header('Connection: close');
exit;