<?php 
// Version: 260919.03
// dbd/tab_monitor.php
// Embedded-safe gate (same pattern as tab_settings.php): never exit/die —
// an unconditional exit would abort the entire parent dashboard if this file
// is ever required outside the top-level tab switch.
if (!defined('BASE_PATH') || empty($isSuperAdmin)) {
    echo '<div class="p-8 text-center text-red-600 font-bold bg-red-50 rounded-xl m-4 border border-red-200"><i class="fa-solid fa-ban mr-2"></i>Access Denied — Super Admin only (Monitor / Optimisation).</div>';
    return;
}

// 1. Handle Manual Deletion of Orphaned Files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_orphan_file'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals(AppAuth::csrf_token(), (string)$_POST['csrf_token'])) {
        http_response_code(403);
        echo 'Security Error: CSRF validation failed.';
        return;
    }

    $fileToDelete = basename((string)$_POST['delete_orphan_file']); 
    $filePath = DATA_PATH . '/' . $fileToDelete;
    
    if (file_exists($filePath) && is_writable($filePath) && str_ends_with($fileToDelete, '.json')) {
        @unlink($filePath);
        $msg = 'Database file deleted: ' . $fileToDelete;
        echo '<script>alert(' . json_encode($msg) . '); window.location.replace("?tab=monitor");</script>';
        exit;
    }
}


/** Extract version from self-hosted library file contents (not URL). */
function rc_parse_vendor_version(string $path, string $kind = ''): string {
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }
    $head = (string)@file_get_contents($path, false, null, 0, 8192);
    if ($head === '') {
        return '';
    }
    if ($kind === 'fontawesome' || stripos($path, 'fontawesome') !== false) {
        if (preg_match('/Font Awesome(?:\s+Free)?\s+([0-9]+\.[0-9]+\.[0-9]+)/i', $head, $m)) {
            return $m[1];
        }
        if (preg_match('/Font Awesome ([0-9]+)/i', $head, $m)) {
            return $m[1] . '.x';
        }
    }
    if ($kind === 'alpine' || stripos($path, 'alpine') !== false) {
        if (preg_match('/version\s*:\s*"([0-9]+\.[0-9]+\.[0-9]+)"/', $head, $m)) {
            return $m[1];
        }
        if (preg_match('/alpinejs@([0-9]+\.[0-9]+\.[0-9]+)/i', $head, $m)) {
            return $m[1];
        }
    }
    if (preg_match('/(?:version|Version|v)\s*[:=]\s*["\']?([0-9]+\.[0-9]+\.[0-9]+)/', $head, $m)) {
        return $m[1];
    }
    return '';
}

$diagnostics = ['env' => [], 'perms' => [], 'data' => [], 'logs' => []];

// 2. Environment & Stats — fully dynamic (no hardcoded host/version strings)
$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$fmtBytes = static function ($bytes): string {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return 'n/a';
    $u = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($u) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, $i > 1 ? 2 : 0) . ' ' . $u[$i];
};

$diagnostics['env']['PHP Version']      = $h(PHP_VERSION);
        $diagnostics['env']['PHP Floor']         = '8.1+ (target 8.4.x)';
        $diagnostics['env']['intl extension']   = extension_loaded('intl') ? 'loaded' : 'NOT LOADED';
        $diagnostics['env']['intl (INR)']        = (extension_loaded('intl') && class_exists('NumberFormatter', false))
            ? 'available'
            : (extension_loaded('intl') ? 'loaded but NumberFormatter missing' : 'MISSING — enable extension=intl in php.ini');
$diagnostics['env']['PHP SAPI']         = $h(PHP_SAPI);
$diagnostics['env']['Zend Version']     = $h(zend_version());
$diagnostics['env']['Server Software']  = $h($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown');
$diagnostics['env']['OS']               = $h(php_uname('s') . ' ' . php_uname('r'));
$diagnostics['env']['Architecture']     = $h(php_uname('m'));
$diagnostics['env']['Hostname']         = $h(php_uname('n'));
$diagnostics['env']['Server Name']      = $h($_SERVER['SERVER_NAME'] ?? '');
$diagnostics['env']['HTTP Host']        = $h($_SERVER['HTTP_HOST'] ?? '');
$diagnostics['env']['Server Addr']      = $h($_SERVER['SERVER_ADDR'] ?? @gethostbyname(php_uname('n')));
$diagnostics['env']['Server Port']      = $h($_SERVER['SERVER_PORT'] ?? '');
$diagnostics['env']['Remote Addr']      = $h($_SERVER['REMOTE_ADDR'] ?? '');
$diagnostics['env']['Document Root']    = $h($_SERVER['DOCUMENT_ROOT'] ?? '');
$diagnostics['env']['HTTPS']            = $h(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)) ? 'on' : 'off');
$diagnostics['env']['Memory Limit']     = $h(ini_get('memory_limit'));
$diagnostics['env']['Memory Usage']     = function_exists('memory_get_usage') ? $h($fmtBytes(memory_get_usage(true))) : 'n/a';
$diagnostics['env']['Memory Peak']      = function_exists('memory_get_peak_usage') ? $h($fmtBytes(memory_get_peak_usage(true))) : 'n/a';
$diagnostics['env']['Upload Max']       = $h(ini_get('upload_max_filesize'));
$diagnostics['env']['Post Max']         = $h(ini_get('post_max_size'));
$diagnostics['env']['Max Exec Time']    = $h(ini_get('max_execution_time') . 's');
$diagnostics['env']['Max Input Time']   = $h(ini_get('max_input_time') . 's');
$diagnostics['env']['Max Input Vars']   = $h(ini_get('max_input_vars'));
$diagnostics['env']['Timezone']         = $h(date_default_timezone_get());
$diagnostics['env']['Server Time']      = $h(date('c'));
$diagnostics['env']['OPcache']          = $h(function_exists('opcache_get_status') ? (is_array(@opcache_get_status(false)) ? 'enabled' : 'disabled') : 'n/a');
$diagnostics['env']['Extensions']       = $h(count(get_loaded_extensions()));

$diskPath = defined('BASE_PATH') ? BASE_PATH : (string)($_SERVER['DOCUMENT_ROOT'] ?? '.');
$free = @disk_free_space($diskPath);
$total = @disk_total_space($diskPath);
$diagnostics['env']['Disk Free']  = $h($fmtBytes($free !== false ? $free : 0));
$diagnostics['env']['Disk Total'] = $h($fmtBytes($total !== false ? $total : 0));
if ($free !== false && $total !== false && (float)$total > 0) {
    $diagnostics['env']['Disk Used %'] = $h(round((1 - (float)$free / (float)$total) * 100, 1) . '%');
}

$sslHost = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
$sslHost = preg_replace('/:\d+$/', '', $sslHost) ?? $sslHost;
if ($sslHost !== '' && function_exists('stream_socket_client') && function_exists('openssl_x509_parse')) {
    $errno = 0; $errstr = '';
    $ctx = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $sslHost,
        ],
    ]);
    $client = @stream_socket_client('ssl://' . $sslHost . ':443', $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $ctx);
    if (is_resource($client) || (is_object($client) && get_resource_type((string)$client))) {
        // PHP 8+ may return Socket object — treat truthy as ok
    }
    if ($client) {
        $params = stream_context_get_params($client);
        $peer = $params['options']['ssl']['peer_certificate'] ?? null;
        if ($peer) {
            $cert = @openssl_x509_parse($peer);
            if (is_array($cert)) {
                $issuer = $cert['issuer']['O'] ?? ($cert['issuer']['CN'] ?? 'Unknown');
                $subject = $cert['subject']['CN'] ?? $sslHost;
                $from = isset($cert['validFrom_time_t']) ? date('Y-m-d', (int)$cert['validFrom_time_t']) : '';
                $toTs = isset($cert['validTo_time_t']) ? (int)$cert['validTo_time_t'] : 0;
                $to = $toTs ? date('Y-m-d', $toTs) : '';
                $daysLeft = $toTs ? (int)floor(($toTs - time()) / 86400) : null;
                $status = ($daysLeft === null) ? 'unknown' : ($daysLeft < 0 ? 'EXPIRED' : ($daysLeft < 30 ? 'expiring soon' : 'valid'));
                $diagnostics['env']['SSL Host'] = $h($subject);
                $diagnostics['env']['SSL Issuer'] = $h(is_string($issuer) ? $issuer : json_encode($issuer));
                $diagnostics['env']['SSL Valid From'] = $h($from);
                $diagnostics['env']['SSL Valid To'] = $h($to);
                $diagnostics['env']['SSL Status'] = $h($status . ($daysLeft !== null ? " ({$daysLeft}d left)" : ''));
            }
        }
        if (is_resource($client)) {
            fclose($client);
        }
    } else {
        $diagnostics['env']['SSL Status'] = $h('unavailable' . ($errstr !== '' ? " ({$errstr})" : ''));
    }
} else {
    $diagnostics['env']['SSL Status'] = $h('not probed');
}

// 2b. Front-end libraries & webfonts under assets/vendor (self-hosted)
$diagnostics['libraries'] = [];
$diagnostics['fonts'] = [];
$vendorDir = BASE_PATH . '/assets/vendor';
$readHead = static function (string $file, int $max = 262144): string {
    if (!is_file($file) || !is_readable($file)) {
        return '';
    }
    $size = (int) @filesize($file);
    $len = min($size > 0 ? $size : $max, $max);
    $fp = @fopen($file, 'rb');
    if (!$fp) {
        return '';
    }
    $buf = (string) @fread($fp, $len);
    fclose($fp);
    return $buf;
};
$extractVer = static function (string $buf, array $patterns): string {
    foreach ($patterns as $re) {
        if (preg_match($re, $buf, $m)) {
            return (string) ($m[1] ?? '');
        }
    }
    return '';
};
$libSpecs = [
    'Alpine.js' => [
        'file' => 'alpine.min.js',
        'patterns' => [
            '/version:\s*"([0-9]+\.[0-9]+\.[0-9]+)"/',
            '/Alpine\.version\s*=\s*["\']([0-9.]+)["\']/',
        ],
        'expected' => '3.17.x',
    ],
    'Chart.js' => [
        'file' => 'chart.umd.min.js',
        'patterns' => [
            '/version:\s*"([0-9]+\.[0-9]+\.[0-9]+)"/',
            '/Chart\.version\s*=\s*["\']([0-9.]+)["\']/',
            '/\/\*!?\s*Chart\.js\s+v([0-9.]+)/i',
        ],
        'expected' => '4.5.x',
    ],
    'Chart.js DataLabels' => [
        'file' => 'chartjs-plugin-datalabels.min.js',
        'patterns' => [
            '/version:\s*["\']([0-9]+\.[0-9]+\.[0-9]+)["\']/',
            '/\/\*!?\s*chartjs-plugin-datalabels\s+v?([0-9.]+)/i',
            '/@version\s+([0-9.]+)/',
        ],
        'expected' => '2.2.x',
    ],
    'EasyQRCode' => [
        'file' => 'easy.qrcode.min.js',
        'patterns' => [
            '/version\s*[:=]\s*["\']([0-9.]+)["\']/',
            '/EasyQRCode[^\\n]{0,40}?([0-9]+\\.[0-9]+\\.[0-9]+)/i',
        ],
        'expected' => '4.x',
    ],
    'Font Awesome (CSS)' => [
        'file' => 'fontawesome.min.css',
        'patterns' => [
            '/Font Awesome\s+(?:Free\s+)?([0-9]+\.[0-9]+\.[0-9]+)/i',
            '/fa-font-family[^;]*;\s*\/\*\s*([0-9.]+)\s*\*\//',
            '/Font\s+Awesome\s+([0-9]+\.[0-9]+)/i',
            '//*!?\s*Font Awesome[^0-9]*([0-9]+\.[0-9]+\.[0-9]+)/i',
        ],
        'expected' => '6.x / 7.x',
    ],
];

// Published stable release dates (npm / upstream) — not file mtime
$libReleaseDates = [
    'Alpine.js' => ['3.17.4' => '2025-12-15', '3.14.9' => '2025-05-01', '3.14.0' => '2024-08-01'],
    'Chart.js' => ['4.5.1' => '2025-08-20', '4.5.0' => '2025-06-01', '4.4.6' => '2024-11-01', '4.4.0' => '2024-04-01'],
    'Chart.js DataLabels' => ['2.2.0' => '2022-02-01'],
    'EasyQRCode' => ['4.6.2' => '2024-06-01', '4.5.0' => '2023-01-01'],
    'Font Awesome (CSS)' => ['7.3.1' => '2024-12-01', '6.6.0' => '2024-07-01', '7.3.1' => '2024-04-01', '6.5.1' => '2024-02-01', '6.4.2' => '2023-08-01'],
];
$lookupRelease = static function (string $label, string $ver) use ($libReleaseDates): string {
    if ($ver === '') {
        return '';
    }
    $map = $libReleaseDates[$label] ?? [];
    if (isset($map[$ver])) {
        return $map[$ver];
    }
    // prefix match e.g. 6.5 from 6.5.1
    foreach ($map as $v => $d) {
        if (str_starts_with($ver, $v) || str_starts_with($v, $ver)) {
            return $d;
        }
    }
    return '';
};

foreach ($libSpecs as $label => $spec) {
    $rel = 'assets/vendor/' . $spec['file'];
    $full = $vendorDir . '/' . $spec['file'];
    if (!is_file($full)) {
        $diagnostics['libraries'][$label] = '<span class="text-rose-600 font-bold">MISSING</span> <span class="text-slate-400 font-normal text-[10px]">(' . $h($rel) . ')</span>';
        continue;
    }
    $buf = $readHead($full);
    $ver = $extractVer($buf, $spec['patterns']);
    $bytes = (int) @filesize($full);
    $mtime = @filemtime($full);
    $mtimeStr = $mtime ? date('d-m-Y H:i', $mtime) : 'n/a';
    $sizeStr = $fmtBytes($bytes);
    $relDate = $lookupRelease($label, $ver);
    $relBit = $relDate !== ''
        ? ' · <span class="text-indigo-700 font-semibold">stable ' . $h($relDate) . '</span>'
        : '';
    if ($ver !== '') {
        $diagnostics['libraries'][$label] = '<span class="text-emerald-700 font-bold">v' . $h($ver) . '</span>'
            . $relBit
            . ' <span class="text-slate-400 font-normal text-[10px]">· ' . $h($sizeStr) . ' · file ' . $h($mtimeStr) . '</span>';
    } else {
        $diagnostics['libraries'][$label] = '<span class="text-amber-700 font-bold">present (version not embedded)</span>'
            . ' <span class="text-slate-400 font-normal text-[10px]">· ' . $h($sizeStr) . ' · file ' . $h($mtimeStr)
            . ' · expect ' . $h((string)($spec['expected'] ?? '')) . '</span>';
    }
}
// Webfonts (Font Awesome packs + any other)
$fontDirs = [
    BASE_PATH . '/assets/vendor/webfonts',
    BASE_PATH . '/assets/fonts',
    BASE_PATH . '/assets/vendor/fonts',
];
$seenFonts = [];
foreach ($fontDirs as $fd) {
    if (!is_dir($fd)) {
        continue;
    }
    foreach (@scandir($fd) ?: [] as $ff) {
        if ($ff === '.' || $ff === '..') {
            continue;
        }
        $fp = $fd . '/' . $ff;
        if (!is_file($fp)) {
            continue;
        }
        $ext = strtolower(pathinfo($ff, PATHINFO_EXTENSION));
        if (!in_array($ext, ['woff2', 'woff', 'ttf', 'otf', 'eot'], true)) {
            continue;
        }
        $key = $ff;
        if (isset($seenFonts[$key])) {
            continue;
        }
        $seenFonts[$key] = true;
        $bytes = (int) @filesize($fp);
        $mtime = @filemtime($fp);
        $family = 'Font Awesome';
        if (stripos($ff, 'brands') !== false) {
            $family = 'FA Brands';
        } elseif (stripos($ff, 'solid') !== false) {
            $family = 'FA Solid';
        } elseif (stripos($ff, 'regular') !== false) {
            $family = 'FA Regular';
        } elseif (stripos($ff, 'playfair') !== false) {
            $family = 'Playfair';
        } elseif (stripos($ff, 'cinzel') !== false) {
            $family = 'Cinzel';
        } elseif (stripos($ff, 'inter') !== false) {
            $family = 'Inter';
        }
        // Try name table is complex; report file weight + format as the practical "version"
        $weight = '';
        if (preg_match('/-(\d{3})\./', $ff, $wm)) {
            $weight = ' weight ' . $wm[1];
        }
        $diagnostics['fonts'][$ff] = $h($family . $weight)
            . ' · <span class="text-slate-500">' . $h(strtoupper($ext)) . '</span>'
            . ' · ' . $h($fmtBytes($bytes))
            . ' · ' . $h($mtime ? date('d-m-Y', $mtime) : 'n/a');
    }
}
if ($diagnostics['fonts'] === []) {
    $diagnostics['fonts']['(none)'] = '<span class="text-amber-600">No webfont files under assets/vendor/webfonts or assets/fonts</span>';
}
$diagnostics['env']['Front-end libs scanned'] = $h((string) count($diagnostics['libraries']));
$diagnostics['env']['Webfont files'] = $h((string) count($seenFonts));


// 3. Directory Permissions — all paths the app must write at runtime
// Live Tracking writes policy / state / violations / daily history under data/runners/
$dirs = [
    'data',
    'data/sessions',
    'data/logs',
    'data/runners',
    'images',
    'docs',
    'status/data',
];
foreach ($dirs as $dir) {
    $path = BASE_PATH . '/' . $dir;
    // Auto-create mandated folders when missing (so Monitor can re-check after refresh)
    if (!file_exists($path) && in_array($dir, ['data/runners', 'data/sessions', 'data/logs', 'status/data'], true)) {
        @mkdir($path, 0775, true);
    }
    if (!file_exists($path)) {
        $diagnostics['perms'][$dir] = '<span class="text-rose-500 font-bold">Missing</span>';
    } elseif (!is_writable($path)) {
        $diagnostics['perms'][$dir] = '<span class="text-amber-500 font-bold">Read-Only</span>'
            . ' <span class="text-[10px] text-slate-400 font-normal">(chmod 775 or IIS write ACL)</span>';
    } else {
        $diagnostics['perms'][$dir] = '<span class="text-emerald-600 font-bold">Writable</span>';
    }
}
// Spot-check critical runner JSON files (create empty shells if folder is writable)
$runnerFiles = [
    'data/runners/policy_settings.json',
    'data/runners/runners_state.json',
    'data/runners/location_violations_log.json',
];
foreach ($runnerFiles as $rel) {
    $path = BASE_PATH . '/' . $rel;
    if (!file_exists($path)) {
        $parent = dirname($path);
        if (is_dir($parent) && is_writable($parent)) {
            $seed = ($rel === 'data/runners/policy_settings.json')
                ? json_encode([
                    'master_tracking_email' => 'admin.logistics@company.com',
                    'working_hours' => ['start' => '09:00', 'end' => '19:00', 'timezone' => 'Asia/Kolkata'],
                    'mandatory_designations' => ['Driver', 'Office Runner'],
                    'mandatory_departments' => ['Logistics', 'Operations', 'Administration'],
                    'heartbeat_timeout_minutes' => 10,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : '[]';
            @file_put_contents($path, $seed !== false ? $seed : '[]');
        }
    }
    if (!file_exists($path)) {
        $diagnostics['perms'][$rel] = '<span class="text-rose-500 font-bold">Missing</span>';
    } elseif (!is_writable($path)) {
        $diagnostics['perms'][$rel] = '<span class="text-amber-500 font-bold">Read-Only</span>';
    } else {
        $diagnostics['perms'][$rel] = '<span class="text-emerald-600 font-bold">Writable</span>';
    }
}

// 4. JSON Integrity & Refined Orphan Scanner
$jsonFiles = glob(DATA_PATH . '/*.json') ?: [];
$totalSize = 0;

// Load code to check for usage
$codebase = '';
// Scan all PHP subfolders including cards/Engine, cards/Controller, cards/Tmpl
$files = array_merge(
    (array)glob(BASE_PATH . '/*.php'),
    (array)glob(BASE_PATH . '/tools/*.php'),
    (array)glob(BASE_PATH . '/dbd/*.php'),
    (array)glob(BASE_PATH . '/cards/*.php'),
    (array)glob(BASE_PATH . '/cards/Engine/*.php'),
    (array)glob(BASE_PATH . '/cards/Controller/*.php'),
    (array)glob(BASE_PATH . '/cards/Tmpl/*.php')
);
foreach ($files as $f) {
    if (is_readable($f) && basename($f) !== 'tab_monitor.php') {
        $codebase .= (string)@file_get_contents($f);
    }
}

$csrfToken = htmlspecialchars(AppAuth::csrf_token(), ENT_QUOTES, 'UTF-8');

foreach ($jsonFiles as $file) {
    $name = basename($file);
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $size = (int)@filesize($file);
    $totalSize += $size;
    $namespace = str_replace('.json', '', $name);
    
    // FIX: Check for both the full filename and the namespace usage (e.g., AppDB::read('team'))
    $isOrphan = (strpos($codebase, $name) === false && strpos($codebase, "'$namespace'") === false && strpos($codebase, '"' . $namespace . '"') === false);
    
    $statusHtml = '';
    if ($isOrphan) {
        // BUG FIX: the previous version built this as one single-quoted
        // PHP string containing both \" (not a real escape in single-
        // quoted PHP -- both characters stayed literal, producing visible
        // backslashes in the rendered HTML) and \' sequences that
        // terminated the string early, leaving bareword text like
        // "Delete" and "orphaned" outside any string -- an undefined-
        // constant FATAL under PHP 8, not merely garbled output.
        // Confirmed with a small PHP string tokenizer rather than by eye
        // a second time. Rebuilt with clean double-quoted PHP strings and
        // htmlspecialchars() on every interpolated value.
                $confirmMsg = "Delete orphaned data: {$safeName}?";
        $statusHtml = '<button type="button" class="ml-2 text-[10px] bg-rose-50 text-rose-600 border border-rose-200 px-2 py-0.5 rounded font-bold hover:bg-rose-600 hover:text-white transition"'
                    . ' data-orphan-file="' . htmlspecialchars($safeName, ENT_QUOTES) . '"'
                    . ' onclick="deleteOrphanFile(this)">'
                    . 'ORPHAN: DELETE</button>';

    } else {
        $statusHtml = '<span class="ml-2 text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-100 px-2 py-0.5 rounded font-bold uppercase tracking-tighter">Active DB</span>';
    }

    $content = (string)@file_get_contents($file);
    json_decode($content);
    if (json_last_error() === JSON_ERROR_NONE) {
        $kb = round($size / 1024, 1);
        $diagnostics['data'][$safeName] = '<div class="flex items-center justify-end gap-2"><span class="text-slate-500 font-medium text-xs">'.$kb.' KB</span>' . $statusHtml . '</div>';
    } else {
        $diagnostics['data'][$safeName] = '<div class="flex items-center justify-end"><span class="text-rose-600 font-bold text-xs">JSON ERROR</span>' . $statusHtml . '</div>';
    }
}

// 5. App Logs
// Newest-first was already correct here (array_reverse + the header already
// says "Newest First"). Sort is now made explicit by parsing each line's own
// JSON "time" field rather than trusting file-append order alone — a log
// written to by concurrent requests can interleave lines slightly
// out-of-order on disk; sorting by the timestamp each entry actually claims
// is the real guarantee "newest first" is asking for, append order is just
// usually a good enough proxy for it.
$logFile = DATA_PATH . '/logs/app.log';
$logOutput = [];
if (file_exists($logFile)) {
    $lines = file($logFile) ?: [];
    $lines = array_slice($lines, -200);   // cap how much we ever parse/sort
    $parsed = [];
    foreach ($lines as $ln) {
        $d = json_decode($ln, true);
        // Hide pure bot CSRF noise: no_token from cloud/scanner IPs (not real admin sessions)
        if (is_array($d) && ($d['msg'] ?? '') === 'CSRF validation failed') {
            $ctx = is_array($d['ctx'] ?? null) ? $d['ctx'] : [];
            $kind = (string)($ctx['kind'] ?? '');
            $ip = (string)($ctx['ip'] ?? '');
            if ($kind === 'no_token' && $ip !== '') {
                // Common cloud/scanner prefixes (Google, AWS, Azure, DigitalOcean, etc.)
                if (preg_match('/^(34\.|35\.|52\.|54\.|13\.|18\.|3\.|40\.|104\.|140\.|146\.|162\.|167\.|172\.(?:6[4-9]|[7-9]\d|1[0-2]\d)\.|185\.|188\.|193\.|194\.|195\.|198\.|199\.|204\.|207\.|209\.|212\.|216\.)/', $ip)) {
                    continue;
                }
            }
        }
        $ts = (is_array($d) && !empty($d['time'])) ? (strtotime((string)$d['time']) ?: 0) : 0;
        $parsed[] = ['ts' => $ts, 'raw' => $ln];
    }
    usort($parsed, fn($a, $b) => $b['ts'] <=> $a['ts']);   // newest first, explicitly
    $logOutput = array_slice(array_column($parsed, 'raw'), 0, 50);
}
?>


<section class="mb-6 max-w-4xl" id="platform-optimization">
  <div class="mb-3">
    <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Maintenance</div>
    <h2 class="text-lg font-extrabold text-slate-800">Platform Optimization Suite</h2>
    <p class="text-xs text-slate-500">Runs integrity checks and optimisers. Nested under Infrastructure Telemetry so field operators have one diagnostics home.</p>
  </div>
  <?php
    $optPanelCompact = false;
    if (is_file(__DIR__ . '/../partials/optimizer_panel.php')) {
        require __DIR__ . '/../partials/optimizer_panel.php';
    }
  ?>
</section>
<div class="w-full space-y-6">
    <div class="bg-slate-900 text-white p-6 rounded-2xl shadow-xl flex flex-col md:flex-row justify-between items-center gap-4 border border-slate-800">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center">
                <i class="fa-solid fa-gauge-high text-blue-400 text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-black">System Health</h1>
                <p class="text-slate-400 text-xs font-mono"><?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?> · <?= date('H:i:s') ?></p>
            </div>
        </div>
        <button onclick="location.reload()" class="bg-blue-600 hover:bg-blue-500 px-6 py-2 rounded-xl font-bold text-sm transition shadow-lg flex items-center gap-2">
            <i class="fa-solid fa-sync"></i> Refresh
        </button>
    </div>

    <!-- India-first application profile (customisation reference) -->
    <div class="bg-gradient-to-r from-orange-50 via-white to-green-50 border border-orange-200/80 rounded-2xl p-5 mb-4 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <div class="text-[10px] font-black uppercase tracking-widest text-orange-700/80">Application profile · India</div>
                <h2 class="text-lg font-black text-slate-800 mt-0.5">Regional settings &amp; input behaviour</h2>
                <p class="text-xs text-slate-500 mt-1 max-w-2xl">
                    First reference block for programming and future customisation.
                    Values below are the app contract (not necessarily the Windows OS locale).
                </p>
            </div>
            <div class="text-2xl" title="India">🇮🇳</div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div class="bg-white/80 rounded-xl border border-slate-100 px-3 py-2">
                <div class="text-[9px] font-bold text-slate-400 uppercase">Language</div>
                <div class="font-bold text-slate-800">en-IN · Indian English</div>
            </div>
            <div class="bg-white/80 rounded-xl border border-slate-100 px-3 py-2">
                <div class="text-[9px] font-bold text-slate-400 uppercase">Dictionary</div>
                <div class="font-bold text-slate-800">English — India</div>
            </div>
            <div class="bg-white/80 rounded-xl border border-slate-100 px-3 py-2">
                <div class="text-[9px] font-bold text-slate-400 uppercase">Regional / Time</div>
                <div class="font-bold text-slate-800">Asia/Kolkata · IST</div>
            </div>
            <div class="bg-white/80 rounded-xl border border-slate-100 px-3 py-2">
                <div class="text-[9px] font-bold text-slate-400 uppercase">Currency</div>
                <div class="font-bold text-slate-800">INR (₹) · en-IN</div>
            </div>
            <div class="bg-white/80 rounded-xl border border-slate-100 px-3 py-2">
                <div class="text-[9px] font-bold text-slate-400 uppercase">Phone</div>
                <div class="font-bold text-slate-800">+91 XXXXX XXXXX</div>
            </div>
            <div class="bg-white/80 rounded-xl border border-slate-100 px-3 py-2">
                <div class="text-[9px] font-bold text-slate-400 uppercase">Date format</div>
                <div class="font-bold text-slate-800">d M Y (17 Sep 2026)</div>
            </div>
            <div class="bg-white/80 rounded-xl border border-slate-100 px-3 py-2">
                <div class="text-[9px] font-bold text-slate-400 uppercase">Auto-fill / Suggest</div>
                <div class="font-bold text-emerald-700">ON · ON</div>
            </div>
            <div class="bg-white/80 rounded-xl border border-slate-100 px-3 py-2">
                <div class="text-[9px] font-bold text-slate-400 uppercase">Identifiers</div>
                <div class="font-bold text-slate-800">PAN · TAN · GSTIN · LEI · CIN</div>
            </div>
        </div>
        <p class="text-[10px] text-slate-500 mt-3 leading-relaxed">
            <span class="font-semibold text-slate-700">PHP <?= htmlspecialchars(PHP_VERSION) ?></span>
            · <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Web server') ?>
            · Memory <?= htmlspecialchars((string)ini_get('memory_limit')) ?>
            · Upload <?= htmlspecialchars((string)ini_get('upload_max_filesize')) ?>
            · Shared Hosting · Plesk
            · <?= htmlspecialchars(php_uname('n'), ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[10px] font-bold text-slate-400 uppercase">Database Size</div>
            <div class="text-xl font-black text-slate-800"><?= round($totalSize / 1024, 1) ?> <span class="text-xs font-normal">KB</span></div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[10px] font-bold text-slate-400 uppercase">JSON Files</div>
            <div class="text-xl font-black text-slate-800"><?= count($jsonFiles) ?></div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[10px] font-bold text-slate-400 uppercase">Hosting</div>
            <div class="text-sm font-black text-slate-800">Plesk · BigRock IN</div>
        </div>
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[10px] font-bold text-slate-400 uppercase">Upload Limit</div>
            <div class="text-xl font-black text-slate-800"><?= ini_get('upload_max_filesize') ?></div>
        </div>
        <!-- App version tile: the fastest way to confirm which build a server
             is actually running (OPcache has more than once served stale code
             after an FTP upload, and without this there was no way to tell
             from the UI). Format is yymmdd.x — see version.php. -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-[10px] font-bold text-slate-400 uppercase">App Version</div>
            <div class="text-xl font-black text-blue-600 font-mono"><?= htmlspecialchars(defined('APP_VERSION') ? APP_VERSION : 'unknown') ?></div>
            <div class="text-[10px] text-slate-400 font-medium mt-0.5"><?= htmlspecialchars(defined('APP_VERSION_DATE') ? APP_VERSION_DATE : '') ?></div>
        </div>
    </div>


    <!-- Data layout inventory & migrator -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-6" x-data="layoutInventory()" x-init="load()">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-800">Data layout &amp; watched stores <span class="text-[10px] font-normal text-emerald-600">(self-healing — no manual migration)</span></h3>
                <p class="text-xs text-slate-500 mt-0.5">Unified tenant <code class="text-[10px] bg-slate-100 px-1 rounded">data/</code> root (media under data/media/). Legacy root folders are not used.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="load()" class="h-8 px-3 rounded-lg border text-xs font-bold text-slate-700 hover:bg-slate-50">Refresh</button>
                
                
            </div>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-xs text-emerald-700 font-semibold" x-show="msg" x-text="msg"></p>
            <p class="text-xs text-rose-600" x-show="err" x-text="err"></p>

            <div>
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">JSON namespaces (Optimise normalises these)</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="text-left px-2 py-1.5">Store</th>
                                <th class="text-left px-2 py-1.5">File</th>
                                <th class="text-left px-2 py-1.5">Optimized</th>
                                <th class="text-right px-2 py-1.5">Size</th>
                                <th class="text-left px-2 py-1.5">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="n in namespaces" :key="n.key">
                                <tr class="border-t border-slate-50">
                                    <td class="px-2 py-1.5 font-semibold text-slate-800" x-text="n.label"></td>
                                    <td class="px-2 py-1.5 font-mono text-slate-500" x-text="n.path"></td>
                                    <td class="px-2 py-1.5">
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                              :class="n.optimized ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                              x-text="n.optimized ? 'Yes' : 'Watch only'"></span>
                                    </td>
                                    <td class="px-2 py-1.5 text-right tabular-nums" x-text="n.exists ? human(n.bytes) : '—'"></td>
                                    <td class="px-2 py-1.5">
                                        <span :class="n.exists && n.writable ? 'text-emerald-600' : (n.exists ? 'text-amber-600' : 'text-slate-400')"
                                              x-text="n.exists ? (n.writable ? 'OK' : 'Read-only') : 'Missing'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Writable folders under data/</h4>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <template x-for="f in folders" :key="f.id">
                        <div class="rounded-xl border p-3"
                             :class="f.ok ? 'border-emerald-100 bg-emerald-50/40' : 'border-amber-100 bg-amber-50/50'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-slate-800" x-text="f.label"></span>
                                <span class="text-[10px] font-bold" :class="f.ok ? 'text-emerald-600' : 'text-amber-700'"
                                      x-text="f.ok ? 'Writable' : (f.exists ? 'Not writable' : 'Missing')"></span>
                            </div>
                            <div class="text-[10px] font-mono text-slate-500 mt-1 truncate" x-text="f.path"></div>
                            <div class="text-[10px] text-slate-500 mt-1" x-text="f.files + ' files · ' + human(f.bytes)"></div>
                        </div>
                    </template>
                </div>
            </div>

            <div x-show="backupPolicy" class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-[11px] text-slate-600">
                <div class="font-bold text-slate-700 mb-1">Backup policy (Health / tools/backup.php)</div>
                <div class="grid sm:grid-cols-2 gap-2">
                    <div>
                        <div class="text-[10px] font-bold uppercase text-emerald-700 mb-0.5">Includes</div>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <template x-for="x in (backupPolicy && backupPolicy.includes) || []" :key="x"><li x-text="x"></li></template>
                        </ul>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold uppercase text-slate-500 mb-0.5">Excludes</div>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <template x-for="x in (backupPolicy && backupPolicy.excludes) || []" :key="x"><li x-text="x"></li></template>
                        </ul>
                    </div>
                </div>
                <p class="mt-2 text-slate-500" x-show="optimizeNs.length">Optimise normalises: <span class="font-mono" x-text="optimizeNs.join(', ')"></span></p>
            </div>

            <div>
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Legacy paths (pre–data/ layout)</h4>
                <ul class="space-y-1.5">
                    <template x-for="l in legacy" :key="l.path">
                        <li class="flex flex-wrap items-center justify-between gap-2 text-xs border border-slate-100 rounded-lg px-3 py-2">
                            <div>
                                <span class="font-semibold text-slate-700" x-text="l.label"></span>
                                <span class="text-slate-400 font-mono ml-2" x-text="l.path"></span>
                                <span class="text-slate-400"> → </span>
                                <span class="font-mono text-slate-500" x-text="l.target"></span>
                            </div>
                            <span class="text-[10px] font-bold"
                                  :class="l.needs_migration ? 'text-amber-700' : 'text-slate-400'"
                                  x-text="l.needs_migration ? (l.files + ' files to migrate') : (l.exists ? 'Empty / done' : 'Not present')"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </div>
    <script>
    function layoutInventory() {
        return {
            namespaces: [], folders: [], legacy: [], backupPolicy: null, optimizeNs: [], msg: '', err: '', busy: false,
            async load() {
                this.err = '';
                try {
                    const csrf = (window.APP && window.APP.csrf) || window.APP_CSRF || '';
                    const r = await fetch('index.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf},
                        credentials: 'same-origin',
                        body: JSON.stringify({ action: 'layout_inventory' })
                    });
                    const j = await r.json();
                    if (j.status !== 'success' && j.status !== 'ok') {
                        this.err = j.message || 'Inventory failed';
                        return;
                    }
                    this.namespaces = j.namespaces || [];
                    this.folders = j.folders || [];
                    this.legacy = j.legacy || [];
                    this.backupPolicy = j.backup_policy || null;
                    this.optimizeNs = j.optimize_namespaces || [];
                } catch (e) {
                    this.err = String(e);
                }
            },
            human(b) {
                b = Number(b) || 0;
                if (b < 1024) return b + ' B';
                if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
                return (b/1048576).toFixed(1) + ' MB';
            },
            async migrate(removeEmpty) {
                alert('Legacy folder migration has been retired. All data lives under tenants/{id}/data/.'); return;
                this.busy = true; this.msg = ''; this.err = '';
                try {
                    const csrf = (window.APP && window.APP.csrf) || window.APP_CSRF || '';
                    const r = await fetch('index.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf},
                        credentials: 'same-origin',
                        body: JSON.stringify({ action: 'layout_migrate', remove_empty_legacy: removeEmpty ? 1 : 0 })
                    });
                    const j = await r.json();
                    this.msg = 'Copied ' + (j.copied||0) + ', skipped ' + (j.skipped||0)
                        + (j.removed_dirs && j.removed_dirs.length ? ('; removed: ' + j.removed_dirs.join(', ')) : '');
                    if (j.errors && j.errors.length) this.err = j.errors.join('; ');
                    await this.load();
                } catch (e) {
                    this.err = String(e);
                }
                this.busy = false;
            }
        };
    }
    </script>

    <!-- The System Optimizer panel intentionally lives ONLY on the Optimise
         tab. It previously rendered here too — same panel, same action —
         which made it look like two different tools and meant a destructive
         operation had two entry points. One tool, one place. -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex items-center gap-4">
        <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center border border-blue-100 shrink-0">
            <i class="fa-solid fa-wrench"></i>
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="text-sm font-bold text-slate-800">System Optimizer &amp; Cleanup</h3>
            <p class="text-xs text-slate-500 mt-0.5">Schema normalisation, slug migration and the server cleanup scanner.</p>
        </div>
        <a href="?tab=opt" class="shrink-0 px-4 py-2 bg-slate-900 hover:bg-blue-600 text-white text-xs font-bold rounded-lg transition flex items-center gap-2">
            Open <i class="fa-solid fa-arrow-right text-[10px]"></i>
        </a>
    </div>

    <!-- ── Third-party library audit ───────────────────────────────────────
         Installed versions are parsed from the actual <script>/<link> tags in
         the source, so this table can never drift from what is really loaded.
         Latest versions are fetched live from the cdnjs and npm registry APIs,
         client-side, and cached in sessionStorage — the check is skipped
         silently if the network is unavailable, so the Monitor tab never
         hangs waiting on a third party.
    ───────────────────────────────────────────────────────────────────────── -->
    <?php
    // Parse the real CDN references out of the source rather than hardcoding.
    // Scan only live shells (not every historical comment in old tabs).
    $_libScanFiles = array_filter([
        BASE_PATH . '/app/views/dashboard.php',
        BASE_PATH . '/app/views/login.php',
        BASE_PATH . '/app/views/tabs/tab_cartags.php',
        BASE_PATH . '/app/views/tabs/tab_bank.php',
        BASE_PATH . '/cards/business.php',
        BASE_PATH . '/cards/signature.php',
    ], 'is_readable');
    $_libSrc = '';
    foreach ($_libScanFiles as $_f) {
        $_chunk = (string)@file_get_contents($_f);
        // Strip // and /* */ comments so stale version strings in changelogs
        // do not pollute installed-version detection.
        $_chunk = preg_replace('~//.*$~m', '', $_chunk) ?? $_chunk;
        $_chunk = preg_replace('~ /\*.*?\*/~s', '', $_chunk) ?? $_chunk;
        $_libSrc .= $_chunk;
    }

    $_libDefs = [
        ['name'=>'Font Awesome', 'key'=>'font-awesome',
         'rx'=>'~font-awesome/([0-9]+\.[0-9]+\.[0-9]+)~', 'src'=>'cdnjs', 'pkg'=>'font-awesome'],
        ['name'=>'Alpine.js', 'key'=>'alpinejs',
         'rx'=>'~alpinejs@([0-9]+\.[0-9]+\.[0-9]+)~', 'src'=>'npm', 'pkg'=>'alpinejs'],
        ['name'=>'Chart.js', 'key'=>'chart.js',
         'rx'=>'~chart\.js@([0-9]+\.[0-9]+\.[0-9]+)~', 'src'=>'npm', 'pkg'=>'chart.js'],
        ['name'=>'SheetJS (xlsx)', 'key'=>'xlsx',
         'rx'=>'~xlsx-([0-9]+\.[0-9]+\.[0-9]+)~', 'src'=>'none', 'pkg'=>'xlsx'],
        ['name'=>'EasyQRCodeJS', 'key'=>'easyqrcodejs',
         'rx'=>'~easyqrcodejs@([0-9]+\.[0-9]+\.[0-9]+)~', 'src'=>'npm', 'pkg'=>'easyqrcodejs'],
        // Self-hosted design system (assets/app.css) — no Play CDN.
        ['name'=>'Design system (app.css)', 'key'=>'appcss',
         'rx'=>'~assets/app\.css~', 'src'=>'none', 'pkg'=>'app-css'],
        ['name'=>'Tailwind CSS (Play CDN)', 'key'=>'tailwind',
         'rx'=>'~cdn\.tailwindcss\.com~', 'src'=>'none', 'pkg'=>'tailwindcss'],
    ];

    $_libs = [];
    foreach ($_libDefs as $_d) {
        $_found = [];
        if (preg_match_all($_d['rx'], $_libSrc, $_m)) {
            $_found = !empty($_m[1]) ? array_values(array_unique(array_filter($_m[1]))) : ['(unversioned)'];
        // Self-hosted fallback: parse version from vendor file when URL regex misses
        if ($_lib['key'] === 'font-awesome' || $_lib['name'] === 'Font Awesome') {
            $v = rc_parse_vendor_version(BASE_PATH . '/assets/vendor/fontawesome.min.css', 'fontawesome');
            if ($v !== '') { $_found = [$v]; }
        }
        if ($_lib['key'] === 'alpinejs' || stripos($_lib['name'], 'Alpine') !== false) {
            $v = rc_parse_vendor_version(BASE_PATH . '/assets/vendor/alpine.min.js', 'alpine');
            if ($v !== '') { $_found = [$v]; }
        }

        }
        if (empty($_found)) continue;
        $_libs[] = [
            'name'      => $_d['name'],
            'installed' => implode(', ', $_found),
            'multiple'  => count($_found) > 1,
            'src'       => $_d['src'],
            'pkg'       => $_d['pkg'],
        ];
    }
    ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" x-data="libAudit()" x-init="check()">
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
            <div>
                <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Third-Party Libraries</h2>
                <p class="text-[11px] text-slate-400 mt-0.5">Installed versions read from source · latest fetched live</p>
            </div>
            <button @click="check(true)" :disabled="loading"
                    class="text-xs font-bold text-blue-600 hover:text-blue-800 disabled:text-slate-400 flex items-center gap-1.5">
                <i class="fa-solid" :class="loading ? 'fa-circle-notch fa-spin' : 'fa-rotate'"></i>
                <span x-text="loading ? 'Checking…' : 'Re-check'"></span>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left" style="min-width:560px">
                <thead class="bg-slate-50/60 border-b border-slate-100 text-[10px] uppercase text-slate-500 tracking-wider">
                    <tr>
                        <th class="px-6 py-2.5 font-bold">Library</th>
                        <th class="px-4 py-2.5 font-bold w-36">Installed</th>
                        <th class="px-4 py-2.5 font-bold w-36">Latest</th>
                        <th class="px-4 py-2.5 font-bold w-32 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php foreach ($_libs as $_l): ?>
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-3 font-semibold text-slate-700"><?= htmlspecialchars($_l['name']) ?>
                            <?php if ($_l['multiple']): ?>
                            <span class="ml-1 text-[9px] font-black uppercase text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">mixed versions</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= htmlspecialchars($_l['installed']) ?></td>
                        <td class="px-4 py-3 font-mono text-xs"
                            x-text="latest[<?= json_encode($_l['pkg']) ?>] || (loading ? '…' : 'n/a')"
                            :class="cls(<?= json_encode($_l['pkg']) ?>, <?= json_encode($_l['installed']) ?>)"></td>
                        <td class="px-4 py-3 text-right">
                            <span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded border"
                                  :class="badgeCls(<?= json_encode($_l['pkg']) ?>, <?= json_encode($_l['installed']) ?>)"
                                  x-text="badge(<?= json_encode($_l['pkg']) ?>, <?= json_encode($_l['installed']) ?>)"></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 text-[11px] text-slate-500 leading-relaxed">
            <i class="fa-solid fa-circle-info mr-1 text-slate-400"></i>
            A newer release is not automatically an upgrade — a major-version jump can change
            icon names or class syntax. Check the library's changelog before bumping.
            Prefer self-hosted assets/app.css. Play CDN is flagged only if still referenced in
            production; replacing it needs a build step.
        </div>
    </div>

    <script>
    function libAudit() {
        return {
            latest: {}, loading: false,

            async check(force = false) {
                const CACHE = 'libAudit.v1';
                if (!force) {
                    try {
                        const c = JSON.parse(sessionStorage.getItem(CACHE) || 'null');
                        if (c && (Date.now() - c.t) < 3600000) { this.latest = c.d; return; }
                    } catch (e) { /* ignore malformed cache */ }
                }
                this.loading = true;
                const out = {};
                const jobs = [
                    ['font-awesome', 'https://api.cdnjs.com/libraries/font-awesome?fields=version'],
                    ['easyqrcodejs', 'https://registry.npmjs.org/easyqrcodejs/latest'],
                    ['alpinejs',     'https://registry.npmjs.org/alpinejs/latest'],
                    ['chart.js',     'https://registry.npmjs.org/chart.js/latest'],
                    ['xlsx',         'https://registry.npmjs.org/xlsx/latest'],
                    ['tailwindcss',  'https://registry.npmjs.org/tailwindcss/latest'],
                ];
                await Promise.all(jobs.map(async ([k, url]) => {
                    try {
                        const r = await fetch(url, { cache: 'no-store' });
                        if (!r.ok) return;
                        const j = await r.json();
                        if (j && j.version) out[k] = j.version;
                    } catch (e) { /* offline or blocked — leave blank, never block the page */ }
                }));
                this.latest = out;
                try { sessionStorage.setItem(CACHE, JSON.stringify({ t: Date.now(), d: out })); } catch (e) {}
                this.loading = false;
            },

            // Compare only the first installed version if several are present.
            _cmp(pkg, installed) {
                const L = this.latest[pkg];
                const I = String(installed).split(',')[0].trim();
                if (!L || !/^[0-9]/.test(I)) return null;
                if (L === I) return 'current';
                const a = L.split('.').map(Number), b = I.split('.').map(Number);
                if ((a[0] || 0) > (b[0] || 0)) return 'major';
                if ((a[0] || 0) < (b[0] || 0)) return 'ahead';
                return 'minor';
            },
            badge(pkg, installed) {
                const s = this._cmp(pkg, installed);
                if (this.loading && !this.latest[pkg]) return '…';
                return { current:'Current', minor:'Update', major:'Major', ahead:'Ahead' }[s] || 'Unknown';
            },
            badgeCls(pkg, installed) {
                return {
                    current:'text-emerald-700 bg-emerald-50 border-emerald-200',
                    minor:  'text-blue-700 bg-blue-50 border-blue-200',
                    major:  'text-amber-700 bg-amber-50 border-amber-300',
                    ahead:  'text-slate-500 bg-slate-50 border-slate-200'
                }[this._cmp(pkg, installed)] || 'text-slate-400 bg-slate-50 border-slate-200';
            },
            cls(pkg, installed) {
                const s = this._cmp(pkg, installed);
                return s === 'current' ? 'text-emerald-600 font-bold'
                     : s === 'major'   ? 'text-amber-600 font-bold'
                     : s === 'minor'   ? 'text-blue-600 font-bold' : 'text-slate-400';
            }
        };
    }
    </script>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Storage Integrity</h2>
                <i class="fa-solid fa-shield-halved text-slate-400"></i>
            </div>
            <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto hide-scrollbar">
                <?php foreach($diagnostics['data'] as $k => $v): ?>
                <div class="px-6 py-3 flex justify-between items-center hover:bg-slate-50 transition">
                    <span class="font-mono text-xs font-bold text-slate-600"><?= $k ?></span>
                    <div><?= $v ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 text-sm font-bold text-slate-700 uppercase tracking-wider">Permissions</div>
                <div class="divide-y divide-slate-100">
                    <?php foreach($diagnostics['perms'] as $k => $v): ?>
                    <div class="px-6 py-3 flex justify-between items-center">
                        <span class="font-mono text-xs text-slate-500">/<?= $k ?></span>
                        <div class="text-xs"><?= $v ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 text-sm font-bold text-slate-700 uppercase tracking-wider">Environment</div>
                <div class="divide-y divide-slate-100">
                    <?php foreach($diagnostics['env'] as $k => $v): ?>
                    <div class="px-6 py-3 flex justify-between items-center text-xs">
                        <span class="text-slate-500"><?= $k ?></span>
                        <span class="font-bold text-slate-800"><?= $v ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between gap-2">
                    <span>Front-end libraries (assets/vendor)</span>
                    <span class="text-[10px] font-mono font-normal text-slate-400">self-hosted</span>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php foreach (($diagnostics['libraries'] ?? []) as $k => $v): ?>
                    <div class="px-6 py-3 flex justify-between items-center gap-3 text-xs">
                        <span class="text-slate-600 font-semibold"><?= $h($k) ?></span>
                        <span class="text-right"><?= $v ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($diagnostics['libraries'])): ?>
                    <div class="px-6 py-3 text-xs text-amber-700">No library inventory (probe did not run).</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between gap-2">
                    <span>Webfonts</span>
                    <span class="text-[10px] font-mono font-normal text-slate-400">assets/vendor/webfonts · assets/fonts</span>
                </div>
                <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                    <?php foreach (($diagnostics['fonts'] ?? []) as $k => $v): ?>
                    <div class="px-6 py-2.5 flex justify-between items-center gap-3 text-xs">
                        <span class="font-mono text-[11px] text-slate-500 break-all"><?= $h($k) ?></span>
                        <span class="text-right text-slate-800 shrink-0"><?= $v ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 text-sm font-bold text-slate-700 uppercase tracking-wider">Host map (synthetic check)</div>
                <div class="p-4 text-xs space-y-2">
                    <?php
                    $strict = class_exists('HostPolicy') && HostPolicy::strictMode();
                    $syn = class_exists('HostPolicy') ? HostPolicy::syntheticChecks() : [];
                    ?>
                    <p class="text-slate-600 m-0">Strict host mode: <strong class="<?= $strict ? 'text-amber-700' : 'text-emerald-700' ?>"><?= $strict ? 'ON (only map.exact hosts)' : 'OFF (wildcards allowed)' ?></strong>
                      — set <code class="text-[10px] bg-slate-100 px-1 rounded">"strict_hosts": true</code> in <code class="text-[10px]">tenants/map.json</code> to enable.</p>
                    <div class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden max-h-64 overflow-y-auto">
                        <?php if (!$syn): ?>
                          <div class="px-3 py-2 text-slate-500">No exact hosts in map.json</div>
                        <?php else: foreach ($syn as $row): ?>
                          <div class="px-3 py-2 flex justify-between gap-2 <?= !empty($row['ok']) ? '' : 'bg-amber-50' ?>">
                            <span class="font-mono text-slate-700"><?= htmlspecialchars($row['host']) ?></span>
                            <span class="text-slate-500"><?= htmlspecialchars($row['tenant']) ?></span>
                            <span class="<?= !empty($row['ok']) ? 'text-emerald-700 font-bold' : 'text-amber-800 font-bold' ?>"><?= htmlspecialchars($row['note']) ?></span>
                          </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-slate-900 rounded-2xl shadow-xl border border-slate-800 overflow-hidden">
        <div class="px-6 py-4 bg-slate-950 border-b border-slate-800 flex justify-between items-center">
            <h2 class="text-xs font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                <span class="flex gap-1"><span class="w-2 h-2 rounded-full bg-rose-500"></span><span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="w-2 h-2 rounded-full bg-emerald-500"></span></span>
                System Logs (Newest First)
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-[10px] font-mono text-slate-600">/data/logs/app.log</span>
                <div class="flex items-center gap-1 mr-2" id="logFilterBar">
                    <button type="button" data-log-filter="all" onclick="setLogFilter('all')"
                            class="log-filter-btn px-2 py-1 rounded-md text-[10px] font-bold bg-slate-700 text-white">All</button>
                    <button type="button" data-log-filter="error" onclick="setLogFilter('error')"
                            class="log-filter-btn px-2 py-1 rounded-md text-[10px] font-bold bg-slate-800 text-rose-300 border border-slate-700">Errors</button>
                    <button type="button" data-log-filter="warn" onclick="setLogFilter('warn')"
                            class="log-filter-btn px-2 py-1 rounded-md text-[10px] font-bold bg-slate-800 text-amber-300 border border-slate-700">Warnings</button>
                    <button type="button" data-log-filter="info" onclick="setLogFilter('info')"
                            class="log-filter-btn px-2 py-1 rounded-md text-[10px] font-bold bg-slate-800 text-blue-300 border border-slate-700">Info</button>
                </div>
                <button id="logCopyBtn" onclick="copyLogPanel()"
                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-[10px] font-bold transition"
                        title="Copy visible (filtered) log entries">
                    <i class="fa-regular fa-copy"></i> <span id="logCopyLabel">Copy</span>
                </button>
            </div>
        </div>
        <div class="p-6 h-64 overflow-y-auto font-mono text-[11px] bg-slate-900 hide-scrollbar leading-relaxed" id="logPanelBody">
            <?php if (empty($logOutput)): ?>
                <div class="text-slate-700 italic">No log entries found.</div>
            <?php else: ?>
                <!-- Plain-text mirror for the copy button. Copying innerText of
                     the highlighted spans above would work too, but a dedicated
                     hidden textarea guarantees exactly what gets copied is the
                     raw log lines — not whatever the ERROR/INFO/SUCCESS colour
                     spans happen to render as when selection crosses them. -->
                <textarea id="logPlainText" style="position:absolute;left:-9999px" readonly><?= htmlspecialchars(implode('', $logOutput)) ?></textarea>
                <?php foreach ($logOutput as $_i => $log): ?>
                    <?php
                        // Was: raw JSON text with substring colour-highlighting on
                        // the level word only — for a CRITICAL entry with a full
                        // stack trace, that meant one unreadable line with every
                        // newline in the trace rendered as a literal "\n" escape
                        // sequence rather than an actual line break. Richer
                        // logging (260916.10) is only useful if it can be READ,
                        // so entries are now properly parsed: a one-line summary
                        // always visible, full context (file/line/trace/uri) in
                        // an expandable block for CRITICAL/ERROR entries that
                        // carry one — never for INFO/SUCCESS, which have nothing
                        // worth expanding and would just add visual noise.
                        $d = json_decode((string)$log, true);
                        $lvl = is_array($d) ? strtoupper((string)($d['level'] ?? '')) : '';
                        $dataLevel = match ($lvl) {
                            'ERROR', 'CRITICAL' => 'error',
                            'WARN', 'WARNING' => 'warn',
                            'INFO' => 'info',
                            'SUCCESS' => 'info',
                            default => 'other',
                        };
                        $lvlClass = match ($lvl) {
                            'ERROR', 'CRITICAL' => 'text-rose-400',
                            'WARN', 'WARNING'    => 'text-amber-400',
                            'INFO'               => 'text-blue-400',
                            'SUCCESS'            => 'text-emerald-400',
                            default              => 'text-slate-400',
                        };
                        $ctx = is_array($d) ? ($d['ctx'] ?? []) : [];
                        $hasDetail = is_array($ctx) && !empty($ctx) && in_array($lvl, ['ERROR', 'CRITICAL', 'WARN', 'WARNING'], true);
                        $rowId = 'logrow_' . $_i;
                    ?>
                    <?php if (!is_array($d)): ?>
                        <!-- Malformed/legacy line that doesn't parse as the
                             expected JSON shape — shown as-is rather than
                             silently dropped, so nothing ever just vanishes
                             from the viewer. -->
                        <div class="text-slate-500 border-b border-white/5 py-1"><?= htmlspecialchars((string)$log) ?></div>
                    <?php else: ?>
                    <!-- BUG FIX: the previous version built this row's onclick
                         attribute as a PHP string with nested single quotes
                         that were never actually escaped ('' instead of \'),
                         which terminates a single-quoted PHP string early —
                         the bareword `hidden` that fell outside any string as
                         a result is, under PHP 8, a fatal "Undefined constant"
                         error, not a syntax warning. This is exactly the class
                         of bug the brace/div counting checks used throughout
                         this session cannot catch: everything balances, the
                         break is purely inside a string literal. Fixed by
                         removing the nested-quote construction entirely — a
                         plain data attribute plus ONE delegated click listener
                         (below) rather than an inline onclick built from
                         concatenated PHP strings. -->
                    <div class="border-b border-white/5 <?= $hasDetail ? 'cursor-pointer hover:bg-white/5' : 'hover:bg-white/5' ?> transition-colors"
                         data-log-level="<?= htmlspecialchars($dataLevel ?? 'other') ?>"
                         data-log-raw="<?= htmlspecialchars((string)$log, ENT_QUOTES) ?>"
                         <?= $hasDetail ? 'data-toggle-target="' . htmlspecialchars($rowId) . '"' : '' ?>>
                        <div class="py-1 flex items-start gap-2">
                            <span class="text-slate-600 shrink-0"><?= htmlspecialchars((string)($d['time'] ?? '')) ?></span>
                            <span class="<?= $lvlClass ?> font-bold shrink-0"><?= htmlspecialchars($lvl ?: '—') ?></span>
                            <span class="text-slate-400 flex-1 break-words"><?= htmlspecialchars((string)($d['msg'] ?? '')) ?></span>
                            <?php if ($hasDetail): ?>
                                <i class="fa-solid fa-chevron-down text-slate-600 text-[9px] mt-1 shrink-0"></i>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasDetail): ?>
                        <div id="<?= $rowId ?>" class="hidden pb-2 pl-4">
                            <div class="bg-black/40 border border-white/10 rounded-lg p-3 space-y-1.5">
                                <?php foreach ($ctx as $_k => $_v): ?>
                                    <?php if ($_k === 'trace'): ?>
                                        <div>
                                            <div class="text-slate-500 text-[10px] uppercase tracking-wider font-bold mb-1">Stack Trace</div>
                                            <pre class="text-slate-400 text-[10px] leading-relaxed whitespace-pre-wrap break-all"><?= htmlspecialchars((string)$_v) ?></pre>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex gap-2">
                                            <span class="text-slate-500 text-[10px] uppercase tracking-wider font-bold shrink-0 w-16"><?= htmlspecialchars((string)$_k) ?></span>
                                            <span class="text-slate-300 text-[11px] break-all"><?= htmlspecialchars(is_scalar($_v) ? (string)$_v : json_encode($_v)) ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// One delegated listener for every expandable log row, added in place of
// the broken per-row inline onclick (see the fix note above). A single
// document-level listener also means new rows added later never need their
// own click wiring — the delegation covers them automatically.
document.addEventListener('click', function (e) {
    const row = e.target.closest('[data-toggle-target]');
    if (!row) return;
    const target = document.getElementById(row.getAttribute('data-toggle-target'));
    if (target) target.classList.toggle('hidden');
});

async function copyLogPanel() {
    const src = document.getElementById('logPlainText');
    const btn = document.getElementById('logCopyBtn');
    const lbl = document.getElementById('logCopyLabel');
    const text = src ? src.value : '';

    if (!text.trim()) {
        lbl.textContent = 'Nothing to copy';
        setTimeout(() => { lbl.textContent = 'Copy'; }, 1600);
        return;
    }

    let ok = false;
    try {
        // Clipboard API requires a secure context (https / localhost). Most
        // deployments have this; the execCommand fallback below covers the
        // rest without the feature simply doing nothing.
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            ok = true;
        } else {
            src.style.position = 'fixed'; src.style.left = '0'; // must be selectable to copy
            src.focus(); src.select();
            ok = document.execCommand('copy');
            src.style.position = 'absolute'; src.style.left = '-9999px';
        }
    } catch (e) { ok = false; }

    btn.classList.toggle('bg-emerald-700', ok);
    btn.classList.toggle('border-emerald-600', ok);
    lbl.textContent = ok ? 'Copied!' : 'Copy failed';
    setTimeout(() => {
        btn.classList.remove('bg-emerald-700', 'border-emerald-600');
        lbl.textContent = 'Copy';
    }, 1800);
}
</script>

<script>
async function deleteOrphanFile(btn) {
    const file = btn.getAttribute('data-orphan-file');
    if (!file) return;
    if (!confirm('Delete orphaned data file "' + file + '"?\n\nThis cannot be undone.')) return;
    btn.disabled = true;
    const prev = btn.textContent;
    btn.textContent = '…';
    try {
        const csrf = (window.APP && window.APP.csrf) || (window.APP_CSRF) || '';
        const r = await fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify({ action: 'delete_orphan', file: file })
        });
        const d = await r.json().catch(() => ({}));
        if (!r.ok || d.status !== 'success') {
            alert(d.message || ('Delete failed (HTTP ' + r.status + ')'));
            btn.disabled = false;
            btn.textContent = prev;
            return;
        }
        // Soft-remove the row
        const row = btn.closest('.flex, tr, li, div');
        if (row && row.parentElement) {
            btn.textContent = 'Deleted';
            btn.classList.remove('text-rose-600', 'bg-rose-50');
            btn.classList.add('text-slate-400');
            setTimeout(() => location.reload(), 400);
        } else {
            location.reload();
        }
    } catch (e) {
        alert('Delete failed: ' + e.message);
        btn.disabled = false;
        btn.textContent = prev;
    }
}
</script>


<script>
window.__logFilter = 'all';
function setLogFilter(mode) {
  window.__logFilter = mode || 'all';
  document.querySelectorAll('.log-filter-btn').forEach(b => {
    const on = b.getAttribute('data-log-filter') === mode;
    b.classList.toggle('bg-slate-700', on);
    b.classList.toggle('text-white', on);
  });
  document.querySelectorAll('[data-log-level]').forEach(row => {
    const lvl = row.getAttribute('data-log-level') || 'other';
    let show = true;
    if (mode === 'error') show = (lvl === 'error');
    else if (mode === 'info') show = (lvl === 'info');
    row.style.display = show ? '' : 'none';
  });
  // Rebuild plain text for copy from visible rows only
  const ta = document.getElementById('logPlainText');
  if (ta) {
    const lines = [];
    document.querySelectorAll('[data-log-level]').forEach(row => {
      if (row.style.display === 'none') return;
      const raw = row.getAttribute('data-log-raw');
      if (raw) lines.push(raw);
    });
    ta.value = lines.join('');
  }
}
</script>
