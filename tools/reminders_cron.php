<?php
declare(strict_types=1);
/**
 * Scheduled reminders runner — call via Windows Task Scheduler or cron.
 * Example: php tools/reminders_cron.php
 * Or HTTP: /tools/reminders_cron.php?token=SECRET
 * Secret file: data/value/cron_token.txt (one line). Version: 260921.41
 */
define('BASE_PATH', str_replace('\\', '/', dirname(__DIR__)));
require_once BASE_PATH . '/app/ValueStore.php';

$tokenFile = BASE_PATH . '/data/value/cron_token.txt';
$cli = (PHP_SAPI === 'cli');
if (!$cli) {
    $need = is_file($tokenFile) ? trim((string)@file_get_contents($tokenFile)) : '';
    $got = (string)($_GET['token'] ?? '');
    if ($need === '' || !hash_equals($need, $got)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden. Create data/value/cron_token.txt and pass ?token=\n";
        exit;
    }
}

// Inline minimal scan (same logic as API)
$today = new DateTimeImmutable('today');
$reminders = [];
$docsPath = is_file(BASE_PATH . '/data/docs.json') ? BASE_PATH . '/data/docs.json' : BASE_PATH . '/data/documents.json';
$docs = is_file($docsPath) ? (json_decode((string)@file_get_contents($docsPath), true) ?: []) : [];
foreach ($docs as $d) {
    if (!is_array($d)) {
        continue;
    }
    $exp = $d['expiry'] ?? $d['expiry_date'] ?? $d['expires_on'] ?? $d['valid_till'] ?? '';
    if ($exp === '' || $exp === null) {
        continue;
    }
    try {
        $dt = new DateTimeImmutable((string)$exp);
    } catch (Throwable $e) {
        continue;
    }
    $days = (int)$today->diff($dt)->format('%r%a');
    if ($days > 30) {
        continue;
    }
    $title = (string)($d['title'] ?? $d['name'] ?? 'Document');
    $reminders[] = [
        'type' => 'doc_expiry',
        'title' => $title,
        'days_left' => $days,
        'wa_hi' => $days < 0
            ? "Document expire ho chuka hai: {$title}."
            : "Document {$days} din mein expire hoga: {$title}.",
    ];
}

ValueStore::write('reminders_last', ['at' => date('c'), 'items' => $reminders, 'source' => 'cron']);
ValueStore::audit('reminders_cron', 'system', ['count' => count($reminders)]);

// Optional: append to a simple mail queue file for external SMTP pickup
$queue = BASE_PATH . '/data/value/reminder_queue.json';
@file_put_contents($queue, json_encode(['at' => date('c'), 'items' => $reminders], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$out = ['status' => 'ok', 'count' => count($reminders), 'at' => date('c')];
if ($cli) {
    echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out);
}
