<?php
declare(strict_types=1);
/**
 * Commercial launch — shared rich meta for public share pages.
 * Version: 260925.01
 */
if (!class_exists('SeoShare')) {
class SeoShare
{
    public static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function baseUrl(): string
    {
        if (class_exists('AppUtils') && method_exists('AppUtils', 'getBaseUrl')) {
            return rtrim((string)AppUtils::getBaseUrl(), '/');
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return ($https ? 'https://' : 'http://') . $host;
    }

    /**
     * @param array{title:string,description?:string,url?:string,image?:string,type?:string,robots?:string,site_name?:string} $m
     */
    public static function tags(array $m): string
    {
        $title = self::h((string)($m['title'] ?? 'Resource Centre'));
        $desc = self::h((string)($m['description'] ?? ''));
        $url = self::h((string)($m['url'] ?? self::baseUrl()));
        $img = self::h((string)($m['image'] ?? ''));
        $type = self::h((string)($m['type'] ?? 'profile'));
        $robots = self::h((string)($m['robots'] ?? 'index, follow'));
        $site = self::h((string)($m['site_name'] ?? 'Resource Centre'));
        $locale = 'en_IN';

        $out = [];
        $out[] = '<title>' . $title . '</title>';
        if ($desc !== '') {
            $out[] = '<meta name="description" content="' . $desc . '">';
        }
        $out[] = '<meta name="robots" content="' . $robots . '">';
        $out[] = '<link rel="canonical" href="' . $url . '">';
        $out[] = '<meta property="og:type" content="' . $type . '">';
        $out[] = '<meta property="og:title" content="' . $title . '">';
        if ($desc !== '') {
            $out[] = '<meta property="og:description" content="' . $desc . '">';
        }
        $out[] = '<meta property="og:url" content="' . $url . '">';
        $out[] = '<meta property="og:site_name" content="' . $site . '">';
        $out[] = '<meta property="og:locale" content="' . $locale . '">';
        if ($img !== '') {
            $out[] = '<meta property="og:image" content="' . $img . '">';
            $out[] = '<meta property="og:image:alt" content="' . $title . '">';
        }
        $out[] = '<meta name="twitter:card" content="' . ($img !== '' ? 'summary_large_image' : 'summary') . '">';
        $out[] = '<meta name="twitter:title" content="' . $title . '">';
        if ($desc !== '') {
            $out[] = '<meta name="twitter:description" content="' . $desc . '">';
        }
        if ($img !== '') {
            $out[] = '<meta name="twitter:image" content="' . $img . '">';
        }
        return implode("\n    ", $out) . "\n";
    }

    public static function personCard(string $kind, array $person, array $company = []): string
    {
        $name = trim((string)($person['name'] ?? 'Contact'));
        $role = trim((string)($person['designation'] ?? $person['role'] ?? ''));
        $comp = trim((string)($company['name'] ?? ''));
        $slug = (string)($person['slug'] ?? $person['id'] ?? '');
        $base = self::baseUrl();
        $url = $base . '/?card=' . rawurlencode($kind) . '&slug=' . rawurlencode($slug);
        $photo = trim((string)($person['photo'] ?? $person['photo_url'] ?? ''));
        $img = '';
        if ($photo !== '') {
            if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
                $img = $photo;
            } else {
                $img = $base . '/images/' . rawurlencode(basename($photo));
            }
        } elseif (!empty($company['logo']) || !empty($company['logo_url'])) {
            $logo = (string)($company['logo_url'] ?? $company['logo'] ?? '');
            $img = (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://'))
                ? $logo
                : ($base . '/images/' . rawurlencode(basename($logo)));
        }
        // Dynamic SVG OG card when no photo/logo — strong WhatsApp/LinkedIn preview
        if ($img === '') {
            $q = http_build_query([
                'name' => $name,
                'role' => ($role !== '' && $role !== '-') ? $role : '',
                'company' => $comp !== '' ? $comp : 'Resource Centre',
                'kind' => $labels[$kind] ?? 'Digital Card',
            ]);
            $img = $base . '/tools/og_card.php?' . $q;
        }
        $labels = [
            'business' => 'Digital Business Card',
            'id' => 'Employee ID Card',
            'visiting' => 'Visiting Card',
            'qr' => 'QR Contact Card',
        ];
        $label = $labels[$kind] ?? 'Contact Card';
        $title = $name . ($role !== '' && $role !== '-' ? ' · ' . $role : '') . ($comp !== '' ? ' | ' . $comp : '');
        if (mb_strlen($title) > 65) {
            $title = $name . ($comp !== '' ? ' | ' . $comp : '') . ' · ' . $label;
        }
        $desc = $label . ' for ' . $name;
        if ($role !== '' && $role !== '-') {
            $desc .= ', ' . $role;
        }
        if ($comp !== '') {
            $desc .= ' at ' . $comp;
        }
        $desc .= '. Official contact details, share and save to phone.';
        return self::tags([
            'title' => $title,
            'description' => $desc,
            'url' => $url,
            'image' => $img,
            'type' => 'profile',
            'robots' => 'index, follow',
            'site_name' => $comp !== '' ? $comp : 'Resource Centre',
        ]);
    }
}
}
