<?php
declare(strict_types=1);
namespace App\Rc\Support;

/**
 * Human messages for PHP upload error codes.
 * Version: 20261002.15
 */
final class UploadErrors
{
    public static function message(int $code, ?string $iniMax = null): string
    {
        $iniMax = $iniMax ?? (string) ini_get('upload_max_filesize');
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                "File is too large. This server accepts up to {$iniMax} per file. "
                . 'Compress the document, or add it as an External URL instead.',
            UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server has no temporary upload folder configured. Contact your host.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file to disk.',
            UPLOAD_ERR_EXTENSION => 'A server extension blocked this upload.',
            UPLOAD_ERR_NO_FILE => 'No file was received.',
            default => "Upload failed (error {$code}).",
        };
    }
}
