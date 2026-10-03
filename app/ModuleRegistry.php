<?php
declare(strict_types=1);
/**
 * ModuleRegistry — per-tenant module + sub-feature toggles.
 * Storage: tenants/{id}/data/config/modules.json
 * Super Admin configures; all roles respect gates.
 * Version: 20261001.02
 */
final class ModuleRegistry
{
    /** Canonical module catalogue (id => meta). */
    public static function catalogue(): array
    {
        return [
            'team' => [
                'label' => 'Human Capital Index',
                'group' => 'Human Capital',
                'features' => [
                    'team_print' => 'Directory print / VCF export',
                    'team_blood' => 'Blood group reports',
                    'team_janam' => 'Janam Patri link from profile',
                    'team_numero' => 'Numerology link from profile',
                ],
            ],
            'tracking' => [
                'label' => 'Live Location',
                'group' => 'Field Operations',
                'features' => [
                    'tracking_map' => 'Live map',
                    'tracking_history' => 'Travel history',
                    'tracking_violations' => 'Policy violations',
                ],
            ],
            'ops' => [
                'label' => 'Field Ops Hub',
                'group' => 'Field Operations',
                'features' => [],
            ],
            'cartags' => [
                'label' => 'Vehicle Inventory',
                'group' => 'Field Operations',
                'features' => [
                    'cartags_oem' => 'OEM master logos',
                ],
            ],
            'dispatch' => [
                'label' => 'Dispatch & Routes',
                'group' => 'Field Operations',
                'features' => [
                    'dispatch_hindi' => 'Roman-Hindi mobile dispatch',
                ],
            ],
            'locations' => [
                'label' => 'Standard Locations',
                'group' => 'Field Operations',
                'features' => [],
            ],
            'assets' => [
                'label' => 'Asset Checkout',
                'group' => 'Field Operations',
                'features' => [],
            ],
            'bank' => [
                'label' => 'Treasury & Banking',
                'group' => 'Institutional Resources',
                'features' => [
                    'bank_qr' => 'UPI / QR previews',
                    'bank_import' => 'Statement import',
                ],
            ],
            'docs' => [
                'label' => 'Document Vault',
                'group' => 'Institutional Resources',
                'features' => [],
            ],
            'events' => [
                'label' => 'Calendar',
                'group' => 'Institutional Resources',
                'features' => [],
            ],
            'statutory' => [
                'label' => 'Compliance Register',
                'group' => 'Institutional Resources',
                'features' => [],
            ],
            'expiry' => [
                'label' => 'Expiry Radar',
                'group' => 'Institutional Resources',
                'features' => [],
            ],
            'insights' => [
                'label' => 'Insights (Numero / Janam / Blood)',
                'group' => 'Insights',
                'features' => [
                    'numero' => 'Numerology reports',
                    'janam' => 'Janam Patri & Milan',
                    'blood' => 'Blood clinical brief',
                ],
            ],
            'numero' => [
                'label' => 'Numerology',
                'group' => 'Insights',
                'features' => [],
            ],
            'org' => [
                'label' => 'Organizational Chart',
                'group' => 'Administration',
                'features' => [],
            ],
            'company' => [
                'label' => 'Organizational Configuration',
                'group' => 'Administration',
                'features' => [],
            ],
            'designations' => [
                'label' => 'Role Taxonomy',
                'group' => 'Administration',
                'features' => [],
            ],
            'departments' => [
                'label' => 'Organizational Units',
                'group' => 'Administration',
                'features' => [],
            ],
            'settings' => [
                'label' => 'Enterprise Integrations',
                'group' => 'Administration',
                'features' => [],
            ],
            'cctv' => [
                'label' => 'Surveillance Access',
                'group' => 'Administration',
                'features' => [],
            ],
            'leads' => [
                'label' => 'Opportunity Pipeline',
                'group' => 'Administration',
                'features' => [],
            ],
            'status' => [
                'label' => 'Programme Delivery Status',
                'group' => 'Administration',
                'features' => [],
            ],
            'health' => [
                'label' => 'Platform Health',
                'group' => 'Administration',
                'features' => [],
            ],
            'audit' => [
                'label' => 'Audit Ledger',
                'group' => 'Administration',
                'features' => [],
            ],
            'terms' => [
                'label' => 'Governance & AUP',
                'group' => 'System',
                'features' => [],
            ],
            'access' => [
                'label' => 'Access Mode',
                'group' => 'System',
                'features' => [],
            ],
            'statistics' => [
                'label' => 'Statistics',
                'group' => 'System',
                'features' => [],
            ],
            // Super-admin only modules stay always available on control plane
            'tenants' => [
                'label' => 'Tenant Setup',
                'group' => 'Control Plane',
                'features' => [
                    'modules_config' => 'Per-tenant module matrix',
                ],
            ],
            'monitor' => [
                'label' => 'Monitor / Optimisation',
                'group' => 'Control Plane',
                'features' => [],
            ],
            'opt' => [
                'label' => 'Platform Optimization Suite',
                'group' => 'Control Plane',
                'features' => [],
            ],
        ];
    }

    public static function configPath(): string
    {
        $base = defined('DATA_PATH') ? DATA_PATH : (defined('BASE_PATH') ? BASE_PATH . '/data' : dirname(__DIR__) . '/data');
        $dir = rtrim($base, '/\\') . '/config';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/modules.json';
    }

    /** Default: all catalogue modules enabled, all features enabled. */
    public static function defaults(): array
    {
        $mods = [];
        foreach (self::catalogue() as $id => $meta) {
            $feats = [];
            foreach (array_keys($meta['features'] ?? []) as $fid) {
                $feats[$fid] = true;
            }
            $mods[$id] = [
                'enabled' => true,
                'features' => $feats,
            ];
        }
        return [
            'version' => 1,
            'updated_at' => null,
            'modules' => $mods,
        ];
    }

    public static function load(): array
    {
        $path = self::configPath();
        $defaults = self::defaults();
        if (!is_file($path)) {
            return $defaults;
        }
        $j = json_decode((string)@file_get_contents($path), true);
        if (!is_array($j) || !isset($j['modules']) || !is_array($j['modules'])) {
            return $defaults;
        }
        // Merge so new catalogue entries appear enabled by default
        foreach ($defaults['modules'] as $id => $def) {
            if (!isset($j['modules'][$id]) || !is_array($j['modules'][$id])) {
                $j['modules'][$id] = $def;
                continue;
            }
            if (!array_key_exists('enabled', $j['modules'][$id])) {
                $j['modules'][$id]['enabled'] = true;
            }
            $feats = is_array($j['modules'][$id]['features'] ?? null) ? $j['modules'][$id]['features'] : [];
            foreach ($def['features'] as $fid => $on) {
                if (!array_key_exists($fid, $feats)) {
                    $feats[$fid] = $on;
                }
            }
            $j['modules'][$id]['features'] = $feats;
        }
        return $j;
    }

    public static function save(array $cfg): bool
    {
        $path = self::configPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return false;
            }
        }
        @chmod($dir, 0775);
        $cfg['version'] = 1;
        $cfg['updated_at'] = date('c');
        $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $path . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $json . "
", LOCK_EX);
        if ($written === false) {
            $ok = @file_put_contents($path, $json . "
", LOCK_EX) !== false;
            if ($ok) {
                @chmod($path, 0664);
            }
            return $ok;
        }
        if (is_file($path)) {
            @unlink($path);
        }
        if (!@rename($tmp, $path)) {
            $ok = @file_put_contents($path, $json . "
", LOCK_EX) !== false;
            @unlink($tmp);
            if ($ok) {
                @chmod($path, 0664);
            }
            return $ok;
        }
        @chmod($path, 0664);
        return true;
    }

    public static function isEnabled(string $moduleId): bool
    {
        // Control-plane modules always on for super admin routes; still respect file for tenant ops tabs
        $cfg = self::load();
        $m = $cfg['modules'][$moduleId] ?? null;
        if ($m === null) {
            return true; // unknown = allow (forward compatible)
        }
        return !empty($m['enabled']);
    }

    public static function featureEnabled(string $moduleId, string $featureId): bool
    {
        if (!self::isEnabled($moduleId)) {
            return false;
        }
        $cfg = self::load();
        $feats = $cfg['modules'][$moduleId]['features'] ?? [];
        if (!array_key_exists($featureId, $feats)) {
            return true;
        }
        return !empty($feats[$featureId]);
    }

    /** Filter tab list to enabled modules only. Never strips control-plane for super admin caller. */
    public static function filterTabs(array $tabs, bool $isSuperAdmin = false): array
    {
        $always = $isSuperAdmin ? ['tenants', 'monitor', 'opt', 'access'] : ['terms', 'access'];
        $out = [];
        foreach ($tabs as $t) {
            $t = (string)$t;
            if (in_array($t, $always, true) || self::isEnabled($t)) {
                $out[] = $t;
            }
        }
        return array_values(array_unique($out));
    }

    /** Public payload for Alpine / session UI prefs. */
    public static function clientPayload(): array
    {
        $cfg = self::load();
        $enabled = [];
        $features = [];
        foreach ($cfg['modules'] as $id => $m) {
            $enabled[$id] = !empty($m['enabled']);
            $features[$id] = is_array($m['features'] ?? null) ? $m['features'] : [];
        }
        return [
            'enabled' => $enabled,
            'features' => $features,
            'updated_at' => $cfg['updated_at'] ?? null,
        ];
    }

    /**
     * If current tab is disabled, pick a safe fallback and optional flash message.
     * @return array{tab:string, redirected:bool, message:?string}
     */
    public static function resolveTab(string $requested, array $validTabs): array
    {
        if (in_array($requested, $validTabs, true) && self::isEnabled($requested)) {
            return ['tab' => $requested, 'redirected' => false, 'message' => null];
        }
        // Prefer team, else first valid
        $fallback = in_array('team', $validTabs, true) ? 'team' : ($validTabs[0] ?? 'team');
        if (!self::isEnabled($fallback) && !empty($validTabs)) {
            foreach ($validTabs as $t) {
                if (self::isEnabled($t) || in_array($t, ['terms', 'access'], true)) {
                    $fallback = $t;
                    break;
                }
            }
        }
        $msg = 'That module is switched off for this organisation. Contact your Super Admin if you need it restored.';
        return ['tab' => $fallback, 'redirected' => true, 'message' => $msg];
    }
}
