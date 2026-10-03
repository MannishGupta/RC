<?php
declare(strict_types=1);
namespace App\Rc\Http;

/**
 * Documented index.php action names (catalog only — dispatch stays in index).
 * Version: 20261002.15
 */
final class ActionCatalog
{
    /** @return list<string> */
    public static function writeActions(): array
    {
        return [
            'save', 'delete', 'bulk_delete', 'import', 'optimize',
            'backup', 'restore', 'modules_save', 'tenant_save', 'switch_role',
        ];
    }

    /** @return list<string> */
    public static function readActions(): array
    {
        return [
            'dashboard', 'probe_doc_size', 'probe_url_size', 'synthetic_hosts',
            'export', 'list',
        ];
    }

    public static function isKnown(string $action): bool
    {
        $action = strtolower(trim($action));
        if ($action === '') {
            return true;
        }
        return in_array($action, self::writeActions(), true)
            || in_array($action, self::readActions(), true);
    }
}
