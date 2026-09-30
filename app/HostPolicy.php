<?php
declare(strict_types=1);
/**
 * Optional strict host allowlist + synthetic host check helpers.
 * Version: 260926.33
 */
if (!defined('BASE_PATH')) { exit; }

class HostPolicy
{
    public static function strictMode(): bool
    {
        $mapFile = BASE_PATH . '/tenants/map.json';
        if (!is_file($mapFile)) {
            return false;
        }
        $map = json_decode((string) @file_get_contents($mapFile), true) ?: [];
        return !empty($map['strict_hosts']);
    }

    public static function isHostAllowed(string $host): bool
    {
        $host = strtolower(trim($host));
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }
        if (!self::strictMode()) {
            return true;
        }
        $mapFile = BASE_PATH . '/tenants/map.json';
        $map = json_decode((string) @file_get_contents($mapFile), true) ?: [];
        $exact = is_array($map['exact'] ?? null) ? $map['exact'] : [];
        return isset($exact[$host]);
    }

    /** @return list<array{host:string,tenant:string,ok:bool,note:string}> */
    public static function syntheticChecks(): array
    {
        $mapFile = BASE_PATH . '/tenants/map.json';
        $map = is_file($mapFile) ? (json_decode((string) @file_get_contents($mapFile), true) ?: []) : [];
        $exact = is_array($map['exact'] ?? null) ? $map['exact'] : [];
        $out = [];
        $seen = [];
        foreach ($exact as $host => $tid) {
            $host = strtolower((string) $host);
            $tid = (string) $tid;
            if (isset($seen[$host])) {
                continue;
            }
            $seen[$host] = true;
            $data = BASE_PATH . '/tenants/' . $tid . '/data';
            $ok = is_dir($data) && is_writable($data);
            $out[] = [
                'host' => $host,
                'tenant' => $tid,
                'ok' => $ok,
                'note' => $ok ? 'data writable' : (is_dir($data) ? 'data not writable' : 'tenant data missing'),
            ];
        }
        return $out;
    }
}
