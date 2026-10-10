<?php
// cards/visiting.php — Version: 260916.14
if (!defined('BASE_PATH')) exit;
if (!class_exists('SeoShare') && is_file(BASE_PATH . '/app/SeoShare.php')) {
    require_once BASE_PATH . '/app/SeoShare.php';
}
/**
 * CHANGELOG v260907.17
 *  - Complete visual redesign, same private-members-card aesthetic as
 *    cards/id.php (obsidian + gold hairlines, refined serif name) — matches
 *    cards/business.php's gold-accent language so all three cards read as
 *    one family. Previous design used a generic diagonal blue gradient
 *    shape unrelated to the rest of the app's visual identity.
 *  - Personal + Company social links added, split per the same
 *    $personalSocials / $companySocials logic used in card_business.php and
 *    card_id.php — kept small (icon-only), matching a real print business
 *    card's information density.
 *  - QR generation and the print/"View Digital Profile" toolbar are
 *    unchanged in mechanism (already client-side EasyQRCodeJS) — only the
 *    visual design changed underneath them.
 */

$ctx = CardContext::get($_GET['slug'] ?? '');
if (!$ctx) { http_response_code(404); exit('<p style="font-family:sans-serif;padding:2rem;text-align:center">Profile not found.</p>'); }

$p = $ctx['person'];
$c = $ctx['company'];
$meta = $ctx['meta'];

$name = $p['name'] ?? 'Contact';
$role = $p['designation'] ?? $p['role'] ?? '-';
$dept = $p['department'] ?? $p['dept'] ?? '-';
$companyName = $c['name'] ?? 'Organization';

if (class_exists('AppDB')) {
    $designationKey = $p['designation_id'] ?? $p['designation_code'] ?? null;
    if ($designationKey && (!$role || $role === '-')) {
        $desigs = AppDB::read('designations') ?: [];
        foreach ($desigs as $d) {
            if (($d['id'] ?? null) == $designationKey || ($d['code'] ?? null) == $designationKey) {
                $role = $d['name'] ?? '-'; break;
            }
        }
    }
    $departmentKey = $p['department_id'] ?? $p['department_code'] ?? null;
    if ($departmentKey && (!$dept || $dept === '-')) {
        $depts = AppDB::read('departments') ?: [];
        foreach ($depts as $d) {
            if (($d['id'] ?? null) == $departmentKey || ($d['code'] ?? null) == $departmentKey) {
                $dept = $d['name'] ?? '-'; break;
            }
        }
    }
}

$photo = !empty($p['photo']) ? '/images/' . basename($p['photo']) : '';
$logo  = !empty($c['logo'])  ? '/images/' . basename($c['logo'])  : '';

// ── Personal / Company social split (matches card_business.php / id.php) ──
$_personSocial  = is_array($p['social'] ?? null) ? $p['social'] : [];
$_companySocial = is_array($c['social'] ?? null) ? $c['social'] : [];
$_validUrl = function ($v): string {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return '';
    if (!preg_match('~^[a-z][a-z0-9+.\-]*:~i', $v)) $v = 'https://' . ltrim($v, '/');
    return preg_match('~^https?://~i', $v) ? $v : '';
};
$_meta = [
    'linkedin'  => ['fa-brands fa-linkedin-in', 'LinkedIn'],
    'twitter'   => ['fa-brands fa-x-twitter',   'X'],
    'x'         => ['fa-brands fa-x-twitter',   'X'],
    'instagram' => ['fa-brands fa-instagram',   'Instagram'],
    'facebook'  => ['fa-brands fa-facebook-f',  'Facebook'],
    'youtube'   => ['fa-brands fa-youtube',     'YouTube'],
];
$personalSocials = []; $companySocials = [];
foreach (array_keys($_meta) as $_k) {
    $_pv = $_validUrl($_personSocial[$_k]  ?? ''); if ($_pv !== '') $personalSocials[$_k] = $_pv;
    $_cv = $_validUrl($_companySocial[$_k] ?? ''); if ($_cv !== '') $companySocials[$_k]  = $_cv;
}
if (isset($personalSocials['x']) && isset($personalSocials['twitter'])) unset($personalSocials['x']);
if (isset($companySocials['x'])  && isset($companySocials['twitter']))  unset($companySocials['x']);
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <?php
    if (class_exists('SeoShare')) {
        echo SeoShare::personCard('visiting', is_array($person ?? null) ? $person : [], is_array($company ?? null) ? $company : []);
    } else {
        echo '<title>Visiting Card — ' . htmlspecialchars((string)($name ?? ''), ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<meta name="description" content="' . htmlspecialchars('Visiting card for ' . (string)($name ?? 'team member') . ' — official contact details.', ENT_QUOTES, 'UTF-8') . '">';
    }
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
    <link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'">
    <noscript><link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" rel="stylesheet" crossorigin="anonymous" referrerpolicy="no-referrer"></noscript>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --obsidian:#0a0a0a; --charcoal:#141210; --gold:#c9a84c; --gold-light:#e8d5a3;
            --gold-dim:rgba(201,168,76,.32); --ivory:#f5f2ea; --ivory-dim:#a39c8e; --hairline:rgba(201,168,76,.2);
        }
        body{
            /* TYPOGRAPHY FIX: was missing 'Aptos' (this project's established primary corporate typeface, used everywhere else -- dashboard.php, business.php, the numerology report) and had NO Devanagari fallback at all. Any Hindi text in a name, department, or company field -- a genuine, reachable case in an Indian company directory, with no language-toggle logic needed to trigger it -- would have rendered in the browser's uncontrolled default Devanagari font, visually mismatched against the rest of the card. Aligned to the same stack used consistently across the rest of the app. */
            font-family:'Inter','Aptos','Noto Sans Devanagari',system-ui,-apple-system,'Segoe UI',sans-serif;
            background: radial-gradient(circle at 50% 20%, #1c1a17 0%, #050505 70%);
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            min-height:100vh;padding:28px 16px;-webkit-font-smoothing:antialiased;
        }

        .vcard{
            width:100%;max-width:500px;aspect-ratio:500/290;position:relative;
            background:var(--charcoal);border-radius:16px;overflow:hidden;
            display:flex;flex-direction:column;justify-content:space-between;
            box-shadow:0 35px 80px -20px rgba(0,0,0,.75),0 0 0 1px var(--hairline);
        }
        .vcard::before{
            content:'';position:absolute;inset:9px;border:1px solid var(--hairline);
            border-radius:11px;pointer-events:none;z-index:5;
        }
        /* A faint engraved monogram, low-opacity, purely textural — the
           kind of quiet detail that reads as "considered" rather than
           "decorated". Never competes with the actual content on top. */
        .vcard::after{
            content: attr(data-mono);
            position:absolute; right:-6px; bottom:-40px; font-family:'Instrument Serif',serif;
            font-size:180px; font-style:italic; color:rgba(201,168,76,.045); line-height:1; z-index:0;
            pointer-events:none; user-select:none;
        }

        .vc-top{position:relative;z-index:2;padding:20px 26px 0;display:flex;justify-content:space-between;align-items:flex-start;gap:14px}
        .logo-pill{display:inline-flex;align-items:center;background:rgba(255,255,255,.94);padding:6px 12px;border-radius:7px}
        .logo-pill img{max-height:22px;max-width:130px;object-fit:contain;display:block}
        .co-text{font-size:14px;font-weight:800;letter-spacing:.04em;color:var(--ivory)}
        .vc-org{text-align:right}
        .vc-org-name{font-size:9.5px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--gold)}
        .vc-addr{font-size:9px;color:var(--ivory-dim);margin-top:3px;max-width:170px;line-height:1.45}

        .vc-mid{position:relative;z-index:2;padding:14px 26px;display:flex;align-items:center;gap:16px}
        .vc-avatar{width:66px;height:66px;border-radius:50%;padding:2.5px;flex-shrink:0;
            background:conic-gradient(from 180deg,var(--gold),var(--gold-light),var(--gold))}
        /* Upward-biased crop — see cards/id.php for the reasoning. */
        .vc-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover;object-position:50% 25%;border:2.5px solid var(--charcoal);display:block;cursor:zoom-in}
        .vc-name{font-family:'Instrument Serif','Noto Sans Devanagari',serif;font-size:25px;font-weight:400;color:var(--ivory);line-height:1.1}
        .vc-role{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--gold);margin-top:4px}
        .vc-dept{display:inline-block;margin-top:5px;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
            color:var(--ivory-dim);border:1px solid var(--hairline);padding:2px 8px;border-radius:20px}

        .vc-bottom{position:relative;z-index:2;background:rgba(0,0,0,.28);border-top:1px solid var(--hairline);
            padding:12px 26px;display:flex;justify-content:space-between;align-items:center;gap:12px}
        .vc-contact{display:flex;flex-direction:column;gap:4px}
        .vc-contact div{display:flex;align-items:center;gap:7px;font-size:10.5px;font-weight:500;color:var(--ivory)}
        .vc-contact i{color:var(--gold);width:12px;font-size:10px}
        .vc-soc{display:flex;gap:6px}
        .vc-soc a{width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;
            background:rgba(255,255,255,.06);border:1px solid var(--hairline);color:var(--gold-light);font-size:9.5px;text-decoration:none}
        .vc-qr{background:#fff;padding:5px;border-radius:8px;line-height:0;flex-shrink:0}
        .vc-qr canvas,.vc-qr img{display:block;width:88px;height:88px}

        .toolbar{margin-top:26px;display:flex;gap:10px}
        .tbtn{font:700 12.5px 'Inter',sans-serif;padding:10px 20px;border-radius:999px;cursor:pointer;text-decoration:none;
            display:inline-flex;align-items:center;gap:8px;border:1px solid transparent}
        .tbtn.primary{background:var(--gold);color:#181510}
        .tbtn.ghost{background:rgba(255,255,255,.06);color:var(--ivory);border-color:var(--hairline)}

        @media print{
            body{background:#fff;display:block;margin:0;padding:0}
            .vcard{box-shadow:none;border-radius:0;margin:0 auto;max-width:500px}
            .toolbar{display:none}
        }
    </style>
</head>
<body>

    <div class="vcard" data-mono="<?= htmlspecialchars(mb_strtoupper(mb_substr($companyName, 0, 1))) ?>">
        <div class="vc-top">
            <?php if($logo): ?>
                <span class="logo-pill"><img src="<?= htmlspecialchars($logo) ?>" alt="Logo" width="104" height="20" decoding="async"></span>
            <?php else: ?>
                <span class="co-text"><?= htmlspecialchars(mb_strtoupper(mb_substr($companyName, 0, 3))) ?></span>
            <?php endif; ?>
            <div class="vc-org">
                <div class="vc-org-name"><?= htmlspecialchars($companyName) ?></div>
                <?php if(!empty($c['address'])): ?><div class="vc-addr"><?= htmlspecialchars($c['address']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="vc-mid">
            <?php if($photo): ?>
            <div class="vc-avatar">
                <img src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($name) ?>"
                     width="80" height="80" fetchpriority="high" decoding="async"
                     data-lightbox-src="<?= htmlspecialchars($photo) ?>" data-lightbox-name="<?= htmlspecialchars($name) ?>">
            </div>
            <?php endif; ?>
            <div>
                <h1 class="vc-name"><?= htmlspecialchars($name) ?></h1>
                <?php if($role !== '-'): ?><div class="vc-role"><?= htmlspecialchars($role) ?></div><?php endif; ?>
                <?php if($dept !== '-'): ?><span class="vc-dept"><?= htmlspecialchars($dept) ?></span><?php endif; ?>
            </div>
        </div>

        <div class="vc-bottom">
            <div class="vc-contact">
                <?php if(!empty($p['phone'])): ?><div><i class="fa-solid fa-phone"></i><?= htmlspecialchars($p['phone']) ?></div><?php endif; ?>
                <?php if(!empty($p['email'])): ?><div><i class="fa-solid fa-envelope"></i><?= htmlspecialchars($p['email']) ?></div><?php endif; ?>
                <?php if($personalSocials || $companySocials): ?>
                <div class="vc-soc">
                    <?php foreach($personalSocials as $k=>$u): [$ic,$lb]=$_meta[$k]; ?>
                    <a href="<?= htmlspecialchars($u) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $lb ?>" title="Personal &middot; <?= $lb ?>"><i class="<?= $ic ?>"></i></a>
                    <?php endforeach; ?>
                    <?php foreach($companySocials as $k=>$u): [$ic,$lb]=$_meta[$k]; ?>
                    <a href="<?= htmlspecialchars($u) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $lb ?>" title="<?= htmlspecialchars($companyName) ?> &middot; <?= $lb ?>" style="opacity:.65"><i class="<?= $ic ?>"></i></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="vc-qr" id="qrcode-mini"></div>
        </div>
    </div>

    <div class="toolbar" data-html2canvas-ignore>
        <button onclick="window.print()" class="tbtn ghost"><i class="fa-solid fa-print"></i> Print Card</button>
        <a href="<?= htmlspecialchars($meta['url']) ?>" class="tbtn primary"><i class="fa-solid fa-arrow-up-right-from-square"></i> View Digital Profile</a>
    </div>

<!-- EasyQRCodeJS 4.6.2 — replaces qrcodejs 1.0.0, abandoned since 2016
     with issues open from 2024–2026 and an unfixed code-length overflow.
     Same `new QRCode(el, options)` constructor, so this is a drop-in. -->
<script src="/assets/vendor/easy.qrcode.min.js"></script>
<script>
    // 88px, not the original 44px: this card is explicitly PRINTED
    // (@media print + a Print Card button) — a physically small, low-
    // module-density QR is fragile on screen and unreliable once printed
    // on real paper at typical scan distance.
    new QRCode(document.getElementById("qrcode-mini"), {
        text: <?= json_encode($meta['url']) ?>,
        width: 88, height: 88,
        colorDark : "#0a0a0a",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
    });
</script>
<?php require __DIR__ . '/partials/lightbox.php'; ?>
</body>
</html>
