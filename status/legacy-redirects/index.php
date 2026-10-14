<?php

$target = 'https://rc.fusionlimited.in/status/';
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Cache-Control: no-store');
header('Location: ' . $target . $qs, true, 301);
header('X-Robots-Tag: noindex');
exit;
