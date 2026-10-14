<?php
declare(strict_types=1);

final class RolePack
{
    public static function config(): array
    {
        $path = BASE_PATH . '/data/value/roles.json';
        if (!is_file($path)) {
            return ['crm_pack' => 'logistics', 'packs' => []];
        }
        $j = json_decode((string)@file_get_contents($path), true);
        return is_array($j) ? $j : ['crm_pack' => 'logistics', 'packs' => []];
    }

    public static function packIdForUser(?string $user): string
    {
        if ($user === 'admin' || $user === 'super_admin') {
            return 'admin';
        }
         
        if ($user === 'public' || $user === null || $user === '' || $user === 'general_hr') {
            return 'general_hr';
        }
        $cfg = self::config();
        $id = (string)($cfg['crm_pack'] ?? 'logistics');
        return $id !== '' ? $id : 'general_hr';
    }

     
    public static function allowedTabs(?string $user): ?array
    {
        $packId = self::packIdForUser($user);
        // Company Admin + Super Admin: all tabs (Organisation Setup included)
        if ($packId === 'admin' || $user === 'admin' || $user === 'super_admin') {
            return null;
        }
        $cfg = self::config();
        foreach ($cfg['packs'] ?? [] as $p) {
            if (!is_array($p) || ($p['id'] ?? '') !== $packId) {
                continue;
            }
            $tabs = $p['tabs'] ?? [];
            if (in_array('*', $tabs, true)) {
                return null;
            }
            $list = array_values(array_map('strval', $tabs));
            // Ensure company admins never lose Organisation Setup if pack is mis-named
            if (($user === 'admin' || $user === 'crm') && !in_array('company', $list, true)) {
                $list[] = 'company';
            }
            return $list;
        }
        // Default operational pack — includes Organisation Setup for co. admin workflows
        return [
            'team', 'company', 'designations', 'departments', 'locations', 'bank', 'docs',
            'mediakit', 'events', 'statutory', 'numero', 'cctv', 'terms', 'data_rights', 'access',
            'statistics', 'cartags', 'assets', 'expiry', 'org', 'status', 'janam', 'settings',
        ];
    }

    public static function canAccess(?string $user, string $tab): bool
    {
        $allowed = self::allowedTabs($user);
        if ($allowed === null) {
            return true;
        }
        return in_array($tab, $allowed, true);
    }

    public static function filterTabs(?string $user, array $tabs): array
    {
        $allowed = self::allowedTabs($user);
        if ($allowed === null) {
            return $tabs;
        }
        return array_values(array_filter($tabs, static fn($t) => in_array($t, $allowed, true)));
    }
}
