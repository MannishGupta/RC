<?php
declare(strict_types=1);
namespace App\Rc\Domain\Bank;

/**
 * UPI QR into tenant media — offline pure-PHP first, GD PNG when available, API last resort.
 * Version: 20261002.13
 */
final class UpiQr
{
    /**
     * @param array<string,mixed> $bank
     * @return string|null Filename under images/ (png or svg)
     */
    public static function generatePng(array $bank): ?string
    {
        $upi = trim((string) ($bank['upi_id'] ?? $bank['upi'] ?? ''));
        if ($upi === '') {
            return null;
        }
        $holder = trim((string) ($bank['holder_name'] ?? $bank['account_holder'] ?? ''));
        $payload = 'upi://pay?pa=' . rawurlencode($upi)
            . ($holder !== '' ? '&pn=' . rawurlencode($holder) : '')
            . '&cu=INR';

        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($bank['id'] ?? $bank['slug'] ?? uniqid('b', true)));
        if ($id === '') {
            $id = uniqid('b', true);
        }

        $imgDir = defined('IMG_PATH')
            ? IMG_PATH
            : ((defined('DATA_PATH') ? DATA_PATH : (defined('BASE_PATH') ? BASE_PATH . '/data' : dirname(__DIR__, 4) . '/data')) . '/media/images');
        if (!is_dir($imgDir)) {
            @mkdir($imgDir, 0775, true);
        }

        // 1) Offline pure-PHP QR (no network)
        $offline = self::encodeOffline($payload, $imgDir, $id);
        if ($offline !== null) {
            return $offline;
        }

        // 2) Remote API fallback (shared hosts without GD / encoder issues)
        $fname = 'bank-qr-' . $id . '.png';
        $path = $imgDir . DIRECTORY_SEPARATOR . $fname;
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=12&ecc=M&data=' . rawurlencode($payload);
        $bin = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $bin = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code >= 400) {
                $bin = false;
            }
        }
        if ($bin === false && filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $ctx = stream_context_create(['http' => ['timeout' => 20], 'ssl' => ['verify_peer' => true]]);
            $bin = @file_get_contents($url, false, $ctx);
        }
        if ($bin === false || !is_string($bin) || strlen($bin) < 64) {
            return null;
        }
        if (substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        if (@file_put_contents($path, $bin) === false) {
            return null;
        }
        @chmod($path, 0664);
        return $fname;
    }

    private static function encodeOffline(string $payload, string $imgDir, string $id): ?string
    {
        $lib = __DIR__ . '/lib/qrcode.php';
        if (!is_file($lib)) {
            return null;
        }
        if (!class_exists('QRCode', false)) {
            require_once $lib;
        }
        if (!class_exists('QRCode', false) || !defined('QR_ERROR_CORRECT_LEVEL_M')) {
            return null;
        }
        try {
            $qr = \QRCode::getMinimumQRCode($payload, QR_ERROR_CORRECT_LEVEL_M);
        } catch (\Throwable $e) {
            return null;
        }

        // Prefer PNG when GD is available (email clients, bank cards)
        if (function_exists('imagecreatetruecolor') && function_exists('imagepng') && method_exists($qr, 'createImage')) {
            $im = @$qr->createImage(6, 8);
            if (is_resource($im) || (is_object($im) && $im instanceof \GdImage)) {
                $fname = 'bank-qr-' . $id . '.png';
                $path = $imgDir . DIRECTORY_SEPARATOR . $fname;
                $ok = @imagepng($im, $path, 6);
                if (function_exists('imagedestroy')) {
                    @imagedestroy($im);
                }
                if ($ok) {
                    @chmod($path, 0664);
                    return $fname;
                }
            }
        }

        // SVG always works without GD
        if (method_exists($qr, 'printSVG')) {
            ob_start();
            try {
                $qr->printSVG(4);
            } catch (\Throwable $e) {
                ob_end_clean();
                return null;
            }
            $svg = (string) ob_get_clean();
            if ($svg === '' || stripos($svg, '<svg') === false) {
                return null;
            }
            $fname = 'bank-qr-' . $id . '.svg';
            $path = $imgDir . DIRECTORY_SEPARATOR . $fname;
            if (@file_put_contents($path, $svg) === false) {
                return null;
            }
            @chmod($path, 0664);
            return $fname;
        }

        return null;
    }
}
