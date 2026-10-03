<?php
declare(strict_types=1);
namespace App\Rc\Support;

/**
 * Universal field tidy for JSON entity saves.
 * Version: 20261002.18
 */
final class FieldNormalize
{
    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function apply(array $data): array
    {
        if (!empty($data['name']) && is_string($data['name'])) {
            $n = trim($data['name']);
            if (function_exists('mb_convert_case') && function_exists('mb_strtolower')) {
                $data['name'] = \mb_convert_case(\mb_strtolower($n, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            } else {
                $data['name'] = ucwords(strtolower($n));
            }
        }
        if (!empty($data['email']) && is_string($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }
        if (!empty($data['phone']) && is_string($data['phone']) && class_exists('AppSlug', false)) {
            $data['phone'] = \AppSlug::normalizePhone($data['phone']);
        }
        foreach (['photo', 'logo', 'image', 'cover', 'favicon'] as $imgKey) {
            if (!empty($data[$imgKey]) && is_string($data[$imgKey])) {
                $p = str_replace(['\\', "\0"], ['/', ''], $data[$imgKey]);
                if (!preg_match('#^https?://#i', $p) && !str_starts_with($p, 'data:')) {
                    $data[$imgKey] = basename($p);
                }
            }
        }
        return $data;
    }
}

