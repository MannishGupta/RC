<?php
/**
 * tools/print_directory.php — Corporate visiting-card directory print
 * Version: 20261003.29
 *
 * Stacked visiting cards: Photo, Name, Designation, Department, Location, Phone, Email
 * Excluded: Project, social links, website, technical fields
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
    if (!is_array($d)) {
        continue;
    }
    $id = (string)($d['id'] ?? '');
    if ($id !== '') {
        $desigMap[$id] = $d;
    }
}
$deptMap = [];
foreach ($depts as $d) {
    if (!is_array($d)) {
        continue;
    }
    $id = (string)($d['id'] ?? '');
    if ($id !== '') {
        $deptMap[$id] = $d;
    }
}
$locMap = [];
foreach ($locs as $l) {
    if (!is_array($l)) {
        continue;
    }
    $id = (string)($l['id'] ?? '');
    if ($id !== '') {
        $locMap[$id] = $l;
    }
}

$rankOf = static function (array $m) use ($desigMap): int {
    $did = (string)($m['designation_id'] ?? '');
    if ($did !== '' && isset($desigMap[$did]['rank'])) {
        return (int)$desigMap[$did]['rank'];
    }
    $r = (int)($m['rank'] ?? 0);
    return ($r > 0 && $r < 900) ? $r : 9999;
};

usort($team, static function ($a, $b) use ($rankOf) {
    if (!is_array($a) || !is_array($b)) {
        return 0;
    }
    $ra = $rankOf($a);
    $rb = $rankOf($b);
    if ($ra !== $rb) {
        return $ra <=> $rb;
    }
    return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
});

$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$now = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('d-m-Y, g:i a') . ' IST';
$origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$photoUrl = static function (array $m) use ($origin): string {
    $photo = trim((string)($m['photo'] ?? ''));
    if ($photo === '') {
        return '';
    }
    if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
        return $photo;
    }
    $base = basename($photo);
    // Prefer media_serve (multi-tenant safe)
    return rtrim($origin, '/') . '/media_serve.php?f=' . rawurlencode($base);
};

$total = 0;
foreach ($team as $m) {
    if (is_array($m) && trim((string)($m['name'] ?? '')) !== '') {
        $total++;
    }
}
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Human Capital Directory — <?= $h($compName) ?></title>
<meta name="description" content="Printable human capital directory for <?= $h($compName) ?>.">
<meta name="robots" content="noindex">
<style>
  :root {
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e2e8f0;
    --soft: #f8fafc;
    --accent: #0f172a;
    --role: #1e40af;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    padding: 18px 20px 48px;
    font-family: "Inter", "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
    color: var(--ink);
    background: #fff;
    font-size: 10pt;
    line-height: 1.35;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .toolbar {
    display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;
  }
  .toolbar button {
    border: 1px solid var(--line); background: var(--soft); color: var(--ink);
    border-radius: 8px; padding: 9px 16px; font-weight: 700; font-size: 12px; cursor: pointer;
  }
  .toolbar button.primary {
    background: var(--ink); color: #fff; border-color: var(--ink);
  }
  .masthead {
    display: flex; justify-content: space-between; align-items: flex-end;
    border-bottom: 2px solid var(--ink); padding-bottom: 12px; margin-bottom: 18px;
  }
  .masthead h1 {
    margin: 0; font-size: 16pt; font-weight: 800; letter-spacing: -0.02em;
  }
  .masthead .sub {
    margin: 4px 0 0; font-size: 9pt; color: var(--muted); font-weight: 600;
  }
  .masthead .meta {
    text-align: right; color: var(--muted); font-size: 8.5pt; line-height: 1.45;
  }
  .grid-cards {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
    align-items: stretch;
  }
  .vcard {
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    break-inside: avoid;
    page-break-inside: avoid;
    height: 100%;
  }
  .vcard-top { height: 3px; background: var(--ink); }
  .vcard-body {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px 12px;
  }
  .vcard-left {
    flex: 0 0 84px;
    width: 84px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
  }
  .photo {
    width: 84px;
    height: 84px;
    border-radius: 10px;
    object-fit: cover;
    object-position: center 18%;
    border: 1px solid var(--line);
    background: var(--soft);
    display: block;
  }
  .photo.ph {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #94a3b8;
    font-size: 26pt;
  }
  .vcard-meta-sex {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 4px;
    width: 100%;
  }
  .vcard-meta-sex span {
    font-size: 7.5pt;
    font-weight: 700;
    color: var(--muted);
    border: 1px solid var(--line);
    background: var(--soft);
    border-radius: 999px;
    padding: 1px 6px;
    line-height: 1.3;
  }
  .vcard-right {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .name {
    margin: 0 0 2px;
    font-weight: 800;
    font-size: 12.5pt;
    color: var(--ink);
    letter-spacing: -0.01em;
    line-height: 1.2;
    overflow-wrap: anywhere;
  }
  .line {
    margin: 0;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 8.5pt;
    font-weight: 600;
    color: #334155;
    line-height: 1.35;
    max-width: 100%;
    overflow-wrap: anywhere;
  }
  .line .ic {
    flex: 0 0 14px;
    width: 14px;
    text-align: center;
    color: #64748b;
    font-size: 9pt;
    line-height: 1.35;
  }
  .line.role { color: var(--role); font-weight: 700; font-size: 9pt; }
  .line.dept { color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .03em; font-size: 8pt; }
  .line.email { color: var(--role); word-break: break-all; }
  .empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 40px 16px;
    color: var(--muted);
    border: 1px dashed var(--line);
    border-radius: 10px;
  }
  .footer {
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid var(--line);
    font-size: 8pt;
    color: var(--muted);
    display: flex;
    justify-content: space-between;
    gap: 12px;
  }
  .footer .brand { font-weight: 700; color: #475569; }

  @media print {
    .toolbar { display: none !important; }
    body {
      padding: 0; background: #fff !important; color: #0f172a !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .photo {
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    @page { size: A4 portrait; margin: 12mm 10mm; }
    .grid-cards { display: block; }
    .vcard {
      display: inline-block;
      width: calc(50% - 12px);
      vertical-align: top;
      margin: 0 4px 12px;
      break-inside: avoid;
      page-break-inside: avoid;
      box-shadow: none;
      border-color: #94a3b8;
      height: auto;
    }
  }
  @media (max-width: 720px) {
    .grid-cards { grid-template-columns: 1fr; }
    .masthead { flex-direction: column; align-items: flex-start; gap: 8px; }
    .masthead .meta { text-align: left; }
  }
</style>
</head>
<body>
  <div class="toolbar no-print">
    <button type="button" class="primary" onclick="window.print()">Print directory</button>
    <button type="button" onclick="window.close()">Close</button>
  </div>

  <header class="masthead">
    <div>
      <h1><?= $h($compName) ?></h1>
      <p class="sub">Human Capital Directory · Visiting card format</p>
    </div>
    <div class="meta">
      <div><?= (int)$total ?> personnel</div>
      <div><?= $h($now) ?></div>
    </div>
  </header>

  <div class="grid-cards">
<?php
$printed = 0;
foreach ($team as $m) {
    if (!is_array($m)) {
        continue;
    }
    $name = trim((string)($m['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $printed++;

    $did = (string)($m['designation_id'] ?? '');
    $deptid = (string)($m['department_id'] ?? '');

    $desig = trim((string)(
        $m['designation_name'] ?? $m['designation'] ?? $m['title']
        ?? ($desigMap[$did]['name'] ?? $desigMap[$did]['title'] ?? '')
    ));
    $dept = trim((string)(
        $m['department_name'] ?? $m['department']
        ?? ($deptMap[$deptid]['name'] ?? '')
    ));
    $locid = (string)($m['location_id'] ?? '');
    $loc = trim((string)(
        $m['location_name'] ?? $m['location'] ?? $m['office'] ?? $m['branch']
        ?? ($locMap[$locid]['name'] ?? '')
    ));
    $dE = (string)($desigMap[$did]['emoji'] ?? $desigMap[$did]['icon'] ?? '');
    $tE = (string)($deptMap[$deptid]['emoji'] ?? $deptMap[$deptid]['icon'] ?? '');
    if ($dE !== '' && mb_strpos($desig, $dE) === false) {
        $desig = trim($desig . ' ' . $dE);
    }
    if ($tE !== '' && mb_strpos($dept, $tE) === false) {
        $dept = trim($dept . ' ' . $tE);
    }
    $phone = trim((string)($m['phone'] ?? $m['mobile'] ?? $m['tel'] ?? ''));
    $email = trim((string)($m['email'] ?? ''));
    $purl = $photoUrl($m);
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
    // Sex symbol + current age (below photo) — no blood/DOB row
    $sexRaw = strtolower(trim((string)($m['gender'] ?? $m['sex'] ?? '')));
    $sexSym = '';
    if ($sexRaw !== '') {
        if (preg_match('/^(m|male|man|boy)/', $sexRaw)) $sexSym = '♂';
        elseif (preg_match('/^(f|female|woman|girl)/', $sexRaw)) $sexSym = '♀';
        else $sexSym = mb_strtoupper(mb_substr($sexRaw, 0, 1));
    }
    $ageLabel = '';
    $dob = trim((string)($m['dob'] ?? $m['birthday'] ?? $m['birth_date'] ?? ''));
    if ($dob !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dob, $dm)) {
        try {
            $born = new DateTime($dm[1] . '-' . $dm[2] . '-' . $dm[3], new DateTimeZone('Asia/Kolkata'));
            $today = new DateTime('today', new DateTimeZone('Asia/Kolkata'));
            $age = (int)$born->diff($today)->y;
            if ($age >= 0 && $age < 120) $ageLabel = $age . 'y';
        } catch (Throwable $e) {}
    }
?>
    <article class="vcard">
      <div class="vcard-top"></div>
      <div class="vcard-body">
        <div class="vcard-left">
          <?php if ($purl !== ''): ?>
            <img class="photo" src="<?= $h($purl) ?>" alt="<?= $h($name) ?>" width="84" height="84" loading="eager"
                 onerror="this.outerHTML='<div class=&quot;photo ph&quot;><?= $h($initial) ?></div>'">
          <?php else: ?>
            <div class="photo ph" aria-hidden="true"><?= $h($initial) ?></div>
          <?php endif; ?>
          <?php if ($sexSym !== '' || $ageLabel !== ''): ?>
          <div class="vcard-meta-sex">
            <?php if ($sexSym !== ''): ?><span title="Sex"><?= $h($sexSym) ?></span><?php endif; ?>
            <?php if ($ageLabel !== ''): ?><span title="Age"><?= $h($ageLabel) ?></span><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="vcard-right">
          <p class="name"><?= $h($name) ?></p>
          <?php if ($desig !== ''): ?><p class="line role"><span class="ic" aria-hidden="true">👔</span><span><?= $h($desig) ?></span></p><?php endif; ?>
          <?php if ($dept !== ''): ?><p class="line dept"><span class="ic" aria-hidden="true">🏢</span><span><?= $h($dept) ?></span></p><?php endif; ?>
          <?php if ($loc !== ''): ?><p class="line"><span class="ic" aria-hidden="true">📍</span><span><?= $h($loc) ?></span></p><?php endif; ?>
          <?php if ($phone !== ''): ?><p class="line"><span class="ic" aria-hidden="true">📞</span><span><?= $h($phone) ?></span></p><?php endif; ?>
          <?php if ($email !== ''): ?><p class="line email"><span class="ic" aria-hidden="true">✉</span><span><?= $h($email) ?></span></p><?php endif; ?>
        </div>
      </div>
    </article>
<?php
}
if ($printed === 0):
?>
    <div class="empty">No personnel records to print.</div>
<?php endif; ?>
  </div>

  <footer class="footer">
    <span class="brand"><?= $h($compName) ?> · <?= (int)$printed ?> visiting cards</span>
    <span>Confidential · For internal use · <?= $h($now) ?></span>
  </footer>
  <script>
    // Auto-open print dialog when requested
    (function () {
      var q = new URLSearchParams(window.location.search);
      if (q.get('autoprint') === '1') {
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
      }
    })();
  </script>
</body>
</html>
