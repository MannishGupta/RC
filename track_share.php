<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) { define('BASE_PATH', __DIR__); }
require_once BASE_PATH . '/app/tenant_bootstrap.php';

/**
 * Version: 1.2
 * Browser Geolocation share page for mandatory-tracked team members.
 *
 * - Server-side rendering of member / org details (crawler-safe)
 * - Full SEO + Open Graph + Twitter Card meta in the initial HTML head
 * - Posts GPS pings to runners.php?action=update_location
 *
 * Query: ?id={team_member_id|slug} optional deep-link for a specific person
 */
$root = __DIR__;
$dataRoot = defined('DATA_PATH') ? DATA_PATH : ($root . '/data');
$teamFile = $dataRoot . '/team.json';
$desigFile = $dataRoot . '/designations.json';
$companyFile = $dataRoot . '/company.json';

/**
 * @return array<int, array<string, mixed>>
 */
function ts_read_json_list(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $raw = json_decode((string) @file_get_contents($path), true);
    if (!is_array($raw)) {
        return [];
    }
    if (isset($raw['items']) && is_array($raw['items'])) {
        $raw = $raw['items'];
    }
    if ($raw !== [] && !isset($raw[0]) && (isset($raw['name']) || isset($raw['logo']))) {
        return [$raw];
    }
    $out = [];
    foreach ($raw as $row) {
        if (is_array($row)) {
            $out[] = $row;
        }
    }
    return $out;
}

function ts_truthy($v): bool
{
    return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'on';
}

function ts_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ts_abs_url(string $pathOrUrl): string
{
    $pathOrUrl = trim($pathOrUrl);
    if ($pathOrUrl === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $pathOrUrl)) {
        return $pathOrUrl;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    if ($pathOrUrl[0] !== '/') {
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        if ($base === '' || $base === '.') {
            $base = '';
        }
        $pathOrUrl = $base . '/' . ltrim($pathOrUrl, '/');
    }
    return $scheme . '://' . $host . $pathOrUrl;
}

function ts_canonical_self(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/track_share.php');
    $uri = strtok($uri, '#') ?: '/track_share.php';
    return $scheme . '://' . $host . $uri;
}

$team = ts_read_json_list($teamFile);
$desigs = ts_read_json_list($desigFile);
$companyRows = ts_read_json_list($companyFile);
$company = $companyRows[0] ?? [];

$orgName = trim((string) ($company['name'] ?? 'Organization'));
if ($orgName === '') {
    $orgName = 'Organization';
}
$orgWebsite = trim((string) ($company['website'] ?? ''));
$orgLogo = trim((string) ($company['logo'] ?? ''));
$orgCover = trim((string) ($company['cover'] ?? ''));
$orgFavicon = trim((string) ($company['favicon'] ?? ''));

$trackDesigKeys = [];
foreach ($desigs as $d) {
    if (!ts_truthy($d['mandatory_live_tracking'] ?? $d['require_live_tracking'] ?? $d['live_tracking'] ?? false)) {
        continue;
    }
    foreach (['id', 'code', 'slug', 'name'] as $f) {
        $k = strtolower(trim((string) ($d[$f] ?? '')));
        if ($k !== '') {
            $trackDesigKeys[$k] = true;
        }
    }
}

$trackable = [];
foreach ($team as $m) {
    $keys = [];
    foreach (['designation_id', 'designation_code', 'designation', 'designation_name', 'labels'] as $f) {
        $k = strtolower(trim((string) ($m[$f] ?? '')));
        if ($k !== '') {
            $keys[] = $k;
        }
    }
    $ok = false;
    foreach ($keys as $k) {
        if (isset($trackDesigKeys[$k])) {
            $ok = true;
            break;
        }
    }
    if (ts_truthy($m['is_tracking_enabled'] ?? $m['mandatory_live_tracking'] ?? false)) {
        $ok = true;
    }
    if (!$ok) {
        continue;
    }
    $id = (string) ($m['id'] ?? $m['slug'] ?? '');
    if ($id === '') {
        continue;
    }
    $trackable[] = [
        'id' => $id,
        'slug' => (string) ($m['slug'] ?? ''),
        'name' => (string) ($m['name'] ?? 'Employee'),
        'designation' => (string) ($m['designation_name'] ?? $m['designation'] ?? ''),
        'department' => (string) ($m['department_name'] ?? $m['department'] ?? ''),
        'photo' => (string) ($m['photo'] ?? ''),
        'phone' => (string) ($m['phone'] ?? $m['mobile'] ?? ''),
    ];
}

usort($trackable, static function ($a, $b) {
    return strcasecmp($a['name'], $b['name']);
});

$requestId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$selected = null;
if ($requestId !== '') {
    $needle = strtolower($requestId);
    foreach ($trackable as $row) {
        if (strtolower($row['id']) === $needle || strtolower($row['slug']) === $needle) {
            $selected = $row;
            break;
        }
    }
    if ($selected === null) {
        foreach ($team as $m) {
            if (!is_array($m)) {
                continue;
            }
            $tid = (string) ($m['id'] ?? '');
            $tslug = (string) ($m['slug'] ?? '');
            if (strtolower($tid) === $needle || strtolower($tslug) === $needle) {
                $selected = [
                    'id' => $tid !== '' ? $tid : $tslug,
                    'slug' => $tslug,
                    'name' => (string) ($m['name'] ?? 'Team member'),
                    'designation' => (string) ($m['designation_name'] ?? $m['designation'] ?? ''),
                    'department' => (string) ($m['department_name'] ?? $m['department'] ?? ''),
                    'photo' => (string) ($m['photo'] ?? ''),
                    'phone' => (string) ($m['phone'] ?? $m['mobile'] ?? ''),
                    '_not_mandatory' => true,
                ];
                break;
            }
        }
    }
}

$canonical = ts_canonical_self();
$siteName = $orgName;

if ($selected !== null) {
    $personName = $selected['name'];
    $role = trim($selected['designation'] . ($selected['department'] !== '' ? ' · ' . $selected['department'] : ''));
    $pageTitle = $personName . ' — Live location share | ' . $orgName;
    $pageDescription = $personName
        . ($role !== '' ? ' (' . $role . ')' : '')
        . ' is sharing live duty location with '
        . $orgName
        . '. Open this secure page on your phone, allow GPS, and keep it open during your shift.';
    $ogType = 'profile';
} else {
    $pageTitle = 'Authorize Live Geolocation | ' . $orgName;
    $pageDescription = 'Duty staff at '
        . $orgName
        . ' can share live GPS location from this page during working hours. Select your name, allow location access, and keep the page open while on duty.';
    $ogType = 'website';
}

$ogImage = '';
if ($selected !== null && $selected['photo'] !== '') {
    $ogImage = ts_abs_url('images/' . ltrim($selected['photo'], '/'));
}
if ($ogImage === '' && $orgCover !== '') {
    $ogImage = ts_abs_url('images/' . ltrim($orgCover, '/'));
}
if ($ogImage === '' && $orgLogo !== '') {
    $ogImage = ts_abs_url('images/' . ltrim($orgLogo, '/'));
}
if ($ogImage === '') {
    $label = rawurlencode(mb_substr($selected['name'] ?? $orgName, 0, 24));
    $ogImage = 'https://ui-avatars.com/api/?name=' . $label . '&size=1200&background=1e3a5f&color=ffffff&bold=true&format=png';
}

$robots = 'index, follow';
$faviconHref = $orgFavicon !== ''
    ? ts_abs_url('images/' . ltrim($orgFavicon, '/'))
    : ts_abs_url('favicon.ico');

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => $selected !== null ? 'Person' : 'WebPage',
    'name' => $selected !== null ? $selected['name'] : $pageTitle,
    'description' => $pageDescription,
    'url' => $canonical,
];
if ($selected !== null) {
    if ($selected['designation'] !== '') {
        $jsonLd['jobTitle'] = $selected['designation'];
    }
    $jsonLd['worksFor'] = [
        '@type' => 'Organization',
        'name' => $orgName,
        'url' => $orgWebsite !== '' ? $orgWebsite : ts_abs_url('/'),
    ];
    if ($selected['photo'] !== '') {
        $jsonLd['image'] = ts_abs_url('images/' . ltrim($selected['photo'], '/'));
    }
} else {
    $jsonLd['isPartOf'] = [
        '@type' => 'WebSite',
        'name' => $orgName,
        'url' => $orgWebsite !== '' ? $orgWebsite : ts_abs_url('/'),
    ];
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <title><?= ts_h($pageTitle) ?></title>
  <meta name="description" content="<?= ts_h($pageDescription) ?>">
  <meta name="robots" content="<?= ts_h($robots) ?>">
  <link rel="canonical" href="<?= ts_h($canonical) ?>">
  <link rel="icon" href="<?= ts_h($faviconHref) ?>">
  <meta name="theme-color" content="#0f172a">
  <meta name="author" content="<?= ts_h($orgName) ?>">
  <meta name="application-name" content="<?= ts_h($orgName . ' Live Location') ?>">

  <!-- Open Graph (Facebook / WhatsApp / LinkedIn / Telegram) -->
  <meta property="og:type" content="<?= ts_h($ogType) ?>">
  <meta property="og:title" content="<?= ts_h($pageTitle) ?>">
  <meta property="og:description" content="<?= ts_h($pageDescription) ?>">
  <meta property="og:image" content="<?= ts_h($ogImage) ?>">
  <meta property="og:image:secure_url" content="<?= ts_h($ogImage) ?>">
  <meta property="og:image:alt" content="<?= ts_h($selected['name'] ?? $orgName) ?>">
  <meta property="og:url" content="<?= ts_h($canonical) ?>">
  <meta property="og:site_name" content="<?= ts_h($siteName) ?>">
  <meta property="og:locale" content="en_IN">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= ts_h($pageTitle) ?>">
  <meta name="twitter:description" content="<?= ts_h($pageDescription) ?>">
  <meta name="twitter:image" content="<?= ts_h($ogImage) ?>">
  <meta name="twitter:image:alt" content="<?= ts_h($selected['name'] ?? $orgName) ?>">

  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

  <style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background: #0b1220; color: #e2e8f0;
      display: flex; flex-direction: column; align-items: center; padding: 1.25rem;
    }
    .card {
      width: 100%; max-width: 26rem; background: #111827; border: 1px solid #1e293b;
      border-radius: 1rem; padding: 1.25rem; box-shadow: 0 20px 40px rgba(0,0,0,.35);
    }
    h1 { margin: 0 0 .35rem; font-size: 1.15rem; font-weight: 800; color: #f8fafc; }
    .sub { margin: 0 0 1rem; font-size: .8rem; color: #94a3b8; line-height: 1.45; }
    label { display: block; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; margin-bottom: .35rem; }
    select, button {
      width: 100%; height: 2.75rem; border-radius: .65rem; font-size: .9rem; font-weight: 600;
      border: 1px solid #334155; background: #0f172a; color: #f1f5f9;
    }
    select { padding: 0 .75rem; margin-bottom: 1rem; }
    button {
      cursor: pointer; border: 0; background: linear-gradient(180deg, #3b82f6, #2563eb);
      box-shadow: 0 8px 20px rgba(37,99,235,.35);
    }
    button.stop { background: linear-gradient(180deg, #f43f5e, #e11d48); box-shadow: 0 8px 20px rgba(225,29,72,.3); }
    button:disabled { opacity: .5; cursor: not-allowed; }
    .status {
      margin-top: 1rem; padding: .75rem .85rem; border-radius: .65rem; background: #0f172a;
      border: 1px solid #1e293b; font-size: .8rem; line-height: 1.5; color: #cbd5e1;
      min-height: 3.5rem; white-space: pre-line;
    }
    .status.on { border-color: #166534; background: #052e16; color: #bbf7d0; }
    .status.err { border-color: #9f1239; background: #4c0519; color: #fecdd3; }
    .pill {
      display: inline-flex; align-items: center; gap: .35rem; font-size: .65rem; font-weight: 800;
      text-transform: uppercase; letter-spacing: .05em; padding: .2rem .5rem; border-radius: 999px;
      background: #1e293b; color: #94a3b8; margin-bottom: .75rem;
    }
    .pill.live { background: #14532d; color: #86efac; }
    .meta { margin-top: .75rem; font-size: .7rem; color: #64748b; }
    a.back { color: #60a5fa; font-size: .8rem; text-decoration: none; margin-top: 1rem; display: inline-block; }
    .profile {
      display: flex; gap: .85rem; align-items: center; margin-bottom: 1rem;
      padding: .75rem; border-radius: .75rem; background: #0f172a; border: 1px solid #1e293b;
    }
    .profile img, .profile .ph {
      width: 56px; height: 56px; border-radius: 10px; object-fit: cover;
      background: #1e293b; flex-shrink: 0; display: flex; align-items: center; justify-content: center;
      font-weight: 800; color: #94a3b8; font-size: 1.25rem;
    }
    .profile .nm { font-weight: 800; color: #f8fafc; font-size: .95rem; }
    .profile .rl { font-size: .75rem; color: #94a3b8; margin-top: .15rem; }
    .noscript {
      max-width: 26rem; margin: 0 auto 1rem; padding: .75rem 1rem; border-radius: .65rem;
      background: #422006; border: 1px solid #a16207; color: #fde68a; font-size: .8rem; line-height: 1.45;
    }
  </style>
</head>
<body>
  <noscript>
    <div class="noscript">
      JavaScript is required to stream GPS pings. Page identity and preview data were rendered on the server for <?= ts_h($orgName) ?><?= $selected ? ' — ' . ts_h($selected['name']) : '' ?>.
    </div>
  </noscript>

  <div class="card">
    <div class="pill" id="pill">Ready</div>
    <h1><?= $selected !== null ? ts_h($selected['name'] . ' — live location') : 'Share live location' ?></h1>
    <p class="sub">
      <?= ts_h($orgName) ?> —
      <?php if ($selected !== null): ?>
        duty location share for <strong><?= ts_h($selected['name']) ?></strong><?= $selected['designation'] !== '' ? ' (' . ts_h($selected['designation']) . ')' : '' ?>.
        Open on your phone, allow GPS, and keep this page open during your shift.
      <?php else: ?>
        for staff whose designation has <strong>Mandatory live tracking</strong> enabled. Select your name, allow location, and keep this page open during your shift.
      <?php endif; ?>
    </p>

    <?php if ($selected !== null): ?>
      <div class="profile">
        <?php if ($selected['photo'] !== ''): ?>
          <img src="<?= ts_h(ts_abs_url('images/' . ltrim($selected['photo'], '/'))) ?>" alt="<?= ts_h($selected['name']) ?>" width="56" height="56">
        <?php else: ?>
          <div class="ph" aria-hidden="true"><?= ts_h(mb_strtoupper(mb_substr($selected['name'], 0, 1))) ?></div>
        <?php endif; ?>
        <div>
          <div class="nm"><?= ts_h($selected['name']) ?></div>
          <div class="rl"><?= ts_h(trim($selected['designation'] . ($selected['department'] !== '' ? ' · ' . $selected['department'] : ''))) ?></div>
        </div>
      </div>
      <?php if (!empty($selected['_not_mandatory'])): ?>
        <p class="status err" style="margin-top:0;margin-bottom:1rem">This profile is not on a mandatory-tracking designation. You may still share location; HR should enable the designation flag if required.</p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($trackable === [] && $selected === null): ?>
      <p class="status err">No trackable team members found. HR must tick <em>Mandatory live tracking</em> on a designation and assign people to it in the Resource Center.</p>
    <?php else: ?>
      <label for="member">Who are you?</label>
      <select id="member" name="member">
        <option value="">— Select your name —</option>
        <?php
        $options = $trackable;
        if ($selected !== null) {
            $foundInList = false;
            foreach ($options as $o) {
                if ($o['id'] === $selected['id']) {
                    $foundInList = true;
                    break;
                }
            }
            if (!$foundInList) {
                array_unshift($options, $selected);
            }
        }
        foreach ($options as $m):
            $isSel = ($selected !== null && $selected['id'] === $m['id'])
                || ($requestId !== '' && (strcasecmp($requestId, $m['id']) === 0 || strcasecmp($requestId, $m['slug'] ?? '') === 0));
            ?>
          <option value="<?= ts_h($m['id']) ?>"
                  data-name="<?= ts_h($m['name']) ?>"
                  data-desig="<?= ts_h($m['designation']) ?>"
                  data-dept="<?= ts_h($m['department']) ?>"
                  <?= $isSel ? ' selected' : '' ?>>
            <?= ts_h($m['name']) ?><?= $m['designation'] !== '' ? ' · ' . ts_h($m['designation']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="button" id="btn"<?= ($selected !== null || $requestId !== '') ? '' : ' disabled' ?>>Start sharing</button>
      <div class="status" id="status">Select your name, then allow location when the browser asks.</div>
      <p class="meta">Pings every 30 seconds via this device’s GPS. Data is stored by <?= ts_h($orgName) ?> under the Resource Center live-tracking module.</p>
    <?php endif; ?>
  </div>
  <a class="back" href="./">← Resource Center</a>

  <div hidden>
    <h2><?= ts_h($pageTitle) ?></h2>
    <p><?= ts_h($pageDescription) ?></p>
    <?php if ($selected !== null): ?>
      <p>Member: <?= ts_h($selected['name']) ?></p>
      <p>Role: <?= ts_h($selected['designation']) ?></p>
      <p>Organization: <?= ts_h($orgName) ?></p>
    <?php endif; ?>
    <p>Canonical: <?= ts_h($canonical) ?></p>
  </div>

<script>
(function () {
  var sel = document.getElementById('member');
  var btn = document.getElementById('btn');
  var statusEl = document.getElementById('status');
  var pill = document.getElementById('pill');
  if (!sel || !btn) return;

  var watching = false;
  var watchId = null;
  var timer = null;

  function setStatus(msg, cls) {
    statusEl.textContent = msg;
    statusEl.className = 'status' + (cls ? ' ' + cls : '');
  }
  function setPill(text, live) {
    pill.textContent = text;
    pill.className = 'pill' + (live ? ' live' : '');
  }

  sel.addEventListener('change', function () {
    btn.disabled = !sel.value || watching;
  });
  if (sel.value) btn.disabled = false;

  async function postPing(pos) {
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    var body = {
      id: opt.value,
      name: opt.getAttribute('data-name') || opt.text,
      designation: opt.getAttribute('data-desig') || '',
      department: opt.getAttribute('data-dept') || '',
      lat: pos.coords.latitude,
      lng: pos.coords.longitude,
      speed_kmh: pos.coords.speed != null && !isNaN(pos.coords.speed) ? (pos.coords.speed * 3.6) : null,
      accuracy_m: pos.coords.accuracy || null
    };
    try {
      var res = await fetch('runners.php?action=update_location', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body),
        credentials: 'same-origin'
      });
      var data = await res.json().catch(function () { return {}; });
      if (!res.ok || data.status === 'error' || data.ok === false) {
        throw new Error(data.message || data.error || ('HTTP ' + res.status));
      }
      setStatus(
        'Sharing · ' + body.name + '\n' +
        body.lat.toFixed(5) + ', ' + body.lng.toFixed(5) +
        (body.speed_kmh != null ? (' · ' + body.speed_kmh.toFixed(1) + ' km/h') : '') +
        '\nLast sent: ' + new Date().toLocaleTimeString(),
        'on'
      );
      setPill('Live', true);
    } catch (e) {
      setStatus('Ping failed: ' + (e.message || e), 'err');
      setPill('Error', false);
    }
  }

  function onPos(pos) { postPing(pos); }
  function onErr(err) {
    setStatus('Location error: ' + (err && err.message ? err.message : 'permission denied or unavailable'), 'err');
    setPill('Blocked', false);
    stopShare(false);
  }

  function startShare() {
    if (!sel.value) return;
    if (!navigator.geolocation) {
      setStatus('This browser does not support Geolocation.', 'err');
      return;
    }
    watching = true;
    btn.disabled = false;
    btn.textContent = 'Stop sharing';
    btn.classList.add('stop');
    setStatus('Requesting location permission…', '');
    setPill('Starting…', false);
    watchId = navigator.geolocation.watchPosition(onPos, onErr, {
      enableHighAccuracy: true,
      maximumAge: 10000,
      timeout: 20000
    });
    timer = setInterval(function () {
      navigator.geolocation.getCurrentPosition(onPos, function () {}, {
        enableHighAccuracy: true,
        maximumAge: 5000,
        timeout: 15000
      });
    }, 30000);
  }

  function stopShare(notifyServer) {
    watching = false;
    if (watchId != null && navigator.geolocation) {
      try { navigator.geolocation.clearWatch(watchId); } catch (e) {}
    }
    watchId = null;
    if (timer) clearInterval(timer);
    timer = null;
    btn.textContent = 'Start sharing';
    btn.classList.remove('stop');
    btn.disabled = !sel.value;
    setPill('Stopped', false);
    if (notifyServer !== false) {
      var opt = sel.options[sel.selectedIndex];
      if (opt && opt.value) {
        fetch('runners.php?action=report_turn_off', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            id: opt.value,
            name: opt.getAttribute('data-name') || opt.text,
            designation: opt.getAttribute('data-desig') || '',
            department: opt.getAttribute('data-dept') || ''
          }),
          credentials: 'same-origin'
        }).catch(function () {});
      }
    }
  }

  btn.addEventListener('click', function () {
    if (watching) {
      stopShare(true);
      setStatus('Sharing stopped. You can close this page.', '');
    } else {
      startShare();
    }
  });

  window.addEventListener('beforeunload', function () {
    if (watching) stopShare(true);
  });
})();
</script>
</body>
</html>
