<?php

 
 
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
AppLocale::boot();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   isHttps() ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name(cfg('site.cookie_prefix', 'fusion') . '_admin_secure');
session_start();

 
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    die('Access Denied. You must be logged into the Admin Console to generate a backup.');
}

 
 
 
$zipFileName = preg_replace('/[^A-Za-z0-9]+/', '_', (string)cfg('brand.company', 'Portal')) . '_Backup_' . date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(4)) . '.zip';
$zipFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFileName;

 
$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Failed to create ZIP archive. Check server write permissions on ' . sys_get_temp_dir());
}

function addFolderToZip(string $dir, ZipArchive $zipArchive, string $zipPath = ''): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $filePath  = $dir . DIRECTORY_SEPARATOR . $file;
         
        if (is_link($filePath)) continue;
        $localPath = $zipPath ? $zipPath . '/' . $file : $file;
        if (is_dir($filePath)) {
            $zipArchive->addEmptyDir($localPath);
            addFolderToZip($filePath, $zipArchive, $localPath);
        } else {
            $zipArchive->addFile($filePath, $localPath);
        }
    }
}

 
if (is_dir(DATA_DIR)) {
    $zip->addEmptyDir('data');
    addFolderToZip(DATA_DIR, $zip, 'data');
}

 
if (is_dir(UPLOAD_DIR)) {
    $zip->addEmptyDir('uploads');
    addFolderToZip(rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR), $zip, 'uploads');
}

$zip->close();

 
if (!file_exists($zipFilePath)) {
    http_response_code(500);
    die('Backup generation failed — ZIP file not found after creation.');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($zipFilePath) . '"');
header('Content-Length: ' . filesize($zipFilePath));
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

ob_clean();
flush();
readfile($zipFilePath);
unlink($zipFilePath);  
exit;
