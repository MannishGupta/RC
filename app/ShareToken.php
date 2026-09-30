<?php
declare(strict_types=1);
/**
 * Signed, expiring share tokens for public cards.
 * Version: 260926.33
 *
 * URL shape (optional): ?card=business&slug=mg&sig=...&exp=...
 * Legacy unsigned slug links still work unless tenant config sets share.require_sig=true
 */
if (!defined('BASE_PATH')) { exit; }

class ShareToken
{
    public static function secret(): string
    {
        $cfg = [];
        if (defined('TENANT_CONFIG_FILE') && is_file(TENANT_CONFIG_FILE)) {
            $cfg = json_decode((string) @file_get_contents(TENANT_CONFIG_FILE), true) ?: [];
        }
        $s = (string) ($cfg['share_secret'] ?? '');
        if ($s === '') {
            $s = hash('sha256', (defined('BASE_PATH') ? BASE_PATH : '') . '|' . (defined('TENANT_ID') ? TENANT_ID : 'default') . '|rc-share-v1');
        }
        return $s;
    }

    public static function requireSig(): bool
    {
        if (defined('TENANT_CONFIG_FILE') && is_file(TENANT_CONFIG_FILE)) {
            $cfg = json_decode((string) @file_get_contents(TENANT_CONFIG_FILE), true) ?: [];
            return !empty($cfg['share']['require_sig']);
        }
        return false;
    }

    public static function sign(string $card, string $slug, int $ttlSeconds = 86400 * 30): array
    {
        $exp = time() + max(60, $ttlSeconds);
        $payload = strtolower($card) . '|' . strtolower($slug) . '|' . $exp . '|' . (defined('TENANT_ID') ? TENANT_ID : '');
        $sig = hash_hmac('sha256', $payload, self::secret());
        return ['sig' => $sig, 'exp' => $exp];
    }

    public static function verify(string $card, string $slug, ?string $sig, $exp): bool
    {
        $exp = (int) $exp;
        if ($sig === null || $sig === '' || $exp < 1) {
            return false;
        }
        if ($exp < time()) {
            return false;
        }
        $payload = strtolower($card) . '|' . strtolower($slug) . '|' . $exp . '|' . (defined('TENANT_ID') ? TENANT_ID : '');
        $expect = hash_hmac('sha256', $payload, self::secret());
        return hash_equals($expect, $sig);
    }

    public static function url(string $card, string $slug, int $ttlSeconds = 86400 * 30): string
    {
        $t = self::sign($card, $slug, $ttlSeconds);
        $q = http_build_query([
            'card' => $card,
            'slug' => $slug,
            'sig' => $t['sig'],
            'exp' => $t['exp'],
        ]);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . '/?' . $q;
    }
}
