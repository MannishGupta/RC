<?php 
// cards/id.php — Version: 260916.14
if (!defined('BASE_PATH')) exit;
if (!class_exists('SeoShare') && is_file(BASE_PATH . '/app/SeoShare.php')) {
    require_once BASE_PATH . '/app/SeoShare.php';
}
/**
 * CHANGELOG v260907.17
 *  - Complete visual redesign: private-members-card aesthetic (obsidian +
 *    gold hairlines, refined serif name), matching cards/business.php's
 *    existing gold-accent language rather than the previous generic corporate
 *    HR-badge look. Requested explicitly: "ultra modern, ultra HNI, ultra
 *    elite".
 *  - QR moved from an EXTERNAL third-party call (api.qrserver.com — every ID
 *    card view sent this person's card URL to a server outside this app) to
 *    client-side EasyQRCodeJS, matching card_qr.php / card_visiting.php /
 *    the vehicle-tag stickers. No network call, no data leak, one fewer
 *    external dependency.
 *  - Personal + Company social links added, split per the same
 *    $personalSocials / $companySocials logic used in card_business.php
 *    since 260907.15 — a company Instagram no longer has anywhere to render
 *    as "personal", and vice versa. Kept deliberately small (icon-only, no
 *    label rows) — this is a compact badge format, not the full card.
 */

$person = $person ?? [];
$company = $company ?? [];

$name = $person['name'] ?? 'Unknown Employee';
$role = $person['designation'] ?? $person['role'] ?? '-';
$dept = $person['department'] ?? $person['dept'] ?? '-';
$bloodGroup = $person['blood_group'] ?? 'N/A';
$phone = $person['phone'] ?? '-';
$empId = $person['code'] ?? (isset($person['id']) && strlen((string)$person['id']) < 12 ? $person['id'] : 'DEFAULT');
$slug = $person['slug'] ?? $person['id'] ?? '';

if (class_exists('AppDB')) {
    $designationKey = $person['designation_id'] ?? $person['designation_code'] ?? null;
    if ($designationKey && (!$role || $role === '-')) {
        $desigs = AppDB::read('designations') ?: [];
        foreach ($desigs as $d) {
            if (($d['id'] ?? null) == $designationKey || ($d['code'] ?? null) == $designationKey) {
                $role = $d['name'] ?? '-'; break;
            }
        }
    }
    $departmentKey = $person['department_id'] ?? $person['department_code'] ?? null;
    if ($departmentKey && (!$dept || $dept === '-')) {
        $depts = AppDB::read('departments') ?: [];
        foreach ($depts as $d) {
            if (($d['id'] ?? null) == $departmentKey || ($d['code'] ?? null) == $departmentKey) {
                $dept = $d['name'] ?? '-'; break;
            }
        }
    }
}

$photoFile = !empty($person['photo']) ? basename($person['photo']) : '';
$logoFile  = !empty($company['logo'])  ? basename($company['logo'])  : '';

$photo = $photoFile
    ? '/images/' . $photoFile
    : 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=1a1a1a&color=c9a84c&size=256';

$logo        = $logoFile ? '/images/' . $logoFile : '';
$companyName = $company['name'] ?? 'Organization';
$website     = $company['website'] ?? '';

$rawHost  = $_SERVER['HTTP_HOST'] ?? 'localhost';
$safeHost = preg_replace('/[^a-zA-Z0-9.:-]/', '', $rawHost);
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl  = $scheme . '://' . $safeHost;
$cardUrl  = $baseUrl . '/?card=business&slug=' . urlencode($slug);

// ── Personal / Company social split (matches card_business.php) ──────────
$_personSocial  = is_array($person['social']  ?? null) ? $person['social']  : [];
$_companySocial = is_array($company['social'] ?? null) ? $company['social'] : [];
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
        echo SeoShare::personCard('id', is_array($person ?? null) ? $person : [], is_array($company ?? null) ? $company : []);
    } else {
        echo '<title>' . htmlspecialchars((string)($name ?? 'ID'), ENT_QUOTES, 'UTF-8') . ' — ID Card</title>';
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
            --obsidian:#0a0a0a; --charcoal:#161513; --charcoal-2:#1e1c19;
            --gold:#c9a84c; --gold-light:#e8d5a3; --gold-dim:rgba(201,168,76,.35);
            --ivory:#f5f2ea; --ivory-dim:#a8a196; --hairline:rgba(201,168,76,.22);
        }
        html,body{height:100%}
        body{
            /* TYPOGRAPHY FIX: was missing 'Aptos' (this project's established primary corporate typeface, used everywhere else -- dashboard.php, business.php, the numerology report) and had NO Devanagari fallback at all. Any Hindi text in a name, department, or company field -- a genuine, reachable case in an Indian company directory, with no language-toggle logic needed to trigger it -- would have rendered in the browser's uncontrolled default Devanagari font, visually mismatched against the rest of the card. Aligned to the same stack used consistently across the rest of the app. */
            font-family:'Aptos','Inter','Noto Sans Devanagari',system-ui,-apple-system,'Segoe UI',sans-serif;
            background:
                radial-gradient(circle at 50% 0%, #1a1a1a 0%, #050505 65%);
            display:flex;justify-content:center;align-items:center;min-height:100vh;
            padding:28px 16px;-webkit-font-smoothing:antialiased;
        }

        .id-card{
            width:336px;background:var(--charcoal);border-radius:20px;
            overflow:hidden;position:relative;
            box-shadow:0 30px 70px -20px rgba(0,0,0,.7),0 0 0 1px var(--hairline);
        }
        /* Hairline corner accents — the "engraved" detail that separates a
           premium card from a flat rectangle. */
        .id-card::before{
            content:'';position:absolute;inset:10px;border:1px solid var(--hairline);
            border-radius:14px;pointer-events:none;z-index:5;
        }

        .id-header{
            background:linear-gradient(160deg,var(--obsidian),#000 70%);
            padding:22px 24px 46px;position:relative;
        }
        .id-header::after{
            content:'';position:absolute;left:24px;right:24px;bottom:0;height:1px;
            background:linear-gradient(90deg,transparent,var(--gold-dim) 20%,var(--gold-dim) 80%,transparent);
        }
        .id-top{display:flex;align-items:center;justify-content:space-between;position:relative;z-index:6}
        .id-eyebrow{font-size:9px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);}
        .logo-pill{display:inline-flex;align-items:center;background:rgba(255,255,255,.94);padding:5px 10px;border-radius:6px;}
        .logo-pill img{max-height:20px;max-width:104px;object-fit:contain;display:block}
        .company-text{font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--ivory-dim)}

        .avatar-wrap{
            position:absolute;left:50%;bottom:-48px;transform:translateX(-50%);
            width:112px;height:112px;border-radius:50%;padding:3px;z-index:10;
            background:conic-gradient(from 180deg,var(--gold),var(--gold-light),var(--gold));
            overflow:visible;box-sizing:border-box;
        }
        .avatar-wrap img{
            width:100%;height:100%;border-radius:50%;
            object-fit:cover;object-position:center 18%;
            border:3px solid var(--charcoal);display:block;cursor:zoom-in;
            background:#1e293b;
        }

        .id-body{padding:64px 26px 8px;text-align:center}
        .id-name{font-family:'Instrument Serif','Noto Sans Devanagari',serif;font-size:25px;font-weight:400;color:var(--ivory);letter-spacing:.01em;line-height:1.15}
        .id-role{margin-top:6px;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)}

        .id-grid{
            display:grid;grid-template-columns:1fr 1fr;gap:1px;
            margin:22px 24px 0;background:var(--hairline);border:1px solid var(--hairline);border-radius:12px;overflow:hidden;
        }
        .id-cell{background:var(--charcoal-2);padding:11px 14px}
        .id-cell.full{grid-column:1/-1}
        .id-k{font-size:8.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--ivory-dim)}
        .id-v{font-size:12.5px;font-weight:600;color:var(--ivory);margin-top:3px;font-family:'Inter',monospace;letter-spacing:.01em}
        .id-v.blood{color:#e8918e}
        .id-v.trunc{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

        /* Small, quiet social rails — icon-only, this is a compact badge. */
        .id-soc-block{margin:16px 24px 0}
        .id-soc-label{font-size:8px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--ivory-dim);opacity:.7;margin-bottom:6px;text-align:center}
        .id-soc{display:flex;justify-content:center;gap:7px}
        .id-soc a{width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;
            background:var(--charcoal-2);border:1px solid var(--hairline);color:var(--gold-light);font-size:11px;text-decoration:none}

        .id-footer{
            margin-top:20px;padding:16px 24px calc(18px + env(safe-area-inset-bottom));
            display:flex;align-items:center;justify-content:space-between;gap:12px;
            border-top:1px solid var(--hairline);
        }
        .id-org{font-size:10.5px;font-weight:700;letter-spacing:.05em;color:var(--ivory)}
        .id-web{font-size:9px;color:var(--ivory-dim);margin-top:2px}
        .id-qr{background:#fff;padding:6px;border-radius:8px;line-height:0;flex-shrink:0}
        .id-qr canvas,.id-qr img{display:block;width:64px;height:64px}

        @media (max-width:380px){ .id-card{width:100%;max-width:336px} }
    </style>
</head>
<body>

    <div class="id-card">
        <div class="id-header">
            <div class="id-top">
                <span class="id-eyebrow">Access&nbsp;ID</span>
                <?php if($logo): ?>
                    <span class="logo-pill"><img src="<?= htmlspecialchars($logo) ?>" alt="Logo" width="104" height="20" decoding="async"></span>
                <?php else: ?>
                    <span class="company-text"><?= htmlspecialchars($companyName) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="avatar-wrap">
            <img src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($name) ?>"
                 width="120" height="120" fetchpriority="high" decoding="async"
                 data-lightbox-src="<?= htmlspecialchars($photo) ?>"
                 data-lightbox-name="<?= htmlspecialchars($name) ?>">
        </div>

        <div class="id-body">
            <h1 class="id-name"><?= htmlspecialchars($name) ?></h1>
            <?php if($role !== '-'): ?><div class="id-role"><?= htmlspecialchars($role) ?></div><?php endif; ?>
        </div>

        <div class="id-grid">
            <div class="id-cell"><div class="id-k">Employee ID</div><div class="id-v"><?= htmlspecialchars((string)$empId) ?></div></div>
            <div class="id-cell"><div class="id-k">Blood Group</div><div class="id-v blood"><?= htmlspecialchars($bloodGroup) ?></div></div>
            <div class="id-cell full"><div class="id-k">Department</div><div class="id-v trunc" title="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept !== '-' ? $dept : 'N/A') ?></div></div>
            <div class="id-cell full"><div class="id-k">Contact</div><div class="id-v trunc"><?= htmlspecialchars($phone) ?></div></div>
        </div>

        <?php if($personalSocials): ?>
        <div class="id-soc-block">
            <div class="id-soc-label">Personal</div>
            <div class="id-soc">
                <?php foreach($personalSocials as $k=>$u): [$ic,$lb]=$_meta[$k]; ?>
                <a href="<?= htmlspecialchars($u) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $lb ?>" title="<?= $lb ?>"><i class="<?= $ic ?>"></i></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($companySocials): ?>
        <div class="id-soc-block">
            <div class="id-soc-label"><?= htmlspecialchars($companyName) ?></div>
            <div class="id-soc">
                <?php foreach($companySocials as $k=>$u): [$ic,$lb]=$_meta[$k]; ?>
                <a href="<?= htmlspecialchars($u) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $lb ?>" title="<?= $lb ?>"><i class="<?= $ic ?>"></i></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="id-footer">
            <div>
                <div class="id-org"><?= htmlspecialchars($companyName) ?></div>
                <?php if($website): ?><div class="id-web"><?= htmlspecialchars(str_replace(['http://','https://'], '', $website)) ?></div><?php endif; ?>
            </div>
            <div class="id-qr" id="idQr"></div>
        </div>
    </div>

<!-- EasyQRCodeJS — client-side, replaces the previous call to an external
     QR image API that sent this card's URL to a third-party server on
     every single view. -->
<script src="/assets/vendor/easy.qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('idQr'), {
    text: <?= json_encode($cardUrl) ?>,
    // 64px physical size, not the original 46px: this URL needs roughly
    // a Version-4 QR (33x33 modules), and much below this, module size
    // drops under ~1.3px — fragile for a phone camera to resolve reliably.
    width: 64, height: 64,
    colorDark: '#0a0a0a', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
});
</script>
<?php require __DIR__ . '/partials/lightbox.php'; ?>
</body>
</html>
