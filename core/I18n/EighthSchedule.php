<?php
// Version: 3.1.1
declare(strict_types=1);

/**
 * Eighth Schedule (Constitution of India) language codes for notices/consent.
 * BCP-47 style short codes used in UI; full names for labels.
 */
final class EighthSchedule
{
    /** @return array<string, array{name:string, native:string, bcp47:string}> */
    public static function languages(): array
    {
        return [
            'as' => ['name' => 'Assamese', 'native' => 'অসমীয়া', 'bcp47' => 'as-IN'],
            'bn' => ['name' => 'Bengali', 'native' => 'বাংলা', 'bcp47' => 'bn-IN'],
            'brx' => ['name' => 'Bodo', 'native' => 'बड़ो', 'bcp47' => 'brx-IN'],
            'doi' => ['name' => 'Dogri', 'native' => 'डोगरी', 'bcp47' => 'doi-IN'],
            'gu' => ['name' => 'Gujarati', 'native' => 'ગુજરાતી', 'bcp47' => 'gu-IN'],
            'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी', 'bcp47' => 'hi-IN'],
            'kn' => ['name' => 'Kannada', 'native' => 'ಕನ್ನಡ', 'bcp47' => 'kn-IN'],
            'ks' => ['name' => 'Kashmiri', 'native' => 'کٲشُر', 'bcp47' => 'ks-IN'],
            'kok' => ['name' => 'Konkani', 'native' => 'कोंकणी', 'bcp47' => 'kok-IN'],
            'mai' => ['name' => 'Maithili', 'native' => 'मैथिली', 'bcp47' => 'mai-IN'],
            'ml' => ['name' => 'Malayalam', 'native' => 'മലയാളം', 'bcp47' => 'ml-IN'],
            'mni' => ['name' => 'Manipuri', 'native' => 'মৈতৈলোন্', 'bcp47' => 'mni-IN'],
            'mr' => ['name' => 'Marathi', 'native' => 'मराठी', 'bcp47' => 'mr-IN'],
            'ne' => ['name' => 'Nepali', 'native' => 'नेपाली', 'bcp47' => 'ne-IN'],
            'or' => ['name' => 'Odia', 'native' => 'ଓଡ଼ିଆ', 'bcp47' => 'or-IN'],
            'pa' => ['name' => 'Punjabi', 'native' => 'ਪੰਜਾਬੀ', 'bcp47' => 'pa-IN'],
            'sa' => ['name' => 'Sanskrit', 'native' => 'संस्कृतम्', 'bcp47' => 'sa-IN'],
            'sat' => ['name' => 'Santali', 'native' => 'ᱥᱟᱱᱛᱟᱲᱤ', 'bcp47' => 'sat-IN'],
            'sd' => ['name' => 'Sindhi', 'native' => 'سنڌي', 'bcp47' => 'sd-IN'],
            'ta' => ['name' => 'Tamil', 'native' => 'தமிழ்', 'bcp47' => 'ta-IN'],
            'te' => ['name' => 'Telugu', 'native' => 'తెలుగు', 'bcp47' => 'te-IN'],
            'ur' => ['name' => 'Urdu', 'native' => 'اردو', 'bcp47' => 'ur-IN'],
            'en' => ['name' => 'English', 'native' => 'English', 'bcp47' => 'en-IN'],
        ];
    }

    public static function isSupported(string $code): bool
    {
        $code = strtolower(preg_replace('/[^a-z]/', '', $code) ?? '');
        return isset(self::languages()[$code]);
    }

    public static function normalize(string $code): string
    {
        $code = strtolower(trim($code));
        if (str_contains($code, '-')) {
            $code = explode('-', $code, 2)[0];
        }
        $code = preg_replace('/[^a-z]/', '', $code) ?? 'en';
        return self::isSupported($code) ? $code : 'en';
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::languages());
    }
}
