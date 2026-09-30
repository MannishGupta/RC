<?php
/**
 * tools/print_directory.php — Visiting-card directory print (light theme)
 * Version: 260921.59
 */
declare(strict_types=1);

$base = dirname(__DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}

require_once $base . '/app/bootstrap.php';
if (session_status() === PHP_SESSION_NONE && class_exists('AppAuth')) {
    AppAuth::initSession();
}

$__u = (string)($_SESSION['user'] ?? '');
if ($__u === '' || !in_array($__u, ['admin', 'super_admin', 'public', 'crm'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Access denied. Sign in via the Resource Centre first.';
    exit;
}


$team = class_exists('AppDB') ? (AppDB::read('team') ?: []) : [];
if (!is_array($team)) {
    $team = [];
}
$desigs = class_exists('AppDB') ? (AppDB::read('designations') ?: []) : [];
$depts  = class_exists('AppDB') ? (AppDB::read('departments') ?: []) : [];
$locs   = class_exists('AppDB') ? (AppDB::read('locations') ?: []) : [];
$company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$compName = (string)($company['name'] ?? 'Organisation');

$desigMap = [];
foreach ($desigs as $d) {
    if (!is_array($d)) continue;
    $id = (string)($d['id'] ?? '');
    if ($id !== '') $desigMap[$id] = $d;
}
$deptMap = [];
foreach ($depts as $d) {
    if (!is_array($d)) continue;
    $id = (string)($d['id'] ?? '');
    if ($id !== '') $deptMap[$id] = $d;
}
$locMap = [];
foreach ($locs as $d) {
    if (!is_array($d)) continue;
    $id = (string)($d['id'] ?? '');
    if ($id !== '') $locMap[$id] = $d;
}

$rankOf = static function (array $m) use ($desigMap): int {
    $did = (string)($m['designation_id'] ?? '');
    if ($did !== '' && isset($desigMap[$did]['rank'])) {
        return (int)$desigMap[$did]['rank'];
    }
    return 9999;
};

usort($team, static function ($a, $b) use ($rankOf) {
    if (!is_array($a) || !is_array($b)) return 0;
    $ra = $rankOf($a); $rb = $rankOf($b);
    if ($ra !== $rb) return $ra <=> $rb;
    return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
});

$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$now = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d M Y, g:i a') . ' IST';
$origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$photoUrl = static function (array $m) use ($origin): string {
    $photo = trim((string)($m['photo'] ?? ''));
    if ($photo === '') return '';
    if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) return $photo;
    return rtrim($origin, '/') . '/images/' . rawurlencode(basename($photo));
};
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Directory — <?= $h($compName) ?></title>
<style>
  :root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --accent:#1d4ed8; --soft:#f8fafc; }
  * { box-sizing: border-box; }
  body {
    margin: 0; padding: 16px 18px 40px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    color: var(--ink); background: #fff; font-size: 10pt; line-height: 1.4;
  }
  .toolbar { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
  .toolbar button {
    border: 1px solid var(--line); background: var(--soft); color: var(--ink);
    border-radius: 8px; padding: 8px 14px; font-weight: 700; font-size: 12px; cursor: pointer;
  }
  .toolbar button.primary { background: var(--accent); color: #fff; border-color: var(--accent); }
  .masthead {
    display: flex; justify-content: space-between; align-items: flex-end;
    border-bottom: 3px solid var(--accent); padding-bottom: 10px; margin-bottom: 16px;
  }
  .masthead h1 { margin: 0; font-size: 18pt; letter-spacing: -0.03em; }
  .masthead .meta { text-align: right; color: var(--muted); font-size: 9pt; }
  .grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
  .vcard {
    border: 1px solid var(--line);
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    break-inside: avoid;
    page-break-inside: avoid;
    display: flex;
    flex-direction: column;
  }
  .vcard-accent { height: 4px; background: #1e293b; }
  .vcard-body { display: flex; gap: 10px; padding: 12px; }
  .photo {
    width: 64px; height: 64px; border-radius: 10px; object-fit: cover;
    background: var(--soft); border: 1px solid var(--line); flex-shrink: 0;
  }
  .photo.ph {
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; color: var(--muted); font-size: 18pt;
  }
  .name { font-weight: 800; font-size: 11pt; margin: 0 0 2px; color: var(--ink); }
  .role { font-weight: 600; font-size: 9pt; color: var(--accent); margin: 0 0 6px; }
  .line { font-size: 8.5pt; color: #334155; margin: 2px 0; }
  .foot {
    border-top: 1px solid #f1f5f9; background: var(--soft);
    padding: 6px 12px; font-size: 8pt; color: var(--muted);
    display: flex; flex-wrap: wrap; gap: 6px;
  }
  .pill {
    border: 1px solid var(--line); background: #fff; border-radius: 999px;
    padding: 1px 8px; font-weight: 600;
  }
  .footer {
    margin-top: 16px; padding-top: 8px; border-top: 1px solid var(--line);
    font-size: 8pt; color: var(--muted); display: flex; justify-content: space-between;
  }
  @media print {
    .toolbar { display: none !important; }
    body { padding: 0; background: #fff !important; color: #0f172a !important; }
    @page { margin: 10mm; size: A4; }
    .vcard { box-shadow: none; }
  }
  @media (max-width: 720px) {
    .grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <button type="button" class="primary" onclick="window.print()">Print / Save PDF</button>
    <button type="button" onclick="window.close()">Close</button>
  </div>

  <header class="masthead">
    <div>
      <h1><?= $h($compName) ?></h1>
      <div style="color:var(--muted);font-size:10pt">Human Capital Index · <?= count($team) ?> contacts</div>
    </div>
    <div class="meta">
      <strong>Official directory</strong>
      <?= $h($now) ?>
    </div>
  </header>

  <div class="grid">
<?php
$n = 0;
$accents = ['#0f172a','#1d4ed8','#4338ca','#0f766e','#0369a1','#6d28d9','#0e7490','#047857'];
foreach ($team as $m):
    if (!is_array($m)) continue;
    $n++;
    $name = (string)($m['name'] ?? '—');
    $did = (string)($m['designation_id'] ?? '');
    $deptid = (string)($m['department_id'] ?? '');
    $locid = (string)($m['location_id'] ?? '');
    $desig = (string)($m['designation_name'] ?? $m['designation'] ?? ($desigMap[$did]['name'] ?? $desigMap[$did]['title'] ?? ''));
    $dept  = (string)($m['department_name'] ?? $m['department'] ?? ($deptMap[$deptid]['name'] ?? ''));
    $loc   = (string)($m['location_name'] ?? $m['location'] ?? ($locMap[$locid]['name'] ?? ''));
    $phone = (string)($m['phone'] ?? $m['mobile'] ?? '');
    $email = (string)($m['email'] ?? '');
    $dob   = (string)($m['dob'] ?? '');
    $gender = (string)($m['gender'] ?? '');
    $blood = (string)($m['blood_group'] ?? $m['blood'] ?? '');
    $gotra = (string)($m['gotra'] ?? '');
    $purl = $photoUrl($m);
    $accent = $accents[($n - 1) % count($accents)];
?>
    <article class="vcard">
      <div class="vcard-accent" style="background:<?= $h($accent) ?>"></div>
      <div class="vcard-body">
        <?php if ($purl !== ''): ?>
          <img class="photo" src="<?= $h($purl) ?>" alt="" width="64" height="64">
        <?php else: ?>
          <div class="photo ph"><?= $h(mb_strtoupper(mb_substr($name, 0, 1))) ?></div>
        <?php endif; ?>
        <div>
          <p class="name"><?= $h($name) ?></p>
          <?php if ($desig !== ''): ?><p class="role"><?= $h($desig) ?></p><?php endif; ?>
          <?php if ($dept !== ''): ?><p class="line"><?= $h($dept) ?></p><?php endif; ?>
          <?php if ($loc !== ''): ?><p class="line"><?= $h($loc) ?></p><?php endif; ?>
          <?php if ($phone !== ''): ?><p class="line"><?= $h($phone) ?></p><?php endif; ?>
          <?php if ($email !== ''): ?><p class="line"><?= $h($email) ?></p><?php endif; ?>
        </div>
      </div>
      <?php if ($gotra || $blood || $dob || $gender): ?>
      <div class="foot">
        <?php if ($gotra): ?><span class="pill">Gotra · <?= $h($gotra) ?></span><?php endif; ?>
        <?php if ($blood): ?><span class="pill">Blood · <?= $h($blood) ?></span><?php endif; ?>
        <?php if ($dob): ?><span class="pill">DOB · <?= $h($dob) ?></span><?php endif; ?>
        <?php if ($gender): ?><span class="pill"><?= $h($gender) ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
    </article>
<?php endforeach; ?>
  </div>

  <div class="footer">
    <span><?= $h($compName) ?> · <?= $n ?> visiting cards</span>
    <span><?= $h($now) ?> · Confidential</span>
  </div>
</body>
</html>
