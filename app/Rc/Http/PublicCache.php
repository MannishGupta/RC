<?php
declare(strict_types=1);
namespace App\Rc\Http;

/**
 * Lightweight cache headers for public card/share responses.
 * Safe on shared hosting; does not cache authenticated admin HTML.
 * Version: 20261002.04
 */
final class PublicCache
{
    public static function sendCardHeaders(int $maxAgeSeconds = 300): void
    {
        if (headers_sent()) {
            return;
        }
        // Only for anonymous-looking responses (caller responsibility)
        header('Cache-Control: public, max-age=' . max(0, $maxAgeSeconds) . ', stale-while-revalidate=60');
        header('Vary: Accept-Encoding');
    }

    public static function sendNoStore(): void
    {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
