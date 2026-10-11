<?php
declare(strict_types=1);
$target = 'https://rc.fusionlimited.in/status/';
$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Cache-Control: no-store');
header('Location: ' . $target . $qs, true, 301);
header('X-Robots-Tag: noindex');
exit;
