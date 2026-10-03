<?php
declare(strict_types=1);
namespace App\Rc\Support;

/**
 * Resolve upload destination directory for save fields.
 * Version: 20261002.19
 */
final class UploadDest
{
    public static function forField(string $field): string
    {
        if (str_contains($field, 'doc')) {
            return defined('DOC_PATH') ? DOC_PATH : ((defined('DATA_PATH') ? DATA_PATH : '') . '/media/docs');
        }
        return defined('IMG_PATH') ? IMG_PATH : ((defined('DATA_PATH') ? DATA_PATH : '') . '/media/images');
    }

    public static function ensureWritable(string $dir): bool
    {
        if ($dir === '') {
            return false;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return is_dir($dir) && is_writable($dir);
    }
}
