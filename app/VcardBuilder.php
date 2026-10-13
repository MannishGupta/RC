<?php
declare(strict_types=1);

/**
 * Shared vCard 3.0 builder — field order preferred by iOS Contacts, Google
 * Contacts, Outlook, Samsung, and most “scan to save” / AI contact parsers.
 *
 * Order (generic labels):
 *   VERSION → N → FN → ORG (Company; Unit) → TITLE → TEL → EMAIL → URL
 *   → ADR → BDAY → PHOTO → NOTE → CATEGORIES → social → UID → REV
 *
 * vCard 3.0 is used (not 4.0) for widest import + PHOTO support.
 */
final class VcardBuilder
{
    public static function esc(string $v): string
    {
        $v = str_replace(['\\', ';', ',', "\r\n", "\r", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $v);
        return $v;
    }

    /** RFC 6350 §3.2 line folding (octets). */
    public static function fold(string $line): string
    {
        $line = rtrim($line, "\r\n");
        if (strlen($line) <= 75) {
            return $line . "\r\n";
        }
        $out = substr($line, 0, 75) . "\r\n";
        $rest = substr($line, 75);
        while ($rest !== '') {
            $out .= ' ' . substr($rest, 0, 74) . "\r\n";
            $rest = substr($rest, 74);
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $person  team member (name, phone, email, designation…, photo path/url)
     * @param array<string,mixed> $company organisation record
     * @param array{include_photo?:bool,photo_max_bytes?:int,uid?:string,note?:string} $opts
     */
    public static function build(array $person, array $company = [], array $opts = []): string
    {
        $includePhoto = $opts['include_photo'] ?? true;
        $photoMax = (int) ($opts['photo_max_bytes'] ?? 120000);

        $fullName = trim((string) ($person['name'] ?? $person['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = 'Contact';
        }

        // Structured name: Family;Given;Additional;Prefix;Suffix
        $parts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) === 1) {
            $family = '';
            $given = $parts[0];
            $middle = '';
        } else {
            $family = (string) array_pop($parts);
            $given = (string) ($parts[0] ?? '');
            $middle = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
        }

        $org = trim((string) ($company['name'] ?? $person['company_name'] ?? ''));
        $dept = trim((string) (
            $person['department_name'] ?? $person['department'] ?? $person['dept'] ?? ''
        ));
        if ($dept === '-' || strcasecmp($dept, 'n/a') === 0) {
            $dept = '';
        }
        $title = trim((string) (
            $person['designation_name'] ?? $person['designation'] ?? $person['role'] ?? $person['title'] ?? ''
        ));
        if ($title === '-' || strcasecmp($title, 'n/a') === 0) {
            $title = '';
        }

        $phones = self::collectPhones($person);
        $emails = self::collectEmails($person);
        $website = trim((string) (
            $person['website'] ?? $company['website'] ?? $company['url'] ?? ''
        ));
        if ($website !== '' && !preg_match('~^https?://~i', $website)) {
            $website = 'https://' . $website;
        }

        $lines = [];
        $lines[] = 'BEGIN:VCARD';
        $lines[] = 'VERSION:3.0';
        $lines[] = 'PRODID:-//Resource Centre//EN';
        // Identity first (parsers and AI expect N/FN early)
        $lines[] = 'N:' . self::esc($family) . ';' . self::esc($given) . ';' . self::esc($middle) . ';;';
        $lines[] = 'FN:' . self::esc($fullName);
        // Company then department — standard “Company / Department” slots
        if ($org !== '' || $dept !== '') {
            $lines[] = 'ORG:' . self::esc($org) . ($dept !== '' ? ';' . self::esc($dept) : '');
        }
        if ($title !== '') {
            $lines[] = 'TITLE:' . self::esc($title);
        }
        foreach ($phones as $i => $ph) {
            $uri = preg_replace('/[^\d+]/', '', $ph) ?: $ph;
            $type = $i === 0 ? 'CELL,VOICE,PREF' : 'CELL,VOICE';
            // Dual form: TYPE list works on iOS/Google; VALUE=uri helps strict parsers
            $lines[] = 'TEL;TYPE=' . $type . ':' . self::esc($ph);
            if ($uri !== '' && $uri !== $ph) {
                // skip duplicate uri line to avoid double entries on some importers
            }
        }
        foreach ($emails as $i => $em) {
            $type = $i === 0 ? 'PREF,INTERNET' : 'INTERNET';
            $lines[] = 'EMAIL;TYPE=' . $type . ':' . self::esc($em);
        }
        if ($website !== '') {
            $lines[] = 'URL:' . self::esc($website);
        }

        // Work address from company / location
        $street = trim((string) ($person['address'] ?? $company['address'] ?? $company['street'] ?? ''));
        $city = trim((string) ($person['city'] ?? $company['city'] ?? ''));
        $state = trim((string) ($person['state'] ?? $company['state'] ?? ''));
        $pin = trim((string) ($person['pincode'] ?? $person['pin'] ?? $company['pincode'] ?? $company['pin'] ?? ''));
        $country = trim((string) ($company['country'] ?? 'India'));
        $locName = trim((string) ($person['location_name'] ?? $person['location'] ?? ''));
        if ($street === '' && $locName !== '') {
            $street = $locName;
        }
        if ($street !== '' || $city !== '' || $state !== '' || $pin !== '') {
            // ADR: PO;Extended;Street;City;Region;Postcode;Country
            $lines[] = 'ADR;TYPE=WORK:;;'
                . self::esc($street) . ';'
                . self::esc($city) . ';'
                . self::esc($state) . ';'
                . self::esc($pin) . ';'
                . self::esc($country !== '' ? $country : 'India');
        }

        // BDAY only when explicitly requested (not on public digital cards — privacy)
        if (!empty($opts['include_bday'])) {
            $bday = trim((string) ($person['dob'] ?? $person['birth_date'] ?? $person['birthday'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $bday)) {
                $lines[] = 'BDAY:' . substr($bday, 0, 4) . substr($bday, 5, 2) . substr($bday, 8, 2);
            }
        }

        if ($includePhoto) {
            $photoLine = self::photoLine($person, $photoMax);
            if ($photoLine !== null) {
                $lines[] = $photoLine;
            }
        }

        $note = trim((string) ($opts['note'] ?? $person['bio'] ?? $person['note'] ?? ''));
        if ($note !== '') {
            $lines[] = 'NOTE:' . self::esc($note);
        }
        if ($dept !== '') {
            $lines[] = 'CATEGORIES:' . self::esc($dept);
        }

        // Social — X-SOCIALPROFILE is read by iOS; others ignore harmlessly
        $socialMap = [
            'linkedin' => $person['linkedin'] ?? $company['linkedin'] ?? '',
            'twitter' => $person['twitter'] ?? $person['x'] ?? $company['twitter'] ?? '',
            'facebook' => $person['facebook'] ?? $company['facebook'] ?? '',
            'instagram' => $person['instagram'] ?? $company['instagram'] ?? '',
        ];
        foreach ($socialMap as $plat => $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            $lines[] = 'X-SOCIALPROFILE;TYPE=' . $plat . ':' . self::esc($url);
        }

        $slug = trim((string) ($person['slug'] ?? $person['id'] ?? ''));
        $uid = trim((string) ($opts['uid'] ?? ''));
        if ($uid === '') {
            $host = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'resource-centre.local'));
            $uid = ($slug !== '' ? $slug : md5($fullName . $emails[0] ?? '')) . '@' . $host;
        }
        $lines[] = 'UID:' . self::esc($uid);
        $lines[] = 'REV:' . gmdate('Ymd\THis\Z');
        $lines[] = 'END:VCARD';

        $out = '';
        foreach ($lines as $ln) {
            if (str_starts_with($ln, 'PHOTO;')) {
                // fold photo payload carefully
                $out .= self::foldPhotoLine($ln);
            } else {
                $out .= self::fold($ln);
            }
        }
        return $out;
    }

    /** Raw vCard string suitable for QR (no photo — keeps QR scannable). */
    public static function buildForQr(array $person, array $company = []): string
    {
        return self::build($person, $company, ['include_photo' => false]);
    }

    /**
     * @return list<string>
     */
    private static function collectPhones(array $person): array
    {
        $out = [];
        foreach (['phone', 'mobile', 'tel', 'whatsapp', 'wa'] as $k) {
            if (!isset($person[$k])) {
                continue;
            }
            foreach ((array) $person[$k] as $ph) {
                $ph = trim((string) $ph);
                if ($ph !== '' && !in_array($ph, $out, true)) {
                    $out[] = $ph;
                }
            }
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private static function collectEmails(array $person): array
    {
        $out = [];
        foreach (['email', 'mail'] as $k) {
            if (!isset($person[$k])) {
                continue;
            }
            foreach ((array) $person[$k] as $em) {
                $em = trim((string) $em);
                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && !in_array($em, $out, true)) {
                    $out[] = $em;
                }
            }
        }
        return $out;
    }

    private static function photoLine(array $person, int $maxBytes): ?string
    {
        $path = '';
        $candidates = [
            $person['photo_path'] ?? '',
            $person['photo'] ?? '',
            $person['image'] ?? '',
        ];
        foreach ($candidates as $c) {
            $c = trim((string) $c);
            if ($c === '') {
                continue;
            }
            if (preg_match('~^https?://~i', $c)) {
                // Prefer file path; remote fetch is unreliable on shared hosts
                continue;
            }
            $try = $c;
            if (defined('IMG_PATH') && is_file(IMG_PATH . '/' . ltrim($c, '/'))) {
                $path = IMG_PATH . '/' . ltrim($c, '/');
                break;
            }
            if (defined('DATA_PATH') && is_file(DATA_PATH . '/media/images/' . basename($c))) {
                $path = DATA_PATH . '/media/images/' . basename($c);
                break;
            }
            if (defined('BASE_PATH') && is_file(BASE_PATH . '/' . ltrim($c, '/'))) {
                $path = BASE_PATH . '/' . ltrim($c, '/');
                break;
            }
            if (is_file($c)) {
                $path = $c;
                break;
            }
        }
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $bin = @file_get_contents($path);
        if ($bin === false || $bin === '') {
            return null;
        }
        // Downscale via GD if large
        if (strlen($bin) > $maxBytes && function_exists('imagecreatefromstring')) {
            $im = @imagecreatefromstring($bin);
            if ($im !== false) {
                $w = imagesx($im);
                $h = imagesy($im);
                $maxEdge = 320;
                if ($w > $maxEdge || $h > $maxEdge) {
                    $scale = $maxEdge / max($w, $h);
                    $nw = max(1, (int) ($w * $scale));
                    $nh = max(1, (int) ($h * $scale));
                    $dst = imagecreatetruecolor($nw, $nh);
                    if ($dst) {
                        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
                        ob_start();
                        imagejpeg($dst, null, 82);
                        $bin = (string) ob_get_clean();
                        imagedestroy($dst);
                    }
                }
                imagedestroy($im);
            }
        }
        if (strlen($bin) > $maxBytes * 2) {
            return null; // still too big — skip photo
        }
        $b64 = base64_encode($bin);
        $mime = 'JPEG';
        $fi = @getimagesize($path);
        if (is_array($fi) && !empty($fi['mime'])) {
            if (str_contains($fi['mime'], 'png')) {
                $mime = 'PNG';
            } elseif (str_contains($fi['mime'], 'webp')) {
                $mime = 'JPEG'; // many clients reject WEBP in vCard
            }
        }
        return 'PHOTO;ENCODING=b;TYPE=' . $mime . ':' . $b64;
    }

    private static function foldPhotoLine(string $line): string
    {
        // PHOTO;ENCODING=b;TYPE=JPEG:<base64>
        $pos = strpos($line, ':');
        if ($pos === false) {
            return self::fold($line);
        }
        $head = substr($line, 0, $pos + 1);
        $b64 = substr($line, $pos + 1);
        $out = $head;
        $first = 75 - strlen($head);
        if ($first < 20) {
            $first = 20;
        }
        $out .= substr($b64, 0, $first) . "\r\n";
        $rest = substr($b64, $first);
        while ($rest !== '') {
            $out .= ' ' . substr($rest, 0, 74) . "\r\n";
            $rest = substr($rest, 74);
        }
        return $out;
    }
}
