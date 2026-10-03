<?php
declare(strict_types=1);
namespace App\Rc\Support;

/** Filename / slug sanitization. Version: 20261002.13 */
final class SafeName
{
    public static function slug(string $s): string
    {
        $s = strtolower(trim(str_replace('\\', '/', $s)));
        $s = basename($s);
        return (string) preg_replace('/[^a-z0-9\-]+/', '', $s);
    }

    public static function fileStem(string $s): string
    {
        $s = strtolower(trim(basename(str_replace('\\', '/', $s))));
        return (string) preg_replace('/[^a-z0-9_-]+/', '', $s);
    }
}
