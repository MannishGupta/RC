<?php
declare(strict_types=1);
namespace App\Rc\Support;

/**
 * Human-readable byte sizes. Version: 20261002.12
 */
final class Bytes
{
    public static function format(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $b = (float) $bytes;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }
        $decimals = ($b < 10 && $i > 0) ? 1 : 0;
        return round($b, $decimals) . ' ' . $units[$i];
    }
}
