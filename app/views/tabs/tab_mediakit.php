<?php
/**
 * Media Kit — brand asset library for dealers & partners.
 * Layout: Header → Share library → Add assets (admin) → Browse → Guidelines
 */
if (!defined('BASE_PATH')) {
    exit;
}
$__isAd = !empty($isAdmin) || !empty($isSuperAdmin);

$mkConnStatus = [];
if (!class_exists('MediaKitSources') && defined('BASE_PATH') && is_file(BASE_PATH . '/app/MediaKitSources.php')) {
    require_once BASE_PATH . '/app/MediaKitSources.php';
}
if (class_exists('MediaKitSources')) {
    $mkConnStatus = MediaKitSources::connectionStatus();
}


$kit = [];
if (class_exists('AppDB')) {
    $raw = AppDB::read('mediakit');
    if (is_array($raw)) {
        if (isset($raw['name']) || isset($raw['file']) || isset($raw['id'])) {
            if (!isset($raw[0])) {
                $raw = [$raw];
            }
        }
        $seenId = [];
        $seenFp = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!isset($row['name']) && !isset($row['title']) && !isset($row['file']) && !isset($row['photo']) && !isset($row['caption'])) {
                continue;
            }
            $id = (string)($row['id'] ?? '');
            if ($id === '') {
                $id = 'mk' . substr(sha1(json_encode($row)), 0, 12);
                $row['id'] = $id;
            }
            if (isset($seenId[$id])) {
                continue;
            }
            $fp = strtolower(trim((string)($row['name'] ?? $row['title'] ?? '')))
                . '|' . strtolower(trim((string)($row['file'] ?? $row['photo'] ?? '')))
                . '|' . strtolower(trim((string)($row['caption'] ?? '')));
            if ($fp !== '||' && isset($seenFp[$fp])) {
                continue;
            }
            $seenId[$id] = true;
            if ($fp !== '||') {
                $seenFp[$fp] = true;
            }
            $kit[] = $row;
        }
        if (!empty($__isAd) && class_exists('AppDB') && is_array($raw)) {
            $rawCount = 0;
            foreach ($raw as $__r) {
                if (is_array($__r)) {
                    $rawCount++;
                }
            }
            if ($rawCount > count($kit) && count($kit) > 0) {
                @AppDB::save('mediakit', array_values($kit));
            }
        }
    }
}

$guidelines = [
    [
        'id' => 'instagram',
        'name' => 'Instagram',
        'icon' => 'fa-brands fa-instagram',
        'items' => [
            ['label' => 'Feed post', 'size' => '1080 × 1080 px', 'ratio' => '1:1', 'orient' => 'Square', 'formats' => 'JPG, PNG', 'caption' => 'Primary caption ≤ 2,200 chars; first 125 chars matter. 3–5 hashtags. CTA in first line.'],
            ['label' => 'Feed portrait', 'size' => '1080 × 1350 px', 'ratio' => '4:5', 'orient' => 'Portrait', 'formats' => 'JPG, PNG', 'caption' => 'Best organic reach format. Keep safe zone clear of UI chrome.'],
            ['label' => 'Stories / Reels cover', 'size' => '1080 × 1920 px', 'ratio' => '9:16', 'orient' => 'Vertical', 'formats' => 'JPG, PNG, MP4', 'caption' => 'Reels: 15–90s; hook in 1–3s. On-screen text large; logo top or bottom third.'],
        ],
    ],
    [
        'id' => 'whatsapp',
        'name' => 'WhatsApp',
        'icon' => 'fa-brands fa-whatsapp',
        'items' => [
            ['label' => 'Status (image)', 'size' => '1080 × 1920 px', 'ratio' => '9:16', 'orient' => 'Vertical', 'formats' => 'JPG, PNG', 'caption' => 'Status text short; brand mark legible on mobile. Avoid tiny type.'],
            ['label' => 'Status (video)', 'size' => '1080 × 1920 px', 'ratio' => '9:16', 'orient' => 'Vertical', 'formats' => 'MP4 (H.264)', 'caption' => 'Keep under ~30s for Status. Broadcast lists: clearer CTA + link in chat, not only on creative.'],
            ['label' => 'Broadcast / chat image', 'size' => '1600 × 1600 px or 1200 × 628 px', 'ratio' => '1:1 or ~1.91:1', 'orient' => 'Square / landscape', 'formats' => 'JPG, PNG', 'caption' => 'Message body carries detail; image is the brand moment.'],
        ],
    ],
    [
        'id' => 'facebook',
        'name' => 'Facebook',
        'icon' => 'fa-brands fa-facebook',
        'items' => [
            ['label' => 'Feed post', 'size' => '1200 × 630 px', 'ratio' => '1.91:1', 'orient' => 'Landscape', 'formats' => 'JPG, PNG', 'caption' => 'Link posts use ~1.91:1. Shared images: 1080×1080 also works.'],
            ['label' => 'Stories', 'size' => '1080 × 1920 px', 'ratio' => '9:16', 'orient' => 'Vertical', 'formats' => 'JPG, PNG, MP4', 'caption' => 'Same safe zones as IG Stories. Logo away from top/bottom UI.'],
            ['label' => 'Event / cover style', 'size' => '1920 × 1005 px', 'ratio' => '~1.91:1', 'orient' => 'Landscape', 'formats' => 'JPG, PNG', 'caption' => 'Keep subject in centre third; edges may crop on mobile.'],
        ],
    ],
    [
        'id' => 'linkedin',
        'name' => 'LinkedIn',
        'icon' => 'fa-brands fa-linkedin',
        'items' => [
            ['label' => 'Feed image', 'size' => '1200 × 627 px', 'ratio' => '1.91:1', 'orient' => 'Landscape', 'formats' => 'JPG, PNG', 'caption' => 'Professional tone; first 140 chars of post are critical. 3–5 hashtags max.'],
            ['label' => 'Square post', 'size' => '1080 × 1080 px', 'ratio' => '1:1', 'orient' => 'Square', 'formats' => 'JPG, PNG', 'caption' => 'Document carousels: PDF preferred for multi-page; single image still 1:1 or 1.91:1.'],
            ['label' => 'Article hero', 'size' => '1280 × 720 px', 'ratio' => '16:9', 'orient' => 'Landscape', 'formats' => 'JPG, PNG', 'caption' => 'Articles: strong headline in post text; image supports, does not replace title.'],
        ],
    ],
    [
        'id' => 'x',
        'name' => 'X (Twitter)',
        'icon' => 'fa-brands fa-x-twitter',
        'items' => [
            ['label' => 'Single image', 'size' => '1600 × 900 px', 'ratio' => '16:9', 'orient' => 'Landscape', 'formats' => 'JPG, PNG, WEBP', 'caption' => 'Post ≤ 280 chars (or long-form where enabled). Image alt text for accessibility.'],
            ['label' => 'Two-image / card', 'size' => '800 × 800 px each', 'ratio' => '1:1', 'orient' => 'Square', 'formats' => 'JPG, PNG', 'caption' => 'Keep key message readable at small size in timeline.'],
        ],
    ],
    [
        'id' => 'threads',
        'name' => 'Threads',
        'icon' => 'fa-brands fa-threads',
        'items' => [
            ['label' => 'Feed image', 'size' => '1080 × 1350 px', 'ratio' => '4:5', 'orient' => 'Portrait', 'formats' => 'JPG, PNG', 'caption' => 'Align with IG brand voice; shorter posts perform well. Same 4:5 preference as IG feed.'],
            ['label' => 'Square', 'size' => '1080 × 1080 px', 'ratio' => '1:1', 'orient' => 'Square', 'formats' => 'JPG, PNG', 'caption' => 'Logo + product clear; avoid dense paragraphs on the creative.'],
        ],
    ],
    [
        'id' => 'other',
        'name' => 'Other networks',
        'icon' => 'fa-solid fa-globe',
        'items' => [
            ['label' => 'YouTube thumbnail', 'size' => '1280 × 720 px', 'ratio' => '16:9', 'orient' => 'Landscape', 'formats' => 'JPG, PNG', 'caption' => 'Face + 3–5 words max on thumb; high contrast.'],
            ['label' => 'Pinterest pin', 'size' => '1000 × 1500 px', 'ratio' => '2:3', 'orient' => 'Portrait', 'formats' => 'JPG, PNG', 'caption' => 'Descriptive pin title; brand mark small but clear.'],
            ['label' => 'Generic share / OG', 'size' => '1200 × 630 px', 'ratio' => '1.91:1', 'orient' => 'Landscape', 'formats' => 'JPG, PNG', 'caption' => 'Default for link previews (web, chat unfurl). Match organisation cover where possible.'],
        ],
    ],
];




if (!function_exists('rc_mk_asset_href')) {
    function rc_mk_asset_href(array $item): string
    {
        $f = trim((string)($item['file'] ?? $item['photo'] ?? $item['url'] ?? $item['doc_file'] ?? ''));
        if ($f === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $f) || str_starts_with($f, 'data:') || str_starts_with($f, '/')) {
            return $f;
        }
        $base = basename(str_replace('\\', '/', $f));
        return '/media_serve.php?f=' . rawurlencode($base);
    }
    function rc_mk_ext(array $item): string
    {
        $ft = strtolower(ltrim((string)($item['file_type'] ?? ''), '.'));
        if ($ft !== '') {
            return $ft;
        }
        $f = (string)($item['filename'] ?? $item['file'] ?? $item['photo'] ?? $item['url'] ?? '');
        $path = parse_url($f, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $f = $path;
        }
        $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        return $e;
    }
    function rc_mk_is_image(array $item): bool
    {
        $e = rc_mk_ext($item);
        if (in_array($e, ['png','jpg','jpeg','gif','webp','avif','bmp','svg','svgz'], true)) {
            return true;
        }
        $mime = strtolower((string)($item['mime'] ?? ''));
        return str_starts_with($mime, 'image/');
    }
    function rc_mk_icon(string $ext): string
    {
        $map = [
            'pdf' => 'fa-solid fa-file-pdf', 'doc' => 'fa-solid fa-file-word', 'docx' => 'fa-solid fa-file-word',
            'xls' => 'fa-solid fa-file-excel', 'xlsx' => 'fa-solid fa-file-excel',
            'ppt' => 'fa-solid fa-file-powerpoint', 'pptx' => 'fa-solid fa-file-powerpoint',
            'mp4' => 'fa-solid fa-file-video', 'webm' => 'fa-solid fa-file-video', 'mov' => 'fa-solid fa-file-video',
            'mp3' => 'fa-solid fa-file-audio', 'zip' => 'fa-solid fa-file-zipper',
            'svg' => 'fa-solid fa-bezier-curve', 'svgz' => 'fa-solid fa-bezier-curve',
            'png' => 'fa-solid fa-file-image', 'jpg' => 'fa-solid fa-file-image', 'jpeg' => 'fa-solid fa-file-image',
            'webp' => 'fa-solid fa-file-image', 'avif' => 'fa-solid fa-file-image', 'gif' => 'fa-solid fa-file-image',
        ];
        return $map[$ext] ?? 'fa-solid fa-file';
    }
    function rc_mk_bytes($b): string
    {
        $b = (int)$b;
        if ($b <= 0) {
            return '';
        }
        if ($b >= 1048576) {
            return (round($b / 104857.6) / 10) . ' MB';
        }
        if ($b >= 1024) {
            return (string)(int)round($b / 1024) . ' KB';
        }
        return $b . ' B';
    }
}

// Enrich each asset with on-disk metadata (fast: one index scan per root)
$imgRoot = defined('IMG_PATH') ? rtrim((string)IMG_PATH, '/\\') : '';
$docRoot = defined('DOC_PATH') ? rtrim((string)DOC_PATH, '/\\') : '';
$dataMedia = (defined('DATA_PATH') ? rtrim((string)DATA_PATH, '/\\') : '') . '/media/images';
$searchRoots = array_values(array_unique(array_filter([
    $imgRoot, $docRoot, $dataMedia,
    $imgRoot !== '' ? $imgRoot . '/mediakit' : '',
    $dataMedia . '/mediakit',
])));
// Build lowercase filename → absolute path map once (avoids N×scandir)
$__mkIndex = [];
foreach ($searchRoots as $root) {
    if ($root === '' || !is_dir($root)) {
        continue;
    }
    $list = @scandir($root);
    if (!is_array($list)) {
        continue;
    }
    foreach ($list as $sf) {
        if ($sf === '.' || $sf === '..') {
            continue;
        }
        $full = $root . DIRECTORY_SEPARATOR . $sf;
        if (!is_file($full)) {
            continue;
        }
        $__mkIndex[strtolower($sf)] = $full;
        // stem map for sibling ext (optimizer .png → .webp)
        $stem = strtolower(pathinfo($sf, PATHINFO_FILENAME));
        if ($stem !== '' && !isset($__mkIndex['#stem:' . $stem])) {
            $__mkIndex['#stem:' . $stem] = $full;
        }
    }
}
foreach ($kit as &$__mkRow) {
    if (!is_array($__mkRow)) {
        continue;
    }
    $rawPath = trim((string)($__mkRow['file'] ?? $__mkRow['photo'] ?? $__mkRow['doc_file'] ?? ''));
    $fn = basename(str_replace('\\', '/', $rawPath));
    if ($rawPath === '' || preg_match('~^https?://~i', $rawPath) || str_starts_with($rawPath, 'data:')) {
        $src = (string)($__mkRow['file'] ?? $__mkRow['photo'] ?? $__mkRow['url'] ?? '');
        if ($src !== '' && empty($__mkRow['file_type'])) {
            $ext = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?: $src, PATHINFO_EXTENSION));
            if ($ext !== '') {
                $__mkRow['file_type'] = $ext;
            }
        }
        continue;
    }
    $abs = $__mkIndex[strtolower($fn)] ?? null;
    if ($abs === null) {
        $stem = strtolower(pathinfo($fn, PATHINFO_FILENAME));
        $abs = $__mkIndex['#stem:' . $stem] ?? null;
        if ($abs !== null) {
            $fn = basename($abs);
        }
    }
    if ($abs === null || $abs === '') {
        if (empty($__mkRow['file_type'])) {
            $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
            if ($ext !== '') {
                $__mkRow['file_type'] = $ext;
            }
        }
        $__mkRow['filename'] = $__mkRow['filename'] ?? $fn;
        continue;
    }
    $__mkRow['filename'] = $fn;
    $__mkRow['file'] = $fn;
    $__mkRow['photo'] = $fn;
    $__mkRow['preview_file'] = $fn;
    $__mkRow['exists'] = true;
    if (empty($__mkRow['bytes'])) {
        $__mkRow['bytes'] = (int)@filesize($abs);
    }
    if (empty($__mkRow['mtime'])) {
        $__mkRow['mtime'] = (int)@filemtime($abs);
    }
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if ($ext !== '') {
        $__mkRow['file_type'] = $ext;
    }
    // Only probe dimensions when missing (avoids getimagesize on every request)
    if ((empty($__mkRow['width']) || empty($__mkRow['height']))
        && in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp'], true)
        && function_exists('getimagesize')) {
        $isz = @getimagesize($abs);
        if (is_array($isz) && !empty($isz[0]) && !empty($isz[1])) {
            $__mkRow['width'] = (int)$isz[0];
            $__mkRow['height'] = (int)$isz[1];
            if (empty($__mkRow['mime']) && !empty($isz['mime'])) {
                $__mkRow['mime'] = (string)$isz['mime'];
            }
        }
    }
    if (empty($__mkRow['mime'])) {
        static $mimeMap = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
        ];
        if (isset($mimeMap[$ext])) {
            $__mkRow['mime'] = $mimeMap[$ext];
        }
    }
}
unset($__mkRow, $__mkIndex);

$categories = [
    'logo'     => ['label' => 'Logos (SVG)', 'icon' => 'fa-solid fa-bezier-curve', 'hint' => 'Vector logos — infinite scale'],
    'print'    => ['label' => 'Print (high-res)', 'icon' => 'fa-solid fa-print', 'hint' => '300 DPI+ photos for print'],
    'promo'    => ['label' => 'Promotional creatives', 'icon' => 'fa-solid fa-bullhorn', 'hint' => 'Campaign art'],
    'whatsapp' => ['label' => 'WhatsApp status', 'icon' => 'fa-brands fa-whatsapp', 'hint' => '9:16 status packs'],
    'social'   => ['label' => 'Social posts', 'icon' => 'fa-solid fa-share-nodes', 'hint' => 'Feed-ready'],
    'video'    => ['label' => 'Video / Reels', 'icon' => 'fa-solid fa-film', 'hint' => 'Short-form video'],
    'other'    => ['label' => 'Other assets', 'icon' => 'fa-solid fa-folder-open', 'hint' => 'Misc'],
];

/* Full library hub (SharePoint / OneDrive / external pack) */
$hubPath = (defined('DATA_PATH') ? DATA_PATH : '') . '/config/mediakit_hub.json';
$hub = ['library_url' => '', 'library_label' => 'Full media kit library'];
if (is_file($hubPath)) {
    $j = json_decode((string)@file_get_contents($hubPath), true);
    if (is_array($j)) {
        $hub = array_merge($hub, $j);
    }
}

$h = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$coName = '';
if (class_exists('AppDB')) {
    $co = AppDB::read('company') ?: [];
    if (isset($co[0]) && is_array($co[0])) {
        $co = $co[0];
    }
    $coName = trim((string)($co['name'] ?? ''));
    $coSocial = [];
    if (isset($co['social']) && is_array($co['social'])) {
        $coSocial = $co['social'];
    }
}
if (!isset($coSocial) || !is_array($coSocial)) {
    $coSocial = [];
}

$__socialPresets = [];
$__socMap = [
    'instagram' => ['label' => 'Instagram', 'icon' => 'fa-brands fa-instagram', 'platform' => 'instagram', 'color' => '#E1306C'],
    'facebook'  => ['label' => 'Facebook',  'icon' => 'fa-brands fa-facebook-f', 'platform' => 'facebook', 'color' => '#1877F2'],
    'linkedin'  => ['label' => 'LinkedIn',  'icon' => 'fa-brands fa-linkedin-in', 'platform' => 'linkedin', 'color' => '#0A66C2'],
    'twitter'   => ['label' => 'X',         'icon' => 'fa-brands fa-x-twitter', 'platform' => 'x', 'color' => '#0f1419'],
    'x'         => ['label' => 'X',         'icon' => 'fa-brands fa-x-twitter', 'platform' => 'x', 'color' => '#0f1419'],
    'youtube'   => ['label' => 'YouTube',   'icon' => 'fa-brands fa-youtube', 'platform' => 'youtube', 'color' => '#FF0000'],
];
if (!empty($coSocial) && is_array($coSocial)) {
    foreach ($__socMap as $key => $meta) {
        $val = trim((string)($coSocial[$key] ?? ''));
        if ($val === '') {
            continue;
        }
        // skip duplicate x/twitter
        if ($key === 'x' && !empty($coSocial['twitter'])) {
            continue;
        }
        $__socialPresets[] = [
            'key' => $key,
            'label' => $meta['label'],
            'icon' => $meta['icon'],
            'platform' => $meta['platform'],
            'url' => $val,
            'color' => $meta['color'],
        ];
    }
}
?>
<style>
/* ── Media Kit layout (token-based) ── */
.mk{color:var(--rc-ink);max-width:72rem}
.mk-head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.25rem}
.mk-eyebrow{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--rc-accent);margin:0}
.mk-h1{font-size:1.35rem;font-weight:800;letter-spacing:-.02em;margin:.15rem 0 0;color:var(--rc-ink);line-height:1.25}
.mk-lead{font-size:13px;color:var(--rc-ink-muted);margin:.4rem 0 0;max-width:36rem;line-height:1.45}
.mk-actions{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.mk-btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;height:2.25rem;padding:0 .9rem;border-radius:.65rem;font-size:12px;font-weight:800;border:1px solid var(--rc-border);background:var(--rc-card);color:var(--rc-ink);cursor:pointer;text-decoration:none;white-space:nowrap}
.mk-btn:hover{border-color:var(--rc-accent);color:var(--rc-accent)}
.mk-btn-pri{background:var(--rc-accent);color:#fff!important;border-color:var(--rc-accent)}
.mk-btn-pri:hover{filter:brightness(1.06);color:#fff!important}
.mk-btn-wa{background:#ecfdf5;color:#0f7a4a;border-color:#a7f3d0}
.mk-btn-mail{background:#eff6ff;color:#1e40af;border-color:#bfdbfe}
.mk-btn-danger{background:#fff1f2;color:#b91c1c;border-color:#fecaca}
.mk-btn:disabled{opacity:.5;cursor:not-allowed}

.mk-sec{border:1px solid var(--rc-border);border-radius:1rem;background:var(--rc-card);margin-bottom:1rem;overflow:hidden}
.mk-sec-hd{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;padding:.85rem 1rem;border-bottom:1px solid var(--rc-border);background:var(--rc-paper)}
.mk-sec-title{font-size:13px;font-weight:800;margin:0;color:var(--rc-ink);display:flex;align-items:center;gap:.45rem}
.mk-sec-title i{color:var(--rc-accent);font-size:12px}
.mk-sec-bd{padding:1rem}

.mk-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.65rem;margin-bottom:1.25rem}
.mk-step{border:1px solid var(--rc-border);border-radius:.85rem;padding:.75rem .85rem;background:var(--rc-card)}
.mk-step-n{font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--rc-accent)}
.mk-step-t{font-size:12px;font-weight:800;margin:.2rem 0 0;color:var(--rc-ink)}
.mk-step-d{font-size:11px;color:var(--rc-ink-muted);margin:.2rem 0 0;line-height:1.35}

.mk-hub-url{display:flex;align-items:center;gap:.4rem;min-width:0;margin-top:.5rem}
.mk-hub-url a{flex:1;min-width:0;font-size:11px;font-family:ui-monospace,monospace;color:var(--rc-accent);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mk-hub-url a:hover{text-decoration:underline}
.mk-desc{font-size:12px;line-height:1.45;color:var(--rc-ink-muted);margin:0;word-break:normal;overflow-wrap:break-word}

.mk-drop{border:2px dashed var(--rc-border);border-radius:.85rem;background:var(--rc-paper);padding:1.25rem;text-align:center;transition:border-color .15s,background .15s}
.mk-drop.is-drag,.mk-drop:hover{border-color:var(--rc-accent);background:color-mix(in srgb,var(--rc-accent) 6%,var(--rc-paper))}
.mk-queue{text-align:left;max-width:28rem;margin:.75rem auto 0;font-size:11px;color:var(--rc-ink-soft)}
.mk-queue ul{margin:.25rem 0 0;padding:0;list-style:none;max-height:6rem;overflow:auto}
.mk-queue li{display:flex;justify-content:space-between;gap:.5rem;padding:.15rem 0}
.mk-bar{height:.4rem;border-radius:999px;background:var(--rc-border);overflow:hidden;max-width:16rem;margin:.5rem auto 0}
.mk-bar>i{display:block;height:100%;background:var(--rc-accent);border-radius:999px;transition:width .15s}

.mk-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;padding:.75rem 1rem;border-bottom:1px solid var(--rc-border);background:var(--rc-paper);position:sticky;top:0;z-index:4}
.mk-search{height:2.15rem;flex:1;min-width:9rem;max-width:16rem;border:1px solid var(--rc-border);border-radius:.6rem;padding:0 .75rem;font-size:12px;background:var(--rc-card);color:var(--rc-ink)}
.mk-chips{display:flex;flex-wrap:wrap;gap:.35rem;padding:.65rem 1rem 0}
.mk-chip{display:inline-flex;align-items:center;gap:.3rem;height:1.85rem;padding:0 .65rem;border-radius:999px;font-size:11px;font-weight:700;border:1px solid var(--rc-border);background:var(--rc-card);color:var(--rc-ink-muted);cursor:pointer}
.mk-chip.is-on{background:color-mix(in srgb,var(--rc-accent) 12%,var(--rc-card));border-color:var(--rc-accent);color:var(--rc-accent)}

.mk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:.85rem;padding:1rem}
.mk-card{display:flex;flex-direction:column;border:1px solid var(--rc-border);border-radius:.9rem;background:var(--rc-card);overflow:hidden;transition:box-shadow .15s,transform .15s}
.mk-card:hover{box-shadow:0 8px 24px rgba(15,23,42,.08);transform:translateY(-1px)}

.mk-thumb{position:relative;aspect-ratio:1;background:linear-gradient(145deg,#c4a574 0%,#e8d5a3 45%,#f5e6c8 100%);display:flex;align-items:center;justify-content:center;overflow:hidden}
.mk-preview-img{max-width:92%;max-height:92%;width:auto;height:auto;object-fit:contain;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.12);display:block}
.mk-preview-embed,.mk-preview-video{width:92%;height:92%;border:0;border-radius:6px;background:#fff;object-fit:contain}
.mk-type-fallback{position:absolute;inset:0;display:none;align-items:center;justify-content:center;flex-direction:column;gap:.35rem}

.mk-type-icon{display:flex;flex-direction:column;align-items:center;gap:.35rem;color:var(--rc-ink);opacity:.85}
.mk-type-icon i{font-size:2rem}
.mk-type-icon span{font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.mk-attrs{display:flex;flex-direction:column;gap:2px;font-size:10px;line-height:1.35;margin:.35rem 0 .15rem;color:var(--rc-ink-muted)}
.mk-attr-pair{display:grid;grid-template-columns:5.5rem 1fr;gap:2px 8px}
.mk-attrs dt{font-weight:700;color:var(--rc-ink-soft,var(--rc-ink-muted));white-space:nowrap}
.mk-attrs dd{margin:0;word-break:break-all;color:var(--rc-ink)}
.mk-chips-inline{display:flex;flex-wrap:wrap;gap:4px;margin:.25rem 0 .35rem}
.mk-file-chip{display:inline-flex;align-items:center;gap:.3rem;font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;border:1px solid var(--rc-border);background:var(--rc-paper);color:var(--rc-ink);margin-right:4px;margin-bottom:4px}

.mk-thumb{
  aspect-ratio:16/10;position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;
  background:radial-gradient(ellipse 120% 80% at 50% 100%,#c4a574 0%,transparent 55%),
             linear-gradient(165deg,#f5e6c8 0%,#e8d4a8 38%,#d4b896 72%,#c9a882 100%);
}
.mk-thumb img{width:100%;height:100%;object-fit:contain;position:relative;z-index:1}
.mk-badge{position:absolute;left:.45rem;top:.45rem;z-index:2;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;padding:.2rem .4rem;border-radius:.35rem;background:rgba(255,252,245,.94);border:1px solid rgba(180,140,80,.35);color:#3d2e1a}
.mk-body{padding:.8rem;display:flex;flex-direction:column;gap:.3rem;flex:1}
.mk-name{font-size:13px;font-weight:800;margin:0;color:var(--rc-ink);line-height:1.3}
.mk-meta{font-size:10px;font-weight:700;color:var(--rc-ink-muted);text-transform:uppercase;letter-spacing:.03em}
.mk-cap{font-size:11px;color:var(--rc-ink-muted);margin:0;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.mk-cap strong{font-weight:800;color:var(--rc-ink)}
.mk-cap em{font-style:italic}
.mk-row{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:auto;padding-top:.35rem}
.mk-actions,.mk-actions button,.mk-btn{pointer-events:auto!important;cursor:pointer!important;position:relative;z-index:6}
.mk-preview-embed{pointer-events:none}
.mk-body{position:relative;z-index:3}
.mk-thumb{pointer-events:none}
.mk-thumb a,.mk-thumb button{pointer-events:auto}

.mk-empty{text-align:center;padding:2.5rem 1rem;margin:1rem;border:2px dashed var(--rc-border);border-radius:.9rem;background:var(--rc-paper)}

.mk-table{width:100%;border-collapse:collapse;font-size:12px}
.mk-table th{text-align:left;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--rc-ink-muted);padding:.5rem .85rem;border-bottom:1px solid var(--rc-border);background:var(--rc-paper)}
.mk-table td{padding:.55rem .85rem;border-bottom:1px solid var(--rc-border);color:var(--rc-ink-soft);vertical-align:top;line-height:1.4}
.mk-table td:first-child{font-weight:700;color:var(--rc-ink);white-space:nowrap}
.mk-toast{position:fixed;bottom:1.2rem;right:1.2rem;z-index:80;background:var(--rc-ink);color:#fff;font-size:12px;font-weight:700;padding:.6rem 1rem;border-radius:.75rem;box-shadow:0 8px 24px rgba(0,0,0,.2)}

@media (max-width:640px){
  .mk-h1{font-size:1.15rem}
  .mk-grid{grid-template-columns:1fr;padding:.75rem}
  .mk-sec-bd{padding:.75rem}
}
.mk-soc-bar{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.65rem}
.mk-soc-btn{display:inline-flex;align-items:center;gap:.35rem;height:2rem;padding:0 .7rem;border-radius:999px;font-size:11px;font-weight:800;border:1px solid var(--rc-border);background:var(--rc-card);color:var(--rc-ink);cursor:pointer}
.mk-soc-btn:hover{border-color:var(--rc-accent)}
.mk-soc-btn.is-on{border-color:var(--rc-accent);background:color-mix(in srgb,var(--rc-accent) 10%,var(--rc-card));color:var(--rc-accent)}
.mk-soc-btn:disabled{opacity:.4;cursor:not-allowed}
.mk-soc-empty{font-size:11px;color:var(--rc-ink-muted);margin-top:.5rem}

.mk-form-grid{display:grid;grid-template-columns:1fr;gap:.65rem;align-items:end}
@media (min-width:640px){
  .mk-form-grid{grid-template-columns:minmax(0,1fr) 9rem 8.5rem;gap:.5rem .65rem}
}
.mk-field{display:flex;flex-direction:column;gap:.3rem;min-width:0}
.mk-field>span{font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--rc-ink-muted)}
.mk-field .mk-input,.mk-field select.mk-input{
  height:2.35rem;width:100%;border:1px solid var(--rc-border);border-radius:.65rem;
  padding:0 .75rem;font-size:13px;background:var(--rc-card);color:var(--rc-ink);
  box-sizing:border-box;appearance:none;-webkit-appearance:none
}
.mk-field select.mk-input{
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23605E5C' d='M1 1l5 5 5-5'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:right .7rem center;padding-right:1.75rem
}
.mk-field .mk-input:focus{outline:2px solid color-mix(in srgb,var(--rc-accent) 45%,transparent);outline-offset:1px;border-color:var(--rc-accent)}
.mk-form-grid .mk-btn-fetch{height:2.35rem;width:100%;justify-content:center}
.mk-drop-controls{display:grid;grid-template-columns:1fr;gap:.5rem;max-width:22rem;margin:.85rem auto 0}
@media (min-width:480px){
  .mk-drop-controls{grid-template-columns:minmax(0,1fr) auto auto;align-items:center;max-width:28rem}
}
.mk-drop-controls select.mk-input{height:2.35rem;width:100%;border:1px solid var(--rc-border);border-radius:.65rem;padding:0 .75rem;font-size:13px;background:var(--rc-card);color:var(--rc-ink);box-sizing:border-box}
.mk-soc-bar{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.75rem;padding-top:.65rem;border-top:1px solid var(--rc-border)}
</style>

<div class="mk w-full" x-data="rcMediaKit()">

  <!-- 1. Header -->
  <header class="mk-head">
    <div class="min-w-0">
      <p class="mk-eyebrow">Documents · Media Kit</p>
      <h1 class="mk-h1"><?= $h($coName !== '' ? $coName . ' media kit' : 'Brand media kit') ?></h1>
      <p class="mk-lead">Logos, print files, promos and social creatives — sized for dealers and partners.</p>
      <p class="text-[11px] font-bold mt-2 m-0" style="color:var(--rc-ink-muted)">
        <span x-text="items.length"></span> asset<span x-show="items.length!==1">s</span>
        <span x-show="filtered.length!==items.length"> · <span x-text="filtered.length"></span> shown</span>
      </p>
    </div>
    <div class="mk-actions">
      <button type="button" class="mk-btn" @click="showGuides=!showGuides">
        <i class="fa-solid fa-ruler-combined"></i> Size guide
      </button>
      <?php if ($__isAd): ?>
      <button type="button" class="mk-btn-pri mk-btn" @click="addAsset()">
        <i class="fa-solid fa-plus"></i> Add asset
      </button>
      <?php endif; ?>
    </div>
  </header>

  <!-- How it works (compact) -->
  <div class="mk-steps">
    <div class="mk-step">
      <div class="mk-step-n">Step 1</div>
      <div class="mk-step-t">Share the full library</div>
      <div class="mk-step-d">One SharePoint / OneDrive link for the complete pack.</div>
    </div>
    <div class="mk-step">
      <div class="mk-step-n">Step 2</div>
      <div class="mk-step-t">Browse or upload assets</div>
      <div class="mk-step-d">Filter by type; download or share individually.</div>
    </div>
    <div class="mk-step">
      <div class="mk-step-n">Step 3</div>
      <div class="mk-step-t">Follow platform sizes</div>
      <div class="mk-step-d">Instagram, WhatsApp, LinkedIn and more.</div>
    </div>
  </div>

  <!-- 2. Full library share -->
  <section class="mk-sec">
    <div class="mk-sec-hd">
      <h2 class="mk-sec-title"><i class="fa-solid fa-folder-open"></i> Full library</h2>
      <div class="mk-actions" x-show="libraryUrl" x-cloak>
        <button type="button" class="mk-btn mk-btn-wa" @click="shareAllWa()"><i class="fa-brands fa-whatsapp"></i> WhatsApp</button>
        <button type="button" class="mk-btn" @click="shareAllSms()"><i class="fa-solid fa-comment-sms"></i> RCS</button>
        <button type="button" class="mk-btn mk-btn-mail" @click="shareAllEmail()"><i class="fa-solid fa-envelope"></i> Email</button>
        <button type="button" class="mk-btn" @click="copyLibrary()"><i class="fa-solid fa-link"></i> Copy</button>
        <a class="mk-btn" :href="libraryUrl" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a>
      </div>
    </div>
    <div class="mk-sec-bd">
      <h3 class="text-sm font-extrabold m-0" style="color:var(--rc-ink)" x-text="libraryLabel || 'Media kit library'"></h3>
      <p class="mk-desc mt-1">Share everything in one link (logos, print photos, creatives). Individual assets below can still be shared one-by-one.</p>
      <div class="mk-hub-url" x-show="libraryUrl" x-cloak>
        <a :href="libraryUrl" target="_blank" rel="noopener" :title="libraryUrl" x-text="shortUrl(libraryUrl)"></a>
      </div>
      <p class="mk-desc mt-2" x-show="!libraryUrl" x-cloak>
        No library link set.<?php if ($__isAd): ?> Paste a SharePoint or OneDrive folder URL below.<?php endif; ?>
      </p>
      <?php if ($__isAd): ?>
      <form class="mt-3" @submit.prevent="saveHub()">
        <div class="mk-form-grid" style="grid-template-columns:1fr">
          <label class="mk-field">
            <span>Library URL</span>
            <input type="url" class="mk-input" x-model="libraryUrlEdit" placeholder="https://…sharepoint.com/:f:/…">
          </label>
        </div>
        <div class="mk-form-grid mt-2">
          <label class="mk-field" style="grid-column:1 / -2">
            <span>Label</span>
            <input type="text" class="mk-input" x-model="libraryLabelEdit" placeholder="B&R Media Kit">
          </label>
          <div class="mk-field">
            <span class="opacity-0 pointer-events-none" aria-hidden="true">Save</span>
            <button type="submit" class="mk-btn mk-btn-pri mk-btn-fetch" :disabled="hubBusy"><span x-text="hubBusy?'Saving…':'Save link'"></span></button>
          </div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($__isAd): ?>
  <!-- 3. Add assets -->
  <section class="mk-sec">
    <div class="mk-sec-hd">
      <h2 class="mk-sec-title"><i class="fa-solid fa-cloud-arrow-up"></i> Add assets</h2>
    </div>
    <div class="mk-sec-bd space-y-4">
      <div class="mk-drop" :class="dragOver&&'is-drag'"
           @dragover.prevent="dragOver=true" @dragleave.prevent="dragOver=false" @drop.prevent="onDrop($event)">
        <input type="file" class="hidden" x-ref="mkFiles" multiple
               accept="image/*,.svg,.pdf,.mp4,.webm,.mov,.avif,image/svg+xml,image/avif,image/webp"
               @change="onPickFiles($event)">
        <p class="text-sm font-extrabold m-0" style="color:var(--rc-ink)">Drag &amp; drop files here</p>
        <p class="text-xs m-0 mt-1" style="color:var(--rc-ink-muted)">Up to 20 files · 12 MB each · 80 MB total</p>
        <div class="mk-drop-controls">
          <select class="mk-input" x-model="uploadCategory" aria-label="Asset category">
            <option value="promo">Promotional</option>
            <option value="logo">Logos (SVG)</option>
            <option value="print">Print (high-res)</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="social">Social</option>
            <option value="video">Video</option>
            <option value="other">Other</option>
          </select>
          <button type="button" class="mk-btn" style="height:2.35rem" @click="$refs.mkFiles.click()"><i class="fa-solid fa-folder-open"></i> Choose</button>
          <button type="button" class="mk-btn mk-btn-pri" style="height:2.35rem" @click="startUpload()" :disabled="uploading||!queue.length">
            <span x-text="uploading?('Uploading '+uploadProgress):('Upload'+(queue.length?(' '+queue.length):''))"></span>
          </button>
          <button type="button" class="mk-btn" style="height:2.35rem;grid-column:1/-1" x-show="queue.length&&!uploading" @click="queue=[];uploadMsg=''">Clear queue</button>
        </div>
        <div class="mk-queue" x-show="queue.length" x-cloak>
          <div class="font-bold" x-text="queue.length+' queued · '+queueSizeLabel()"></div>
          <ul>
            <template x-for="(f,i) in queue" :key="i">
              <li><span class="truncate" x-text="f.name"></span><span x-text="fmtBytes(f.size)"></span></li>
            </template>
          </ul>
        </div>
        <div x-show="uploading||uploadMsg" x-cloak>
          <div class="mk-bar" x-show="uploading"><i :style="'width:'+uploadPct+'%'"></i></div>
          <p class="text-[11px] font-semibold mt-1 m-0" style="color:var(--rc-ink-muted)" x-text="uploadMsg"></p>
        </div>
      </div>

      <div class="pt-2 border-t" style="border-color:var(--rc-border)">
        <p class="text-xs font-extrabold m-0" style="color:var(--rc-ink)">Import social posts (official)</p>
        <p class="mk-desc mt-0.5">Paste a single public post URL (oEmbed), sync connected official APIs, or add an RSS feed. Profile-page scraping is not used.</p>
        <div class="flex flex-wrap gap-2 mt-2 mb-2">
          <?php foreach ($mkConnStatus as $ck => $cst): ?>
          <span class="mk-soc-btn" style="cursor:default;opacity:<?= !empty($cst['connected']) ? '1' : '.55' ?>">
            <?= !empty($cst['connected']) ? '✓' : '○' ?>
            <?= htmlspecialchars((string)($cst['label'] ?? $ck), ENT_QUOTES, 'UTF-8') ?>
            <span class="text-[10px] font-semibold"><?= !empty($cst['connected']) ? 'Connected' : 'Not connected' ?></span>
          </span>
          <?php endforeach; ?>
        </div>
        <?php if ($__isAd): ?>
        <div class="flex flex-wrap gap-2 mb-2">
          <button type="button" class="mk-btn mk-btn-pri" @click="syncOfficial()" :disabled="socialBusy">
            <span x-text="socialBusy?'Syncing…':'Sync official APIs / RSS'"></span>
          </button>
        </div>
        <?php endif; ?>
        <div class="mk-form-grid mt-2">
          <label class="mk-field">
            <span>@handle or URL</span>
            <input type="text" class="mk-input" x-model="socialHandle" placeholder="@brand, profile URL, or post/reel URL" autocomplete="url">
          </label>
          <label class="mk-field">
            <span>Platform</span>
            <select class="mk-input" x-model="socialPlatform">
              <option value="auto">Auto</option>
              <option value="instagram">Instagram</option>
              <option value="facebook">Facebook</option>
              <option value="linkedin">LinkedIn</option>
              <option value="x">X / Twitter</option>
              <option value="youtube">YouTube</option>
            </select>
          </label>
          <div class="mk-field">
            <span class="opacity-0 pointer-events-none select-none" aria-hidden="true">Action</span>
            <button type="button" class="mk-btn mk-btn-pri mk-btn-fetch" @click="fetchSocial()" :disabled="socialBusy || !socialHandle">
              <span x-text="socialBusy?'Fetching…':'Fetch & save'"></span>
            </button>
          </div>
        </div>
        <!-- Saved company social links → fill input -->
        <div class="mk-soc-bar" aria-label="Saved social links">
          <?php if (!empty($__socialPresets)): ?>
            <?php foreach ($__socialPresets as $sp): ?>
            <button type="button" class="mk-soc-btn"
                    :class="socialHandle===<?= json_encode($sp['url'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_UNESCAPED_SLASHES) ?> && 'is-on'"
                    :disabled="socialBusy"
                    title="<?= htmlspecialchars($sp['url'], ENT_QUOTES, 'UTF-8') ?>"
                    @click="useSavedSocial(<?= htmlspecialchars(json_encode([
                        'url' => $sp['url'],
                        'platform' => $sp['platform'],
                        'label' => $sp['label'],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">
              <i class="<?= htmlspecialchars($sp['icon'], ENT_QUOTES, 'UTF-8') ?>" style="color:<?= htmlspecialchars($sp['color'], ENT_QUOTES, 'UTF-8') ?>"></i>
              <?= htmlspecialchars($sp['label'], ENT_QUOTES, 'UTF-8') ?>
            </button>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="mk-soc-empty m-0">No social links saved yet — add them under Organisation setup → Social channels. Buttons will appear here and fill this field.</p>
          <?php endif; ?>
        </div>
        <p class="text-[11px] mt-1 m-0" style="color:var(--rc-ink-muted)" x-show="socialMsg" x-text="socialMsg" x-cloak></p>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 4. Browse assets -->
  <section class="mk-sec">
    <div class="mk-sec-hd">
      <h2 class="mk-sec-title"><i class="fa-solid fa-images"></i> Asset library</h2>
    </div>
    <div class="mk-chips">
      <button type="button" class="mk-chip" :class="filter==='all'&&'is-on'" @click="filter='all'">All</button>
      <?php foreach ($categories as $cid => $c): ?>
      <button type="button" class="mk-chip" :class="filter==='<?= $h($cid) ?>'&&'is-on'" @click="filter='<?= $h($cid) ?>'">
        <i class="<?= $h($c['icon']) ?> text-[10px]"></i> <?= $h($c['label']) ?>
      </button>
      <?php endforeach; ?>
    </div>
    <div class="mk-toolbar">
      <input type="search" class="mk-search" x-model="q" placeholder="Search name, platform, caption…" aria-label="Search assets">
      <span class="text-[11px] font-semibold" style="color:var(--rc-ink-muted)" x-text="(_shown)+' shown'"></span>
      <?php if (!empty($__isAd)): ?>
      <button type="button" class="mk-btn mk-btn-pri ml-auto" onclick="event.preventDefault();window.rcMkAdd();"><i class="fa-solid fa-plus"></i> Add asset</button>
      <?php endif; ?>
    </div>

    <template x-if="filtered.length===0">
      <div class="mk-empty">
        <i class="fa-solid fa-photo-film text-3xl opacity-30 mb-2"></i>
        <p class="text-sm font-extrabold m-0" style="color:var(--rc-ink)">No assets in this view</p>
        <p class="mk-desc mt-1">Try another category or clear search.<?php if ($__isAd): ?> Or upload files above.<?php endif; ?></p>
      </div>
    </template>

    <div class="mk-grid" id="mk-asset-grid">
<?php if (count($kit) === 0): ?>
      <div class="mk-empty" style="grid-column:1/-1">
        <i class="fa-solid fa-photo-film text-3xl opacity-30 mb-2"></i>
        <p class="text-sm font-extrabold m-0" style="color:var(--rc-ink)">No assets yet</p>
        <p class="mk-desc mt-1"><?php if ($__isAd): ?>Upload files above to build the library.<?php else: ?>Ask an administrator to add brand assets.<?php endif; ?></p>
      </div>
<?php else: ?>
<?php foreach ($kit as $item):
    if (!is_array($item)) continue;
    $name = trim((string)($item['name'] ?? $item['title'] ?? 'Untitled'));
    $cat = strtolower(trim((string)($item['category'] ?? 'other')));
    $href = rc_mk_asset_href($item);
    $ext = rc_mk_ext($item);
    $icon = rc_mk_icon($ext);
    $bytesLbl = rc_mk_bytes($item['bytes'] ?? 0);
    $w = (int)($item['width'] ?? 0);
    $hgt = (int)($item['height'] ?? 0);
    $dim = ($w > 0 && $hgt > 0) ? ($w . ' × ' . $hgt . ' px') : (string)($item['size_hint'] ?? '');
    $mime = (string)($item['mime'] ?? '');
    $fn = (string)($item['filename'] ?? basename((string)($item['file'] ?? $item['photo'] ?? '')));
    $platform = trim((string)($item['platform'] ?? ''));
    $caption = (string)($item['caption'] ?? $item['notes'] ?? '');
    $mtime = (int)($item['mtime'] ?? 0);
    if ($mtime <= 0 && !empty($item['updated_at'])) {
        $mtime = (int)@strtotime((string)$item['updated_at']);
    }
    $mtimeLbl = $mtime > 0 ? date('d M Y, H:i', $mtime) : '';
    $srcLbl = trim((string)($item['source'] ?? $item['provider'] ?? ''));
    // Prefer resolved on-disk name (webp sibling) for the public URL
    if (!empty($item['preview_file'])) {
        $fn = (string)$item['preview_file'];
        $href = '/media_serve.php?f=' . rawurlencode($fn);
        $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION)) ?: $ext;
        $icon = rc_mk_icon($ext);
    } elseif (!empty($item['filename'])) {
        $fn = (string)$item['filename'];
        $href = '/media_serve.php?f=' . rawurlencode($fn);
    }
    $isImg = rc_mk_is_image($item) || in_array($ext, ['png','jpg','jpeg','gif','webp','avif','bmp','svg','svgz'], true);
    $isImg = $isImg && $href !== '';
    $isPdf = ($ext === 'pdf' && $href !== '');
    $isVideo = in_array($ext, ['mp4','webm','mov'], true) && $href !== '';
    $catLabel = $categories[$cat]['label'] ?? ucfirst($cat ?: 'Other');
    $searchBlob = strtolower($name . ' ' . $cat . ' ' . $platform . ' ' . $caption . ' ' . $fn . ' ' . $ext);
?>
      <article class="mk-card" data-mk-id-card="<?= htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-mk-cat="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" data-mk-q="<?= htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mk-thumb">
          <span class="mk-badge"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
<?php if ($isImg): ?>
          <img class="mk-preview-img" src="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
               data-src-name="<?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?>"
               alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
               loading="lazy" decoding="async"
               onerror="window.rcMkPreviewFail&&window.rcMkPreviewFail(this)">
          <div class="mk-type-icon mk-type-fallback" style="display:none">
            <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars(strtoupper($ext ?: 'FILE'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
<?php elseif ($isPdf): ?>
          <div class="mk-type-icon" style="display:flex">
            <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
            <span>PDF</span>
          </div>
<?php elseif ($isVideo): ?>
          <video class="mk-preview-video" src="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" muted playsinline preload="none"></video>
<?php else: ?>
          <div class="mk-type-icon" style="display:flex">
            <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars(strtoupper($ext ?: 'FILE'), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
<?php endif; ?>
        </div>
        <div class="mk-body">
          <h3 class="mk-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h3>
          <div class="mk-chips-inline">
<?php if ($ext !== ''): ?>
            <span class="mk-file-chip"><i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> text-[10px]"></i> <?= htmlspecialchars(strtoupper($ext), ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
<?php if ($bytesLbl !== ''): ?>
            <span class="mk-file-chip"><?= htmlspecialchars($bytesLbl, ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
<?php if ($dim !== ''): ?>
            <span class="mk-file-chip"><?= htmlspecialchars($dim, ENT_QUOTES, 'UTF-8') ?></span>
<?php endif; ?>
          </div>
          <dl class="mk-attrs">
<?php if ($fn !== ''): ?><div class="mk-attr-pair"><dt>File</dt><dd><?= htmlspecialchars($fn, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
<?php if ($ext !== ''): ?><div class="mk-attr-pair"><dt>Type</dt><dd><?= htmlspecialchars(strtoupper($ext), ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
<?php if ($mime !== ''): ?><div class="mk-attr-pair"><dt>MIME</dt><dd><?= htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
<?php if ($bytesLbl !== ''): ?><div class="mk-attr-pair"><dt>Size</dt><dd><?= htmlspecialchars($bytesLbl, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
<?php if ($dim !== ''): ?><div class="mk-attr-pair"><dt>Dimensions</dt><dd><?= htmlspecialchars($dim, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
<?php if ($catLabel !== ''): ?><div class="mk-attr-pair"><dt>Category</dt><dd><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
<?php if ($platform !== ''): ?><div class="mk-attr-pair"><dt>Platform</dt><dd><?= htmlspecialchars($platform, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
<?php if ($mtimeLbl !== ''): ?><div class="mk-attr-pair"><dt>Updated</dt><dd><?= htmlspecialchars($mtimeLbl, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
<?php if ($srcLbl !== ''): ?><div class="mk-attr-pair"><dt>Source</dt><dd><?= htmlspecialchars($srcLbl, ENT_QUOTES, 'UTF-8') ?></dd></div><?php endif; ?>
          </dl>
<?php if ($caption !== ''): ?>
          <p class="mk-cap"><?= nl2br(htmlspecialchars($caption, ENT_QUOTES, 'UTF-8')) ?></p>
<?php endif; ?>
          <div class="mk-row">
<?php if ($href !== ''): ?>
            <a class="mk-btn" style="height:1.85rem;padding:0 .55rem;font-size:10px" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a>
            <button type="button" class="mk-btn" style="height:1.85rem;padding:0 .55rem;font-size:10px" data-path="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" onclick="(function(b){var p=b.getAttribute('data-path')||'';var u=/^https?:/i.test(p)?p:(location.origin+p);if(navigator.clipboard)navigator.clipboard.writeText(u);})(this)"><i class="fa-solid fa-link"></i> Copy</button>
<?php endif; ?>
            <a class="mk-btn mk-btn-wa" style="height:1.85rem;padding:0 .55rem;font-size:10px" target="_blank" rel="noopener"
               href="https://wa.me/?text=<?= rawurlencode($name . ($caption !== '' ? "\n\n" . $caption : '') . ($href !== '' ? "\n\n" . $href : '')) ?>"><i class="fa-brands fa-whatsapp"></i> WA</a>
            <a class="mk-btn mk-btn-mail" style="height:1.85rem;padding:0 .55rem;font-size:10px"
               href="mailto:?subject=<?= rawurlencode($name) ?>&body=<?= rawurlencode($name . "\n\n" . $caption . "\n\n" . $href) ?>"><i class="fa-solid fa-envelope"></i> Email</a>
          </div>
<?php if (!empty($__isAd)):
    $mkId = (string)($item['id'] ?? '');
    $mkJson = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
          <div class="mk-row mk-actions border-t pt-2" style="border-color:var(--rc-border);position:relative;z-index:5;pointer-events:auto">
            <button type="button" class="mk-btn" style="height:1.85rem;padding:0 .55rem;font-size:10px;pointer-events:auto;cursor:pointer;position:relative;z-index:6"
              data-mk-id="<?= htmlspecialchars($mkId, ENT_QUOTES, 'UTF-8') ?>"
              onclick="event.preventDefault();event.stopPropagation();window.rcMkEdit(this);">
              <i class="fa-solid fa-pen"></i> Edit
            </button>
            <button type="button" class="mk-btn mk-btn-danger" style="height:1.85rem;padding:0 .55rem;font-size:10px;pointer-events:auto;cursor:pointer;position:relative;z-index:6"
              data-mk-id="<?= htmlspecialchars($mkId, ENT_QUOTES, 'UTF-8') ?>"
              onclick="event.preventDefault();event.stopPropagation();window.rcMkDelete(this.getAttribute('data-mk-id'));">
              <i class="fa-solid fa-trash"></i> Delete
            </button>
          </div>
<?php endif; ?>
        </div>
      </article>
<?php endforeach; ?>
<?php endif; ?>
    </div>

  <!-- 5. Platform guidelines -->
  <section class="mk-sec" x-show="showGuides" x-cloak id="mk-guidelines">
    <div class="mk-sec-hd">
      <h2 class="mk-sec-title"><i class="fa-solid fa-ruler-combined"></i> Platform size guide</h2>
      <button type="button" class="mk-btn" @click="showGuides=false">Hide</button>
    </div>
    <div class="mk-sec-bd space-y-3">
      <p class="mk-desc">Recommended dimensions, ratios and formats for consistent organic posting.</p>
      <?php foreach ($guidelines as $g): ?>
      <details class="border rounded-xl overflow-hidden" style="border-color:var(--rc-border)">
        <summary class="cursor-pointer list-none px-4 py-3 flex items-center gap-2 font-bold text-sm" style="color:var(--rc-ink);background:var(--rc-paper)">
          <i class="<?= $h($g['icon']) ?>" style="color:var(--rc-accent)"></i>
          <?= $h($g['name']) ?>
          <span class="ml-auto text-[10px] font-semibold" style="color:var(--rc-ink-muted)"><?= count($g['items']) ?> placements</span>
        </summary>
        <div class="overflow-x-auto">
          <table class="mk-table">
            <thead><tr><th>Placement</th><th>Size</th><th>Ratio</th><th>Orientation</th><th>Formats</th><th>Caption / notes</th></tr></thead>
            <tbody>
              <?php foreach ($g['items'] as $row): ?>
              <tr>
                <td><?= $h($row['label']) ?></td>
                <td class="font-mono text-[11px]"><?= $h($row['size']) ?></td>
                <td><?= $h($row['ratio']) ?></td>
                <td><?= $h($row['orient']) ?></td>
                <td><?= $h($row['formats']) ?></td>
                <td style="color:var(--rc-ink-muted)"><?= $h($row['caption']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="mk-toast" x-show="toast" x-text="toast" x-cloak></div>
</div>

<?php
$__mkSeedSlim = [];
foreach ($kit as $__row) {
    if (!is_array($__row)) {
        continue;
    }
    $__mkSeedSlim[] = [
        'id' => (string)($__row['id'] ?? ''),
        'name' => (string)($__row['name'] ?? $__row['title'] ?? ''),
        'title' => (string)($__row['title'] ?? ''),
        'category' => (string)($__row['category'] ?? 'promo'),
        'platform' => (string)($__row['platform'] ?? ''),
        'caption' => (string)($__row['caption'] ?? ''),
        'notes' => (string)($__row['notes'] ?? ''),
        'file' => (string)($__row['file'] ?? $__row['photo'] ?? ''),
        'photo' => (string)($__row['photo'] ?? $__row['file'] ?? ''),
        'file_type' => (string)($__row['file_type'] ?? ''),
        'width' => $__row['width'] ?? '',
        'height' => $__row['height'] ?? '',
        'bytes' => $__row['bytes'] ?? '',
    ];
}
?>
<script>
window.rcMediaUrl = window.rcMediaUrl || function (name) {
  if (!name) return '';
  var n = String(name).trim();
  if (/^https?:\/\//i.test(n) || n.indexOf('data:') === 0) return n;
  n = n.split('?')[0].replace(/^.*[\\/]/, '');
  if (!n) return '';
  return '/media_serve.php?f=' + encodeURIComponent(n);
};

window.rcMkPreviewFail = function (el) {
  if (!el) return;
  var step = parseInt(el.dataset.rcStep || '0', 10);
  var n = (el.getAttribute('data-src-name') || '').replace(/^.*[\\/]/, '');
  if (!n) {
    try { n = decodeURIComponent((el.src.split('f=')[1] || '').split('&')[0]); } catch (e) {}
  }
  var stem = n.replace(/\.[^.]+$/, '');
  el.dataset.rcStep = String(step + 1);
  var tries = [
    '/media_serve.php?f=' + encodeURIComponent(n),
    '/images/' + encodeURIComponent(n),
    '/media_serve.php?f=' + encodeURIComponent(stem + '.webp'),
    '/media_serve.php?f=' + encodeURIComponent(stem + '.jpg'),
    '/media_serve.php?f=' + encodeURIComponent(stem + '.png'),
    '/media_serve.php?f=' + encodeURIComponent(stem + '.jpeg'),
    '/images/' + encodeURIComponent(stem + '.webp')
  ];
  if (step < tries.length) { el.src = tries[step]; return; }
  el.style.display = 'none';
  var ic = el.parentNode && el.parentNode.querySelector('.mk-type-fallback, .mk-type-icon');
  if (ic) ic.style.display = 'flex';
};

window.rcMediaOnError = window.rcMediaOnError || function (el) {
  if (!el) return;
  var step = parseInt(el.dataset.rcStep || '0', 10);
  var n = (el.getAttribute('data-src-name') || '').replace(/^.*[\\/]/, '');
  if (!n) {
    try { n = decodeURIComponent((el.src.split('f=')[1] || '').split('&')[0]); } catch (e) {}
  }
  el.dataset.rcStep = String(step + 1);
  if (step === 0 && n) { el.src = '/media_serve.php?f=' + encodeURIComponent(n); return; }
  if (step === 1 && n) { el.src = '/images/' + encodeURIComponent(n); return; }
  if (step === 2 && n) { el.src = '/media_serve.php?f=' + encodeURIComponent(n.replace(/\.[^.]+$/, '') + '.webp'); return; }
  if (step === 3 && n) { el.src = '/media_serve.php?f=' + encodeURIComponent(n.replace(/\.[^.]+$/, '') + '.jpg'); return; }
  if (step === 4 && n) { el.src = '/media_serve.php?f=' + encodeURIComponent(n.replace(/\.[^.]+$/, '') + '.png'); return; }
  el.style.display = 'none';
};

window.rcMkEdit = function (btn) {
  try {
    if (!btn) return;
    var id = btn.getAttribute('data-mk-id') || '';
    var item = null;
    var seed = window.__RC_MK_SEED__;
    if (!Array.isArray(seed)) seed = [];
    if (id && seed.length) {
      item = seed.find(function (x) { return String(x && x.id) === String(id); }) || null;
    }
    if (!item) {
      var raw = btn.getAttribute('data-mk-item') || '';
      if (raw) {
        try { item = JSON.parse(raw); } catch (e1) {}
      }
    }
    if (!item && id) {
      // Minimal stub so editor still opens; user can re-upload file if needed
      item = { id: id, name: id, category: 'promo', file: '', photo: '' };
    }
    if (!item) { alert('Asset not found (id: ' + id + ')'); return; }
    if (typeof window.openModalEditor === 'function') {
      window.openModalEditor(true, 'mediakit', item);
    } else {
      alert('Editor not loaded — hard-refresh the page');
    }
  } catch (e) {
    console.error(e);
    alert('Could not open editor: ' + (e.message || e));
  }
};

window.rcMkDelete = async function (id) {
  id = String(id || '');
  if (!id) { alert('Missing asset id'); return; }
  if (!confirm('Delete this media kit asset permanently?')) return;
  var csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf)
    || (window.APP && window.APP.csrf)
    || (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').content)
    || '';
  try {
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('ns', 'mediakit');
    fd.append('id', id);
    if (csrf) fd.append('csrf_token', csrf);
    var r = await fetch((window.location.pathname || '/') + '', {
      method: 'POST',
      credentials: 'same-origin',
      headers: Object.assign({ 'Accept': 'application/json' }, csrf ? { 'X-CSRF-Token': csrf } : {}),
      body: fd
    });
    var text = await r.text();
    var j = {};
    try { j = JSON.parse(text); } catch (e) { throw new Error(text.slice(0, 200) || ('HTTP ' + r.status)); }
    if (j.status === 'success' || j.ok) {
      var card = document.querySelector('[data-mk-id-card="' + String(id).replace(/"/g, '') + '"]');
      if (card) card.remove();
      else location.reload();
    } else {
      alert(j.message || j.error || 'Delete failed');
    }
  } catch (e) {
    alert(String(e.message || e));
  }
};
window.rcMkAdd = function () {
  if (window.openModalEditor) {
    window.openModalEditor(false, 'mediakit', null);
  } else {
    alert('Editor not loaded — refresh the page');
  }
};

/* seed built above */
window.__RC_MK_SEED__ = <?= json_encode($__mkSeedSlim ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
function rcMediaKit() {
  const seed = window.__RC_MK_SEED__ || [];
  const cats = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
  function dedupe(list) {
    var seen = {};
    var out = [];
    (list || []).forEach(function (row) {
      if (!row || typeof row !== 'object') return;
      var id = String(row.id || '');
      var url = String(row.url || '').split('?')[0].replace(/\/$/, '').toLowerCase();
      var fp = String(row.content_fp || '') || [String(row.platform||'').toLowerCase(), String(row.name||row.title||'').toLowerCase(), String(row.caption||'').toLowerCase().slice(0,200), url].join('|');
      if (id && seen['id:'+id]) return;
      if (url && seen['url:'+url]) return;
      if (fp && seen['fp:'+fp]) return;
      if (id) seen['id:'+id] = 1;
      if (url) seen['url:'+url] = 1;
      if (fp) seen['fp:'+fp] = 1;
      out.push(row);
    });
    return out;
  }
  const hubUrl = <?= json_encode((string)($hub['library_url'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
  const hubLabel = <?= json_encode((string)($hub['library_label'] ?? 'Full media kit library'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
  return {
    items: dedupe(seed),
    filter: 'all',
    q: '',
    _shown: 0,
    init() {
      var self = this;
      this.$watch('filter', function () { self.applyDomFilter(); });
      this.$watch('q', function () { self.applyDomFilter(); });
      this.$nextTick(function () { self.applyDomFilter(); });
    },
    busyId: '',
    toast: '',
    _fail: {},
    libraryUrl: hubUrl,
    libraryLabel: hubLabel,
    libraryUrlEdit: hubUrl,
    libraryLabelEdit: hubLabel,
    hubBusy: false,
    showGuides: false,
    dragOver: false,
    uploading: false,
    uploadProgress: '',
    uploadPct: 0,
    uploadMsg: '',
    uploadCategory: 'promo',
    queue: [],
    socialHandle: '',
    socialPlatform: 'auto',
    socialPresets: <?= json_encode($__socialPresets ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
    socialBusy: false,
    socialMsg: '',
    get filtered() {
      var q = (this.q || '').toLowerCase().trim();
      return this.items.filter(function (it) {
        var cat = String(it.category || 'other').toLowerCase();
        if (this.filter !== 'all' && cat !== this.filter) return false;
        if (!q) return true;
        return [it.name, it.title, it.platform, it.caption, it.notes].join(' ').toLowerCase().indexOf(q) >= 0;
      }.bind(this));
    },
    catLabel: function (c) {
      var k = String(c || 'other').toLowerCase();
      return (cats[k] && cats[k].label) || k;
    },
    /** Prefer media_serve, then /images/, then absolute URL */

    fileName: function (item) {
      var f = String((item && (item.file || item.photo || item.url)) || '').trim();
      if (!f || /^https?:\/\//i.test(f) || f.indexOf('data:') === 0) return f;
      return f.split(/[/\\]/).pop();
    },
    hasFile: function (item) {
      return !!(item && (item.file || item.photo || item.url));
    },
    href: function (item) {
      var f = String((item && (item.file || item.photo || item.url)) || '').trim();
      if (!f) return '';
      if (/^https?:\/\//i.test(f) || f.indexOf('data:') === 0) return f;
      if (f.charAt(0) === '/' && f.indexOf('/media_serve') === -1 && f.indexOf('/images/') === 0) {
        return f;
      }
      var base = this.fileName(item);
      return (window.rcMediaUrl ? window.rcMediaUrl(base) : ('/media_serve.php?f=' + encodeURIComponent(base)));
    },
    
    extOf: function (item) {
      if (!item) return '';
      var ft = String(item.file_type || '').toLowerCase().replace(/^\./, '');
      if (ft) return ft;
      var f = String(item.filename || item.file || item.photo || item.url || '');
      var m = f.match(/\.([a-z0-9]{1,8})(?:\?|$)/i);
      return m ? m[1].toLowerCase() : '';
    },
    isImagePreview: function (item) {
      if (!item || item._previewFailed) return false;
      if (!this.hasFile(item)) return false;
      var e = this.extOf(item);
      if (['png','jpg','jpeg','gif','webp','avif','bmp','svg','svgz'].indexOf(e) >= 0) return true;
      var mime = String(item.mime || '').toLowerCase();
      return mime.indexOf('image/') === 0;
    },
    fileIcon: function (item) {
      var e = this.extOf(item);
      var map = {
        pdf: 'fa-solid fa-file-pdf', doc: 'fa-solid fa-file-word', docx: 'fa-solid fa-file-word',
        xls: 'fa-solid fa-file-excel', xlsx: 'fa-solid fa-file-excel',
        ppt: 'fa-solid fa-file-powerpoint', pptx: 'fa-solid fa-file-powerpoint',
        mp4: 'fa-solid fa-file-video', webm: 'fa-solid fa-file-video', mov: 'fa-solid fa-file-video',
        mp3: 'fa-solid fa-file-audio', wav: 'fa-solid fa-file-audio',
        zip: 'fa-solid fa-file-zipper', rar: 'fa-solid fa-file-zipper', '7z': 'fa-solid fa-file-zipper',
        svg: 'fa-solid fa-bezier-curve', svgz: 'fa-solid fa-bezier-curve',
        png: 'fa-solid fa-file-image', jpg: 'fa-solid fa-file-image', jpeg: 'fa-solid fa-file-image',
        webp: 'fa-solid fa-file-image', avif: 'fa-solid fa-file-image', gif: 'fa-solid fa-file-image',
        ico: 'fa-solid fa-file-image', txt: 'fa-solid fa-file-lines', csv: 'fa-solid fa-file-csv',
        json: 'fa-solid fa-file-code', html: 'fa-solid fa-file-code'
      };
      return map[e] || 'fa-solid fa-file';
    },
    formatBytes: function (b) {
      b = Number(b || 0);
      if (!b || b < 0) return '';
      if (b >= 1048576) return (Math.round(b / 104857.6) / 10) + ' MB';
      if (b >= 1024) return Math.round(b / 1024) + ' KB';
      return b + ' B';
    },
    dimLine: function (item) {
      if (!item) return '';
      if (item.width && item.height) return item.width + ' × ' + item.height + ' px';
      if (item.size_hint) return String(item.size_hint);
      return '';
    },
    formatMtime: function (ts) {
      ts = Number(ts || 0);
      if (!ts) return '';
      try {
        var d = new Date(ts * 1000);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
      } catch (e) { return ''; }
    },
    hasDetail: function (item) {
      return this.detailRows(item).some(function (r) { return !!r[1]; });
    },
    detailRows: function (item) {
      if (!item) return [];
      var rows = [
        ['File', item.filename || this.fileName(item) || ''],
        ['Type', (this.extOf(item) || '').toUpperCase()],
        ['MIME', item.mime || ''],
        ['Size', this.formatBytes(item.bytes)],
        ['Dimensions', this.dimLine(item)],
        ['Category', this.catLabel(item.category)],
        ['Platform', item.platform || ''],
        ['Updated', this.formatMtime(item.mtime || item.updated_at)],
        ['Source', item.source || item.provider || ''],
        ['ID', item.id || '']
      ];
      return rows.filter(function (r) { return !!r[1]; });
    },
    onPreviewError: function (ev, item) {
      if (item) item._previewFailed = true;
      if (typeof window.rcMediaOnError === 'function') window.rcMediaOnError(ev.target);
    },

    
    applyDomFilter: function () {
      var cat = String(this.filter || 'all').toLowerCase();
      var q = String(this.q || '').toLowerCase().trim();
      var n = 0;
      document.querySelectorAll('#mk-asset-grid .mk-card').forEach(function (el) {
        var c = (el.getAttribute('data-mk-cat') || '').toLowerCase();
        var blob = (el.getAttribute('data-mk-q') || '');
        var ok = (cat === 'all' || c === cat) && (!q || blob.indexOf(q) !== -1);
        el.style.display = ok ? '' : 'none';
        if (ok) n++;
      });
      this._shown = n;
    },

    attrLine: function (item) {
      if (!item) return '';
      var parts = [];
      var ft = String(item.file_type || '').toUpperCase();
      if (!ft && item.file) {
        var m = String(item.file).match(/\.([a-z0-9]+)$/i);
        if (m) ft = m[1].toUpperCase();
      }
      if (ft) parts.push(ft);
      if (item.width && item.height) parts.push(item.width + '×' + item.height);
      else if (item.size_hint) parts.push(item.size_hint);
      if (item.bytes) {
        var b = Number(item.bytes);
        if (b >= 1048576) parts.push((Math.round(b / 104857.6) / 10) + ' MB');
        else if (b >= 1024) parts.push(Math.round(b / 1024) + ' KB');
        else parts.push(b + ' B');
      }
      return parts.join(' · ');
    },
    /** WhatsApp-style: *bold* _italic_ ~strike~ */
    waFormat: function (raw) {
      var s = String(raw || '');
      if (!s) return '';
      s = s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      s = s.replace(/\*(.+?)\*/g, '<strong>$1</strong>');
      s = s.replace(/_(.+?)_/g, '<em>$1</em>');
      s = s.replace(/~(.+?)~/g, '<s>$1</s>');
      s = s.replace(/\n/g, '<br>');
      return s;
    },

    absUrl: function (item) {
      var link = this.href(item);
      if (!link) return location.href.split('#')[0];
      return link.indexOf('http') === 0 ? link : (location.origin + link);
    },
    shareText: function (item) {
      return [item.name || item.title || 'Media', item.caption || item.notes || '', this.absUrl(item)].filter(Boolean).join('\n\n');
    },
    flash: function (m) {
      var self = this;
      self.toast = m;
      clearTimeout(self._tt);
      self._tt = setTimeout(function () { self.toast = ''; }, 2000);
    },
    copyLink: function (item) {
      var u = this.absUrl(item);
      var self = this;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(u).then(function () { self.flash('Link copied'); }).catch(function () { prompt('Copy link', u); });
      } else prompt('Copy link', u);
    },
    shareWa: function (item) { window.open('https://wa.me/?text=' + encodeURIComponent(this.shareText(item)), '_blank', 'noopener'); },
    shareSms: function (item) { location.href = 'sms:?&body=' + encodeURIComponent(this.shareText(item)); },
    shareEmail: function (item) {
      location.href = 'mailto:?subject=' + encodeURIComponent(item.name || 'Media') + '&body=' + encodeURIComponent(this.shareText(item));
    },
    
    shortUrl: function (u) {
      u = String(u || '');
      if (!u) return '';
      try {
        var a = document.createElement('a');
        a.href = u;
        var host = a.hostname.replace(/^www\./, '');
        var path = (a.pathname || '').replace(/\/+$/, '');
        if (path.length > 28) path = path.slice(0, 14) + '…' + path.slice(-10);
        return host + (path && path !== '/' ? path : '') + (a.search ? '…' : '');
      } catch (e) {
        return u.length > 48 ? (u.slice(0, 36) + '…') : u;
      }
    },
    countCat: function (c) {
      var k = String(c || '').toLowerCase();
      return this.items.filter(function (it) { return String(it.category || '').toLowerCase() === k; }).length;
    },
    allShareText: function () {
      var label = this.libraryLabel || 'Media kit';
      var url = this.libraryUrl || '';
      return '*' + label + '*\n\nFull media library — logos (SVG), print photos & creatives:\n' + url;
    },
    shareAllWa: function () {
      if (!this.libraryUrl) { this.flash('Set library URL first'); return; }
      window.open('https://wa.me/?text=' + encodeURIComponent(this.allShareText()), '_blank', 'noopener');
    },
    shareAllSms: function () {
      if (!this.libraryUrl) { this.flash('Set library URL first'); return; }
      location.href = 'sms:?&body=' + encodeURIComponent(this.allShareText());
    },
    shareAllEmail: function () {
      if (!this.libraryUrl) { this.flash('Set library URL first'); return; }
      location.href = 'mailto:?subject=' + encodeURIComponent(this.libraryLabel || 'Media kit') + '&body=' + encodeURIComponent(this.allShareText());
    },
    copyLibrary: function () {
      var u = this.libraryUrl;
      var self = this;
      if (!u) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(u).then(function () { self.flash('Library link copied'); }).catch(function () { prompt('Copy', u); });
      } else prompt('Copy', u);
    },
    saveHub: async function () {
      this.hubBusy = true;
      var csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) || '';
      try {
        var fd = new FormData();
        fd.append('action', 'mediakit_hub_save');
        fd.append('library_url', this.libraryUrlEdit || '');
        fd.append('library_label', this.libraryLabelEdit || '');
        fd.append('csrf_token', csrf);
        var r = await fetch('index.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd });
        var t = await r.text();
        var j = {};
        try { j = JSON.parse(t); } catch (e) { throw new Error(t.slice(0, 160) || ('HTTP ' + r.status)); }
        if (j.status === 'success') {
          this.libraryUrl = this.libraryUrlEdit;
          this.libraryLabel = this.libraryLabelEdit || 'Full media kit library';
          this.flash('Library link saved');
        } else alert(j.message || 'Save failed');
      } catch (e) { alert(String(e.message || e)); }
      this.hubBusy = false;
    },
    
    onPickFiles: function (ev) {
      var files = ev.target && ev.target.files;
      if (files && files.length) this.enqueue(files);
      if (ev.target) ev.target.value = '';
    },
    onDrop: function (ev) {
      this.dragOver = false;
      var files = ev.dataTransfer && ev.dataTransfer.files;
      if (files && files.length) this.enqueue(files);
    },
    fmtBytes: function (b) {
      b = Number(b) || 0;
      if (b >= 1048576) return (Math.round(b / 104857.6) / 10) + ' MB';
      if (b >= 1024) return Math.round(b / 1024) + ' KB';
      return b + ' B';
    },
    queueSizeLabel: function () {
      var t = 0;
      (this.queue || []).forEach(function (f) { t += f.size || 0; });
      return this.fmtBytes(t) + ' / 80 MB';
    },
    enqueue: function (fileList) {
      var maxN = 20, maxEach = 12 * 1024 * 1024, maxTotal = 80 * 1024 * 1024;
      var q = this.queue.slice();
      var total = 0;
      q.forEach(function (f) { total += f.size || 0; });
      var msgs = [];
      Array.prototype.forEach.call(fileList, function (f) {
        if (q.length >= maxN) { msgs.push('Max 20 files'); return; }
        if (f.size > maxEach) { msgs.push(f.name + ': over 12 MB'); return; }
        if (total + f.size > maxTotal) { msgs.push('Batch would exceed 80 MB'); return; }
        q.push(f);
        total += f.size;
      });
      this.queue = q;
      this.uploadMsg = msgs.length ? msgs.join(' · ') : (q.length + ' file(s) ready — press Upload');
    },
    startUpload: async function () {
      if (!this.queue.length || this.uploading) return;
      await this.uploadFiles(this.queue);
    },
    uploadFiles: async function (fileList) {
      var files = Array.prototype.slice.call(fileList || []);
      if (!files.length) return;
      this.uploading = true;
      this.uploadPct = 5;
      this.uploadMsg = 'Starting…';
      var csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) || '';
      var fd = new FormData();
      fd.append('action', 'mediakit_bulk_upload');
      fd.append('category', this.uploadCategory || 'promo');
      fd.append('csrf_token', csrf);
      files.forEach(function (f) { fd.append('files[]', f, f.name); });
      this.uploadProgress = '0/' + files.length;
      try {
        var self = this;
        var r = await new Promise(function (resolve, reject) {
          var xhr = new XMLHttpRequest();
          xhr.open('POST', 'index.php');
          xhr.withCredentials = true;
          xhr.setRequestHeader('X-CSRF-Token', csrf);
          xhr.setRequestHeader('Accept', 'application/json');
          xhr.upload.onprogress = function (ev) {
            if (ev.lengthComputable) {
              self.uploadPct = Math.min(95, Math.round((ev.loaded / ev.total) * 100));
              self.uploadProgress = Math.min(files.length, Math.ceil((ev.loaded / ev.total) * files.length)) + '/' + files.length;
              self.uploadMsg = 'Uploading ' + self.uploadProgress + ' (' + self.uploadPct + '%)';
            }
          };
          xhr.onload = function () { resolve(xhr); };
          xhr.onerror = function () { reject(new Error('Network error')); };
          xhr.send(fd);
        });
        var t = r.responseText || '';
        var j = {};
        try { j = JSON.parse(t); } catch (e) { throw new Error(t.slice(0, 200) || ('HTTP ' + r.status)); }
        if (j.status === 'success') {
          var added = Array.isArray(j.items) ? j.items : [];
          this.items = dedupe(this.items.concat(added));
          this.uploadPct = 100;
          this.uploadProgress = (j.created || added.length) + '/' + files.length;
          this.uploadMsg = 'Uploaded ' + (j.created || added.length) + ' file(s)'
            + (j.errors && j.errors.length ? (' · ' + j.errors.length + ' skipped') : '');
          this.queue = [];
          this.flash(this.uploadMsg);
        } else {
          this.uploadMsg = j.message || 'Upload failed';
          alert(this.uploadMsg);
        }
      } catch (e) {
        this.uploadMsg = String(e.message || e);
        alert(this.uploadMsg);
      }
      this.uploading = false;
    },
    syncOfficial: async function () {
      this.socialBusy = true;
      this.socialMsg = 'Syncing official APIs / RSS…';
      var csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) || '';
      try {
        var fd = new FormData();
        fd.append('action', 'mediakit_sync_official');
        fd.append('csrf_token', csrf);
        var r = await fetch('index.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd });
        var txt = await r.text();
        var j = {};
        try { j = JSON.parse(txt); } catch (e) { throw new Error(txt.slice(0, 200) || ('HTTP ' + r.status)); }
        if (j.status === 'success') {
          this.socialMsg = j.message || 'Synced';
          this.flash(this.socialMsg);
          if (j.created || j.updated) location.reload();
        } else {
          this.socialMsg = j.message || 'Sync failed';
        }
      } catch (e) {
        this.socialMsg = String(e.message || e);
      }
      this.socialBusy = false;
    },
    useSavedSocial: function (spec) {
      if (!spec || !spec.url) return;
      this.socialHandle = String(spec.url);
      this.socialPlatform = String(spec.platform || 'auto');
      this.socialMsg = 'Loaded ' + (spec.label || 'link') + ' from Organisation setup — press Fetch & save';
    },
    fetchSocial: async function () {
      var h = String(this.socialHandle || '').trim();
      if (!h) { this.socialMsg = 'Enter a handle or URL'; return; }
      this.socialBusy = true;
      this.socialMsg = 'Fetching public profile & posts…';
      var csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) || '';
      try {
        var fd = new FormData();
        fd.append('action', 'social_fetch');
        fd.append('handle', h);
        fd.append('platform', this.socialPlatform || 'auto');
        fd.append('fetch_posts', '1');
        fd.append('save_image', '1');
        fd.append('csrf_token', csrf);
        var r = await fetch('index.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd });
        var t = await r.text();
        var j = {};
        try { j = JSON.parse(t); } catch (e) { throw new Error(t.slice(0, 220) || ('HTTP ' + r.status)); }
        if (j.status === 'success') {
          var add = Array.isArray(j.items) ? j.items : (j.item ? [j.item] : []);
          if (add.length) this.items = dedupe(this.items.concat(add));
          this.filter = 'social';
          this.socialMsg = j.message || 'Saved';
          if (typeof j.total_assets === 'number') { /* server is source of truth after merge */ }
          this.flash(this.socialMsg);
        } else {
          this.socialMsg = j.message || 'Fetch failed';
        }
      } catch (e) {
        this.socialMsg = String(e.message || e);
      }
      this.socialBusy = false;
    },

    addAsset: function () { if (window.openModalEditor) window.openModalEditor(false, 'mediakit'); },
    editAsset: function (item) { if (window.openModalEditor) window.openModalEditor(true, 'mediakit', item); },
    deleteAsset: async function (item) {
      var id = String(item.id || '');
      if (!id || !confirm('Delete this media kit asset?')) return;
      this.busyId = id;
      var csrf = (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) || '';
      try {
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('ns', 'mediakit');
        fd.append('id', id);
        fd.append('csrf_token', csrf);
        var r = await fetch('index.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: fd });
        var t = await r.text();
        var j = {};
        try { j = JSON.parse(t); } catch (e) { throw new Error(t.slice(0, 160) || ('HTTP ' + r.status)); }
        if (j.status === 'success') {
          this.items = this.items.filter(function (x) { return String(x.id) !== id; });
          this.flash('Deleted');
        } else alert(j.message || 'Delete failed');
      } catch (e) { alert(String(e.message || e)); }
      this.busyId = '';
    }
  };
}

</script>
