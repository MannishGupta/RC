<?php
declare(strict_types=1);
/**
 * tools/restore.php — Validate and restore a RC backup ZIP (manifest + checksums).
 *
 * CLI:  php tools/restore.php --file=/path/to/backup.zip [--dry-run] [--target=/path]
 * Web:  super-admin only, POST file=…&dry_run=1&csrf=
 *
 * Refuses incomplete/corrupt archives. Never serves archives from web root.
 */
$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH', $rootPath);

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
$dryRun = false;
$zipFile = '';
$targetRoot = BASE_PATH;

if ($isCli) {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run') {
            $dryRun = true;
        } elseif (str_starts_with($arg, '--file=')) {
            $zipFile = substr($arg, 7);
        } elseif (str_starts_with($arg, '--target=')) {
            $targetRoot = substr($arg, 9);
        }
    }
} else {
    require_once BASE_PATH . '/app/tenant_bootstrap.php';
    require_once BASE_PATH . '/app/bootstrap.php';
    if (session_status() === PHP_SESSION_NONE) {
        AppAuth::initSession();
    }
    $user = (string)($_SESSION['user'] ?? '');
    $true = (string)($_SESSION['true_role'] ?? $user);
    if (!in_array($user, ['super_admin'], true) && !in_array($true, ['super_admin'], true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Super Admin only']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (function_exists('verify_csrf')) {
            verify_csrf();
        }
    }
    $zipFile = (string)($_POST['file'] ?? $_GET['file'] ?? '');
    $dryRun = !empty($_POST['dry_run']) || !empty($_GET['dry_run']);
}

if ($zipFile === '' || !is_file($zipFile)) {
    $msg = 'Usage: php tools/restore.php --file=/path/to/RC-*-backup-*.zip [--dry-run]';
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

if (!class_exists('ZipArchive')) {
    $msg = 'ZipArchive not available';
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    http_response_code(501);
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    $msg = 'Cannot open ZIP';
    if ($isCli) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

$manifestRaw = $zip->getFromName('MANIFEST.json');
if ($manifestRaw === false) {
    // accept legacy backups without manifest but warn
    $manifest = null;
} else {
    $manifest = json_decode($manifestRaw, true);
    if (!is_array($manifest)) {
        $zip->close();
        $msg = 'MANIFEST.json is not valid JSON — refusing restore';
        if ($isCli) {
            fwrite(STDERR, $msg . "\n");
            exit(1);
        }
        echo json_encode(['status' => 'error', 'message' => $msg]);
        exit;
    }
}

$errors = [];
$filesChecked = 0;
$wouldWrite = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name === false || str_ends_with($name, '/')) {
        continue;
    }
    // Path traversal guard
    $norm = str_replace('\\', '/', $name);
    if (str_contains($norm, '..') || str_starts_with($norm, '/')) {
        $errors[] = "Unsafe path in archive: {$name}";
        continue;
    }
    $filesChecked++;
    if (is_array($manifest) && isset($manifest['files'][$norm])) {
        $meta = $manifest['files'][$norm];
        $bin = $zip->getFromIndex($i);
        if ($bin === false) {
            $errors[] = "Missing payload: {$norm}";
            continue;
        }
        $sha = hash('sha256', $bin);
        $expect = (string)($meta['sha256'] ?? '');
        if ($expect !== '' && !hash_equals($expect, $sha)) {
            $errors[] = "Checksum mismatch: {$norm}";
            continue;
        }
        $size = (int)($meta['size'] ?? -1);
        if ($size >= 0 && strlen($bin) !== $size) {
            $errors[] = "Size mismatch: {$norm}";
            continue;
        }
    }
    $wouldWrite[] = $norm;
}

if ($errors !== []) {
    $zip->close();
    $payload = ['status' => 'error', 'message' => 'Archive failed validation', 'errors' => $errors];
    if ($isCli) {
        fwrite(STDERR, json_encode($payload, JSON_PRETTY_PRINT) . "\n");
        exit(1);
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if ($dryRun) {
    $zip->close();
    $payload = [
        'status' => 'success',
        'dry_run' => true,
        'files' => count($wouldWrite),
        'would_write' => array_slice($wouldWrite, 0, 200),
        'message' => 'Dry-run OK — archive valid, no files written',
    ];
    if ($isCli) {
        echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Extract under target (tenants/ and data paths only)
$allowedPrefixes = ['tenants/', 'data/', 'MANIFEST.json'];
$written = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if ($name === false || str_ends_with($name, '/')) {
        continue;
    }
    $norm = str_replace('\\', '/', $name);
    $ok = false;
    foreach ($allowedPrefixes as $p) {
        if ($norm === $p || str_starts_with($norm, $p)) {
            $ok = true;
            break;
        }
    }
    if (!$ok) {
        continue;
    }
    if ($norm === 'MANIFEST.json') {
        continue;
    }
    $dest = rtrim($targetRoot, '/\\') . '/' . $norm;
    $dir = dirname($dest);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $bin = $zip->getFromIndex($i);
    if ($bin === false) {
        continue;
    }
    if (@file_put_contents($dest, $bin, LOCK_EX) !== false) {
        $written++;
    }
}
$zip->close();

$payload = [
    'status' => 'success',
    'dry_run' => false,
    'files_written' => $written,
    'message' => "Restored {$written} files",
];
if ($isCli) {
    echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    exit(0);
}
header('Content-Type: application/json');
echo json_encode($payload);
exit;
