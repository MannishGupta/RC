<?php
declare(strict_types=1);
namespace App\Rc\Tenant;

/**
 * Facade over ModuleRegistry — use this from new code.
 * Keeps module enable/disable checks in one place.
 */
final class ModuleGate
{
    public static function enabled(string $moduleId): bool
    {
        if (!class_exists('ModuleRegistry', false)) {
            $path = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/ModuleRegistry.php';
            if (is_readable($path)) {
                require_once $path;
            }
        }
        if (!class_exists('ModuleRegistry', false)) {
            return true;
        }
        return \ModuleRegistry::isEnabled($moduleId);
    }

    public static function feature(string $moduleId, string $featureId): bool
    {
        if (!class_exists('ModuleRegistry', false)) {
            return self::enabled($moduleId);
        }
        return \ModuleRegistry::featureEnabled($moduleId, $featureId);
    }

    /** @param list<string> $tabs */
    public static function filterTabs(array $tabs, bool $isSuperAdmin = false): array
    {
        if (!class_exists('ModuleRegistry', false)) {
            return $tabs;
        }
        return \ModuleRegistry::filterTabs($tabs, $isSuperAdmin);
    }

    public static function clientPayload(): array
    {
        if (!class_exists('ModuleRegistry', false)) {
            return ['enabled' => new \stdClass(), 'features' => new \stdClass()];
        }
        return \ModuleRegistry::clientPayload();
    }
}
