<?php  
declare(strict_types=1);

$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
 
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH'))  define('IMG_PATH',  BASE_PATH . '/images');
if (!defined('DOC_PATH'))  define('DOC_PATH',  BASE_PATH . '/docs');
if (!defined('SESSION_PATH')) define('SESSION_PATH', DATA_PATH . '/sessions');

if (!file_exists(SESSION_PATH)) @mkdir(SESSION_PATH, 0755, true);
require_once BASE_PATH . '/app/bootstrap.php';
if (is_file(BASE_PATH . '/app/VcardBuilder.php')) require_once BASE_PATH . '/app/VcardBuilder.php';

if (session_status() === PHP_SESSION_NONE) AppAuth::initSession();
if (empty($_SESSION['user']) || ($_SESSION['user'] !== 'admin' && $_SESSION['user'] !== 'crm')) {
    http_response_code(403);
    die("<div style='padding:20px;font-family:sans-serif;color:red;font-weight:bold;'>Access Denied. Please log in via the main dashboard.</div>");
}

 
function vcf_esc(string $v): string {
    $v = str_replace(["\r\n", "\r", "\n"], ' ', trim($v));
    return str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $v);
}

 
function vcf_fold(string $line): string {
    if (strlen($line) <= 75) return $line . "\r\n";
    $out = substr($line, 0, 75) . "\r\n";
    $rest = substr($line, 75);
    foreach (str_split($rest, 74) as $chunk) $out .= ' ' . $chunk . "\r\n";
    return $out;
}

 
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
    $max = 400;                                    
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

     
     
    $parts  = preg_split('/\s+/', $name) ?: [$name];
    $family = count($parts) > 1 ? array_pop($parts) : '';
    $given  = array_shift($parts) ?? '';
    $middle = implode(' ', $parts);

    $role = (string)($t['designation'] ?? $desig[(string)($t['designation_id'] ?? $t['designation_code'] ?? '')] ?? '');
    $dep  = (string)($t['department']  ?? $dept[(string)($t['department_id']  ?? $t['department_code']  ?? '')] ?? '');

     
    $p = is_array($t) ? $t : [];
    if ($role !== '' && $role !== '-') {
        $p['title'] = $role;
    }
    if ($dep !== '' && $dep !== '-') {
        $p['department_name'] = $dep;
    }
    if ($locRec) {
        if (empty($p['address'])) {
            $p['address'] = trim((string)($locRec['address'] ?? ''));
        }
        $p['city'] = $p['city'] ?? ($locRec['city'] ?? '');
        $p['state'] = $p['state'] ?? ($locRec['state'] ?? '');
        $p['pincode'] = $p['pincode'] ?? ($locRec['pincode'] ?? '');
        $p['location_name'] = $p['location_name'] ?? ($locRec['name'] ?? '');
    }
    $co = ['name' => $orgName, 'website' => $orgSite];
    if (class_exists('VcardBuilder')) {
        $card = VcardBuilder::build($p, $co, [
            'include_photo' => $includePic,
            'include_bday' => false,
            'uid' => !empty($t['id']) ? ('urn:uuid:rc-' . preg_replace('/[^A-Za-z0-9._-]/', '', (string)$t['id'])) : '',
        ]);
         
        if (!empty($t['slug']) && class_exists('AppUtils')) {
            $cardUrl = rtrim(AppUtils::getBaseUrl(), '/') . '/?card=business&slug=' . rawurlencode((string)$t['slug']);
            $card = str_replace("END:VCARD\r\n", 'URL;TYPE=DigitalCard:' . VcardBuilder::esc($cardUrl) . "\r\nEND:VCARD\r\n", $card);
            if (!str_contains($card, 'END:VCARD')) {
                $card = str_replace('END:VCARD', 'URL:' . VcardBuilder::esc($cardUrl) . "\r\nEND:VCARD", $card);
            }
        }
    } else {
        $card = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:" . vcf_esc((string)($t['name'] ?? '')) . "\r\nEND:VCARD\r\n";
    }

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

 
 
 
$fileCompany = preg_replace('/[^A-Za-z0-9]+/', '', $orgName) ?: 'Directory';
$file = date('ymd') . '_' . $fileCompany . '_' . $count . '.vcf';

if (ob_get_length()) ob_end_clean();
header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . addslashes($file) . '"');
header('Content-Length: ' . strlen($vcf));
header('Cache-Control: no-store');
echo $vcf;
