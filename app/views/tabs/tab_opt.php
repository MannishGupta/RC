<?php
// tab_opt.php — same System Optimizer as Monitor (single shared panel).
if (!defined('BASE_PATH')) exit;
if (empty($isSuperAdmin)) {
    echo '<div class="p-8 text-center text-red-600 font-bold bg-red-50 rounded-xl m-4 border border-red-200"><i class="fa-solid fa-ban mr-2"></i>Access Denied — Super Admin only.</div>';
    return;
}
$optPanelCompact = false;
?>
<div class="max-w-4xl mx-auto w-full pb-12 mt-4 md:mt-8">
    <?php require __DIR__ . '/../partials/optimizer_panel.php'; ?>
</div>
