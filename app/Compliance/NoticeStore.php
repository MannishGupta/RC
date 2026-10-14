<?php
// Version: 3.1.1
declare(strict_types=1);
/** @deprecated Load from core/ — shim for existing requires */
$__core = dirname(__DIR__, 2) . '/core/Compliance/NoticeStore.php';
if (!class_exists('NoticeStore', false) && is_file($__core)) {
    require_once $__core;
}
