<?php
declare(strict_types=1);
/**
 * MasterDirectory — banks & vehicle OEMs (JSON).
 * Logo chain: local SVG → Brandfetch CDN → Clearbit → Google favicon.
 * Version: 20260929.39
 */
final class MasterDirectory
{
    private static function mastersDir(): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        return $base . '/data/masters';
    }

    /** @return list<array<string,mixed>> */
    public static function read(string $kind): array
    {
        $file = self::mastersDir() . '/' . ($kind === 'oems' || $kind === 'vehicle_oems' ? 'vehicle_oems.json' : 'banks.json');
        if (!is_file($file)) {
            return [];
        }
        $raw = json_decode((string)@file_get_contents($file), true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['status'] ?? 'active') === 'inactive') {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    public static function findBank(?string $nameOrSlug): ?array
    {
        return self::findIn(self::read('banks'), $nameOrSlug);
    }

    public static function findOem(?string $nameOrSlug): ?array
    {
        return self::findIn(self::read('vehicle_oems'), $nameOrSlug);
    }

    /** @param list<array<string,mixed>> $rows */
    private static function findIn(array $rows, ?string $q): ?array
    {
        $q = strtolower(trim((string)$q));
        if ($q === '') {
            return null;
        }
        foreach ($rows as $row) {
            $slug = strtolower((string)($row['slug'] ?? ''));
            $name = strtolower((string)($row['display_name'] ?? ''));
            if ($slug === $q || $name === $q || str_contains($name, $q) || str_contains($q, $slug)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Preferred logo URL for UI <img src>.
     * Local monogram always available offline; CDNs enhance when reachable.
     */
    public static function logoUrl(?string $domain, string $kind = 'banks', string $slug = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '';
        $folder = ($kind === 'oems') ? 'oems' : 'banks';

        if ($slug !== '') {
            foreach (['svg', 'png', 'webp'] as $ext) {
                $rel = 'assets/logos/' . $folder . '/' . $slug . '.' . $ext;
                $abs = $base . '/' . $rel;
                if (is_file($abs)) {
                    return '/' . $rel . '?v=' . (string)@filemtime($abs);
                }
            }
        }

        $domain = strtolower(trim((string)$domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain) ?? '';
        $domain = preg_replace('/\/.*$/', '', $domain) ?? '';
        if ($domain === '') {
            return '';
        }

        // Brandfetch public CDN (no API key for basic icon)
        return 'https://cdn.brandfetch.io/' . rawurlencode($domain) . '/w/128/h/128/icon?c=1idQ9bF5F5F5F5F5F5F5';
    }

    /** All candidate URLs for client-side onerror cascade */
    public static function logoCandidates(string $domain, string $kind = 'banks', string $slug = ''): array
    {
        $out = [];
        $domain = strtolower(trim($domain));
        $slug = strtolower(trim($slug));
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        if ($kind === 'banks' && $slug !== '' && is_file($base . '/assets/logos/banks/' . $slug . '.svg')) {
            $out[] = '/assets/logos/banks/' . rawurlencode($slug) . '.svg';
        }
        if (($kind === 'oems' || $kind === 'vehicle_oems') && $slug !== '' && is_file($base . '/assets/logos/oems/' . $slug . '.svg')) {
            $out[] = '/assets/logos/oems/' . rawurlencode($slug) . '.svg';
        }
        // Google favicons — same approach as bank logos (Clearbit often blocked)
        if ($domain !== '') {
            $out[] = 'https://www.google.com/s2/favicons?domain=' . rawurlencode($domain) . '&sz=128';
        }
        return array_values(array_unique($out));
    }

    public static function options(string $kind): array
    {
        $rows = self::read($kind === 'oems' ? 'vehicle_oems' : 'banks');
        $out = [];
        foreach ($rows as $r) {
            $slug = (string)($r['slug'] ?? '');
            $domain = (string)($r['domain_name'] ?? '');
            $k = ($kind === 'oems') ? 'oems' : 'banks';
            $cands = self::logoCandidates($domain, $k, $slug);
            $out[] = [
                'id' => (string)($r['id'] ?? $slug),
                'label' => (string)($r['display_name'] ?? $slug),
                'slug' => $slug,
                'domain' => $domain,
                'logo' => $cands[0] ?? '',
                'logos' => $cands,
                'color' => (string)($r['brand_color'] ?? ''),
            ];
        }
        usort($out, static fn($a, $b) => strcasecmp($a['label'], $b['label']));
        return $out;
    }
}
