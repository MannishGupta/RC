<?php
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | Release v14.1.0 (2026-10-11)
   Single release version across all files. See CHANGELOG.md for changes.
   ───────────────────────────────────────────────────────────────────── */
// admin_backend.php — Admin Logic Engine
// Brute-force throttle, HTTPS-safe cookies, CSP, random IDs,
// extended analytics (top project/tower/referrer/device), privacy-clean IPs
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
AppLocale::boot();

$IDLE_TIMEOUT = max(60, (int)cfg('admin.idle_timeout', 28800));

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', isHttps() ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime',  (string)$IDLE_TIMEOUT);
ini_set('session.cookie_lifetime', '0');      // cookie lives until browser close
session_name(cfg('site.cookie_prefix', 'fusion') . '_admin_secure');
session_start();
if (isset($_SESSION['logged_in'])) {
    // Idle timeout: kill session if admin has been inactive for >8 hours.
    // The comparison MUST happen before _last_active is refreshed, otherwise
    // the elapsed time is always 0 and the timeout never fires.
    if (isset($_SESSION['_last_active']) && (time() - (int)$_SESSION['_last_active']) > $IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        header('Location: admin.php?msg=' . urlencode('Session expired due to inactivity. Please log in again.'));
        exit;
    }
    $_SESSION['_last_active'] = time();
}

// ─── 1. Security / Content-Security-Policy headers ─────────────────────────
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
}

// A storage failure must never leave a half-written state, dump a trace, or show
// a blank page. Report it to the admin and stop before anything else is written.
set_exception_handler(static function (Throwable $e): void {
    error_log('admin_backend: ' . $e->getMessage());
    if (!headers_sent()) {
        header('Location: admin.php?msg=' . urlencode($e->getMessage()));
    }
    exit;
});

// ─── Asset paths (configurable) ──────────────────────────────────────────────
$projectLogo = assetPath('assets/BnR.png');
$companyLogo = assetPath('assets/ag-image.png');
$favicon     = 'assets/favicon.ico?v=' . (@filemtime(__DIR__ . '/assets/favicon.ico') ?: time());

// Ensure data directory exists
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// ─── Admin password hash ─────────────────────────────────────────────────────
// Sourced from config.php, which prefers the ADMIN_HASH environment variable.
// Set it in Plesk and remove the fallback from config.php. (Audit §11)
define('ADMIN_HASH', (string)cfg('admin.hash', ''));
if (ADMIN_HASH === '') {
    http_response_code(500);
    exit('ADMIN_HASH is not configured. Set it in config.php or the environment.');
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ─── 2. Login with brute-force throttle ──────────────────────────────────────
define('LOGIN_MAX_ATTEMPTS', max(1, (int)cfg('admin.login_attempts', 5)));
define('LOGIN_LOCKOUT_SECS', max(30, (int)cfg('admin.login_lockout', 900)));

// Throttle state is held server-side keyed on the client IP — NOT in the
// session. A session-held counter is no throttle at all: the attacker owns the
// cookie, so dropping it resets the count and the lockout never bites.
$loginLockedMinutes = 0;

if (isset($_POST['login']) && is_string($_POST['password'] ?? null)) {
    $state = throttleStatus();

    if ($state['locked']) {
        http_response_code(429);
        header('Retry-After: ' . max(1, $state['remaining']));
        $loginLockedMinutes = (int)ceil(max(1, $state['remaining']) / 60);
    } elseif (password_verify($_POST['password'], ADMIN_HASH)) {
        throttleClear();
        session_regenerate_id(true);
        $_SESSION['logged_in']    = true;
        $_SESSION['_last_active'] = time();
        header('Location: admin.php');
        exit;
    } else {
        throttleRegisterFailure();
        // Uniform delay on failure: blunts rapid-fire guessing and flattens the
        // timing difference between a wrong password and a lockout.
        usleep(400000);
    }
}

// ─── 3. Initialize View Variables ────────────────────────────────────────────
$activeTab          = in_array($_GET['tab'] ?? '', ['updates','components','projects','analytics'], true) ? $_GET['tab'] : 'updates';
$editProjData       = ['id'=>'','name'=>'','rera'=>'','logo'=>''];
$editCompData       = ['id'=>'','project_id'=>'','type'=>'','job'=>''];
$editUpdateData     = ['id'=>'','component_id'=>'','date'=>date('Y-m-d'),'description'=>'','images'=>[],'legacy_title'=>''];
$editProjectId      = '';
$dateForInput       = date('Y-m-d');
$projects           = [];
$components         = [];
$updates            = [];
$analytics          = [];
$projMap            = [];
$compMap            = [];
$feedGroups         = [];
$legacyUpdates      = [];
$a_totalVisits      = 0;
$a_uniqueVisitors   = 0;
$a_avgTimeFormatted = '0m 0s';
$a_interactions     = 0;
$a_topProject       = '—';
$a_topTower         = '—';
$a_topReferrer      = 'Direct';
$a_deviceBreakdown  = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0, 'Unknown' => 0];

// Not logged in — halt backend execution
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    return;
}

// ─── 4. CSRF Protection — per session ───────────────────────────────────────
// Previously derived from ADMIN_HASH alone, which made the token identical for
// every session forever: a token leaked through browser history, a proxy log or
// a Referer header stayed valid indefinitely. It is now bound to a random
// per-session seed, so it rotates with the login. (Audit §10)
//
// Note: this invalidates any bookmarked admin delete URLs — they carry the old
// token and will be rejected. That is the intended behaviour.
function makeCsrfToken(): string {
    if (empty($_SESSION['_csrf_seed']) || !is_string($_SESSION['_csrf_seed'])) {
        $_SESSION['_csrf_seed'] = bin2hex(random_bytes(32));
    }
    return hash_hmac('sha256', $_SESSION['_csrf_seed'], ADMIN_HASH);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals(makeCsrfToken(), $submittedToken)) {
        header('Location: admin.php?msg=' . urlencode('Security check failed — please reload and try again.'));
        exit;
    }
}

function validateGetToken(): bool {
    $token = $_GET['_t'] ?? '';
    return !empty($token) && hash_equals(makeCsrfToken(), $token);
}

// ─── 5. Action Router (GET) ──────────────────────────────────────────────────
if (isset($_GET['delete_project'])) {
    if (!validateGetToken()) { header('Location: admin.php?tab=projects&msg=' . urlencode('Invalid security token.')); exit; }
    $projects = readStoreForWrite(PROJ_FILE);
    $delId    = (string)$_GET['delete_project'];
    $projects = array_values(array_filter($projects, fn($p) => (string)$p['id'] !== $delId));
    atomicWriteJson(PROJ_FILE, $projects);
    header('Location: admin.php?tab=projects&msg=' . urlencode('Project deleted.'));
    exit;
}

if (isset($_GET['delete_comp'])) {
    if (!validateGetToken()) { header('Location: admin.php?tab=components&msg=' . urlencode('Invalid security token.')); exit; }
    $components = readStoreForWrite(COMP_FILE);
    $delId      = (string)$_GET['delete_comp'];
    $components = array_values(array_filter($components, fn($c) => (string)$c['id'] !== $delId));
    atomicWriteJson(COMP_FILE, $components);
    header('Location: admin.php?tab=components&msg=' . urlencode('Component deleted.'));
    exit;
}

if (isset($_GET['delete_update'])) {
    if (!validateGetToken()) { header('Location: admin.php?tab=updates&msg=' . urlencode('Invalid security token.')); exit; }
    $updates = readStoreForWrite(UPD_FILE);
    $delId   = (string)$_GET['delete_update'];
    $updates = array_values(array_filter($updates, fn($u) => (string)$u['id'] !== $delId));
    atomicWriteJson(UPD_FILE, $updates);
    header('Location: admin.php?tab=updates&msg=' . urlencode('Record deleted.'));
    exit;
}

// ─── 6. Action Router (POST) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save_site_config') {
        $configFile = DATA_DIR . DIRECTORY_SEPARATOR . 'site_config.json';
        $existing = safeReadJson($configFile);

        // Accept BOTH an app-relative path (assets/wip.mp4) and an absolute
        // http(s) URL. The field used to be <input type="url">, which made the
        // browser reject the relative form the placeholder itself suggested —
        // so a relative path could never be saved at all.
        $rawUrl = trim($_POST['video_url'] ?? '');
        $rawUrl = ltrim($rawUrl, '/');
        $safe   = $rawUrl !== ''
            && !preg_match('#^\s*(javascript|data|vbscript|file)\s*:#i', $rawUrl)
            && (preg_match('#^https?://#i', $rawUrl) || !str_contains($rawUrl, ':'))
            && !str_contains($rawUrl, '..');
        $existing['video_url'] = $safe ? $rawUrl : '';
        $existing['video_title'] = trim(strip_tags($_POST['video_title'] ?? 'Site Aerial Overview')) ?: 'Site Aerial Overview';
        atomicWriteJson($configFile, $existing);
        header('Location: admin.php?tab=updates&msg=' . urlencode('Featured media settings saved.'));
        exit;
    }

    if ($action === 'optimize_system') {
        $projects   = readStoreForWrite(PROJ_FILE);
        $components = readStoreForWrite(COMP_FILE);
        $updates    = readStoreForWrite(UPD_FILE);
        $analytics  = readStoreForWrite(ANALYTICS_FILE);

        // ── Phase 0: Normalise media filenames ───────────────────────────────
        // The site-root web.config's LowerCaseRule1 301-redirects any URL with an
        // uppercase letter, which broke every legacy image. Fold names to
        // lowercase and strip spaces/'&' so no rewrite rule fires on a media URL.
        [$nameMap, $nameSkipped] = normaliseMediaNames(['uploads', 'assets']);

        if ($nameMap) {
            foreach ($updates as &$u) {
                if (empty($u['images']) || !is_array($u['images'])) continue;
                foreach ($u['images'] as &$img) {
                    if (isset($nameMap[$img])) $img = $nameMap[$img];
                }
                unset($img);
            }
            unset($u);
            foreach ($projects as &$p) {
                if (!empty($p['logo']) && isset($nameMap[$p['logo']])) {
                    $p['logo'] = $nameMap[$p['logo']];
                }
            }
            unset($p);

            $cfgFile = DATA_DIR . DIRECTORY_SEPARATOR . 'site_config.json';
            $cfg     = safeReadJson($cfgFile);
            if (!empty($cfg['video_url']) && isset($nameMap[$cfg['video_url']])) {
                $cfg['video_url'] = $nameMap[$cfg['video_url']];
                atomicWriteJson($cfgFile, $cfg);
            }
        }

        // ── Phase 1: Convert all existing raster images to WebP ──────────────
        // Converts JPEG/PNG/AVIF → WebP at 82% quality with max 1600px resize.
        // Updates JSON references and deletes the original file on success.
        // Skips files already in .webp, .svg, or .gif format.
        $converted  = 0;
        $convErrors = 0;

        // max_execution_time is 30s. Image conversion is the only unbounded part
        // of this run, so stop accepting new work at 20s and finish cleanly —
        // the JSON still gets written and the pass is idempotent, so clicking
        // Optimise again simply continues where this one stopped.
        $deadline  = microtime(true) + 20.0;
        $ranOutOfTime = false;

        $convertToWebp = function (string $relPath) use (&$converted, &$convErrors, $deadline, &$ranOutOfTime): string {
            $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
            if (in_array($ext, ['webp', 'svg', 'gif'], true)) return $relPath;
            if (microtime(true) > $deadline) { $ranOutOfTime = true; return $relPath; }

            $cleanRel = ltrim(str_replace(['..', '\\'], '', $relPath), '/');
            $absPath  = realpath(__DIR__ . DIRECTORY_SEPARATOR . $cleanRel);
            if (!$absPath || !file_exists($absPath)) return $relPath;

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $absPath);
            finfo_close($finfo);

            $source = match ($mime) {
                'image/jpeg' => @imagecreatefromjpeg($absPath),
                'image/png'  => @imagecreatefrompng($absPath),
                'image/webp' => @imagecreatefromwebp($absPath),
                'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($absPath) : false,
                default      => false,
            };
            if (!$source) { $convErrors++; return $relPath; }

            $w = imagesx($source);
            $h = imagesy($source);

            // Guard against zero-dimension (corrupted) images
            if ($w <= 0 || $h <= 0) { imagedestroy($source); $convErrors++; return $relPath; }

            // EXIF auto-rotation for JPEG
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($absPath);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3: $source = imagerotate($source, 180, 0); break;
                        case 6: $source = imagerotate($source, -90, 0); [$w, $h] = [$h, $w]; break;
                        case 8: $source = imagerotate($source, 90, 0);  [$w, $h] = [$h, $w]; break;
                    }
                }
            }

            // Proportional resize to max 1600x1600
            $maxW = 1600; $maxH = 1600;
            $ratio = $w / $h;
            if ($maxW / $maxH > $ratio) { $maxW = (int) round($maxH * $ratio); }
            else                        { $maxH = (int) round($maxW / $ratio); }

            $canvas = imagecreatetruecolor($maxW, $maxH);
            if ($mime === 'image/jpeg') {
                $bg = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $bg);
            } else {
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
                imagefilledrectangle($canvas, 0, 0, $maxW, $maxH, $transparent);
            }
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $maxW, $maxH, $w, $h);

            $newAbsPath = preg_replace('/\.[^.]+$/', '.webp', $absPath);
            $newRelPath = preg_replace('/\.[^.]+$/', '.webp', $cleanRel);

            $ok = imagewebp($canvas, $newAbsPath, 82);
            imagedestroy($canvas);
            imagedestroy($source);

            if ($ok && file_exists($newAbsPath)) {
                if (realpath($newAbsPath) !== realpath($absPath)) {
                    @unlink($absPath);
                }
                $converted++;
                return $newRelPath;
            }
            $convErrors++;
            return $relPath;
        };

        // Convert update images
        foreach ($updates as &$u) {
            if (empty($u['images']) || !is_array($u['images'])) continue;
            foreach ($u['images'] as &$img) { $img = $convertToWebp($img); }
            unset($img);
        }
        unset($u);

        // Convert project logos
        foreach ($projects as &$p) {
            if (!empty($p['logo'])) { $p['logo'] = $convertToWebp($p['logo']); }
        }
        unset($p);

        // ── Phase 2: Purge orphaned upload files ──────────────────────────────
        $validFiles = [];
        foreach ($projects as $p) {
            if (!empty($p['logo'])) {
                $rp = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['..', '\\'], '', $p['logo']), '/'));
                if ($rp) $validFiles[] = $rp;
            }
        }
        foreach ($updates as $u) {
            foreach ((array) ($u['images'] ?? []) as $img) {
                $rp = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['..', '\\'], '', $img), '/'));
                if ($rp) $validFiles[] = $rp;
            }
        }

        $orphaned   = 0;
        $bytesFreed = 0;
        if (is_dir(UPLOAD_DIR)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(UPLOAD_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                if ($file->isFile()) {
                    if ($file->getFilename() === 'web.config') continue;
                    $fp = $file->getRealPath();
                    if (!in_array($fp, $validFiles, true)) {
                        $bytesFreed += $file->getSize();
                        @unlink($fp);
                        $orphaned++;
                    }
                } elseif ($file->isDir()) {
                    $contents = @scandir($file->getRealPath());
                    if (is_array($contents) && count($contents) <= 2) { @rmdir($file->getRealPath()); }
                }
            }
        }

        // ── Phase 2a: rebuild the social card so its date follows the data ───
        $ogCards = ogBuildImages();

        // ── Phase 2b: DPDP retention — drop analytics past its keep-period ───
        $purged = analyticsPurgeExpired();

        // ── Phase 3: Compact analytics log ───────────────────────────────────
        if (count($analytics) > ANALYTICS_CAP) {
            $analytics = array_slice($analytics, -ANALYTICS_CAP);
            atomicWriteJson(ANALYTICS_FILE, $analytics);
        }

        atomicWriteJson(PROJ_FILE, $projects);
        atomicWriteJson(COMP_FILE, $components);
        atomicWriteJson(UPD_FILE,  $updates);

        $mb  = round($bytesFreed / 1048576, 2);
        $msg = 'Optimised: ' . count($nameMap) . ' filenames normalised'
             . ($nameSkipped ? ' (' . count($nameSkipped) . ' skipped)' : '')
             . ", {$converted} images converted to WebP"
             . ($convErrors ? ", {$convErrors} skipped" : '')
             . ", {$orphaned} orphaned files purged ({$mb} MB freed)"
             . ($purged ? ", {$purged} analytics rows past retention removed" : '')
             . ($ogCards ? ", social card rebuilt." : '.')
             . ($ranOutOfTime ? ' TIME LIMIT REACHED — run Optimise again to continue converting.' : '');
        header('Location: admin.php?tab=analytics&msg=' . urlencode($msg));
        exit;
    }

    if ($action === 'housekeeping_delete') {
        $sel = $_POST['unused_files'] ?? [];
        if (!is_array($sel) || !$sel) {
            header('Location: admin.php?tab=analytics&msg=' . urlencode('Nothing was selected.'));
            exit;
        }
        [$hkDeleted, $hkFreed, $hkRefused] = housekeepingDelete(array_map('strval', $sel));
        $msg = $hkDeleted . ' unused file(s) deleted (' . round($hkFreed / 1024, 1) . ' KB freed).';
        if ($hkRefused) {
            $msg .= ' Refused: ' . implode('; ', array_slice($hkRefused, 0, 3));
        }
        header('Location: admin.php?tab=analytics&msg=' . urlencode($msg));
        exit;
    }

    if ($action === 'clear_analytics') {
        atomicWriteJson(ANALYTICS_FILE, []);
        header('Location: admin.php?tab=analytics&msg=' . urlencode('Analytics history cleared.'));
        exit;
    }

    if ($action === 'import_xlsx' && !empty($_POST['xlsx_data'])) {
        $raw = json_decode($_POST['xlsx_data'], true);
        if (is_array($raw)) {
            $validate = function(array $rows, array $allowedKeys): array {
                return array_map(fn($row) => array_intersect_key($row, array_flip($allowedKeys)), $rows);
            };

            if (isset($raw['projects']) && is_array($raw['projects'])) {
                atomicWriteJson(PROJ_FILE, $validate($raw['projects'], ['id', 'name', 'rera', 'logo']));
            }
            if (isset($raw['components']) && is_array($raw['components'])) {
                atomicWriteJson(COMP_FILE, $validate($raw['components'], ['id', 'project_id', 'type', 'job']));
            }
            if (isset($raw['updates']) && is_array($raw['updates'])) {
                $ups = $validate($raw['updates'], ['id', 'component_id', 'date', 'description', 'images', 'legacy_title']);
                // Imported cells never passed through save_update()'s filter — sanitise here
                // or a crafted .xlsx writes arbitrary markup straight into the store.
                foreach ($ups as &$_row) {
                    $_row['description']  = sanitizeRichText($_row['description'] ?? '');
                    $_row['legacy_title'] = trim(strip_tags((string)($_row['legacy_title'] ?? '')));
                }
                unset($_row);
                usort($ups, fn($a, $b) => parseUpdateDate($b['date'] ?? '') <=> parseUpdateDate($a['date'] ?? ''));
                atomicWriteJson(UPD_FILE, $ups);
            }
            header('Location: admin.php?tab=projects&msg=' . urlencode('Database successfully overwritten from Excel.'));
            exit;
        }
    }

    if ($action === 'save_project') {
        assertStoreUnchanged(PROJ_FILE, $_POST['_fp'] ?? null);
        $projects = readStoreForWrite(PROJ_FILE);
        $id       = !empty($_POST['project_id']) ? (string)$_POST['project_id'] : 'proj_' . bin2hex(random_bytes(6));
        $logoPath = (string)($_POST['existing_logo'] ?? '');

        if (isset($_FILES['project_logo']) && ($_FILES['project_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            [$uploadedLogo, ] = uploadEngine($_FILES['project_logo'], true);
            if (!empty($uploadedLogo)) $logoPath = $uploadedLogo;
        }

        $entry = [
            'id'   => $id,
            'name' => trim(strip_tags($_POST['project_name'] ?? '')),
            'rera' => trim(strip_tags($_POST['rera_number']  ?? '')),
            'logo' => $logoPath,
        ];

        $found = false;
        foreach ($projects as &$p) {
            if ((string)$p['id'] === $id) { $p = $entry; $found = true; break; }
        }
        unset($p);
        if (!$found) $projects[] = $entry;

        atomicWriteJson(PROJ_FILE, $projects);
        header('Location: admin.php?tab=projects&msg=' . urlencode('Project saved successfully.'));
        exit;
    }

    if ($action === 'save_component') {
        assertStoreUnchanged(COMP_FILE, $_POST['_fp'] ?? null);
        $components = readStoreForWrite(COMP_FILE);
        $id         = !empty($_POST['component_id']) ? (string)$_POST['component_id'] : 'comp_' . bin2hex(random_bytes(6));

        $entry = [
            'id'         => $id,
            'project_id' => (string)($_POST['project_id'] ?? ''),
            'type'       => trim(strip_tags($_POST['type'] ?? '')),
            'job'        => trim(strip_tags($_POST['job']  ?? '')),
        ];

        $found = false;
        foreach ($components as &$c) {
            if ((string)$c['id'] === $id) { $c = $entry; $found = true; break; }
        }
        unset($c);
        if (!$found) $components[] = $entry;

        atomicWriteJson(COMP_FILE, $components);
        header('Location: admin.php?tab=components&msg=' . urlencode('Directory Node saved successfully.'));
        exit;
    }

    if ($action === 'save_update') {
        assertStoreUnchanged(UPD_FILE, $_POST['_fp'] ?? null);
        $updates      = readStoreForWrite(UPD_FILE);
        $id           = !empty($_POST['update_id']) ? (string)$_POST['update_id'] : 'upd_' . bin2hex(random_bytes(6));
        $existingImgs = json_decode($_POST['existing_images'] ?? '[]', true);
        if (!is_array($existingImgs)) $existingImgs = [];

        // Delete marked images — validate path before unlinking
        if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $delImg) {
                $delImg = (string)$delImg;
                if (isPathInsideUploads($delImg)) {
                    $absPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['..','\\'], '', $delImg), '/'));
                    if ($absPath && file_exists($absPath)) @unlink($absPath);
                }
                $existingImgs = array_values(array_filter($existingImgs, fn($i) => $i !== $delImg));
            }
        }

        [$newImages, $uploadErrors] = uploadEngine($_FILES['images'] ?? [], false);
        $images = array_merge($existingImgs, $newImages);

        $dateRaw  = $_POST['date'] ?? date('Y-m-d');
        $dtParsed = DateTime::createFromFormat('Y-m-d', $dateRaw);
        $storeFmt = (string)cfg('locale.date_store', 'd-M-Y');
        $dateStr  = $dtParsed ? $dtParsed->format($storeFmt) : date($storeFmt);

        $entry = [
            'id'           => $id,
            'component_id' => (string)($_POST['component_id'] ?? ''),
            'date'         => $dateStr,
            'description'  => sanitizeRichText($_POST['description'] ?? ''),
            'images'       => $images,
        ];
        
        if ($entry['component_id'] === 'legacy') {
            $entry['legacy_title'] = trim(strip_tags($_POST['legacy_title'] ?? 'Legacy Update'));
        }

        $found = false;
        foreach ($updates as &$u) {
            if ((string)$u['id'] === $id) { $u = $entry; $found = true; break; }
        }
        unset($u);
        if (!$found) $updates[] = $entry;

        [$projMap, $compMap] = buildMaps(safeReadJson(PROJ_FILE), safeReadJson(COMP_FILE));
        sortUpdates($updates, $compMap, $projMap);
        atomicWriteJson(UPD_FILE, $updates);

        $msg = empty($uploadErrors) ? 'Update published.' : 'Published, but some files failed: ' . implode(', ', $uploadErrors);
        header('Location: admin.php?tab=updates&msg=' . urlencode($msg));
        exit;
    }

    if ($action === 'bulk_delete' && !empty($_POST['selected_updates']) && is_array($_POST['selected_updates'])) {
        $updates  = readStoreForWrite(UPD_FILE);
        $toDelete = array_map('strval', $_POST['selected_updates']);
        $updates  = array_values(array_filter($updates, fn($u) => !in_array((string)$u['id'], $toDelete, true)));
        
        [$projMap, $compMap] = buildMaps(safeReadJson(PROJ_FILE), safeReadJson(COMP_FILE));
        sortUpdates($updates, $compMap, $projMap);
        atomicWriteJson(UPD_FILE, $updates);
        
        header('Location: admin.php?tab=updates&msg=' . urlencode(count($toDelete) . ' records deleted.'));
        exit;
    }
}

// ─── 7. View Data Preparation ────────────────────────────────────────────────
$projects   = safeReadJson(PROJ_FILE);
$components = safeReadJson(COMP_FILE);
$updates    = safeReadJson(UPD_FILE);
$analytics  = safeReadJson(ANALYTICS_FILE);

[$projMap, $compMap] = buildMaps($projects, $components);
sortUpdates($updates, $compMap, $projMap);

// Edit context defaults
$defaultUpdateData = ['id' => '', 'component_id' => '', 'date' => date('Y-m-d'), 'description' => '', 'images' => [], 'legacy_title' => ''];

if (isset($_GET['edit_proj'])) {
    foreach ($projects as $p) {
        if ((string)$p['id'] === (string)$_GET['edit_proj']) { $editProjData = array_merge($editProjData, $p); break; }
    }
}
if (isset($_GET['edit_comp'])) {
    foreach ($components as $c) {
        if ((string)$c['id'] === (string)$_GET['edit_comp']) { $editCompData = array_merge($editCompData, $c); break; }
    }
}
if (isset($_GET['edit'])) {
    foreach ($updates as $u) {
        if ((string)$u['id'] === (string)$_GET['edit']) { $editUpdateData = array_merge($defaultUpdateData, $u); break; }
    }
    if ($editUpdateData['component_id'] !== 'legacy') {
        foreach ($components as $c) {
            if ((string)$c['id'] === (string)$editUpdateData['component_id']) { $editProjectId = $c['project_id']; break; }
        }
    }
}

// Ensure proper date format for HTML inputs
$dtForInput   = DateTime::createFromFormat('d-M-Y', $editUpdateData['date']) ?: DateTime::createFromFormat('Y-m-d', $editUpdateData['date']);
$dateForInput = $dtForInput ? $dtForInput->format('Y-m-d') : date('Y-m-d');

// ─── 8. Analytics Aggregation ────────────────────────────────────────────────
$a_totalVisits = 0;
$a_ips         = [];
$a_totalSecs   = 0;
$a_timeLogs    = 0;
$a_interactions= 0;
$projHits  = [];
$towerHits = [];
$referrers = [];
$devices   = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0, 'Unknown' => 0];

foreach ($analytics as $log) {
    $ipKey = $log['ip_hash'] ?? null;
    if (!empty($ipKey)) $a_ips[] = $ipKey;

    $act = $log['action'] ?? '';
    if ($act === 'page_load')  $a_totalVisits++;
    if ($act === 'time_spent') {
        $a_totalSecs += (int)($log['details']['seconds'] ?? 0);
        $a_timeLogs++;
    }
    if (in_array($act, ['button_click', 'directory_filter', 'image_viewed', 'video_play'], true)) {
        $a_interactions++;
    }

    if ($act === 'directory_filter') {
        $title = trim((string)($log['details']['title'] ?? ''));
        if ($title && $title !== 'Project Master Overview') {
            if (str_contains($title, ' — ')) {
                [$proj, $tower] = explode(' — ', $title, 2);
                $projHits[trim($proj)]   = ($projHits[trim($proj)]   ?? 0) + 1;
                $towerHits[trim($tower)] = ($towerHits[trim($tower)] ?? 0) + 1;
            } else {
                $projHits[$title] = ($projHits[$title] ?? 0) + 1;
            }
        }
    }

    $ref = (string)($log['details']['referrer'] ?? '');
    if ($ref) {
        $domain = parse_url($ref, PHP_URL_HOST) ?: $ref;
        $referrers[$domain] = ($referrers[$domain] ?? 0) + 1;
    }

    $ua = strtolower((string)($log['ua_short'] ?? ''));
    if ($ua) {
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            $devices['Tablet']++;
        } elseif (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            $devices['Mobile']++;
        } else {
            $devices['Desktop']++;
        }
    } else {
        $devices['Unknown']++;
    }
}

$a_uniqueVisitors   = count(array_unique($a_ips));
$avgRaw             = $a_timeLogs > 0 ? (int)round($a_totalSecs / $a_timeLogs) : 0;
$a_avgTimeFormatted = sprintf('%dm %ds', intdiv($avgRaw, 60), $avgRaw % 60);

arsort($projHits);  $a_topProject  = (string)(array_key_first($projHits)  ?? '—');
arsort($towerHits); $a_topTower    = (string)(array_key_first($towerHits) ?? '—');
arsort($referrers); $a_topReferrer = (string)(array_key_first($referrers) ?? 'Direct');
$a_deviceBreakdown = $devices;

$housekeeping = ($activeTab === 'analytics') ? housekeepingScan() : ['code' => [], 'media' => []];

$siteConfig  = safeReadJson(DATA_DIR . '/site_config.json');
$videoUrl    = $siteConfig['video_url']   ?? '';
$videoTitle  = $siteConfig['video_title'] ?? 'Site Aerial Overview';

// ─── 9. Render Groupings ─────────────────────────────────────────────────────
$feedGroups    = [];
$legacyUpdates = [];
foreach ($updates as $u) {
    if ($u['component_id'] === 'legacy') { $legacyUpdates[] = $u; continue; }
    $c = $compMap[$u['component_id']] ?? null;
    if ($c) {
        $pName = $projMap[$c['project_id']]['name'] ?? 'Unknown Project';
        $feedGroups[$pName][$c['type']][] = $u;
    }
}