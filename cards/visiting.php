<?php
 
if (!defined('BASE_PATH')) exit;
if (!class_exists('SeoShare') && is_file(BASE_PATH . '/app/SeoShare.php')) {
    require_once BASE_PATH . '/app/SeoShare.php';
}

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
$vcardText = '';
if (!class_exists('VcardBuilder') && is_file(BASE_PATH . '/app/VcardBuilder.php')) {
    require_once BASE_PATH . '/app/VcardBuilder.php';
}
if (class_exists('VcardBuilder')) {
    $vp = is_array($p) ? $p : [];
    if ($role !== '-' ) $vp['title'] = $role;
    if ($dept !== '-') $vp['department_name'] = $dept;
    $vcardText = VcardBuilder::buildForQr($vp, is_array($c) ? $c : []);
}
$cardUrl = (string)($meta['url'] ?? '');
$vcfUrl = $cardUrl . (str_contains($cardUrl, '?') ? '&' : '?') . 'download=vcf';
 
$qrPayload = ($vcardText !== '' && strlen($vcardText) < 1200) ? $vcardText : $cardUrl;

$logo  = !empty($c['logo'])  ? '/images/' . basename($c['logo'])  : '';

 
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

$h = static function ($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$phone = trim((string)($p['phone'] ?? $p['mobile'] ?? ''));
$email = trim((string)($p['email'] ?? ''));
$website = trim((string)($c['website'] ?? $p['website'] ?? ''));
if ($website !== '' && !preg_match('~^https?://~i', $website)) $website = 'https://' . $website;
$address = trim((string)($p['address'] ?? $c['address'] ?? ''));
$city = trim((string)($p['city'] ?? $c['city'] ?? ''));
$addrLine = trim(implode(', ', array_filter([$address, $city])));
$accent = trim((string)($c['brand_color'] ?? $c['accent'] ?? '#E11D2E'));
if ($accent === '' || $accent[0] !== '#') $accent = '#E11D2E';
$logoBg = (string)($c['logo_bg'] ?? 'light');
$photoUrl = $photo;
if ($photoUrl === '' && !empty($p['photo_url'])) $photoUrl = (string)$p['photo_url'];
?>

<!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
if (class_exists('SeoShare')) {
    echo SeoShare::personCard('visiting', is_array($p)?$p:[], is_array($c)?$c:[]);
} else {
    echo '<title>' . $h($name) . ' — Business Card</title>';
}
?>
<link href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? (string)APP_VERSION : '1') ?>" rel="stylesheet">
<style>
:root {
  --ink: #1a1a1a;
  --muted: #6b7280;
  --line: #e5e7eb;
  --accent: <?= htmlspecialchars($accent, ENT_QUOTES) ?>;
  --card-w: 3.5in;
  --card-h: 2in;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: Inter, "Segoe UI", system-ui, sans-serif;
  background: #f3f4f6;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  gap: 1.25rem;
  -webkit-font-smoothing: antialiased;
}
.sheet {
  width: min(100%, 420px);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
/* Front */
.bc {
  width: 100%;
  aspect-ratio: 3.5 / 2;
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 12px 40px rgba(0,0,0,.12);
  position: relative;
  overflow: hidden;
  display: grid;
  grid-template-columns: 38% 1fr;
}
.bc-left {
  padding: 14px 12px 18px 14px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  border-right: 1px solid var(--line);
  background: #fafafa;
}
.bc-brand {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
}
.bc-logo-plate {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px 6px;
  border-radius: 6px;
  max-width: 100%;
  background: <?= $logoBg === 'dark' ? '#1B1A19' : '#fff' ?>;
  border: 1px solid var(--line);
}
.bc-logo-plate img {
  max-height: 28px;
  max-width: 110px;
  object-fit: contain;
  display: block;
}
.bc-co {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ink);
  line-height: 1.2;
}
.bc-qr {
  background: #fff;
  padding: 4px;
  border: 1px solid var(--line);
  border-radius: 4px;
  width: 72px;
  height: 72px;
  line-height: 0;
}
.bc-qr canvas, .bc-qr img { width: 64px !important; height: 64px !important; display: block; }
.bc-right {
  padding: 14px 16px 20px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  position: relative;
}
.bc-top {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}
.bc-photo {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  object-position: 50% 25%;
  border: 2px solid var(--line);
  flex-shrink: 0;
  background: #e5e7eb;
}
.bc-photo.ph {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  color: #9ca3af;
}
.bc-id { min-width: 0; flex: 1; }
.bc-name {
  font-size: 15px;
  font-weight: 800;
  color: var(--ink);
  line-height: 1.15;
  letter-spacing: -.01em;
}
.bc-role {
  font-size: 11px;
  font-weight: 600;
  color: var(--accent);
  margin-top: 2px;
  border-bottom: 1.5px solid var(--accent);
  display: inline-block;
  padding-bottom: 1px;
}
.bc-rows {
  margin-top: 4px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.bc-row {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  font-size: 10px;
  color: #374151;
  line-height: 1.35;
}
.bc-row i {
  color: var(--accent);
  width: 12px;
  text-align: center;
  margin-top: 1px;
  font-size: 10px;
}
.bc-row a { color: inherit; text-decoration: none; }
.bc-bar {
  position: absolute;
  left: 0; right: 0; bottom: 0;
  height: 8px;
  background: linear-gradient(90deg, #111 0 28%, var(--accent) 28% 100%);
}
/* Back */
.bc-back {
  width: 100%;
  aspect-ratio: 3.5 / 2;
  background: #fff;
  border-radius: 6px;
  box-shadow: 0 12px 40px rgba(0,0,0,.1);
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
}
.bc-back-side {
  position: absolute;
  top: 0; bottom: 0;
  width: 28px;
  background: var(--accent);
}
.bc-back-side.l { left: 0; clip-path: polygon(0 0, 100% 18%, 100% 82%, 0 100%); }
.bc-back-side.r { right: 0; clip-path: polygon(0 18%, 100% 0, 100% 100%, 0 82%); }
.bc-back .bc-logo-plate { padding: 8px 12px; }
.bc-back .bc-logo-plate img { max-height: 40px; max-width: 160px; }
.bc-back-co { font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--ink); }
.bc-back-tag { font-size: 10px; color: var(--muted); letter-spacing: .04em; }
.toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.tbtn {
  font: 600 12px/1 Inter, system-ui, sans-serif;
  padding: 9px 14px;
  border-radius: 999px;
  text-decoration: none;
  border: 1px solid var(--line);
  background: #fff;
  color: var(--ink);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}
.tbtn.primary { background: var(--accent); color: #fff; border-color: var(--accent); }
@media print {
  body { background: #fff; padding: 0; display: block; }
  .toolbar { display: none !important; }
  .sheet { width: 3.5in; gap: 0.15in; }
  .bc, .bc-back { box-shadow: none; border: 1px solid #ddd; border-radius: 0; width: 3.5in; height: 2in; aspect-ratio: auto; }
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
</style>
</head>
<body>
<div class="sheet">
  <!-- FRONT -->
  <article class="bc" aria-label="Business card front">
    <div class="bc-left">
      <div class="bc-brand">
        <?php if ($logo !== ''): ?>
        <span class="bc-logo-plate"><img src="<?= $h($logo) ?>" alt="<?= $h($companyName) ?>"></span>
        <?php else: ?>
        <div class="bc-co"><?= $h($companyName) ?></div>
        <?php endif; ?>
        <?php if ($logo !== ''): ?><div class="bc-co"><?= $h($companyName) ?></div><?php endif; ?>
      </div>
      <div class="bc-qr" id="qrcode-mini" title="Scan to save contact"></div>
    </div>
    <div class="bc-right">
      <div class="bc-top">
        <?php if ($photoUrl !== ''): ?>
          <img class="bc-photo" src="<?= $h($photoUrl) ?>" alt="" width="44" height="44"
               onerror="this.outerHTML='<div class=&quot;bc-photo ph&quot;><?= $h(mb_strtoupper(mb_substr($name,0,1))) ?></div>'">
        <?php else: ?>
          <div class="bc-photo ph" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr($name, 0, 1))) ?></div>
        <?php endif; ?>
        <div class="bc-id">
          <div class="bc-name"><?= $h($name) ?></div>
          <?php if ($role !== '' && $role !== '-'): ?><div class="bc-role"><?= $h($role) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="bc-rows">
        <?php if ($addrLine !== ''): ?>
        <div class="bc-row"><i class="fa-solid fa-location-dot"></i><span><?= $h($addrLine) ?></span></div>
        <?php endif; ?>
        <?php if ($phone !== ''): ?>
        <div class="bc-row"><i class="fa-solid fa-phone"></i><a href="tel:<?= $h(preg_replace('/\s+/', '', $phone)) ?>"><?= $h($phone) ?></a></div>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
        <div class="bc-row"><i class="fa-solid fa-envelope"></i><a href="mailto:<?= $h($email) ?>"><?= $h($email) ?></a></div>
        <?php endif; ?>
        <?php if ($website !== ''): ?>
        <div class="bc-row"><i class="fa-solid fa-globe"></i><a href="<?= $h($website) ?>" target="_blank" rel="noopener"><?= $h(preg_replace('~^https?://~i', '', $website)) ?></a></div>
        <?php endif; ?>
      </div>
      <div class="bc-bar" aria-hidden="true"></div>
    </div>
  </article>

  <!-- BACK -->
  <article class="bc-back" aria-label="Business card back">
    <div class="bc-back-side l" aria-hidden="true"></div>
    <div class="bc-back-side r" aria-hidden="true"></div>
    <?php if ($logo !== ''): ?>
    <span class="bc-logo-plate"><img src="<?= $h($logo) ?>" alt=""></span>
    <?php endif; ?>
    <div class="bc-back-co"><?= $h($companyName) ?></div>
    <?php if ($dept !== '' && $dept !== '-'): ?><div class="bc-back-tag"><?= $h($dept) ?></div><?php endif; ?>
  </article>
</div>

<div class="toolbar" data-html2canvas-ignore>
  <button type="button" onclick="window.print()" class="tbtn"><i class="fa-solid fa-print"></i> Print</button>
  <a class="tbtn" href="<?= $h($vcfUrl) ?>"><i class="fa-solid fa-address-card"></i> Save contact</a>
  <?php
    $__ph = preg_replace('/\D+/', '', $phone);
    $__wa = rawurlencode(trim($name . "\n" . ($role !== '-' ? $role . ' · ' : '') . $companyName . "\n"
      . ($phone !== '' ? "Tel: $phone\n" : '') . ($email !== '' ? "Email: $email\n" : '')
      . "Save contact: $vcfUrl\nCard: $cardUrl"));
  ?>
  <a class="tbtn primary" target="_blank" rel="noopener" href="https://wa.me/<?= $h($__ph) ?>?text=<?= $__wa ?>"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
  <a class="tbtn" href="<?= $h($cardUrl) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Digital card</a>
</div>
<script src="/assets/vendor/easy.qrcode.min.js"></script>
<script>
(function () {
  var el = document.getElementById('qrcode-mini');
  if (!el || typeof QRCode === 'undefined') return;
  new QRCode(el, {
    text: <?= json_encode($qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    width: 64, height: 64,
    colorDark: '#111111', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.H,
    quietZone: 2
  });
})();
</script>
</body>
</html>
