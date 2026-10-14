<?php
declare(strict_types=1);

final class TenantPaths
{
     
    public static function listIds(): array
    {
        if (!defined('BASE_PATH')) {
            return [];
        }
        $tenantsDir = BASE_PATH . '/tenants';
        if (!is_dir($tenantsDir)) {
            return [];
        }
        $out = [];
        $entries = @scandir($tenantsDir);
        if (!is_array($entries)) {
            return [];
        }
        foreach ($entries as $d) {
            if (!self::isTenantId($d)) {
                continue;
            }
            $folder = $tenantsDir . DIRECTORY_SEPARATOR . $d;
             
            if (!@is_dir($folder)) {
                continue;
            }
            if (!class_exists('TenantTombstone', false) && is_file(__DIR__ . '/TenantTombstone.php')) {
                require_once __DIR__ . '/TenantTombstone.php';
            }
            if (class_exists('TenantTombstone') && TenantTombstone::isDeleted((string)$d)) {
                continue;
            }
            $out[] = $d;
        }
        sort($out, SORT_STRING);
        return $out;
    }

    public static function isTenantId(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            return false;
        }
         
        if ($name[0] === '.') {
            return false;
        }
        $lower = strtolower($name);
        if (in_array($lower, ['map.json', 'readme.md', 'readme.txt', 'readme', 'license', 'license.md'], true)) {
            return false;
        }
         
        if (preg_match('/\.(json|md|txt|html|php|htaccess|ini|log|bak|zip)$/i', $name)) {
            return false;
        }
         
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-_]{0,63}$/', $name)) {
            return false;
        }
        return true;
    }

    public static function dataPath(string $tenantId): string
    {
        return BASE_PATH . '/tenants/' . $tenantId . '/data';
    }

    public static function configPath(string $tenantId): string
    {
        return BASE_PATH . '/tenants/' . $tenantId . '/config.json';
    }
}
