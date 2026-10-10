<?php
declare(strict_types=1);
namespace App\Rc\Http;

/**
 * Browser cache policy for public cards vs signed-in UI.
 * Version: 20261002.15
 */
final class CachePolicy
{
    public static function applyForRequest(): void
    {
        if (!class_exists(PublicCache::class, false) && !class_exists('RcPublicCache', false)) {
            return;
        }
        $user = (string) ($_SESSION['user'] ?? '');
        $hasUser = $user !== '' && $user !== 'public';
        $isCard = !empty($_GET['card']) || !empty($_GET['slug']);
        if (!$hasUser && $isCard) {
            if (class_exists('RcPublicCache', false)) {
                \RcPublicCache::sendCardHeaders(180);
            } elseif (class_exists(PublicCache::class, false)) {
                PublicCache::sendCardHeaders(180);
            }
        } elseif ($hasUser) {
            if (class_exists('RcPublicCache', false)) {
                \RcPublicCache::sendNoStore();
            } elseif (class_exists(PublicCache::class, false)) {
                PublicCache::sendNoStore();
            }
        }
    }
}
