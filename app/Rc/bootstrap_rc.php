<?php
declare(strict_types=1);
/**
 * Modular layer bootstrap (strangler). Version: 20261002.15
 */
if (!defined('BASE_PATH')) {
    return;
}

$autoload = __DIR__ . '/Support/Autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    if (class_exists('RcAutoload', false)) {
        RcAutoload::register();
    }
}

$rcFiles = [
    __DIR__ . '/Support/MediaUrl.php',
    __DIR__ . '/Support/Bytes.php',
    __DIR__ . '/Support/SafeName.php',
    __DIR__ . '/Support/UploadErrors.php',
    __DIR__ . '/Support/UploadDest.php',
    __DIR__ . '/Tenant/ModuleGate.php',
    __DIR__ . '/Http/Request.php',
    __DIR__ . '/Http/PublicCache.php',
    __DIR__ . '/Http/JsonResponse.php',
    __DIR__ . '/Http/CachePolicy.php',
    __DIR__ . '/Http/ActionCatalog.php',
    __DIR__ . '/Ui/Asset.php',
    __DIR__ . '/Domain/Bank/UpiQr.php',
    __DIR__ . '/Domain/Astro/Disclaimer.php',
    __DIR__ . '/Domain/Astro/EnginePaths.php',
    __DIR__ . '/Domain/Team/RecordNormalize.php',
    __DIR__ . '/Http/Handlers/SavePrep.php',
    __DIR__ . '/Support/SlugGuard.php',
    __DIR__ . '/Support/FieldNormalize.php',
    __DIR__ . '/Domain/Bank/RecordNormalize.php',
];
foreach ($rcFiles as $rcFile) {
    if (is_file($rcFile)) {
        require_once $rcFile;
    }
}

if (!class_exists('RcMediaUrl', false) && class_exists(\App\Rc\Support\MediaUrl::class, false)) {
    class_alias(\App\Rc\Support\MediaUrl::class, 'RcMediaUrl');
}
if (!class_exists('RcModuleGate', false) && class_exists(\App\Rc\Tenant\ModuleGate::class, false)) {
    class_alias(\App\Rc\Tenant\ModuleGate::class, 'RcModuleGate');
}
if (!class_exists('RcRequest', false) && class_exists(\App\Rc\Http\Request::class, false)) {
    class_alias(\App\Rc\Http\Request::class, 'RcRequest');
}
if (!class_exists('RcPublicCache', false) && class_exists(\App\Rc\Http\PublicCache::class, false)) {
    class_alias(\App\Rc\Http\PublicCache::class, 'RcPublicCache');
}
if (!class_exists('RcAsset', false) && class_exists(\App\Rc\Ui\Asset::class, false)) {
    class_alias(\App\Rc\Ui\Asset::class, 'RcAsset');
}
if (!class_exists('RcCachePolicy', false) && class_exists(\App\Rc\Http\CachePolicy::class, false)) {
    class_alias(\App\Rc\Http\CachePolicy::class, 'RcCachePolicy');
}
