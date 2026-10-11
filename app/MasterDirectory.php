<?php
declare(strict_types=1);
/**
 * MasterDirectory — banks & vehicle OEMs (JSON).
 * Logo chain: local SVG → Brandfetch CDN → proxy → Google favicon.
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
        $qNorm = preg_replace('/\b(bank|limited|ltd|india)\b/', '', $q) ?? $q;
        $qNorm = trim(preg_replace('/\s+/', ' ', $qNorm) ?? '');
        $best = null;
        $bestScore = 0;
        foreach ($rows as $row) {
            $slug = strtolower((string)($row['slug'] ?? ''));
            $name = strtolower((string)($row['display_name'] ?? ''));
            $nameNorm = preg_replace('/\b(bank|limited|ltd|india)\b/', '', $name) ?? $name;
            $nameNorm = trim(preg_replace('/\s+/', ' ', $nameNorm) ?? '');
            $score = 0;
            if ($slug === $q || $name === $q) {
                $score = 100;
            } elseif ($slug !== '' && ($slug === $qNorm || str_contains($q, $slug) || str_contains($qNorm, $slug))) {
                $score = 80;
            } elseif ($nameNorm !== '' && ($nameNorm === $qNorm || str_contains($nameNorm, $qNorm) || str_contains($qNorm, $nameNorm))) {
                $score = 70;
            } elseif (str_contains($name, $q) || str_contains($q, $name)) {
                $score = 50;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }
        return $bestScore >= 50 ? $best : null;
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
            // Deploy-safe master logos (super-admin uploads) — check first
            foreach (['svg', 'png', 'webp'] as $ext) {
                $p = $base . '/data/masters/logos/' . $folder . '/' . $slug . '.' . $ext;
                if (defined('DATA_PATH')) {
                    $p2 = rtrim((string) DATA_PATH, '/') . '/masters/logos/' . $folder . '/' . $slug . '.' . $ext;
                    if (is_file($p2)) {
                        $p = $p2;
                    }
                }
                if (is_file($p)) {
                    return '/media_serve.php?m=' . rawurlencode($folder . '/' . $slug . '.' . $ext)
                        . '&v=' . (string) @filemtime($p);
                }
            }
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
        return '/logo_proxy.php?d=' . rawurlencode($domain) . '&sz=256';
    }

/** All candidate URLs for client-side onerror cascade — real brand marks first */
    public static function logoCandidates(string $domain, string $kind = 'banks', string $slug = ''): array
    {
        $out = [];
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain) ?? '';
        $domain = preg_replace('/\/.*$/', '', $domain) ?? '';
        $slug = strtolower(trim(preg_replace('/[^a-z0-9\-]/', '', $slug) ?? ''));
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

        if (($kind === 'oems' || $kind === 'vehicle_oems') && class_exists('VehicleCatalog') === false) {
            $vc = $base . '/app/VehicleCatalog.php';
            if (is_file($vc)) {
                require_once $vc;
            }
        }

        // 1) Same-origin proxy (fetches Google/DDG/proxy server-side + cache)
        if ($domain !== '') {
            $out[] = '/logo_proxy.php?d=' . rawurlencode($domain) . '&sz=256';
            $out[] = 'https://t2.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&size=256&url=https://' . rawurlencode($domain);
            $out[] = 'https://icons.duckduckgo.com/ip3/' . rawurlencode($domain) . '.ico';
        }

        // 2) VehicleCatalog domain logos when OEM slug/make known
        if (($kind === 'oems' || $kind === 'vehicle_oems') && class_exists('VehicleCatalog')) {
            $make = $slug !== '' ? str_replace('-', ' ', $slug) : $domain;
            if ($make !== '') {
                $logo = VehicleCatalog::getVehicleLogo($make);
                if (is_string($logo) && $logo !== '' && !str_contains($logo, 'generic') && !str_contains($logo, 'silhouette')) {
                    array_unshift($out, $logo);
                }
            }
        }

        // 3) Deploy-safe master logos in data/ (super-admin uploads)
        $folder = ($kind === 'oems' || $kind === 'vehicle_oems') ? 'oems' : 'banks';
        if ($slug !== '') {
            foreach (['svg', 'png', 'webp'] as $ext) {
                $p = $base . '/data/masters/logos/' . $folder . '/' . $slug . '.' . $ext;
                if (defined('DATA_PATH')) {
                    $p2 = rtrim((string) DATA_PATH, '/') . '/masters/logos/' . $folder . '/' . $slug . '.' . $ext;
                    if (is_file($p2)) {
                        $p = $p2;
                    }
                }
                if (is_file($p)) {
                    array_unshift($out, '/media_serve.php?m=' . rawurlencode($folder . '/' . $slug . '.' . $ext)
                        . '&v=' . (string) @filemtime($p));
                }
            }
            // 4) Shipped assets/logos pack
            foreach (['png', 'webp', 'svg'] as $ext) {
                $rel = 'assets/logos/' . $folder . '/' . $slug . '.' . $ext;
                if (is_file($base . '/' . $rel)) {
                    if ($ext === 'svg') {
                        $out[] = '/' . $rel;
                    } else {
                        array_unshift($out, '/' . $rel);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /** Resolve best single logo URL for a bank display name */
    public static function bankLogoFor(string $bankName): string
    {
        $found = self::findBank($bankName);
        $domain = '';
        $slug = '';
        if ($found) {
            $domain = (string)($found['domain_name'] ?? '');
            $slug = (string)($found['slug'] ?? '');
        }
        if ($domain === '') {
            $domain = self::guessBankDomain($bankName);
        }
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $bankName) ?? '');
        }
        $cands = self::logoCandidates($domain, 'banks', $slug);
        return $cands[0] ?? '';
    }

    /** Common Indian bank domains when masters miss a row */
    public static function guessBankDomain(string $name): string
    {
        $bl = strtolower($name);
        $map = [
            'state bank' => 'sbi.co.in', 'sbi' => 'sbi.co.in',
            'hdfc' => 'hdfcbank.com',
            'icici' => 'icicibank.com',
            'axis' => 'axisbank.com',
            'kotak' => 'kotak.com',
            'punjab national' => 'pnbindia.in', 'pnb' => 'pnbindia.in',
            'bank of baroda' => 'bankofbaroda.in', 'bob' => 'bankofbaroda.in',
            'canara' => 'canarabank.com',
            'union bank' => 'unionbankofindia.co.in',
            'bank of india' => 'bankofindia.co.in',
            'indian bank' => 'indianbank.in',
            'idbi' => 'idbibank.in',
            'central bank' => 'centralbankofindia.co.in',
            'uco' => 'ucobank.com',
            'bank of maharashtra' => 'bankofmaharashtra.in',
            'yes bank' => 'yesbank.in',
            'idfc' => 'idfcfirstbank.com',
            'federal' => 'federalbank.co.in',
            'bandhan' => 'bandhanbank.com',
            'indusind' => 'indusind.com',
            'rbl' => 'rblbank.com',
            'city union' => 'cityunionbank.com',
            'au small' => 'aubank.in',
            'punjab & sind' => 'psbindia.com',
            'indian overseas' => 'iob.in',
        ];
        foreach ($map as $k => $d) {
            if (str_contains($bl, $k)) {
                return $d;
            }
        }
        return '';
    }

    /** OEM logo for manufacturer name */
    public static function oemLogoFor(string $makeName): string
    {
        $found = self::findOem($makeName);
        $domain = $found ? (string)($found['domain_name'] ?? '') : '';
        $slug = $found ? (string)($found['slug'] ?? '') : '';
        if (class_exists('VehicleCatalog')) {
            $key = VehicleCatalog::resolveMake($makeName);
            if ($key) {
                $logo = VehicleCatalog::getVehicleLogo($key);
                if ($logo !== '' && !str_contains($logo, 'silhouette')) {
                    return $logo;
                }
            }
        }
        $cands = self::logoCandidates($domain, 'oems', $slug !== '' ? $slug : $makeName);
        return $cands[0] ?? '';
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

    /** Ensure logo folder tree exists (TenantStorage baseline). */
    public static function ensureFiles(): void {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        foreach (['/assets/logos/banks', '/assets/logos/oems'] as $rel) {
            $dir = $base . $rel;
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }
}
