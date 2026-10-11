<?php
/**
 * Place this file at: https://fusionlimited.in/status/index.php
 * (or as the directory default for /status/)
 * Permanent redirect to the RC host.
 */
$target = 'https://rc.fusionlimited.in/status/';
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Cache-Control: no-store');
header('Location: ' . $target . $qs, true, 301);
header('X-Robots-Tag: noindex');
exit;
