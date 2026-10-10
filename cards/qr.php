<?php
// qr.php — Version: 260921.02
// Zero Tailwind Play CDN. Self-contained CSS. EasyQR deferred with safe init.
// 🔒 json_encode for JS URL (prevents XSS via malformed URLs)
if (!defined('BASE_PATH')) exit;
if (!class_exists('SeoShare') && is_file(BASE_PATH . '/app/SeoShare.php')) {
    require_once BASE_PATH . '/app/SeoShare.php';
}

$ctx = CardContext::get($_GET['slug'] ?? '');
if (!$ctx) {
    http_response_code(404);
    exit('<p style="font-family:sans-serif;padding:2rem;text-align:center">Profile not found.</p>');
}

$p = $ctx['person'];
$c = $ctx['company'];
$url = $ctx['meta']['url'];

$h = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$name = (string)($p['name'] ?? '');
$companyName = (string)($c['name'] ?? '');
$photoUrl = (string)($p['photo_url'] ?? '');
$logoUrl = (string)($c['logo_url'] ?? '');
$coverUrl = (string)($c['cover_url'] ?? '');
$initial = strtoupper(substr($name !== '' ? $name : 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f1f5f9">
    <!-- robots via SeoShare -->
    <?php
    if (class_exists('SeoShare')) {
        $pMeta = is_array($p ?? null) ? $p : (is_array($person ?? null) ? $person : []);
        $cMeta = is_array($c ?? null) ? $c : (is_array($company ?? null) ? $company : []);
        echo SeoShare::personCard('qr', $pMeta, $cMeta);
    } else {
        echo '<title>QR Code — ' . $h($name) . '</title>';
        echo '<meta name="description" content="' . $h('QR contact card for ' . $name . ' — scan to save contact details.') . '">';
    }
    ?>
    <!-- EasyQRCodeJS 4.6.2 — deferred; init waits until constructor exists -->
    <script defer src="/assets/vendor/easy.qrcode.min.js"></script>
    <link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" rel="stylesheet"></noscript>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
            background:#f1f5f9;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:1rem;
            -webkit-font-smoothing:antialiased;
        }
        .card{
            width:100%;
            max-width:24rem;
            background:#fff;
            border-radius:1.5rem;
            overflow:hidden;
            box-shadow:0 25px 50px -12px rgba(0,0,0,.25);
            position:relative;
        }
        .cover{
            height:10rem;
            background:#0f172a;
            position:relative;
            z-index:0;
        }
        .cover img{
            width:100%;height:100%;
            object-fit:cover;
            opacity:.6;
            display:block;
        }
        .cover-fallback{
            width:100%;height:100%;
            background:linear-gradient(to right,#1e293b,#0f172a);
        }
        .logo{
            position:absolute;top:1.5rem;right:1.5rem;
            height:2.5rem;max-width:7rem;
            object-fit:contain;
            z-index:20;
            background:rgba(255,255,255,.1);
            border-radius:.25rem;
            padding:.25rem;
            backdrop-filter:blur(4px);
        }
        .body{padding:0 1.5rem 2rem;position:relative}
        .qr-panel{
            margin-top:-60px;
            position:relative;
            z-index:10;
            background:#fff;
            border-radius:1.5rem;
            padding:2rem;
            box-shadow:0 20px 25px -5px rgba(0,0,0,.1),0 10px 10px -5px rgba(0,0,0,.04);
            text-align:center;
        }
        .avatar-row{
            display:flex;justify-content:center;
            margin-top:-4rem;margin-bottom:1rem;
        }
        .avatar,.avatar-fallback{
            width:5rem;height:5rem;
            border-radius:9999px;
            border:4px solid #fff;
            box-shadow:0 4px 6px -1px rgba(0,0,0,.1);
            object-fit:cover;
            background:#fff;
            display:block;
        }
        .avatar-fallback{
            background:#f1f5f9;
            display:flex;align-items:center;justify-content:center;
            font-size:1.25rem;font-weight:700;color:#94a3b8;
        }
        h2{font-size:1.25rem;font-weight:700;color:#1e293b;line-height:1.3}
        .sub{font-size:.875rem;color:#64748b;margin-bottom:1.5rem}
        #qrcode{display:flex;justify-content:center;margin-bottom:1rem}
        .hint{font-size:.75rem;color:#94a3b8}
        .actions{margin-top:1.5rem;text-align:center}
        .btn{
            display:inline-block;
            background:#2563eb;color:#fff;
            font-weight:700;font-size:.9rem;
            padding:.75rem 2rem;
            border-radius:9999px;
            text-decoration:none;
            box-shadow:0 10px 15px -3px rgba(37,99,235,.35);
            transition:background .15s,transform .1s;
        }
        .btn:hover{background:#1d4ed8}
        .btn:active{transform:scale(.95)}
        .foot{
            margin-top:2rem;text-align:center;
            border-top:1px solid #f1f5f9;padding-top:1rem;
        }
        .foot p{
            font-size:10px;color:#94a3b8;
            text-transform:uppercase;letter-spacing:.1em;
        }
    </style>
</head>
<body>

    <div class="card">
        <div class="cover">
            <?php if ($coverUrl !== ''): ?>
                <img src="<?= $h($coverUrl) ?>" alt="" width="384" height="160" decoding="async">
            <?php else: ?>
                <div class="cover-fallback"></div>
            <?php endif; ?>
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= $h($logoUrl) ?>" class="logo" alt="" width="112" height="40" decoding="async">
            <?php endif; ?>
        </div>

        <div class="body">
            <div class="qr-panel">
                <div class="avatar-row">
                    <?php if ($photoUrl !== ''): ?>
                        <img src="<?= $h($photoUrl) ?>" class="avatar" alt="<?= $h($name) ?>"
                             width="80" height="80" fetchpriority="high" decoding="async"
                             data-lightbox-src="<?= $h($photoUrl) ?>"
                             data-lightbox-name="<?= $h($name) ?>">
                    <?php else: ?>
                        <div class="avatar-fallback"><?= $h($initial) ?></div>
                    <?php endif; ?>
                </div>

                <h2><?= $h($name) ?></h2>
                <p class="sub"><?= $h($companyName) ?></p>

                <div id="qrcode"></div>
                <p class="hint">Scan to connect instantly</p>
            </div>

            <div class="actions">
                <a class="btn" href="<?= $h((string)$url) ?>">View Profile</a>
            </div>

            <div class="foot">
                <p>Powered by Arthsathi Limited</p>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var payload = <?= json_encode((string)$url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        function tryInit(attempts) {
            attempts = attempts || 0;
            var Ctor = window.QRCode || window.EasyQRCode;
            if (!Ctor) {
                if (attempts < 100) return setTimeout(function () { tryInit(attempts + 1); }, 30);
                return;
            }
            var el = document.getElementById('qrcode');
            if (!el) return;
            try {
                new Ctor(el, {
                    text: payload,
                    width: 180,
                    height: 180,
                    colorDark: '#1e293b',
                    colorLight: '#ffffff',
                    correctLevel: (Ctor.CorrectLevel && Ctor.CorrectLevel.H) ? Ctor.CorrectLevel.H : 2
                });
            } catch (e) {}
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { tryInit(0); });
        } else {
            tryInit(0);
        }
    })();
    </script>
<?php
$lightbox = __DIR__ . '/partials/lightbox.php';
if (is_readable($lightbox)) require $lightbox;
?>
</body>
</html>
