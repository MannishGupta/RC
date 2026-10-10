<?php
declare(strict_types=1);
namespace App\Rc\Domain\Team;

/**
 * Team record field normalization (birth geo, coords).
 * Version: 20261002.17 — Phase 7 extraction from index.php save
 */
final class RecordNormalize
{
    /**
     * @param array<string,mixed> $newData
     * @return array<string,mixed>
     */
    public static function apply(array $newData): array
    {
        $normCoord = static function ($v, float $min, float $max): ?float {
            if ($v === null || $v === '') {
                return null;
            }
            if (!is_numeric($v)) {
                return null;
            }
            $n = (float) $v;
            if ($n < $min) {
                $n = $min;
            }
            if ($n > $max) {
                $n = $max;
            }
            return round($n, 6);
        };

        $geo = trim((string) ($newData['birth_geo'] ?? $newData['geo'] ?? ''));
        if ($geo !== '' && preg_match('/(-?\d+(?:\.\d+)?)\s*[,\s]+\s*(-?\d+(?:\.\d+)?)/', $geo, $gm)) {
            $newData['birth_lat'] = $gm[1];
            $newData['birth_lng'] = $gm[2];
        }
        $latIn = $newData['birth_lat'] ?? $newData['lat'] ?? null;
        $lngIn = $newData['birth_lng'] ?? $newData['lng'] ?? null;
        $lat = $normCoord($latIn, -90.0, 90.0);
        $lng = $normCoord($lngIn, -180.0, 180.0);
        if ($lat !== null && $lng !== null) {
            $newData['birth_lat'] = $lat;
            $newData['birth_lng'] = $lng;
            $newData['lat'] = $lat;
            $newData['lng'] = $lng;
            $newData['birth_geo'] = $lat . ', ' . $lng;
        } elseif ($lat !== null) {
            $newData['birth_lat'] = $lat;
            $newData['lat'] = $lat;
        } elseif ($lng !== null) {
            $newData['birth_lng'] = $lng;
            $newData['lng'] = $lng;
        } else {
            unset($newData['birth_lat'], $newData['birth_lng'], $newData['lat'], $newData['lng'], $newData['birth_geo']);
        }
        return $newData;
    }

    public static function sanitizeSlug(string $slug): string
    {
        if (class_exists(\App\Rc\Support\SafeName::class, false)) {
            return \App\Rc\Support\SafeName::slug($slug);
        }
        $slug = strtolower(trim(basename(str_replace('\\', '/', $slug))));
        return (string) preg_replace('/[^a-z0-9\-]+/', '', $slug);
    }
}
