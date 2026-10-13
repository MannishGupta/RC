<?php
/**
 * Place this file at: https://fusionlimited.in/status/admin.php
 * Permanent redirect to the RC host admin console.
 */
$target = 'https://rc.fusionlimited.in/status/admin.php';
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Cache-Control: no-store');
header('Location: ' . $target . $qs, true, 301);
header('X-Robots-Tag: noindex');
exit;
