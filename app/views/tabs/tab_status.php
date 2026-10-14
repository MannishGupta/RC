<?php

if (!defined('BASE_PATH')) {
    exit;
}
if (defined('DATA_PATH')) {
    $__sd = rtrim(str_replace(['/', '\\'], '/', (string)DATA_PATH), '/') . '/status';
    if (!is_dir($__sd . '/uploads')) {
        @mkdir($__sd . '/uploads', 0775, true);
    }
    foreach (['projects.json', 'components.json', 'updates.json'] as $__f) {
        $__p = $__sd . '/' . $__f;
        if (!is_file($__p)) {
            @file_put_contents($__p, "[]\n");
        }
    }
}
?>
<div class="rc-status-shell w-full" style="min-height:70vh">
  <div class="flex flex-wrap items-center gap-2 mb-3 text-sm" style="color:var(--rc-ink,#201f1e)">
    <span class="font-bold">Construction Progress</span>
    <span class="opacity-50">·</span>
    <a href="/status/" target="_blank" rel="noopener" class="underline" style="color:var(--rc-accent,#0078D4)">Open full page</a>
    <span class="opacity-50">·</span>
    <a href="/status/admin.php" target="_blank" rel="noopener" class="underline" style="color:var(--rc-accent,#0078D4)">Management Console</a>
  </div>
  <iframe
    src="/status/?embed=1"
    title="Construction Progress"
    class="w-full rounded-xl border"
    style="min-height:72vh;height:calc(100vh - 11rem);border-color:var(--rc-border,#e1dfdd);background:var(--rc-card,#fff)"
    loading="eager"
    referrerpolicy="same-origin"
  ></iframe>
</div>
