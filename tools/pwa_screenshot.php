<?php
declare(strict_types=1);
$root = dirname(__DIR__);
if (!defined('BASE_PATH')) define('BASE_PATH', $root);
if (is_file(BASE_PATH . '/version.php')) require_once BASE_PATH . '/version.php';
$ver = defined('APP_VERSION') ? (string)APP_VERSION : '1';
$form = strtolower((string)($_GET['form'] ?? 'wide'));
$w = ($form === 'narrow') ? 750 : 1280;
$h = ($form === 'narrow') ? 1334 : 720;
if (!function_exists('imagecreatetruecolor')) { http_response_code(501); exit('GD required'); }
$im = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($im, 0, 120, 212);
$card = imagecolorallocate($im, 255, 255, 255);
$ink = imagecolorallocate($im, 32, 31, 30);
$muted = imagecolorallocate($im, 96, 94, 92);
imagefilledrectangle($im, 0, 0, $w, $h, $bg);
$cw = (int)($w * 0.72); $ch = (int)($h * 0.42);
$cx = (int)(($w - $cw) / 2); $cy = (int)(($h - $ch) / 2);
imagefilledrectangle($im, $cx, $cy, $cx + $cw, $cy + $ch, $card);
imagestring($im, 5, $cx + 40, $cy + (int)($ch / 2) - 16, 'Resource Centre', $ink);
imagestring($im, 3, $cx + 40, $cy + (int)($ch / 2) + 8, 'Arthsathi Limited v' . $ver, $muted);
header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
imagepng($im);
imagedestroy($im);
