<?php
declare(strict_types=1);
/**
 * Batch-generate per-tenant Windows installers (web shortcut + ARP metadata).
 * Super-admin only — invoked from index.php action installer_build.
 */
final class InstallerBuild
{
    /** @return list<array{id:string,host:string}> */
    public static function tenantHosts(): array
    {
        $out = [];
        $seen = [];
        $mapFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/tenants/map.json';
        if (!is_file($mapFile)) {
            return $out;
        }
        $map = json_decode((string)@file_get_contents($mapFile), true);
        if (!is_array($map)) {
            return $out;
        }
        $exact = is_array($map['exact'] ?? null) ? $map['exact'] : [];
        foreach ($exact as $host => $tid) {
            $host = strtolower(trim((string)$host));
            $tid = preg_replace('/[^a-z0-9.\-]/i', '', (string)$tid) ?? '';
            if ($host === '' || $tid === '' || isset($seen[$tid])) {
                continue;
            }
            // Prefer non-www primary host
            if (str_starts_with($host, 'www.')) {
                continue;
            }
            $seen[$tid] = true;
            $out[] = ['id' => $tid, 'host' => $host];
        }
        // Fallback: any remaining tenants from map values
        foreach ($exact as $host => $tid) {
            $tid = preg_replace('/[^a-z0-9.\-]/i', '', (string)$tid) ?? '';
            if ($tid === '' || isset($seen[$tid])) {
                continue;
            }
            $seen[$tid] = true;
            $out[] = ['id' => $tid, 'host' => strtolower(trim((string)$host))];
        }
        return $out;
    }

    public static function companyForTenant(string $tid): array
    {
        $base = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/tenants/' . $tid . '/data';
        foreach ([$base . '/company.json', $base . '/config/company.json'] as $f) {
            if (!is_file($f)) {
                continue;
            }
            $j = json_decode((string)@file_get_contents($f), true);
            if (is_array($j)) {
                // singleton or list
                if (isset($j['name'])) {
                    return $j;
                }
                if (isset($j[0]) && is_array($j[0])) {
                    return $j[0];
                }
            }
        }
        return ['name' => ucfirst($tid)];
    }

    public static function escPs(string $v): string
    {
        $v = str_replace(["\r", "\n"], '', $v);
        return str_replace("'", "''", $v);
    }

    /** Build ZIP path; returns [path, filename] or throws. */
    public static function buildZip(): array
    {
        $engine = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/tools/installer/rc-install-engine.ps1';
        if (!is_file($engine)) {
            throw new RuntimeException('Installer engine missing: tools/installer/rc-install-engine.ps1');
        }
        $template = (string)file_get_contents($engine);
        $tenants = self::tenantHosts();
        if (!$tenants) {
            throw new RuntimeException('No tenants found in tenants/map.json exact hosts');
        }
        $tmp = sys_get_temp_dir() . '/rc_installers_' . bin2hex(random_bytes(4));
        if (!@mkdir($tmp, 0700, true) && !is_dir($tmp)) {
            throw new RuntimeException('Cannot create temp build dir');
        }
        $ver = defined('APP_VERSION') ? (string)APP_VERSION : '1.0';
        $zipName = 'rc-windows-installers-' . preg_replace('/[^a-zA-Z0-9._\-]/', '', $ver) . '.zip';
        $zipPath = $tmp . '/' . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZipArchive open failed');
        }
        foreach ($tenants as $t) {
            $tid = $t['id'];
            $host = $t['host'];
            $co = self::companyForTenant($tid);
            $name = trim((string)($co['name'] ?? $tid));
            if ($name === '') {
                $name = $tid;
            }
            $isTrial = !empty($co['is_trial']) || !empty($co['trial']);
            $display = $name;
            if ($isTrial) {
                $display .= ' (Free Trial)';
            }
            $url = 'https://' . $host;
            if (!str_starts_with($host, 'rc.') && !str_contains($host, '.')) {
                $url = 'https://rc.' . $host;
            }
            // Prefer map host as-is (already full hostname)
            $url = 'https://' . preg_replace('~^https?://~i', '', $host);
            $slug = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', $tid) ?? $tid);
            $appId = 'ResourceCentre.' . $slug;
            $phone = (string)($co['support_phone'] ?? $co['phone'] ?? $co['tel'] ?? '');
            $email = (string)($co['support_email'] ?? $co['email'] ?? '');
            $comments = $isTrial
                ? ('Free trial — ' . trim((string)($co['trial_note'] ?? 'evaluation licence')))
                : 'Licensed installation';
            // ARP (Add/Remove Programs / Installed apps) identity:
            //   DisplayName = RC licensed to "{tenant company name}"
            //   Publisher   = Arthsathi Limited (developer)
            //   Version     = current APP_VERSION
            //   DisplayIcon = Arthsathi product icon (not tenant logo)
            $displayName = 'RC licensed to "' . $name . '"';
            if ($isTrial) {
                $displayName .= ' (Free Trial)';
            }
            // Product icon: prefer live Arthsathi host, then local favicon
            $arthsathiIcon = 'https://rc.arthsathi.com/favicon.ico';
            $map = [
                '{{APP_ID}}' => self::escPs($appId),
                '{{DISPLAY_NAME}}' => self::escPs($displayName),
                '{{PUBLISHER}}' => self::escPs('Arthsathi Limited'),
                '{{VERSION}}' => self::escPs($ver),
                '{{URL}}' => self::escPs($url),
                '{{ICON_URL}}' => self::escPs($arthsathiIcon),
                '{{URL_ABOUT}}' => self::escPs('https://www.arthsathi.com'),
                '{{URL_UPDATE}}' => self::escPs($url),
                '{{HELP_LINK}}' => self::escPs('https://www.arthsathi.com'),
                '{{HELP_PHONE}}' => self::escPs($phone),
                '{{CONTACT}}' => self::escPs($email !== '' ? $email : 'licensing@arthsathi.com'),
                '{{COMMENTS}}' => self::escPs(
                    $comments . ' · Resource Centre (RC) by Arthsathi Limited · Tenant: ' . $name . ' · Build ' . $ver
                ),
            ];
            $script = str_replace(array_keys($map), array_values($map), $template);
            $psName = $slug . '-setup.ps1';
            $cmdName = $slug . '-Install.cmd';
            $zip->addFromString($psName, $script);
            $cmd = "@echo off\r\npowershell -NoProfile -ExecutionPolicy Bypass -File \"%~dp0{$psName}\"\r\npause\r\n";
            $zip->addFromString($cmdName, $cmd);
        }
        $zip->addFromString('README.txt', "Resource Centre — Windows installers\r\n"
            . "Each *-Install.cmd creates a desktop + Start Menu shortcut and an Installed Apps entry.\r\n"
            . "Right-click → Run with PowerShell, or double-click the .cmd file.\r\n"
            . "Uninstall from Windows Settings → Apps, or run: powershell -File <slug>-setup.ps1 -Uninstall\r\n");
        $zip->close();
        return [$zipPath, $zipName, count($tenants), $tmp];
    }
}
