<?php
declare(strict_types=1);
namespace App\Rc\Domain\Astro;

/**
 * Canonical paths for numerology / kundli engines (strangler map — no logic move yet).
 * Version: 20261002.13
 */
final class EnginePaths
{
    public static function base(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
    }

    /** @return array<string,string> */
    public static function numerology(): array
    {
        $b = self::base() . '/cards/numerology';
        return [
            'engine_dir' => $b . '/engine',
            'core' => $b . '/engine/core.php',
            'grid' => $b . '/engine/grid.php',
            'scoring' => $b . '/engine/scoring.php',
            'validator' => $b . '/engine/NumerologyValidator.php',
            'mobile_eval' => $b . '/engine/MobileEvaluationEngine.php',
        ];
    }

    public static function janamPatri(): string
    {
        return self::base() . '/janam_patri.php';
    }

    public static function bloodReport(): string
    {
        return self::base() . '/blood_report.php';
    }

    /** Require numerology engine files that exist (optional soft-load). */
    public static function bootNumerology(): void
    {
        foreach (self::numerology() as $key => $path) {
            if ($key === 'engine_dir') {
                continue;
            }
            if (is_file($path)) {
                require_once $path;
            }
        }
    }
}
