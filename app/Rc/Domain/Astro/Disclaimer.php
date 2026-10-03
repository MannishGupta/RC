<?php
declare(strict_types=1);
namespace App\Rc\Domain\Astro;

/**
 * Shared indicative disclaimer for Numerology / Janam / Milan.
 * Version: 20261002.11
 */
final class Disclaimer
{
    public static function en(): string
    {
        return 'Indicative algorithmic guidance only. Not a substitute for a qualified professional reading or medical advice.';
    }

    public static function hi(): string
    {
        return 'यह संकेतात्मक एल्गोरिद्मिक मार्गदर्शन है। योग्य पेशेवर परामर्श या चिकित्सकीय सलाह का विकल्प नहीं।';
    }

    public static function text(string $lang = 'en'): string
    {
        return str_starts_with(strtolower($lang), 'hi') ? self::hi() : self::en();
    }
}
