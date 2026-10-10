<?php
declare(strict_types=1);
namespace App\Rc\Http;

/**
 * Shared JSON API responses (ETag optional).
 * Version: 20261002.11 — Phase 4 extraction from index.php sendJson
 */
final class JsonResponse
{
    public static function send(mixed $data, bool $cacheable = true): never
    {
        if (ob_get_length()) {
            @ob_clean();
        }
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $json = json_encode($data, $flags);
        if ($json === false) {
            $json = '{"status":"error","message":"JSON encode failed"}';
            $cacheable = false;
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        $etag = '"' . hash('sha256', $json) . '"';
        if ($cacheable) {
            $inm = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
            if ($inm !== '' && (hash_equals($etag, $inm) || str_contains($inm, trim($etag, '"')))) {
                http_response_code(304);
                header('ETag: ' . $etag);
                header('Cache-Control: private, must-revalidate');
                exit;
            }
            header('ETag: ' . $etag);
            header('Cache-Control: private, must-revalidate');
        } else {
            header('Cache-Control: no-store');
        }
        echo $json;
        exit;
    }
}
