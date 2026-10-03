<?php
declare(strict_types=1);
/**
 * RolePack — tab allow-list for admin vs CRM packs (data/value/roles.json).
 * Version: 260921.41
 */
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
        $cfg = self::config();
        $id = (string)($cfg['crm_pack'] ?? 'logistics');
        return $id !== '' ? $id : 'readonly';
    }

    /** @return list<string>|null null = all tabs (*) */
    public static function allowedTabs(?string $user): ?array
    {
        $packId = self::packIdForUser($user);
        $cfg = self::config();
        foreach ($cfg['packs'] ?? [] as $p) {
            if (!is_array($p) || ($p['id'] ?? '') !== $packId) {
                continue;
            }
            $tabs = $p['tabs'] ?? [];
            if (in_array('*', $tabs, true)) {
                return null;
            }
            return array_values(array_map('strval', $tabs));
        }
        return ['team', 'terms', 'numero', 'bank', 'docs', 'events', 'locations', 'statutory', 'access', 'statistics'];
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
