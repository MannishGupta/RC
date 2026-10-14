<?php  
declare(strict_types=1);

if (!defined('BASE_PATH')) exit('No direct script access');

final class GoogleWallet {

    private static function config(): array {
        $f = DATA_PATH . '/google_wallet_config.php';
        if (!is_readable($f)) return [];
        $c = @include $f;
        return is_array($c) ? $c : [];
    }

    public static function isConfigured(): bool {
        $c = self::config();
        return !empty($c['enabled']) && !empty($c['issuer_id'])
            && !empty($c['class_id']) && !empty($c['service_account_json']);
    }

    

    public static function passUrl(array $person, array $company, string $slug, string $baseUrl): string {
        if (!self::isConfigured()) return '';

        $c = self::config();
        $keyFile = DATA_PATH . '/' . ltrim((string)$c['service_account_json'], '/');
        if (!is_readable($keyFile)) return '';

        $key = json_decode((string)@file_get_contents($keyFile), true);
        if (!is_array($key) || empty($key['private_key']) || empty($key['client_email'])) return '';

        $objectId = $c['issuer_id'] . '.' . preg_replace('/[^A-Za-z0-9_-]/', '_', $slug);

         
         
         
        $object = [
            'id'      => $objectId,
            'classId' => $c['issuer_id'] . '.' . $c['class_id'],
            'state'   => 'ACTIVE',
            'cardTitle'      => ['defaultValue' => ['language' => 'en', 'value' => (string)($company['name'] ?? '')]],
            'header'         => ['defaultValue' => ['language' => 'en', 'value' => (string)($person['name'] ?? '')]],
            'subheader'      => ['defaultValue' => ['language' => 'en', 'value' => trim((string)($person['designation'] ?? ''))]],
            'hexBackgroundColor' => '#' . self::brandHex($company),
            'logo'    => !empty($company['logo']) ? ['sourceUri' => ['uri' => rtrim($baseUrl, '/') . '/images/' . rawurlencode(basename((string)$company['logo']))]] : null,
            'heroImage' => !empty($person['photo']) ? ['sourceUri' => ['uri' => rtrim($baseUrl, '/') . '/images/' . rawurlencode(basename((string)$person['photo']))]] : null,
            'textModulesData' => array_values(array_filter([
                !empty($person['phone']) ? ['header' => 'PHONE', 'body' => (string)$person['phone'], 'id' => 'phone'] : null,
                !empty($person['email']) ? ['header' => 'EMAIL', 'body' => (string)$person['email'], 'id' => 'email'] : null,
            ])),
            'linksModuleData' => [
                'uris' => [[
                    'uri' => rtrim($baseUrl, '/') . '/?card=business&slug=' . rawurlencode($slug),
                    'description' => 'View digital card',
                ]],
            ],
            'barcode' => [
                'type'  => 'QR_CODE',
                'value' => rtrim($baseUrl, '/') . '/?card=business&slug=' . rawurlencode($slug),
            ],
        ];
        $object = array_filter($object, fn($v) => $v !== null);

        $now = time();
        $payload = [
            'iss' => $key['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => $now,
            'origins' => [$baseUrl],
            'payload' => ['genericObjects' => [$object]],
        ];

        $jwt = self::signJwt($payload, (string)$key['private_key']);
        if ($jwt === '') return '';

        return 'https://pay.google.com/gp/v/save/' . $jwt;
    }

    private static function brandHex(array $company): string {
        $hex = ltrim((string)($company['brand_color'] ?? '#1e3a5f'), '#');
        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? $hex : '1e3a5f';
    }

     
    private static function signJwt(array $payload, string $privateKeyPem): string {
        if (!function_exists('openssl_sign')) return '';

        $b64url = fn(string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [
            $b64url(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $b64url(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);

        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) return '';

        $sig = '';
        $ok = openssl_sign($signingInput, $sig, $key, OPENSSL_ALGO_SHA256);
        if (!$ok) return '';

        $segments[] = $b64url($sig);
        return implode('.', $segments);
    }
}
