<?php
/**
 * tools/print_directory.php — Corporate visiting-card directory print
 * Version: 20261003.21
 *
 * Required on each card: Photo, Name, Designation, Department, Project, Mobile, Email
 * Excluded: location address, social links, website, slug/id technical fields
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
  .grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
  }
  .vcard {
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    background: #fff;
    break-inside: avoid;
    page-break-inside: avoid;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 118px;
  }
  .vcard-top {
    height: 3px;
    background: var(--ink);
  }
  .vcard-body {
    display: flex;
    gap: 14px;
    padding: 14px 14px 12px;
    align-items: flex-start;
  }
  .photo {
    width: 72px;
    height: 72px;
    border-radius: 10px;
    object-fit: cover;
    object-position: 50% 22%;
    background: var(--soft);
    border: 1px solid var(--line);
    flex-shrink: 0;
  }
  .photo.ph {
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; color: #94a3b8; font-size: 20pt;
  }
  .info { min-width: 0; flex: 1; }
  .name {
    margin: 0 0 3px;
    font-weight: 800;
    font-size: 12pt;
    color: var(--ink);
    letter-spacing: -0.01em;
    line-height: 1.2;
  }
  .role {
    margin: 0 0 8px;
    font-weight: 700;
    font-size: 9pt;
    color: var(--role);
    letter-spacing: 0.01em;
  }
  .row {
    display: grid;
    grid-template-columns: 72px 1fr;
    gap: 4px 8px;
    font-size: 8.5pt;
    margin: 0 0 3px;
    align-items: baseline;
  }
  .row .k {
    color: var(--muted);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 7.5pt;
    letter-spacing: 0.04em;
  }
  .row .v {
    color: #1e293b;
    font-weight: 600;
    word-break: break-word;
  }
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
    body { padding: 0; background: #fff !important; color: #0f172a !important; }
    @page { size: A4 portrait; margin: 12mm 10mm; }
    .vcard { box-shadow: none; border-color: #94a3b8; }
    .grid { gap: 10px; }
  }
  @media (max-width: 720px) {
    .grid { grid-template-columns: 1fr; }
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

  <div class="grid">
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
    $project = trim((string)(
        $m['project'] ?? $m['project_name'] ?? $m['project_title']
        ?? $m['assignment'] ?? $m['rera_project_name'] ?? ''
    ));
    $phone = trim((string)($m['phone'] ?? $m['mobile'] ?? $m['tel'] ?? ''));
    $email = trim((string)($m['email'] ?? ''));
    $purl = $photoUrl($m);
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
?>
    <article class="vcard">
      <div class="vcard-top"></div>
      <div class="vcard-body">
        <?php if ($purl !== ''): ?>
          <img class="photo" src="<?= $h($purl) ?>" alt="<?= $h($name) ?>" width="72" height="72" loading="eager">
        <?php else: ?>
          <div class="photo ph" aria-hidden="true"><?= $h($initial) ?></div>
        <?php endif; ?>
        <div class="info">
          <p class="name"><?= $h($name) ?></p>
          <?php if ($desig !== ''): ?>
            <p class="role"><?= $h($desig) ?></p>
          <?php endif; ?>

          <?php if ($dept !== ''): ?>
          <div class="row"><span class="k">Department</span><span class="v"><?= $h($dept) ?></span></div>
          <?php endif; ?>
          <?php if ($project !== ''): ?>
          <div class="row"><span class="k">Project</span><span class="v"><?= $h($project) ?></span></div>
          <?php endif; ?>
          <?php if ($phone !== ''): ?>
          <div class="row"><span class="k">Mobile</span><span class="v"><?= $h($phone) ?></span></div>
          <?php endif; ?>
          <?php if ($email !== ''): ?>
          <div class="row"><span class="k">Email</span><span class="v"><?= $h($email) ?></span></div>
          <?php endif; ?>
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
