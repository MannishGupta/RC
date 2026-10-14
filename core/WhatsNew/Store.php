<?php
// Version: 3.1.1
declare(strict_types=1);

/**
 * Uncapped append-only product changelog (reads VERSION + optional JSONL).
 */
final class WhatsNewStore
{
    public static function jsonlPath(): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dir = $base . '/data/value';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/whatsnew.jsonl';
    }

    /**
     * @return list<array{version:string,date:string,category:string,title:string,body:string}>
     */
    public static function all(): array
    {
        $items = [];
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $verFile = $base . '/VERSION';
        if (is_file($verFile)) {
            $raw = (string) file_get_contents($verFile);
            $blocks = preg_split('/\n(?=\d+\.\d+)/', trim($raw)) ?: [];
            foreach ($blocks as $block) {
                $lines = preg_split('/\R/', trim($block)) ?: [];
                if ($lines === []) {
                    continue;
                }
                $ver = trim($lines[0]);
                $body = trim(implode("\n", array_slice($lines, 1)));
                $cat = 'Improvement';
                if (preg_match('/security|csrf|auth|xss/i', $body)) {
                    $cat = 'Security';
                } elseif (preg_match('/fix|bug|leak/i', $body)) {
                    $cat = 'Fix';
                } elseif (preg_match('/add|new|feature|drawer|compliance/i', $body)) {
                    $cat = 'Feature';
                }
                $items[] = [
                    'version' => $ver,
                    'date' => '',
                    'category' => $cat,
                    'title' => 'Release ' . $ver,
                    'body' => $body !== '' ? $body : 'Release notes',
                ];
            }
        }
        $path = self::jsonlPath();
        if (is_file($path)) {
            foreach (@file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $ln) {
                $j = json_decode($ln, true);
                if (!is_array($j)) {
                    continue;
                }
                $items[] = [
                    'version' => (string) ($j['version'] ?? ''),
                    'date' => (string) ($j['date'] ?? ''),
                    'category' => (string) ($j['category'] ?? 'Improvement'),
                    'title' => (string) ($j['title'] ?? ''),
                    'body' => (string) ($j['body'] ?? ''),
                ];
            }
        }
        return $items;
    }

    public static function append(string $version, string $category, string $title, string $body): bool
    {
        $row = [
            'version' => $version,
            'date' => gmdate('Y-m-d'),
            'category' => $category,
            'title' => $title,
            'body' => $body,
        ];
        return @file_put_contents(self::jsonlPath(), json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) !== false;
    }
}
