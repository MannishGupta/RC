<?php
declare(strict_types=1);
/**
 * Path jail — every file path must resolve under an allowed root.
 * Version: 260926.33
 */
if (!defined('BASE_PATH')) { exit; }

class PathJail
{
    /** @return list<string> */
    public static function allowedRoots(): array
    {
        $roots = [rtrim(str_replace('\\', '/', BASE_PATH), '/')];
        if (defined('DATA_PATH')) {
            $roots[] = rtrim(str_replace('\\', '/', (string) DATA_PATH), '/');
        }
        if (defined('TENANT_ROOT')) {
            $roots[] = rtrim(str_replace('\\', '/', (string) TENANT_ROOT), '/');
        }
        if (!empty($GLOBALS['RC_OPT_DATA_PATH']) && is_string($GLOBALS['RC_OPT_DATA_PATH'])) {
            $roots[] = rtrim(str_replace('\\', '/', $GLOBALS['RC_OPT_DATA_PATH']), '/');
        }
        $tenants = rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/tenants';
        if (is_dir($tenants)) {
            $roots[] = $tenants;
        }
        return array_values(array_unique($roots));
    }

    public static function resolve(string $path, ?string $mustBeUnder = null): ?string
    {
        $path = str_replace(["\0", '\\'], ['', '/'], $path);
        $real = realpath($path);
        if ($real === false) {
            // Allow non-existent path if parent is jailed (for writes)
            $parent = dirname($path);
            $preal = realpath($parent);
            if ($preal === false) {
                return null;
            }
            $real = $preal . '/' . basename($path);
        }
        $real = str_replace('\\', '/', $real);
        $roots = $mustBeUnder !== null
            ? [rtrim(str_replace('\\', '/', $mustBeUnder), '/')]
            : self::allowedRoots();
        foreach ($roots as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/');
            if ($real === $root || str_starts_with($real, $root . '/')) {
                return $real;
            }
        }
        return null;
    }

    public static function assertWritable(string $path, ?string $under = null): string
    {
        $resolved = self::resolve($path, $under);
        if ($resolved === null) {
            throw new RuntimeException('PathJail: path escapes allowed roots: ' . $path);
        }
        return $resolved;
    }
}
