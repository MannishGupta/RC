<?php
// error.php — Version: 260916.14
// error.php - V2.0.SYSTEM_DIAGNOSTICS_AUTH_GATED
// CHANGELOG v2.0: previously gated only by a hardcoded `?key=debug` string
// literally readable in this file's own source — anyone who saw the source
// (or guessed) got PHP version, server details, directory permissions, JSON
// validity of every data file, and the last 50 lines of the application log.
// Now requires an active admin/crm dashboard session, matching print.php.
// Also: display_errors is no longer forced on here — this page shows its
// OWN diagnostics panel regardless; forcing global error display is what
// let stray warnings leak elsewhere in the app.

$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
// BUG FIX: this file -- the permission/diagnostics validator itself --
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('SESSION_PATH')) define('SESSION_PATH', DATA_PATH . '/sessions');

if (!file_exists(SESSION_PATH)) @mkdir(SESSION_PATH, 0755, true);
require_once BASE_PATH . '/app/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) AppAuth::initSession();
if (empty($_SESSION['user']) || !in_array($_SESSION['user'], ['admin', 'crm', 'super_admin'], true)) {
    http_response_code(403);
    die("<div style='font-family:sans-serif; padding: 2rem; text-align:center;'>Access Denied. Please log in via the main dashboard.</div>");
}

if (!defined('IMG_PATH')) if (!defined('IMG_PATH')) define('IMG_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/images');
if (!defined('DOC_PATH')) if (!defined('DOC_PATH')) define('DOC_PATH', (defined('DATA_PATH') ? DATA_PATH : BASE_PATH . '/data') . '/media/documents');

$diagnostics = [
    'env' => [],
    'perms' => [],
    'data' => [],
    'logs' => []
];

// 1. Environment Checks
$diagnostics['env']['PHP Version'] = PHP_VERSION;
$diagnostics['env']['Server Software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$diagnostics['env']['Memory Limit'] = ini_get('memory_limit');
$diagnostics['env']['Upload Max Size'] = ini_get('upload_max_filesize');
$diagnostics['env']['External Fetch (allow_url_fopen)'] = ini_get('allow_url_fopen') ? '<span class="text-emerald-500 font-bold">Yes</span>' : '<span class="text-red-500 font-bold">No</span>';
$diagnostics['env']['display_errors'] = ini_get('display_errors') ? '<span class="text-red-500 font-bold">ON — should be off in production</span>' : '<span class="text-emerald-500 font-bold">Off (correct)</span>';

// 2. Directory Permissions
$dirsToCheck = ['data', 'data/sessions', 'data/logs', 'images', 'docs'];
foreach ($dirsToCheck as $dir) {
    $path = BASE_PATH . '/' . $dir;
    if (!file_exists($path)) {
        $diagnostics['perms'][$dir] = '<span class="text-red-500 font-bold">Missing Directory</span>';
    } elseif (!is_writable($path)) {
        $diagnostics['perms'][$dir] = '<span class="text-red-500 font-bold">Not Writable (Check permissions)</span>';
    } else {
        $diagnostics['perms'][$dir] = '<span class="text-emerald-500 font-bold">OK (Writable)</span>';
    }
}

// 3. JSON Data Integrity
$jsonFiles = glob(DATA_PATH . '/*.json');
if ($jsonFiles) {
    foreach ($jsonFiles as $file) {
        $name = basename($file);
        $content = file_get_contents($file);
        if (trim((string)$content) === '') {
            $diagnostics['data'][$name] = '<span class="text-amber-500 font-bold">Empty File</span>';
            continue;
        }

        json_decode($content);
        $jsonError = json_last_error();

        if ($jsonError === JSON_ERROR_NONE) {
            $diagnostics['data'][$name] = '<span class="text-emerald-500 font-bold">Valid JSON</span>';
        } else {
            $diagnostics['data'][$name] = '<span class="text-red-600 font-black">CORRUPTED: ' . json_last_error_msg() . '</span>';
        }
    }
} else {
    $diagnostics['data']['Status'] = '<span class="text-red-500 font-bold">No JSON files found in /data!</span>';
}

// 4. Read Last 50 Lines of App Log
$logPath = DATA_PATH . '/logs/app.log';
if (file_exists($logPath)) {
    $logLines = file($logPath);
    if ($logLines !== false) {
        $diagnostics['logs'] = array_slice($logLines, -50);
    }
} else {
    $diagnostics['logs'][] = "No application log found.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Resource Centre diagnostics — restricted operator tool.">
<meta name="robots" content="noindex,nofollow">
<title>System Diagnostics</title>
    <link rel="stylesheet" href="/assets/app.css">
    <link href="/assets/vendor/fontawesome.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="/assets/vendor/fontawesome.min.css" rel="stylesheet"></noscript>
</head>
<body class="bg-slate-50 text-slate-800 font-sans p-6 md:p-12">

    <div class="max-w-5xl mx-auto space-y-6">

        <div class="bg-slate-900 text-white p-6 rounded-2xl shadow-xl flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black flex items-center gap-3"><i class="fa-solid fa-stethoscope text-blue-400"></i> System Diagnostic Engine</h1>
                <p class="text-slate-400 text-sm mt-1">Host: <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Unknown') ?> · Logged in as <?= htmlspecialchars($_SESSION['user']) ?></p>
            </div>
            <button onclick="window.location.reload()" class="bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-lg font-bold text-sm transition">Run Scan Again</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2"><i class="fa-solid fa-database text-blue-500 mr-2"></i> Data Integrity (JSON)</h2>
                <p class="text-xs text-slate-500 mb-4">If any file says CORRUPTED, the dashboard's JavaScript will crash globally.</p>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($diagnostics['data'] as $k => $v): ?>
                            <tr><td class="py-2 font-mono text-slate-600"><?= htmlspecialchars($k) ?></td><td class="py-2 text-right"><?= $v ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2"><i class="fa-solid fa-folder-tree text-amber-500 mr-2"></i> File Permissions</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($diagnostics['perms'] as $k => $v): ?>
                            <tr><td class="py-2 font-mono text-slate-600">/<?= htmlspecialchars($k) ?></td><td class="py-2 text-right"><?= $v ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 class="text-lg font-bold text-slate-800 mt-6 mb-4 border-b pb-2"><i class="fa-solid fa-server text-emerald-500 mr-2"></i> Environment</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($diagnostics['env'] as $k => $v): ?>
                            <tr><td class="py-2 text-slate-600 font-medium"><?= htmlspecialchars($k) ?></td><td class="py-2 text-right"><?= $v ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2"><i class="fa-solid fa-terminal text-slate-500 mr-2"></i> System Logs (app.log)</h2>
            <div class="bg-slate-900 text-emerald-400 p-4 rounded-xl overflow-x-auto text-xs font-mono h-64 overflow-y-auto whitespace-pre">
<?php foreach (array_reverse($diagnostics['logs']) as $log) echo htmlspecialchars((string)$log); ?>
            </div>
        </div>

    </div>
</body>
</html>
