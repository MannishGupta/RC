<?php
/**
 * Construction Progress — RC tab bridge.
 */
if (!defined('BASE_PATH')) {
    exit;
}
$__statusPortal = BASE_PATH . '/status/status-tab.php';
if (!is_file($__statusPortal)) {
    echo '<div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-sm p-4">'
        . '<strong>Construction Progress portal not installed.</strong> Upload the <code>status/</code> package.</div>';
    return;
}
// Ensure tenant status dirs exist
if (defined('DATA_PATH')) {
    $__sd = rtrim(str_replace(['/', '\\'], '/', DATA_PATH), '/') . '/status';
    if (!is_dir($__sd)) {
        @mkdir($__sd . '/uploads', 0775, true);
    }
    foreach (['projects.json', 'components.json', 'updates.json'] as $__f) {
        $__p = $__sd . '/' . $__f;
        if (!is_file($__p)) {
            @file_put_contents($__p, "[]\n");
        }
    }
}
try {
    require $__statusPortal;
} catch (Throwable $__e) {
    echo '<div class="rounded-xl border border-rose-200 bg-rose-50 text-rose-900 text-sm p-4">'
        . '<strong>Status module error.</strong> '
        . htmlspecialchars($__e->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</div>';
}
