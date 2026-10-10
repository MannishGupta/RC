<?php // Version: 260916.14
declare(strict_types=1);
/**
 * tools/export_vcf.php — Export the whole directory as a single .vcf file.
 *
 * Sits beside the Print Directory button. One download imports every contact
 * into a phone's address book in one action.
 *
 * FORMAT: vCard 3.0, not 4.0. This is deliberate — 3.0 is what iOS Contacts,
 * Google Contacts, Outlook and Android all import reliably today. 4.0 is the
 * newer spec but its support is still uneven, especially for embedded photos,
 * which is the main thing that makes these cards useful on a phone.
 *
 * Photos are embedded as base64 inside each card (not linked), so contacts
 * keep their picture even offline and even if this server is unreachable
 * later. Photos are downscaled first — a 3 MB camera JPEG per person would
 * make a 200-person export unusable, and phones reject oversized cards.
 *
 * Line folding: RFC 6350 requires lines over 75 octets to be folded. Several
 * importers silently drop a contact whose photo line is one giant unfolded
 * string, so every base64 payload is folded explicitly.
 */

$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
// BUG FIX: this file defined DATA_PATH/IMG_PATH/DOC_PATH directly from
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH'))  define('IMG_PATH',  BASE_PATH . '/images');
if (!defined('DOC_PATH'))  define('DOC_PATH',  BASE_PATH . '/docs');
if (!defined('SESSION_PATH')) define('SESSION_PATH', DATA_PATH . '/sessions');

if (!file_exists(SESSION_PATH)) @mkdir(SESSION_PATH, 0755, true);
require_once BASE_PATH . '/app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) AppAuth::initSession();
if (empty($_SESSION['user']) || ($_SESSION['user'] !== 'admin' && $_SESSION['user'] !== 'crm')) {
    http_response_code(403);
    die("<div style='padding:20px;font-family:sans-serif;color:red;font-weight:bold;'>Access Denied. Please log in via the main dashboard.</div>");
}

/** Strip CR/LF and escape the characters vCard treats as structural. */
function vcf_esc(string $v): string {
    $v = str_replace(["\r\n", "\r", "\n"], ' ', trim($v));
    return str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $v);
}

/** Fold a long value to 75 octets per RFC 6350 (continuation lines start with a space). */
function vcf_fold(string $line): string {
    if (strlen($line) <= 75) return $line . "\r\n";
    $out = substr($line, 0, 75) . "\r\n";
    $rest = substr($line, 75);
    foreach (str_split($rest, 74) as $chunk) $out .= ' ' . $chunk . "\r\n";
    return $out;
}

/** Downscale a photo and return base64 JPEG, or '' if unavailable/too large. */
function vcf_photo(string $file): string {
    $path = IMG_PATH . DIRECTORY_SEPARATOR . basename($file);
    if ($file === '' || !is_file($path)) return '';
    if (!function_exists('imagecreatetruecolor')) {
        $raw = (string)@file_get_contents($path);
        return (strlen($raw) && strlen($raw) < 200000) ? base64_encode($raw) : '';
    }
    $info = @getimagesize($path);
    if (!$info) return '';
    $src = match ($info['mime']) {
        'image/jpeg', 'image/pjpeg' => @imagecreatefromjpeg($path),
        'image/png'                 => @imagecreatefrompng($path),
        'image/webp'                => @imagecreatefromwebp($path),
        'image/gif'                 => @imagecreatefromgif($path),
        default                     => null,
    };
    if (!$src) return '';

    $w = imagesx($src); $h = imagesy($src);
    $max = 400;                                   // plenty for a contact thumbnail
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    ob_start();
    imagejpeg($dst, null, 82);
    $data = (string)ob_get_clean();

    imagedestroy($src); imagedestroy($dst);
    return $data !== '' ? base64_encode($data) : '';
}

// ── Load and resolve ──────────────────────────────────────────────────────
$team    = AppDB::read('team') ?: [];
$company = AppDB::read('company') ?: [];
$desig   = AppLookup::map(AppDB::read('designations') ?: []);
$dept    = AppLookup::map(AppDB::read('departments')  ?: []);

$locs = [];
foreach (AppDB::read('locations') ?: [] as $l) {
    if (!empty($l['id'])) $locs[(string)$l['id']] = $l;
}

$orgName    = (string)($company['name'] ?? '');
$orgSite    = (string)($company['website'] ?? '');
$includePic = (($_GET['photos'] ?? '1') !== '0');

$vcf = '';
$count = 0;

foreach ($team as $t) {
    $name = trim((string)($t['name'] ?? ''));
    if ($name === '') continue;

    // Split into N: components. Indian directory data is usually
    // "First Middle Last"; last token treated as family name.
    $parts  = preg_split('/\s+/', $name) ?: [$name];
    $family = count($parts) > 1 ? array_pop($parts) : '';
    $given  = array_shift($parts) ?? '';
    $middle = implode(' ', $parts);

    $role = (string)($t['designation'] ?? $desig[(string)($t['designation_id'] ?? $t['designation_code'] ?? '')] ?? '');
    $dep  = (string)($t['department']  ?? $dept[(string)($t['department_id']  ?? $t['department_code']  ?? '')] ?? '');

    $card  = "BEGIN:VCARD\r\nVERSION:3.0\r\n";
    // N stays properly structured (Family;Given;Middle) so clients can still
    // sort by surname and match duplicates correctly.
    $card .= 'N:' . vcf_esc($family) . ';' . vcf_esc($given) . ';' . vcf_esc($middle) . ";;\r\n";

    // FN is the display name and is explicitly rebuilt as First Middle Last,
    // rather than echoing the raw stored string (which may carry stray
    // spacing or already be in "Last, First" order in older records).
    $fnOrdered = trim(preg_replace('/\s+/', ' ', $given . ' ' . $middle . ' ' . $family));
    if ($fnOrdered === '') $fnOrdered = $name;
    $card .= 'FN:' . vcf_esc($fnOrdered) . "\r\n";

    // FILE-AS / sort order.
    // Most clients default to filing by surname ("Gupta, Bhuvan"). SORT-STRING
    // is the vCard 3.0 property for overriding that, and X-EVOLUTION-FILE-AS
    // covers Evolution/Thunderbird which read that instead.
    // NOTE: Outlook's "File As" is ALSO governed by a local client setting
    // (File > Options > Contacts > "Default File As order"). A vCard can
    // request an order but cannot override that preference — if Outlook is
    // set to "Last, First" it will re-file on import regardless of any file.
    $card .= 'SORT-STRING:' . vcf_esc($fnOrdered) . "\r\n";
    $card .= 'X-EVOLUTION-FILE-AS:' . vcf_esc($fnOrdered) . "\r\n";

    // ── Linked location, resolved early because ORG depends on it ──────
    $locId  = (string)($t['location_id'] ?? '');
    $locRec = ($locId !== '' && isset($locs[$locId])) ? $locs[$locId] : null;
    $mapUrl = $locRec ? trim((string)($locRec['map_url'] ?? $locRec['map_link'] ?? '')) : '';

    // Base location = the location record's name, falling back to its city.
    $baseLocation = '';
    if ($locRec) {
        $baseLocation = trim((string)($locRec['name'] ?? ''));
        if ($baseLocation === '') $baseLocation = trim((string)($locRec['city'] ?? ''));
    }

    // ── ORG: "Base Location";"Company" ─────────────────────────────────
    // vCard ORG is a structured field: component 1 is the organisation,
    // components 2+ are organisational units. Most clients (Google Contacts,
    // Outlook, iOS) surface component 1 in the "Company" slot and component 2
    // as "Department".
    //
    // NOTE ON THE ORDER: putting Base Location first means those clients will
    // display the LOCATION where they say "Company" (e.g. Company = "New Delhi",
    // Department = "Arthsathi Limited"). That is the requested order and is
    // implemented as asked — it groups contacts by site in a phone's company
    // view, which is useful for a multi-site directory. If you would rather
    // the company name occupy the Company slot, swap the two arguments below.
    $orgParts = array_values(array_filter([
        $baseLocation !== '' ? $baseLocation : null,
        $orgName      !== '' ? $orgName      : null,
    ]));
    if ($orgParts) {
        $card .= 'ORG:' . implode(';', array_map('vcf_esc', $orgParts)) . "\r\n";
    }

    // Department no longer fits in ORG under the requested layout, so it is
    // preserved as an extension rather than silently dropped. Outlook reads
    // X-MS-OL-DESIGN-less extensions harmlessly; other clients ignore it.
    if ($dep !== '' && $dep !== '-') {
        $card .= 'X-DEPARTMENT:' . vcf_esc($dep) . "\r\n";
        // CATEGORIES is the portable way to group contacts. Google Contacts
        // turns each value into a LABEL, iOS into a GROUP, Outlook into
        // categories — so an imported directory arrives pre-organised by
        // department instead of as one flat list of names. This also restores
        // department visibility, which moved out of ORG in 260906.7.
        $card .= 'CATEGORIES:' . vcf_esc($dep) . "\r\n";
    }
    if ($role !== '' && $role !== '-') $card .= 'TITLE:' . vcf_esc($role) . "\r\n";

    // Phones — the record may hold a single string or an array.
    foreach ((array)($t['phone'] ?? []) as $ph) {
        $ph = trim((string)$ph);
        if ($ph !== '') $card .= 'TEL;TYPE=CELL,VOICE:' . vcf_esc($ph) . "\r\n";
    }
    foreach ((array)($t['email'] ?? []) as $em) {
        $em = trim((string)$em);
        if ($em !== '') $card .= 'EMAIL;TYPE=INTERNET,WORK:' . vcf_esc($em) . "\r\n";
    }

    // ── Labelled-URL helper ────────────────────────────────────────────
    // Declared here because EVERY url on the card goes through it. A bare
    // `URL:` line carries no label, so Google Contacts and Outlook both file
    // it under "Other" — which is exactly what was happening to the website
    // and the digital-card link. Item grouping (itemN.URL + itemN.X-ABLabel)
    // is what actually carries a human label across clients.
    $itemNo = 0;
    $addLabelledUrl = function (string $url, string $label) use (&$card, &$itemNo) {
        $url = trim($url);
        if ($url === '') return;
        // Accept values saved without a scheme (e.g. "arthsathi.com").
        if (!preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url)) $url = 'https://' . ltrim($url, '/');
        // Only http(s) may be written out.
        if (!preg_match('~^https?://~i', $url)) return;
        $itemNo++;
        $card .= 'item' . $itemNo . '.URL:' . vcf_esc($url) . "\r\n";
        $card .= 'item' . $itemNo . '.X-ABLabel:' . vcf_esc($label) . "\r\n";
    };

    // Personal/work address stored ON THE TEAM RECORD. This field exists in
    // the team schema and is shown on the business card, but was never read
    // by the exporter — so anyone whose address differs from their site's
    // address silently lost it. Emitted as HOME so it does not collide with
    // the WORK address derived from the location record below.
    $ownAddr = trim((string)($t['address'] ?? ''));
    if ($ownAddr !== '') {
        $card .= 'ADR;TYPE=HOME:;;' . vcf_esc($ownAddr) . ';;;;' . "\r\n";
    }

    // Postal address from the same location record resolved above.
    if ($locRec) {
        $card .= 'ADR;TYPE=WORK:;;'
               . vcf_esc((string)($locRec['address'] ?? '')) . ';'
               . vcf_esc((string)($locRec['city'] ?? '')) . ';'
               . vcf_esc((string)($locRec['state'] ?? '')) . ';'
               . vcf_esc((string)($locRec['pincode'] ?? '')) . ";\r\n";
    }

    // Primary website, explicitly labelled "Homepage".
    $addLabelledUrl((string)($t['website'] ?? $orgSite), 'Homepage');

    // Google Maps pin, labelled "Location".
    if ($mapUrl !== '') $addLabelledUrl($mapUrl, 'Location');

    // BDAY must be ISO yyyy-mm-dd or phones ignore it.
    $dob = trim((string)($t['dob'] ?? ''));
    if ($dob !== '') {
        $ts = strtotime(str_replace('/', '-', $dob));
        if ($ts) $card .= 'BDAY:' . date('Y-m-d', $ts) . "\r\n";
    }

    // Blood group / gender have no standard field — X- extensions keep them
    // without breaking importers that don't understand them.
    if (!empty($t['blood_group'])) $card .= 'X-BLOOD-GROUP:' . vcf_esc((string)$t['blood_group']) . "\r\n";
    if (!empty($t['gender']))      $card .= 'X-GENDER:'      . vcf_esc((string)$t['gender']) . "\r\n";

    // ── Social profiles & links ────────────────────────────────────────
    // PREVIOUS BUG: these were emitted only as X-SOCIALPROFILE, which is an
    // APPLE-ONLY extension. Google Contacts and Outlook ignore it entirely,
    // so every social link silently vanished on import for most users.
    //
    // The portable form is Apple's "item grouping": a numbered itemN.URL
    // paired with itemN.X-ABLabel. Despite the X-AB prefix this is the de
    // facto standard for labelled URLs in vCard 3.0 and is imported as a
    // labelled website by Google Contacts, Outlook, iOS and Android alike.
    // X-SOCIALPROFILE is still emitted alongside it so Apple Contacts shows
    // them in its dedicated Social section — the two are complementary, and
    // clients ignore whichever they do not understand.
    //
    // The stored value may be a decoded array OR a raw JSON string (older
    // records were saved before index.php began decoding on write), so both
    // shapes are handled.
    $social = $t['social'] ?? [];
    if (is_string($social)) {
        $decoded = json_decode($social, true);
        $social  = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($social)) $social = [];

    $socialMap = [
        'linkedin'  => ['LinkedIn',  'linkedin'],
        'twitter'   => ['X (Twitter)', 'twitter'],
        'x'         => ['X (Twitter)', 'twitter'],
        'instagram' => ['Instagram', 'instagram'],
        'facebook'  => ['Facebook',  'facebook'],
        'youtube'   => ['YouTube',   'youtube'],
        'telegram'  => ['Telegram',  'telegram'],
        'whatsapp'  => ['WhatsApp',  'whatsapp'],
    ];
    foreach ($socialMap as $key => [$label, $appleType]) {
        $val = trim((string)($social[$key] ?? ''));
        if ($val === '') continue;
        $addLabelledUrl($val, $label);                     // portable
        $full = preg_match('~^https?://~i', $val) ? $val : 'https://' . ltrim($val, '/');
        $card .= 'X-SOCIALPROFILE;TYPE=' . $appleType . ':' . vcf_esc($full) . "\r\n";  // Apple
    }

    // Link back to the live digital card so the saved contact stays current.
    // PREVIOUS BUG: written as URL;TYPE=Digital Card — a TYPE value cannot
    // contain a space, so strict parsers rejected the line (and with it,
    // sometimes the whole card). Now a properly labelled item group.
    if (!empty($t['slug'])) {
        $addLabelledUrl(rtrim(AppUtils::getBaseUrl(), '/') . '/?card=business&slug=' . rawurlencode((string)$t['slug']), 'Digital Card');
    }

    if ($includePic && !empty($t['photo'])) {
        $b64 = vcf_photo((string)$t['photo']);
        if ($b64 !== '') $card .= vcf_fold('PHOTO;ENCODING=b;TYPE=JPEG:' . $b64);
    }

    // UID — stable per-contact identifier taken from the record's own id.
    // Without it, re-exporting after an edit creates DUPLICATE contacts on
    // every phone that imports the file. With it, compliant clients match the
    // existing card and update it instead. Namespaced so it cannot collide
    // with UIDs from another address book on the same device.
    if (!empty($t['id'])) {
        $card .= 'UID:urn:uuid:arthsathi-' . vcf_esc(preg_replace('/[^A-Za-z0-9._-]/', '', (string)$t['id'])) . "\r\n";
    }

    $card .= 'REV:' . gmdate('Y-m-d\TH:i:s\Z') . "\r\n";
    $card .= "END:VCARD\r\n";

    $vcf .= $card;
    $count++;
}

if ($count === 0) {
    http_response_code(404);
    die("<div style='padding:20px;font-family:sans-serif'>No contacts found to export.</div>");
}

if (class_exists('AppLog')) {
    AppLog::info('Directory exported to VCF', ['contacts' => $count, 'by' => $_SESSION['user']]);
}

// Filename: yymmdd + company + contact count, e.g. 260906_Arthsathi_42.vcf
// Date first so a folder of exports sorts chronologically; the count makes it
// obvious at a glance whether an export is complete or was taken mid-edit.
$fileCompany = preg_replace('/[^A-Za-z0-9]+/', '', $orgName) ?: 'Directory';
$file = date('ymd') . '_' . $fileCompany . '_' . $count . '.vcf';

if (ob_get_length()) ob_end_clean();
header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . addslashes($file) . '"');
header('Content-Length: ' . strlen($vcf));
header('Cache-Control: no-store');
echo $vcf;
