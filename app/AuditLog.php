<?php
declare(strict_types=1);
/**
 * Append-only monthly audit log per tenant.
 * Version: 260926.33
 */
if (!defined('BASE_PATH')) { exit; }

class AuditLog
{
    public static function dataRoot(): string
    {
        if (!empty($GLOBALS['RC_OPT_DATA_PATH']) && is_string($GLOBALS['RC_OPT_DATA_PATH'])) {
            return rtrim(str_replace('\\', '/', $GLOBALS['RC_OPT_DATA_PATH']), '/');
        }
        if (defined('DATA_PATH')) {
            return rtrim(str_replace('\\', '/', (string) DATA_PATH), '/');
        }
        return rtrim(str_replace('\\', '/', BASE_PATH . '/data'), '/');
    }

    /**
     * @param array<string,mixed> $extra
     */
    public static function write(string $action, array $extra = []): void
    {
        $root = self::dataRoot();
        $dir = $root . '/audit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . date('Y-m') . '.jsonl';
        $row = array_merge([
            'ts' => date('c'),
            'action' => $action,
            'tenant' => defined('TENANT_ID') ? (string) TENANT_ID : '',
            'user' => (string) ($_SESSION['user'] ?? 'anonymous'),
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        ], $extra);
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        if (class_exists('PathJail')) {
            try {
                PathJail::assertWritable($file, $root);
            } catch (Throwable $e) {
                return;
            }
        }
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
