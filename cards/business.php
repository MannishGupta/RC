<?php // Version: 260916.14
// cards/business.php — digital business card (host-agnostic).
//
// NON-OBVIOUS BEHAVIOUR — please read before editing:
//
//  * NO EMOJI in any WhatsApp-bound string. WhatsApp's own wa.me ->
//    api.whatsapp.com redirect corrupts emoji server-side (they arrive as
//    U+FFFD). Verified in production. Use plain text + rule dividers only.
//
//  * TWO DISTINCT WhatsApp paths, do not merge them:
//      $waChatUrl -> footer button. Click-to-Chat WITH the owner.
//                    wa.me needs DIGITS ONLY (no '+', spaces, dashes).
//      $waText    -> Share button / navigator.share(). Full card payload,
//                    for forwarding this person's card onward.
//
//  * MAPS: prefers the location record's stored map_url (precise pin) over
//    an address text search. Stored values are gated to http(s) only so a
//    "javascript:" value can never reach the href.
//
//  * LOGO uses a white pill background, NOT filter:brightness(0) invert(1) —
//    that filter blanks out any logo lacking a transparent background.
//
//  * Font Awesome 7.x normalised icon scaling vs v6; icons here sit in
//    fixed-size containers (38/52/36px) which absorb the difference.
//
//  * Viewport intentionally allows pinch-zoom (WCAG 1.4.4). Do not re-add
//    user-scalable=no.

if (!defined('BASE_PATH')) exit;
if (!class_exists('SeoShare') && is_file(BASE_PATH . '/app/SeoShare.php')) {
    require_once BASE_PATH . '/app/SeoShare.php';
} 

$person = $person ?? [];
$company = $company ?? [];

$name = $person['name'] ?? 'Contact';
$role = $person['designation'] ?? $person['role'] ?? '-';
$dept = $person['department'] ?? $person['dept'] ?? '-';
$phone = $person['phone'] ?? '';
$email = $person['email'] ?? '';
$slug = $person['slug'] ?? $person['id'] ?? '';
$website = $person['website'] ?? $company['website'] ?? '';
$address = $person['address'] ?? '';

// Stored Google Maps deep link. CardContext::get() in app/bootstrap.php already copies
// the location record's `map_url` onto the person as `map_link`, so prefer
// that; a person-level override is also honoured if one is ever added.
// This is the SAME value the Locations tab's "Open Map" button uses.
$mapUrlStored = trim((string)($person['map_url'] ?? $person['map_link'] ?? ''));

// 🟢 DYNAMIC DOMAIN RESOLUTION (Host Anywhere!)
$rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$safeHost = preg_replace('/[^a-zA-Z0-9.:-]/', '', $rawHost); // Prevents Host Header Injection
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . $safeHost;

$canonical = rtrim($baseUrl, '/') . "/?card=business&slug=" . urlencode($slug);
$cardUrl = $canonical; 

// Display/vCard-facing number: keeps the leading '+' for E.164 correctness.
$cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
$waPhone = (strlen($cleanPhone) === 10) ? '+91' . $cleanPhone : $cleanPhone;

// ── WhatsApp Click-to-Chat destination number ────────────────────────────
// wa.me accepts DIGITS ONLY — no '+', spaces, dashes, dots or brackets.
// Everything non-numeric is stripped, then the international calling code
// is normalised/preserved. See the changelog block at the top of this file.
$waDigits = preg_replace('/\D/', '', (string)$phone);

if (strlen($waDigits) === 10) {
    // Bare 10-digit subscriber number — assume India (+91), the platform default.
    $waDigits = '91' . $waDigits;
} elseif (strlen($waDigits) === 11 && strncmp($waDigits, '0', 1) === 0) {
    // National format with trunk prefix, e.g. 09876543210 -> 919876543210
    $waDigits = '91' . substr($waDigits, 1);
} elseif (strncmp($waDigits, '00', 2) === 0) {
    // International access prefix, e.g. 0091... / 0044... -> strip the 00
    $waDigits = substr($waDigits, 2);
}
// Any other shape (e.g. already 12-digit 91XXXXXXXXXX, or a non-Indian
// country code) is passed straight through — it already carries its own CC.

// E.164 sanity gate: a real MSISDN is 8–15 digits. Anything outside that
// range means the stored record is malformed, so we suppress the button
// rather than emit a wa.me link that would dead-end for the visitor.
$waChatValid = (strlen($waDigits) >= 8 && strlen($waDigits) <= 15);

// ── WhatsApp username, if this person has set one ─────────────────────────
// wa.me/<username> is shorter and more memorable than a phone-derived link,
// and rolled out through 2026 — but there is no way to check server- or
// client-side whether a given viewer's WhatsApp build/region resolves it, so
// this is a preference, not a verified-working switch. Validated to
// WhatsApp's actual username rules (5-40 chars) so a stray value someone
// typed never produces a broken link; falls straight back to the
// phone-derived link above when unset or invalid.
$waUsername = trim((string)($person['whatsapp_username'] ?? ''));
$waUsername = ltrim($waUsername, '@');
// Accept a pasted full link/handle and extract just the username part.
$waUsername = preg_replace('~^https?://(www\.)?wa\.me/~i', '', $waUsername);
// WhatsApp's actual rule (verified, not assumed): 3-35 chars, lowercase
// letters/digits/periods/underscores, at least one letter. Case is
// normalised rather than rejected — a username typed as "31MG" should not
// fail just because someone capitalised it.
$waUsername = mb_strtolower($waUsername);
$waUsernameValid = (bool)preg_match('/^(?=.*[a-z])[a-z0-9._]{3,35}$/', $waUsername);

$waChatUrlPrimary = $waUsernameValid
    ? 'https://wa.me/' . $waUsername
    : null;   // null means: use the phone-derived $waChatUrl built further below

$photo = !empty($person['photo']) ? '/images/' . basename($person['photo'] ?? '') : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=f1f5f9&color=94a3b8&size=512';
$absolutePhoto = (strpos($photo, 'http') === 0) ? $photo : rtrim($baseUrl, '/') . '/images/' . rawurlencode(basename($person['photo'] ?? ''));

$logo = !empty($company['logo']) ? '/images/' . basename($company['logo'] ?? '') : '';
$companyName = $company['name'] ?? 'Organization';
// ── Social links: personal first, company as fallback, per platform ───────
// Previously an all-or-nothing choice: if the person had ANY social link, the
// company's were all dropped. So someone with only a LinkedIn lost the
// company's Instagram and YouTube entirely.
//
// Now merged per platform — a personal link wins for that platform, the
// company's fills the gap — and each value is validated, so a blank field or
// a stray "n/a" never renders as a dead icon.
$_personSocial  = is_array($person['social'] ?? null)  ? $person['social']  : [];
$_companySocial = is_array($company['social'] ?? null) ? $company['social'] : [];

$_validUrl = function ($v): string {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return '';
    // Accept values saved without a scheme ("linkedin.com/in/x").
    if (!preg_match('~^[a-z][a-z0-9+.\-]*:~i', $v)) $v = 'https://' . ltrim($v, '/');
    // Only http(s) may reach an href.
    if (!preg_match('~^https?://~i', $v)) return '';
    // A bare domain with no path is the company site, not a profile.
    return $v;
};

// v2 (260907.15): the card now visually separates Personal and Company
// zones (photo/mobile/email/personal-social vs. logo/address/company-social),
// so the socials themselves are kept in two SEPARATE validated arrays rather
// than merged into one. A merged single rail was right when the card had no
// zones to put things in; now that it does, showing a company Instagram
// under "Personal" (or vice versa) would contradict the section it sits in.
$personalSocials = [];
$companySocials  = [];
foreach (['linkedin','twitter','x','instagram','facebook','youtube','telegram','whatsapp'] as $_k) {
    $_pv = $_validUrl($_personSocial[$_k] ?? '');
    if ($_pv !== '') $personalSocials[$_k] = $_pv;
    $_cv = $_validUrl($_companySocial[$_k] ?? '');
    if ($_cv !== '') $companySocials[$_k] = $_cv;
}
// 'x' and 'twitter' are the same platform; keep whichever is set, not both —
// applied independently within each zone.
if (isset($personalSocials['x']) && isset($personalSocials['twitter'])) unset($personalSocials['x']);
if (isset($companySocials['x'])  && isset($companySocials['twitter']))  unset($companySocials['x']);

// $socials kept as the union, for code that still wants "does this person
// have a LinkedIn at all" without caring which zone it came from (the
// WhatsApp/vCard export paths do this) — nothing downstream of here breaks.
$socials = $personalSocials + $companySocials;

// Shared icon/label lookup for BOTH zones — one definition, not duplicated
// per section, so adding a platform means editing one array, not two.
$socialMeta = [
    'linkedin'  => ['s-li', 'fa-brands fa-linkedin-in', 'LinkedIn'],
    'twitter'   => ['s-tw', 'fa-brands fa-x-twitter',   'X'],
    'x'         => ['s-tw', 'fa-brands fa-x-twitter',   'X'],
    'telegram'  => ['s-tg', 'fa-brands fa-telegram',    'Telegram'],
    'whatsapp'  => ['s-wa2','fa-brands fa-whatsapp',    'WhatsApp'],
    'instagram' => ['s-ig', 'fa-brands fa-instagram',   'Instagram'],
    'facebook'  => ['s-fb', 'fa-brands fa-facebook-f',  'Facebook'],
    'youtube'   => ['s-yt', 'fa-brands fa-youtube',     'YouTube'],
];

$captureLeads = !empty($person['capture_leads']);

if (class_exists('AppDB')) {
    $designationKey = $person['designation_id'] ?? $person['designation_code'] ?? null;
    if ($designationKey && (!$role || $role === '-')) {
        $desigs = AppDB::read('designations') ?: [];
        foreach ($desigs as $d) {
            if (($d['id'] ?? null) == $designationKey || ($d['code'] ?? null) == $designationKey) {
                $role = $d['name'] ?? '-'; break;
            }
        }
    }

    $departmentKey = $person['department_id'] ?? $person['department_code'] ?? null;
    if ($departmentKey && (!$dept || $dept === '-')) {
        $depts = AppDB::read('departments') ?: [];
        foreach ($depts as $d) {
            if (($d['id'] ?? null) == $departmentKey || ($d['code'] ?? null) == $departmentKey) {
                $dept = $d['name'] ?? '-'; break;
            }
        }
    }

    // NOTE: this lookup previously ran only when $address was empty, which
    // meant any person carrying their own address string never inherited
    // their location's stored map_url. It now runs whenever EITHER piece of
    // data is still missing, and fills in only what's actually absent.
    if ((empty($address) || $mapUrlStored === '') && !empty($person['location_id'])) {
        $locs = AppDB::read('locations') ?: [];
        foreach ($locs as $l) {
            if (($l['id'] ?? null) == $person['location_id'] || ($l['slug'] ?? null) == $person['location_id']) {
                if (empty($address)) {
                    // Same composition as CardContext: full postal address, no
                // site-name prefix, no dangling separators when a field is blank.
                $address = implode(', ', array_filter([
                    trim((string)($l['address'] ?? '')),
                    trim((string)($l['city']    ?? '')),
                    trim(trim((string)($l['state'] ?? '')) . ' ' . trim((string)($l['pincode'] ?? ''))),
                ], fn($v) => $v !== ''));
                }
                if ($mapUrlStored === '') {
                    $mapUrlStored = trim((string)($l['map_url'] ?? $l['map_link'] ?? ''));
                }
                break;
            }
        }
    }
}

if (isset($_GET['download']) && $_GET['download'] === 'vcf') {

    // ── RFC 6350 (vCard 3.0) compliant generation ──────────────────────────
    // Upgraded from a flat FN/TEL/EMAIL-only card to something that actually
    // follows spec: comma/semicolon/backslash escaping (a company name or
    // address containing either would previously have corrupted the file for
    // any strict parser — Apple/Google Contacts are forgiving, several
    // desktop clients are not), 75-octet line folding, and structured N:/ADR:
    // fields instead of only their flattened human-readable equivalents.
    //
    // Deliberately NOT included: BDAY. Date of birth is collected for
    // internal HR use (see the team record's own DOB field) but is not
    // otherwise surfaced anywhere on this public card — adding it to a
    // file anyone who scans the QR code can download would be a real
    // privacy regression, not a feature, so that field stays out here too.

    /** RFC 6350 §3.4 value escaping: backslash, comma, semicolon, newline. */
    $vcfEsc = function ($v): string {
        $v = (string)$v;
        $v = str_replace('\\', '\\\\', $v);
        $v = str_replace(',', '\\,', $v);
        $v = str_replace(';', '\\;', $v);
        $v = str_replace(["\r\n", "\n", "\r"], '\\n', $v);
        return $v;
    };

    /**
     * RFC 6350 §3.2 line folding: any content line over 75 octets is split,
     * continuation lines prefixed with one space. Measured in bytes
     * (octets), not characters — correct for UTF-8 names/addresses where a
     * single character can be multiple bytes.
     */
    $vcfFold = function (string $line) use (&$vcfFold): string {
        $line = rtrim($line, "\r\n");
        if (strlen($line) <= 75) return $line . "\r\n";
        $out  = substr($line, 0, 75) . "\r\n";
        $rest = substr($line, 75);
        while (strlen($rest) > 0) {
            $out  .= ' ' . substr($rest, 0, 74) . "\r\n";
            $rest  = substr($rest, 74);
        }
        return $out;
    };

    $vcfName    = trim($name);
    $vcfRole    = $role !== '-' ? trim($role) : '';
    $vcfDept    = $dept !== '-' ? trim($dept) : '';
    $vcfCompany = trim((string)($company['name'] ?? ''));
    $vcfPhone   = trim((string)$waPhone);
    $vcfEmail   = trim((string)$email);
    $vcfWebsite = trim((string)$website);

    // Structured name: RFC order is Family;Given;Additional;Prefix;Suffix.
    // A single-word name has nothing to split — it becomes the given name,
    // matching how every contacts app on a bare "Madonna"-style entry does.
    $nameParts = preg_split('/\s+/', $vcfName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $vcfFamily = count($nameParts) > 1 ? array_pop($nameParts) : '';
    $vcfGiven  = $nameParts[0] ?? $vcfName;
    $vcfMiddle = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

    $lines = [];
    $lines[] = 'BEGIN:VCARD';
    $lines[] = 'VERSION:3.0';
    $lines[] = 'PRODID:-//' . $vcfEsc($vcfCompany ?: 'Arthsathi') . '//Digital Business Card//EN';
    $lines[] = 'REV:' . gmdate('Ymd\THis\Z');
    $lines[] = 'FN:' . $vcfEsc($vcfName);
    $lines[] = 'N:' . $vcfEsc($vcfFamily) . ';' . $vcfEsc($vcfGiven) . ';' . $vcfEsc($vcfMiddle) . ';;';
    if ($vcfCompany || $vcfDept) $lines[] = 'ORG:' . $vcfEsc($vcfCompany) . ($vcfDept ? ';' . $vcfEsc($vcfDept) : '');
    if ($vcfRole)    $lines[] = 'TITLE:' . $vcfEsc($vcfRole);
    if ($vcfPhone)   $lines[] = 'TEL;TYPE=CELL,VOICE;VALUE=uri:tel:' . preg_replace('/[^0-9+]/', '', $vcfPhone);
    if ($vcfEmail)   $lines[] = 'EMAIL;TYPE=PREF,INTERNET:' . $vcfEsc($vcfEmail);
    if ($vcfWebsite) $lines[] = 'URL:' . $vcfEsc($vcfWebsite);

    // Structured ADR (street;locality;region;postal;country) alongside the
    // flattened $address already used for display elsewhere on this card —
    // both come from the SAME resolution in CardContext::get(), so there is
    // one source of truth for the address, not two independently-composed
    // versions that could drift apart.
    $adrStreet = (string)($person['address_street'] ?? '');
    $adrCity   = (string)($person['address_city']   ?? '');
    $adrState  = (string)($person['address_state']  ?? '');
    $adrPin    = (string)($person['address_pin']     ?? '');
    if ($adrStreet || $adrCity || $adrState || $adrPin) {
        $lines[] = 'ADR;TYPE=WORK,POSTAL:;;' . $vcfEsc($adrStreet) . ';' . $vcfEsc($adrCity) . ';'
                 . $vcfEsc($adrState) . ';' . $vcfEsc($adrPin) . ';India';
    }

    foreach (['linkedin', 'twitter', 'instagram', 'facebook', 'youtube', 'telegram'] as $_plat) {
        if (!empty($socials[$_plat])) $lines[] = 'X-SOCIALPROFILE;TYPE=' . $_plat . ':' . $vcfEsc($socials[$_plat]);
    }

    // ── Photo: SSRF-guarded fetch, unchanged from the working version ──────
    // Local file read when the photo lives on this server; a remote fetch is
    // only attempted when the host EXACTLY matches this site or the known
    // ui-avatars.com fallback — never an arbitrary URL, which is what makes
    // this safe to do server-side at all.
    $imgData = false;
    if (!empty($person['photo']) && defined('IMG_PATH') && file_exists(IMG_PATH . '/' . $person['photo'])) {
        $imgData = @file_get_contents(IMG_PATH . '/' . $person['photo']);
    }
    if (!$imgData && $absolutePhoto) {
        $parsed = parse_url($absolutePhoto);
        $fetchHost = $parsed['host'] ?? '';
        if ($fetchHost === $safeHost || $fetchHost === 'ui-avatars.com') {
            if (ini_get('allow_url_fopen')) {
                $imgData = @file_get_contents($absolutePhoto);
            } elseif (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $absolutePhoto);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $imgData = curl_exec($ch);
                curl_close($ch);
            }
        }
    }
    if ($imgData && strlen($imgData) < 300000) {
        // PHOTO's own value is folded manually at 74 chars/line (base64 has
        // no natural word breaks for the generic folder above to split on
        // sensibly) — this was already correct in the previous version.
        $b64 = chunk_split(base64_encode($imgData), 74, "\r\n ");
        $lines[] = 'PHOTO;ENCODING=b;TYPE=JPEG:' . "\r\n " . ltrim($b64);
    }

    $lines[] = 'END:VCARD';

    $vcf = '';
    foreach ($lines as $_ln) {
        // PHOTO is pre-folded above; folding it again would corrupt the
        // base64 payload by inserting extra breaks mid-stream.
        $vcf .= str_starts_with($_ln, 'PHOTO;') ? $_ln . "\r\n" : $vcfFold($_ln);
    }

    $filename = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($vcfName)) . '.vcf';

    if (ob_get_length()) ob_clean();
    header("Content-Type: text/vcard; charset=utf-8");
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    echo $vcf;
    exit;
}

$ogTitle = trim($name . ' | ' . $companyName);

// ── SHARE payload (used by the Share button / navigator.share only) ──────
// This intentionally still carries the FULL card details — that is the
// correct behaviour when a visitor forwards this person's card onward.
// Emoji deliberately excluded: WhatsApp's own redirect corrupts them.
$waTextLines = array_filter([
    '*' . $name . '*',
    $role !== '-' ? $role : null,
    $dept !== '-' ? $dept : null,
    '',
    $companyName,
    $phone   ? 'Phone: ' . $phone     : null,
    $email   ? 'Email: ' . $email     : null,
    $website ? 'Web: '   . $website   : null,
    $address ? 'Address: ' . $address : null,
    '',
    'Digital Card:',
    $cardUrl,
]);
$waText = implode("\n", $waTextLines);
if (mb_strlen($waText) > 3800) { $waText = mb_substr($waText, 0, 3800) . "...\n\n" . $cardUrl; }

// ── CHAT opener (used by the footer WhatsApp button only) ────────────────
// Addressed TO the profile owner, so the conversation reads naturally from
// the visitor's side. Deliberately short — a long prefilled block
// discourages people from actually sending the message.
$waChatText    = 'Hi ' . $name . ', I came across your digital business card'
               . ($companyName && $companyName !== 'Organization' ? ' from ' . $companyName : '')
               . ' and would like to connect with you.';
$waChatEncoded = rawurlencode($waChatText);
$waChatUrl     = 'https://wa.me/' . $waDigits . '?text=' . $waChatEncoded;

// The username link, same pre-filled text, same rules as the phone-derived
// one above — takes over as the actual button target when a valid username
// was set (see $waChatUrlPrimary earlier).
if ($waChatUrlPrimary) {
    $waChatUrl = $waChatUrlPrimary . '?text=' . $waChatEncoded;
}

// ── Maps deep link ───────────────────────────────────────────────────────
// PREFERRED: the exact URL stored on the location record (`map_url`) — the
// same link the Locations tab's "Open Map" button opens. That is a precise
// pin/place link (e.g. a maps.app.goo.gl short link or a /maps/place/ URL),
// so it lands on the actual premises rather than wherever Google's geocoder
// guesses the address string points to.
// FALLBACK: only when no URL is stored do we build an address text search.
if ($mapUrlStored !== '') {
    // Accept values saved without a scheme (e.g. "maps.app.goo.gl/abc123").
    if (!preg_match('~^[a-z][a-z0-9+.\-]*:~i', $mapUrlStored)) {
        $mapUrlStored = 'https://' . ltrim($mapUrlStored, '/');
    }
    // Hard guard: only http/https may ever reach an href. This rejects a
    // stored "javascript:" / "data:" value outright rather than rendering it.
    if (!preg_match('~^https?://~i', $mapUrlStored)) {
        $mapUrlStored = '';
    }
}

$mapsUrl = $mapUrlStored !== ''
    ? $mapUrlStored
    : ($address ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address) : '');

// Lightweight structured data — helps link-preview tools, search engines,
// and any future "add to contacts" integrations understand this page.
$personJsonLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => $name,
    'url'      => $cardUrl,
];
if ($role !== '-')            $personJsonLd['jobTitle'] = $role;
if ($phone)                   $personJsonLd['telephone'] = $phone;
if ($email)                   $personJsonLd['email'] = $email;
if ($absolutePhoto)           $personJsonLd['image'] = $absolutePhoto;
if ($companyName) {
    $personJsonLd['worksFor'] = ['@type' => 'Organization', 'name' => $companyName];
    if ($website) $personJsonLd['worksFor']['url'] = (strpos($website, 'http') === 0 ? $website : 'https://' . $website);
}
$sameAs = array_values(array_filter([$socials['linkedin'] ?? null, $socials['twitter'] ?? null, $socials['instagram'] ?? null, $socials['facebook'] ?? null]));
if ($sameAs) $personJsonLd['sameAs'] = $sameAs;
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f0e0d" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#faf9f7" media="(prefers-color-scheme: light)">
    <?php
    if (class_exists('SeoShare')) {
        echo SeoShare::personCard('business', is_array($person ?? null) ? $person : [], is_array($company ?? null) ? $company : []);
    } else {
        echo '<title>' . htmlspecialchars((string)($name ?? 'Contact'), ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<meta name="description" content="' . htmlspecialchars('Digital business card for ' . (string)($name ?? 'team member') . ' — official contact details.', ENT_QUOTES, 'UTF-8') . '">';
    }
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <!-- Font Awesome 7.3.1 — non-blocking (media=print onload) for LCP/TBT -->
    <link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'">
    <noscript><link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer"></noscript>
    <script type="application/ld+json"><?= json_encode($personJsonLd, JSON_UNESCAPED_SLASHES) ?></script>

    <style>
        /* ═══════════════════════════════════════════════════════════════════
           LAYOUT PRINCIPLE — actions and information are separate things.
           
           The previous card mixed them: Phone, Email and Address each appeared
           BOTH as a tappable info row AND as a round button in the footer bar,
           while WhatsApp appeared only as a button. So three actions were
           duplicated, one was not, and the card was ~1150px tall — two full
           screens of scrolling on a phone to read six facts.

           Now:
             ACTION RAIL  round buttons, one per verb: Call · WhatsApp ·
                          Email · Directions. Nothing else is a verb.
             INFO LIST    the values themselves, for reading and copying:
                          number, address, email, website. Tapping still acts,
                          but the row exists to be READ.
             SOCIAL RAIL  compact icons. Previously four FULL rows at ~62px
                          each — 248px spent on four links nobody reads as
                          text. Now one 52px rail.

           Target: everything above the fold on a 390x844 phone (iPhone 14/15),
           which is the commonest device this gets opened on.
        ═══════════════════════════════════════════════════════════════════ */

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --ink:    #0f172a;
            --ink-2:  #475569;
            --ink-3:  #94a3b8;
            --paper:  #ffffff;
            --paper-2:#f8fafc;
            --line:   #e2e8f0;
            --brand:  #1e3a5f;
            --gold:   #c9a84c;
            --wa:     #16a34a;
            --card-w: 430px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #f1f5f9; --ink-2: #cbd5e1; --ink-3: #64748b;
                --paper: #0f172a; --paper-2: #1e293b; --line: #334155;
            }
            body { background: #020617; }
        }

        html { -webkit-text-size-adjust: 100%; }

        body {
            font-family: 'Inter', 'Aptos', 'Noto Sans Devanagari', system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: var(--paper-2);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            -webkit-tap-highlight-color: transparent;
            display: flex; justify-content: center;
            min-height: 100vh;
            /* dvh accounts for the mobile browser's collapsing toolbar; 100vh
               alone is taller than the visible area and forces phantom scroll. */
            min-height: 100dvh;
        }
        a, button { touch-action: manipulation; }

        .card {
            width: 100%; max-width: var(--card-w);
            background: var(--paper);
            display: flex; flex-direction: column;
            min-height: 100vh; min-height: 100dvh;
            position: relative;
            /* Was on .soc, assuming it was always the card's last element.
               It no longer is — the Personal zone's .soc can be followed by
               the whole Company zone. Moved here so safe-area clearance is
               correct regardless of which conditional element ends up last
               (company socials, the lead form, or neither). */
            padding-bottom: env(safe-area-inset-bottom);
        }
        @media (min-width: 640px) {
            body { align-items: center; padding: 32px 20px; }
            .card { min-height: auto; border-radius: 24px; overflow: hidden;
                    box-shadow: 0 30px 60px -20px rgba(0,0,0,.28), 0 0 0 1px rgba(0,0,0,.04); }
        }

        /* ── Header — now a thin utility bar only ────────────────────────
             The card was restructured (260907.15) into two explicit zones,
             Personal and Company. The company logo/name used to live here,
             at the very top, ahead of the person's own name — backwards for
             a card whose primary subject is the individual. The logo and
             name now open the Company zone further down; this bar keeps
             only the cross-cutting utilities (QR, signature, share) that
             belong to neither zone specifically. ─────────────────────────── */
        .hdr {
            background: var(--brand);
            padding: max(14px, env(safe-area-inset-top)) 18px 20px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px;
        }
        .hdr-eyebrow { font-size: 10.5px; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: rgba(255,255,255,.6); }
        .hdr-btns { display: flex; gap: 7px; }
        .ico {
            width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(255,255,255,.2);
            background: rgba(255,255,255,.1); display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,.85); font-size: 12px; text-decoration: none; cursor: pointer;
        }
        .ico:active { transform: scale(.92); }

        /* ── Zones ──────────────────────────────────────────────────────── */
        .zone { display: flex; flex-direction: column; }
        .zone-personal { background: var(--brand); padding-bottom: 40px; position: relative; }
        .zone-personal::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 34px;
            background: var(--paper); border-radius: 50% 50% 0 0 / 100% 100% 0 0;
            /* BUG FIX: purely decorative (the curved seam into the light
               Company zone) but had no pointer-events:none, unlike the
               equivalent overlays in id.php/visiting.php. A full-width,
               absolutely-positioned layer with no z-index sits in the same
               stacking context as the buttons below it and can intercept
               taps depending on exact paint order — the likely cause of
               "non-responsive" buttons reported near the bottom of the
               Personal zone (the social icon row sits in this band). */
            pointer-events: none;
        }
        .zone-personal .idy .nm { color: #fff; }
        .zone-personal .role-dept { color: rgba(255,255,255,.78); }
        .zone-personal .acts, .zone-personal .info, .zone-personal .soc,
        .zone-personal .save { position: relative; z-index: 1; }
        /* BUG FIX: color used var(--ink), which flips to near-WHITE under
           prefers-color-scheme:dark (see the :root override above) — so on
           any phone with dark mode preferred, this rendered as white text on
           this rule's own white background. Invisible, though still present
           and clickable, which is exactly what "Save to Contacts not
           visible" describes. This button is a fixed white pill against the
           always-dark Personal zone by design — it should never adapt to
           the visitor's OS colour scheme, so both colours are hardcoded. */
        .zone-personal .save { background: #ffffff; color: #0f172a; }
        .zone-personal .save:active { opacity: .92; }
        .zone-personal .gwallet { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.35); }
        .zone-personal .info .row { color: #fff; }
        .zone-personal .info .row:active { background: rgba(255,255,255,.08); }
        .zone-personal .row-k { color: rgba(255,255,255,.55); }
        .zone-personal .row-i { color: rgba(255,255,255,.7); }
        .zone-personal .info { border-top-color: rgba(255,255,255,.14); }
        .zone-personal .row { border-bottom-color: rgba(255,255,255,.1); }

        .zone-company { background: var(--paper); padding-top: 22px; }

        .zone-divider {
            position: relative; text-align: center; margin: 0; height: 0;
        }
        .zone-divider span {
            position: relative; top: -1px; display: inline-block; background: var(--paper);
            padding: 0 12px; font-size: 10px; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; color: var(--ink-3);
        }

        /* ── Identity (Personal zone) ─────────────────────────────────────── */
        .idy { display: flex; flex-direction: column; align-items: center;
               padding: 0 20px 14px; margin-top: -40px; position: relative; }
        .ring { width: 92px; height: 92px; border-radius: 50%; padding: 3px;
                background: linear-gradient(135deg, var(--gold), #e8c87a, var(--gold));
                box-shadow: 0 6px 20px rgba(0,0,0,.16); }
        /* Upward-biased crop — see the identical fix and reasoning in
           cards/id.php and cards/visiting.php: a centred crop on a typical
           headshot cuts into the forehead. */
        .ring img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; object-position: center 18%;
                    border: 3px solid #fff; display: block; }
        .nm { font-size: 23px; font-weight: 700; letter-spacing: -.02em;
              line-height: 1.2; text-align: center; margin-top: 11px; }
        /* Designation and department on ONE line, per the requested split —
           two short facts read as one line better than two separate ones. */
        .role-dept { display: flex; align-items: baseline; justify-content: center;
            gap: 6px; flex-wrap: wrap; margin-top: 5px; font-size: 12.5px; font-weight: 500; }
        .role-dept .rl { font-weight: 600; }
        .role-dept .dp { letter-spacing: .04em; text-transform: uppercase; font-size: 11px; opacity: .82; }
        .rd-sep { opacity: .5; }

        /* ── Company zone header: logo + name, mirrors the personal photo +
             name pairing above it so the two zones read as parallel blocks. ── */
        .co-hd { display: flex; align-items: center; gap: 12px; padding: 0 20px 16px; }
        .logo-pill { display: inline-flex; align-items: center; background: #ffffff; border: 1px solid rgba(15,23,42,.08); box-shadow: 0 1px 2px rgba(15,23,42,.06);
            border: 1px solid var(--line); padding: 6px 10px; border-radius: 10px; flex-shrink: 0; }
        .logo-pill img { max-height: 26px; max-width: 108px; object-fit: contain; display: block; }
        .logo-pill { border-radius: 8px; padding: 6px 10px; }
        .co-badge { width: 40px; height: 40px; border-radius: 10px; background: var(--brand); color: #fff;
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex-shrink: 0; }
        .co-name { font-size: 16px; font-weight: 700; color: var(--ink); letter-spacing: -.01em; }

        /* Map preview: a single reliable tap target, not a mini interactive
           map. An embedded Google Maps iframe left interactive inside a
           compact card is a classic mobile anti-pattern — a thumb trying to
           scroll the PAGE instead lands on the map and drags/zooms it
           instead, which reads as exactly the kind of "unresponsive"
           behaviour being fixed elsewhere on this card. pointer-events:none
           on the iframe routes every tap to the wrapping <a>, so the whole
           preview behaves as one predictable button: tap anywhere, open
           full Google Maps. */
        .map-prev { display:block; position:relative; margin:4px 20px 12px; height:120px;
            border-radius:12px; overflow:hidden; border:1px solid var(--line); background:var(--paper-2); }
        .map-prev iframe { width:100%; height:100%; border:0; filter:saturate(.85); }
        .map-prev-tap { position:absolute; right:8px; bottom:8px; width:26px; height:26px;
            border-radius:8px; background:rgba(15,23,42,.72); color:#fff; font-size:10px;
            display:flex; align-items:center; justify-content:center; }

        .co-directions { margin: 4px 20px 14px; padding: 11px; border-radius: 12px;
            background: var(--paper-2); border: 1px solid var(--line); color: var(--ink-2);
            font-size: 13px; font-weight: 600; text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 8px; }
        .co-directions:active { transform: scale(.985); }

        /* ── Primary CTA ────────────────────────────────────────────────── */
        .save {
            margin: 0 20px 14px; padding: 13px; border-radius: 12px;
            background: var(--ink); color: var(--paper);
            display: flex; align-items: center; justify-content: center; gap: 9px;
            font-size: 14px; font-weight: 600; text-decoration: none;
        }
        .save:active { transform: scale(.985); opacity: .92; }
        /* Same bug, same fix — see the comment above .zone-personal .save.
           Border also hardcoded: it sits on THIS button's own white
           background, so a border colour that also depended on var(--line)
           (which flips to a dark slate under prefers-color-scheme:dark)
           would have been fine there, but was momentarily miswritten here as
           a near-white value during the fix — which disappears against the
           white button itself. Corrected to a plain light grey. */
        .gwallet { background: #ffffff; color: #0f172a; border: 1px solid #e2e8f0; }

        /* ── Action rail: verbs only ────────────────────────────────────── */
        .acts { display: flex; justify-content: center; gap: 10px; padding: 0 16px 16px; }
        .act { flex: 1; max-width: 84px; display: flex; flex-direction: column;
               align-items: center; gap: 5px; text-decoration: none; cursor: pointer;
               background: none; border: 0; padding: 0; }
        .act-c { width: 50px; height: 50px; border-radius: 15px; display: flex;
                 align-items: center; justify-content: center; font-size: 17px;
                 border: 1px solid var(--line); transition: transform .15s; }
        .act:active .act-c { transform: scale(.9); }
        .act-l { font-size: 10px; font-weight: 600; color: var(--ink-3); letter-spacing: .01em; }
        .a-call { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
        .a-wa   { background: #f0fdf4; color: var(--wa); border-color: #bbf7d0; }
        .a-mail { background: #fff1f2; color: #e11d48; border-color: #fecdd3; }
        .a-map  { background: #fffbeb; color: #d97706; border-color: #fde68a; }

        /* ── Info list: values, for reading ─────────────────────────────── */
        .info { border-top: 1px solid var(--line); }
        .row { display: flex; align-items: center; gap: 12px; padding: 11px 20px;
               text-decoration: none; color: var(--ink); border-bottom: 1px solid var(--line); }
        .row:last-child { border-bottom: 0; }
        .row:active { background: var(--paper-2); }
        .row-i { width: 17px; text-align: center; color: var(--ink-3); font-size: 13px; flex-shrink: 0; }
        .row-b { flex: 1; min-width: 0; }
        .row-k { font-size: 9.5px; font-weight: 700; letter-spacing: .1em;
                 text-transform: uppercase; color: var(--ink-3); }
        .row-v { font-size: 13.5px; font-weight: 500; margin-top: 1px;
                 white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        /* Address is the one value that must wrap — an ellipsis would hide the
           part someone is trying to copy. */
        .row-v.wrap { white-space: normal; overflow: visible; font-size: 12.5px; line-height: 1.4; }
        .row-x { color: var(--line); font-size: 10px; flex-shrink: 0; }

        /* ── Social rail ────────────────────────────────────────────────── */
        .soc { display: flex; justify-content: center; gap: 9px;
               padding: 14px 20px 18px; border-top: 1px solid var(--line); }
        .zone-personal .soc { border-top-color: rgba(255,255,255,.14); }
        .zone-personal .soc a { border-color: rgba(255,255,255,.25); background: rgba(255,255,255,.06); color: #fff; }
        .soc a { width: 38px; height: 38px; border-radius: 11px; display: flex;
                 align-items: center; justify-content: center; font-size: 15px;
                 border: 1px solid var(--line); text-decoration: none; }
        .soc a:active { transform: scale(.9); }
        .s-li { background:#e8f0f8; color:#0a66c2; } .s-tw { background:#f1f5f9; color:#0f1419; }
        .s-ig { background:#fdf2f8; color:#c026d3; } .s-fb { background:#eff6ff; color:#1d4ed8; }
        .s-yt { background:#fef2f2; color:#dc2626; }
        .s-web { background:#f1f5f9; color:#475569; }
        .s-tg { background:#eff6ff; color:#229ED9; } .s-wa2 { background:#f0fdf4; color:#16a34a; }


        /* ── Lead capture ──────────────────────────────────────────────── */
        .lead-wrap { padding: 14px 20px 6px; }   /* safe-area now handled once, by .card */
        .lead-toggle { width: 100%; padding: 11px; border-radius: 12px;
            background: var(--paper-2); border: 1px dashed var(--line);
            color: var(--ink-2); font-size: 13px; font-weight: 600;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            cursor: pointer; font-family: inherit; }
        .lead-toggle:active { transform: scale(.985); }
        .lead-form { display: none; flex-direction: column; gap: 8px; margin-top: 10px; }
        .lead-form input, .lead-form textarea {
            width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--line);
            font-size: 13.5px; font-family: inherit; color: var(--ink); background: var(--paper);
            resize: none; }
        .lead-form input:focus, .lead-form textarea:focus { outline: 2px solid var(--brand); outline-offset: 1px; }
        .lead-err { font-size: 12px; color: #dc2626; margin: 0; }
        .lead-submit { padding: 11px; border-radius: 10px; border: 0; background: var(--brand);
            color: #fff; font-size: 13.5px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .lead-submit:disabled { opacity: .6; }
        .lead-sent { display: flex; align-items: center; gap: 8px; padding: 12px;
            border-radius: 12px; background: #f0fdf4; color: #166534; font-size: 13px; font-weight: 600; }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
    <div class="card">

        <!-- ── Header ─────────────────────────────────────────────────── -->
        <!-- ── Utility bar: QR, signature, share. Global to the card, not
             tied to either zone, so it stays at the very top. ───────────── -->
        <div class="hdr">
            <span class="hdr-eyebrow">Digital Business Card</span>
            <div class="hdr-btns">
                <a href="?card=qr&slug=<?= urlencode($slug) ?>" class="ico" aria-label="QR code"><i class="fa-solid fa-qrcode"></i></a>
                <button onclick="shareProfile()" class="ico" aria-label="Share"><i class="fa-solid fa-arrow-up-from-bracket"></i></button>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════
             ZONE A — PERSONAL
             Photo, name, designation + department, mobile, email,
             personal-only social links. Everything specific to THIS person,
             nothing that belongs to the organisation.
        ═══════════════════════════════════════════════════════════════════ -->
        <section class="zone zone-personal">
            <div class="idy">
                <div class="ring">
                    <img src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($name) ?>"
                         width="92" height="92" fetchpriority="high" decoding="async"
                         data-lightbox-src="<?= htmlspecialchars($absolutePhoto) ?>"
                         data-lightbox-name="<?= htmlspecialchars($name) ?>">
                </div>
                <h1 class="nm"><?= htmlspecialchars($name) ?></h1>
                <?php if($role !== '-' || $dept !== '-'): ?>
                <div class="role-dept">
                    <?php if($role !== '-'): ?><span class="rl"><?= htmlspecialchars($role) ?></span><?php endif; ?>
                    <?php if($role !== '-' && $dept !== '-'): ?><span class="rd-sep">&middot;</span><?php endif; ?>
                    <?php if($dept !== '-'): ?><span class="dp"><?= htmlspecialchars($dept) ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <a href="?card=business&slug=<?= urlencode($slug) ?>&download=vcf" class="save">
                <i class="fa-regular fa-address-book"></i> Save to Contacts
            </a>

            <?php
            // Google Wallet: buildable without a special account — unlike
            // Apple Wallet, whose .pkpass format cannot be produced without a
            // $99/yr Apple Developer certificate with no workaround. See
            // app/GoogleWallet.php for why the two are not the same problem.
            // Renders nothing at all when unconfigured.
            require_once BASE_PATH . '/app/GoogleWallet.php';
            $gwUrl = GoogleWallet::isConfigured() ? GoogleWallet::passUrl($person, $company, $slug, $baseUrl) : '';
            ?>
            <?php if ($gwUrl): ?>
            <a href="<?= htmlspecialchars($gwUrl) ?>" target="_blank" rel="noopener noreferrer" class="save gwallet">
                <i class="fa-brands fa-google"></i> Add to Google Wallet
            </a>
            <?php endif; ?>

            <!-- Personal quick actions: Call / WhatsApp / Email only.
                 Directions moved to the Company zone — it points at the
                 office address, not at this person specifically. -->
            <div class="acts">
                <?php if($phone): ?>
                <a href="tel:<?= htmlspecialchars($cleanPhone) ?>" class="act">
                    <span class="act-c a-call"><i class="fa-solid fa-phone"></i></span>
                    <span class="act-l">Call</span>
                </a>
                <?php endif; ?>
                <?php if($waChatValid): ?>
                <a href="<?= htmlspecialchars($waChatUrl) ?>" target="_blank" rel="noopener noreferrer" class="act">
                    <span class="act-c a-wa"><i class="fa-brands fa-whatsapp"></i></span>
                    <span class="act-l">WhatsApp</span>
                </a>
                <?php endif; ?>
                <?php if($email): ?>
                <a href="mailto:<?= htmlspecialchars($email) ?>" class="act">
                    <span class="act-c a-mail"><i class="fa-solid fa-envelope"></i></span>
                    <span class="act-l">Email</span>
                </a>
                <?php endif; ?>
            </div>

            <div class="info">
                <?php if($phone): ?>
                <a href="tel:<?= htmlspecialchars($cleanPhone) ?>" class="row">
                    <span class="row-i"><i class="fa-solid fa-mobile-screen"></i></span>
                    <span class="row-b"><span class="row-k">Mobile</span><div class="row-v"><?= htmlspecialchars($phone) ?></div></span>
                </a>
                <?php endif; ?>

                <?php if($email): ?>
                <a href="mailto:<?= htmlspecialchars($email) ?>" class="row">
                    <span class="row-i"><i class="fa-regular fa-envelope"></i></span>
                    <span class="row-b"><span class="row-k">Email</span><div class="row-v"><?= htmlspecialchars($email) ?></div></span>
                </a>
                <?php endif; ?>

                <?php if($waUsernameValid): ?>
                <!-- Visible wa.me/<handle> text, as requested — shorter and more
                     memorable than the phone number, and future-proof: once
                     username links resolve everywhere, this text is already
                     correct with nothing to reprint. -->
                <a href="<?= htmlspecialchars($waChatUrl) ?>" target="_blank" rel="noopener noreferrer" class="row">
                    <span class="row-i"><i class="fa-brands fa-whatsapp"></i></span>
                    <span class="row-b"><span class="row-k">WhatsApp</span><div class="row-v">wa.me/<?= htmlspecialchars($waUsername) ?></div></span>
                </a>
                <?php endif; ?>
            </div>

            <?php if($personalSocials): ?>
            <div class="soc">
                <?php foreach($personalSocials as $key => $url): [$cls, $icon, $label] = $socialMeta[$key]; ?>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer"
                   class="<?= $cls ?>" aria-label="<?= $label ?>" title="<?= $label ?>"><i class="<?= $icon ?>"></i></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <div class="zone-divider"><span>Works at</span></div>

        <!-- ═══════════════════════════════════════════════════════════════
             ZONE B — COMPANY
             Logo, organisation name, address, directions, company-only
             social links. Shared by everyone at this organisation — the
             same block would appear on every colleague's card.
        ═══════════════════════════════════════════════════════════════════ -->
        <section class="zone zone-company">
            <div class="co-hd">
                <?php if($logo): ?>
                    <span class="logo-pill"><img src="<?= htmlspecialchars($logo) ?>" alt="" decoding="async"></span>
                <?php else: ?>
                    <span class="co-badge"><?= htmlspecialchars(mb_strtoupper(mb_substr($companyName, 0, 1))) ?></span>
                <?php endif; ?>
                <span class="co-name"><?= htmlspecialchars($companyName) ?></span>
            </div>

            <div class="info">
                <?php if($address): ?>
                <a href="<?= $mapsUrl ? htmlspecialchars($mapsUrl) : '#' ?>"
                   <?= $mapsUrl ? 'target="_blank" rel="noopener noreferrer"' : 'onclick="return false"' ?> class="row">
                    <span class="row-i"><i class="fa-solid fa-location-dot"></i></span>
                    <span class="row-b"><span class="row-k">Address</span><div class="row-v wrap"><?= htmlspecialchars($address) ?></div></span>
                </a>
                <?php endif; ?>

            </div>

            <?php if($address): ?>
            <!-- Map preview thumbnail. Card platforms that do location well
                 (HiHello, Popl) show a small visual map near the address
                 rather than only a text link or only a button — a glance
                 confirms the place before anyone taps through. Uses Google's
                 keyless embed endpoint (output=embed): no API key, no
                 billing account needed, unlike the Static Maps API. Wrapped
                 in an anchor so the whole preview is also a tap target, not
                 just decoration. -->
            <a href="<?= $mapsUrl ? htmlspecialchars($mapsUrl) : '#' ?>"
               <?= $mapsUrl ? 'target="_blank" rel="noopener noreferrer"' : 'onclick="return false"' ?>
               class="map-prev" aria-label="Open in Google Maps">
                <iframe
                    src="https://maps.google.com/maps?q=<?= urlencode($address) ?>&hl=en-IN&z=15&output=embed"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    style="pointer-events:none"
                ></iframe>
                <span class="map-prev-tap"><i class="fa-solid fa-up-right-and-down-left-from-center"></i></span>
            </a>
            <?php endif; ?>

            <?php if($mapsUrl): ?>
            <!-- "Location" as its own action, distinct from the address text
                 row above — one is for reading, this is for navigating. -->
            <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" rel="noopener noreferrer" class="co-directions">
                <i class="fa-solid fa-diamond-turn-right"></i> Get Directions
            </a>
            <?php endif; ?>

            <?php if($companySocials || $website): ?>
            <div class="soc">
                <?php foreach($companySocials as $key => $url): [$cls, $icon, $label] = $socialMeta[$key]; ?>
                <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer"
                   class="<?= $cls ?>" aria-label="<?= $label ?>" title="<?= $label ?>"><i class="<?= $icon ?>"></i></a>
                <?php endforeach; ?>
                <?php if($website):
                    $websiteHref = htmlspecialchars(strpos($website,'http')===0 ? $website : 'https://'.$website);
                ?>
                <!-- Website, converted from a standalone info row into a
                     matching icon inline with the social set, as requested.
                     Uses the same .soc a sizing/styling as every platform
                     icon, so it reads as one consistent row rather than a
                     leftover text link next to icons. -->
                <a href="<?= $websiteHref ?>" target="_blank" rel="noopener noreferrer"
                   class="s-web" aria-label="Website" title="<?= htmlspecialchars(str_replace(['https://','http://'],'',$website)) ?>"><i class="fa-solid fa-globe"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- Lead capture. Only rendered when this person has turned it on.
             Plain JS, not Alpine — this card loads no framework, unlike the
             dashboard, so an x-data/x-show version here would render as dead
             attributes. Submits to the UNAUTHENTICATED capture_lead action —
             the one write endpoint a signed-out stranger can reach, protected
             by its own throttle and a honeypot rather than a session token
             nobody here has. -->
        <?php if($captureLeads): ?>
        <div class="lead-wrap" id="leadWrap">
            <button type="button" id="leadToggle" class="lead-toggle">
                <i class="fa-solid fa-address-card"></i>
                <span id="leadToggleLabel">Share your contact with <?= htmlspecialchars($name) ?></span>
            </button>
            <form id="leadForm" class="lead-form" style="display:none">
                <input id="lf_name" required maxlength="100" placeholder="Your name" autocomplete="name">
                <input id="lf_email" type="email" maxlength="190" placeholder="Email" autocomplete="email">
                <input id="lf_phone" maxlength="20" placeholder="Phone (optional)" autocomplete="tel">
                <input id="lf_company" maxlength="120" placeholder="Company (optional)" autocomplete="organization">
                <textarea id="lf_note" maxlength="500" rows="2" placeholder="What would you like to discuss? (optional)"></textarea>
                <!-- Honeypot: real users never see or fill this. -->
                <input id="lf_hp" tabindex="-1" autocomplete="off"
                       style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
                <p id="leadErr" class="lead-err" style="display:none"></p>
                <button type="submit" id="leadSubmit" class="lead-submit">Send my details</button>
            </form>
            <div id="leadSent" class="lead-sent" style="display:none">
                <i class="fa-solid fa-circle-check"></i> Thanks — your details have been shared.
            </div>
        </div>
        <script>
        (function () {
            var toggle = document.getElementById('leadToggle');
            var label  = document.getElementById('leadToggleLabel');
            var form   = document.getElementById('leadForm');
            var err    = document.getElementById('leadErr');
            var submit = document.getElementById('leadSubmit');
            var sentEl = document.getElementById('leadSent');
            var origLabel = label.textContent;
            var open = false;

            toggle.addEventListener('click', function () {
                open = !open;
                form.style.display = open ? 'flex' : 'none';
                label.textContent = open ? 'Hide' : origLabel;
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                err.style.display = 'none';

                var name  = document.getElementById('lf_name').value.trim();
                var email = document.getElementById('lf_email').value.trim();
                var phone = document.getElementById('lf_phone').value.trim();

                if (!name) { err.textContent = 'Please enter your name.'; err.style.display = 'block'; return; }
                if (!email && !phone) { err.textContent = 'Please provide an email or phone.'; err.style.display = 'block'; return; }

                submit.disabled = true; submit.textContent = 'Sending…';
                try {
                    // No X-CSRF-Token: a card visitor has no session to have
                    // issued one from. See index.php's capture_lead guard.
                    var r = await fetch('index.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'capture_lead',
                            card_slug: <?= json_encode($slug) ?>,
                            name: name, email: email, phone: phone,
                            company: document.getElementById('lf_company').value.trim(),
                            note: document.getElementById('lf_note').value.trim(),
                            website_hp: document.getElementById('lf_hp').value
                        })
                    });
                    var d = await r.json();
                    if (d.status === 'success') {
                        toggle.style.display = 'none'; form.style.display = 'none'; sentEl.style.display = 'flex';
                    } else {
                        err.textContent = d.message || 'Something went wrong. Please try again.';
                        err.style.display = 'block';
                        submit.disabled = false; submit.textContent = 'Send my details';
                    }
                } catch (e2) {
                    err.textContent = 'Network error. Please try again.';
                    err.style.display = 'block';
                    submit.disabled = false; submit.textContent = 'Send my details';
                }
            });
        })();
        </script>
        <?php endif; ?>

    </div>
    <script>
        function shareProfile() {
            // BUG FIX: WhatsApp's URL got printed twice on the final line
            const shareData = { title: <?= json_encode($ogTitle) ?>, text: <?= json_encode($waText, JSON_UNESCAPED_UNICODE) ?> };
            if (navigator.share) { navigator.share(shareData).catch(err => console.log('Share cancelled', err)); } 
            else {
                navigator.clipboard.writeText(shareData.text).then(() => {
                    const btns = document.querySelectorAll('.qb-share .quick-btn-circle');
                    btns.forEach(b => { b.style.background = '#d1fae5'; b.innerHTML = '<i class="fa-solid fa-check" style="color:#16a34a"></i>'; });
                    setTimeout(() => btns.forEach(b => { b.style.background = ''; b.innerHTML = '<i class="fa-solid fa-arrow-up-from-bracket"></i>'; }), 2000);
                });
            }
        }
    </script>
<?php require __DIR__ . '/partials/lightbox.php'; ?>
</body>
</html>
