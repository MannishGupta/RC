<?php
declare(strict_types=1);
namespace App\Rc\Http;

final class PublicCache
{
    public static function sendCardHeaders(int $maxAgeSeconds = 300): void
    {
        if (headers_sent()) {
            return;
        }
         
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
