<?php
// wacard.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

// controller/wacard.php — WhatsApp PNG image card generator (exits early)

// ── WhatsApp PNG Image Card (exits early) ──────────────────────────
if (!empty($_GET['wacard']) && !empty($_GET['slug'])) {
    $wcSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['slug']);
    $wcData = null;
    if (class_exists('AppDB')) {
        if (method_exists('AppDB', 'readOne')) {
            $wcData = AppDB::readOne('team', ['slug' => $wcSlug]);
        } else {
            $t = AppDB::read('team') ?? [];
            foreach ($t as $row) {
                if (strcasecmp((string)($row['slug'] ?? ''), $wcSlug) === 0) {
                    $wcData = $row;
                    break;
                }
            }
        }
    }
    
    if ($wcData && function_exists('imagecreatetruecolor')) {
        // Map DB row to expected generateReport() keys — team row fields vary
        $wcPData = [
            'name'   => trim((string)($wcData['name']   ?? ($wcData['full_name'] ?? ''))),
            'dob'    => trim((string)($wcData['dob']    ?? ($wcData['birth_date'] ?? ''))),
            'phone'  => preg_replace('/\D/', '', (string)($wcData['phone'] ?? ($wcData['mobile'] ?? ''))),
            'gender' => preg_replace('/[^a-zA-Z]/', '', (string)($wcData['gender'] ?? 'Unknown')),
            'photo'  => (string)($wcData['photo'] ?? ''),
        ];
        if (empty($wcPData['name']) || empty($wcPData['dob'])) {
            header('Content-Type: text/plain'); echo 'Incomplete profile data.'; exit;
        }
        $wcReport = AppNumeroEngine::generateReport($wcPData, 'elite');
        $wcp      = $wcReport['profile'];
        $wcc      = $wcReport['core'];
        $wcPlanet = $wcReport['lucky_driver']['name'] ?? '';
        $wcScore  = (int)$wcReport['summary']['score'];
        
        $im     = imagecreatetruecolor(800, 420);
        $bg     = imagecolorallocate($im, 15, 23, 42);
        $accent = imagecolorallocate($im, 249, 115, 22);
        $white  = imagecolorallocate($im, 241, 245, 249);
        $muted  = imagecolorallocate($im, 100, 116, 139);
        $gold   = imagecolorallocate($im, 234, 179, 8);
        
        imagefill($im, 0, 0, $bg);
        imagefilledrectangle($im, 0, 0, 800, 6, $accent);
        
        $font = 5;
        imagestring($im, $font, 40, 40, $wcp['name'], $white);
        
        $langToggle = (isset($_GET['lang']) && $_GET['lang'] === 'hi') ? 'hi' : 'en';
        imagestring($im, 4, 40, 75, $langToggle === 'hi' ? 'वैदिक अंकशास्त्र रिपोर्ट' : 'Vedic Numerology Report', $muted);
        imagestring($im, 3, 40, 100, $wcp['dob'] . ' · ' . $wcp['gender'], $muted);
        
        $nums = [
            ['Driver',     $wcc['driver']],
            ['Life Path',  $wcc['conductor']],
            ['Expression', $wcReport['name_matrix']['full']['root'] ?? 0],
            ['Soul Urge',  $wcReport['name_matrix']['soul_urge']['root'] ?? 0],
        ];
        
        $xOff = 40;
        foreach ($nums as $nm) {
            imagefilledrectangle($im, $xOff, 140, $xOff + 95, 230, imagecolorallocate($im, 30, 41, 59));
            imagestring($im, 2, $xOff + 8, 148, $nm[0], $muted);
            imagestring($im, 5, $xOff + 28, 170, (string)$nm[1], $accent);
            $xOff += 110;
        }
        
        imagestring($im, 4, 40, 260, 'Planet: ' . $wcPlanet, $white);
        imagestring($im, 4, 40, 290, 'Vibrational Score: ' . $wcScore . '/100', $gold);
        imageline($im, 40, 330, 760, 330, $muted);
        
        $rawH    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $safeH   = preg_replace('/[^a-zA-Z0-9.:-]/', '', $rawH);
        $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://';
        $footUrl = $proto . $safeH . strtok($_SERVER['REQUEST_URI'] ?? '/', '?') . '?card=numero&slug=' . urlencode($wcSlug);
        
        imagestring($im, 2, 40, 345, $footUrl, $muted);
        // GD's built-in bitmap fonts cannot render Devanagari, so the Hindi variant
        // was already drawing as garbage here. Use a single ASCII string plus the
        // real build number instead of a hardcoded, wrong version.
        imagestring($im, 2, 40, 370, 'Arthsathi Vedic Numero Engine ' . (defined('APP_VERSION') ? APP_VERSION : ''), $muted);
        
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=3600');
        imagepng($im);
        imagedestroy($im);
        exit;
    }
}
