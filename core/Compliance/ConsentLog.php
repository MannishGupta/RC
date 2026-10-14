<?php
// Version: 3.1.1
declare(strict_types=1);

/**
 * Append-only consent audit log (DPDP-oriented).
 * Stores IP/UA hashes only — not raw identifiers.
 */
final class ConsentLog
{
    public static function path(): string
    {
        $root = defined('DATA_PATH') ? rtrim((string) DATA_PATH, '/\\') : (defined('BASE_PATH') ? BASE_PATH . '/data' : sys_get_temp_dir());
        $dir = $root . '/compliance';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/consents.jsonl';
    }

    /**
     * @param array{purpose_id:string,action:string,notice_version?:string,lang?:string} $event
     */
    public static function write(array $event): bool
    {
        $purpose = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($event['purpose_id'] ?? ''))) ?: 'general';
        $action = strtolower((string) ($event['action'] ?? ''));
        if (!in_array($action, ['grant', 'withdraw', 'deny'], true)) {
            return false;
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $row = [
            'ts' => gmdate('c'),
            'purpose_id' => $purpose,
            'action' => $action,
            'notice_version' => (string) ($event['notice_version'] ?? '1'),
            'lang' => (string) ($event['lang'] ?? 'en'),
            'ip_hash' => $ip !== '' ? hash('sha256', $ip) : '',
            'ua_hash' => $ua !== '' ? hash('sha256', $ua) : '',
        ];
        $line = json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
        return @file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX) !== false;
    }

    /** @return list<array<string,mixed>> */
    public static function recent(int $limit = 50): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice($lines, -max(1, $limit));
        $out = [];
        foreach ($lines as $ln) {
            $j = json_decode($ln, true);
            if (is_array($j)) {
                $out[] = $j;
            }
        }
        return array_reverse($out);
    }
}
