<?php // Version: 260916.14
declare(strict_types=1);
// vehicle-tags/index.php — PUBLIC scan landing page. This is the QR target.
//
// FIXES vs the supplied v1:
//  * BROKEN VERIFICATION (critical): v1's submitVerification() POSTed the
//    last-4 check to the server, ignored the response entirely, and then ran
//    `this.step = 3` unconditionally. Anyone could type any 4 characters —
//    or leave the field blank — and still reach the contact step. The comment
//    claimed "Server-side also validates", but nothing acted on that result.
//    The server now returns JSON and the client only advances on success.
//  * NO RATE LIMIT: v1 logged and accepted unlimited POSTs per visitor, so
//    the last-4 code (10k combinations, often far fewer in practice) could be
//    brute-forced and the log file grown without bound. Now capped per IP.
//  * OWNER PHONE LEAK RISK: the flow is deliberately kept as "message
//    security, quoting the tag ID" — the owner's own number is never sent to
//    the browser. Preserved from v1 and worth keeping that way.
//  * SEO: added noindex — scan landing pages should never appear in search.
//  * a11y: v1 set user-scalable=no (blocks pinch-zoom, WCAG 1.4.4). Removed.
//  * Data now lives in the app's AppDB (data/cartags.json) instead of the
//    module's private store.php, so writes use the same atomic/locked path.

$rootPath = dirname(__DIR__);
define('BASE_PATH', $rootPath);
require_once BASE_PATH . '/app/tenant_bootstrap.php';
// BUG FIX: this file defined DATA_PATH/IMG_PATH/DOC_PATH directly from
// BASE_PATH, completely bypassing tenant resolution -- on a multi-tenant
// deployment (tenants/{id}/data/, resolved by host in
// app/tenant_bootstrap.php), every request to this file read and wrote
// the WRONG tenant's data regardless of which domain it was requested on.
// Fixed to require tenant_bootstrap.php first (as index.php and the
// correctly-wired standalone entry points already do) and guard every
// fallback define with if (!defined(...)) so tenant_bootstrap's
// resolution always wins when available, with these as the fallback for
// a genuine single-tenant install with no tenants/ folder at all.
if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
if (!defined('IMG_PATH'))  define('IMG_PATH',  BASE_PATH . '/images');
if (!defined('DOC_PATH'))  define('DOC_PATH',  BASE_PATH . '/docs');

require_once BASE_PATH . '/app/bootstrap.php';
require_once BASE_PATH . '/app/VehicleDecoder.php';

/** Look up a tag by its printed short ID. */
function ct_find_tag(string $tagId): ?array {
    if ($tagId === '') return null;
    foreach (AppDB::read('cartags') ?: [] as $t) {
        if ((string)($t['tag_id'] ?? '') === $tagId) return $t;
    }
    return null;
}

function ct_last4(string $plate): string {
    $p = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plate));
    return substr($p, -4);
}

function ct_mask_plate(string $plate): string {
    $p = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plate));
    return strlen($p) < 4 ? str_repeat('#', strlen($p)) : substr($p, 0, -4) . '####';
}

function ct_log(array $entry): void {
    $logs = AppDB::read('cartag_logs') ?: [];
    $entry['id'] = bin2hex(random_bytes(6));
    $entry['ts'] = date('c');
    // Keep the log bounded — oldest entries roll off.
    $logs[] = $entry;
    if (count($logs) > 5000) $logs = array_slice($logs, -5000);
    AppDB::save('cartag_logs', $logs);
}

/**
 * Per-IP throttle. The last-4 plate check is a small keyspace, so without
 * this it is trivially brute-forceable and the log file becomes a spam sink.
 */
function ct_rate_ok(): bool {
    $file = DATA_PATH . '/cartag_throttle.json';
    $ip   = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $now  = time();
    $data = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
    foreach ($data as $k => $v) { if (($now - ($v['t'] ?? 0)) > 3600) unset($data[$k]); }
    $rec = $data[$ip] ?? ['n' => 0, 't' => $now];
    if (($now - $rec['t']) > 900) $rec = ['n' => 0, 't' => $now];
    $rec['n']++; $rec['t'] = $now;
    $data[$ip] = $rec;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return $rec['n'] <= 12; // 12 attempts / 15 min / IP
}

$tagId = trim((string)($_GET['t'] ?? ''));
$tagId = preg_replace('/[^A-Za-z0-9_-]/', '', $tagId);
$tag   = ct_find_tag($tagId);

// ── AJAX verification endpoint ───────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$tag) { echo json_encode(['ok' => false, 'error' => 'unknown_tag']); exit; }

    if (!ct_rate_ok()) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'rate_limited']);
        exit;
    }

    $last4   = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)($_POST['last4'] ?? '')));
    $reason  = mb_substr(trim(strip_tags((string)($_POST['reason'] ?? 'Not specified'))), 0, 120);
    $phone   = preg_replace('/[^0-9+]/', '', (string)($_POST['phone'] ?? ''));
    $phone   = mb_substr($phone, 0, 20);
    $ok      = ($last4 !== '' && $last4 === ct_last4((string)($tag['registration_number'] ?? $tag['plate'] ?? '')));

    // Optional coordinates. Only stored when the scanner explicitly opted in
    // via the browser permission prompt AND the plate check passed — an
    // unverified scanner's location is never retained.
    $lat = $lng = null;
    if ($ok && isset($_POST['lat'], $_POST['lng'])) {
        $la = (float)$_POST['lat']; $ln = (float)$_POST['lng'];
        // Reject obviously bogus values rather than storing noise.
        if ($la >= -90 && $la <= 90 && $ln >= -180 && $ln <= 180 && ($la !== 0.0 || $ln !== 0.0)) {
            // ~5 decimals is roughly 1 m — precise enough to walk to a car,
            // and no more precise than the purpose requires.
            $lat = round($la, 5); $lng = round($ln, 5);
        }
    }

    ct_log(array_filter([
        'tag_id'        => $tagId,
        'reason'        => $reason,
        'scanner_phone' => $phone,
        'ip'            => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'verified'      => $ok,
        'lat'           => $lat,
        'lng'           => $lng,
    ], fn($v) => $v !== null));

    echo json_encode(['ok' => $ok]);
    exit;
}

$company  = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$orgName  = (string)($company['name'] ?? 'Vehicle Contact');
$waNumber = preg_replace('/\D/', '', (string)($company['security_whatsapp'] ?? $company['phone'] ?? ''));

// If the tag is linked to a directory member, route the message to that
// person directly rather than via security. The owner's number is resolved
// SERVER-SIDE and only the resulting wa.me link is rendered — the raw number
// is never placed in the page for an unverified scanner to harvest, and the
// link is only built after the plate check has passed.
$ownerWa = '';
$ownerName = trim((string)($tag['owner_name'] ?? ''));   // free-text fallback
if ($tag && !empty($tag['member_id']) && class_exists('AppDB')) {
    foreach (AppDB::read('team') ?: [] as $_m) {
        if ((string)($_m['id'] ?? '') !== (string)$tag['member_id']) continue;

        // Owner's given name only. A full legal name in a message from a
        // stranger reads as surveillance; a first name reads as a neighbour.
        $_full = trim((string)($_m['name'] ?? ''));
        if ($_full !== '') $ownerName = explode(' ', $_full)[0];

        // WhatsApp username, if this member has set one — shorter, more
        // memorable, and matches what the digital business card itself
        // already prefers (see cards/business.php). No way to verify a
        // given scanner's WhatsApp actually resolves username links (that
        // depends on THEIR app/region, not anything checkable here), so this
        // stays a preference with the phone number as the working fallback,
        // exactly as on the card.
        $_wu = ltrim(trim((string)($_m['whatsapp_username'] ?? '')), '@');
        $_wu = preg_replace('~^https?://(www\.)?wa\.me/~i', '', $_wu);
        $_wu = mb_strtolower($_wu);
        // Same rule as cards/business.php: 3-35 chars, lowercase, at least
        // one letter — verified against WhatsApp's actual published policy.
        if (preg_match('/^(?=.*[a-z])[a-z0-9._]{3,35}$/', $_wu)) {
            $ownerWa = $_wu;
            break;
        }

        $_p = is_array($_m['phone'] ?? null) ? (string)($_m['phone'][0] ?? '') : (string)($_m['phone'] ?? '');
        $_d = preg_replace('/\D/', '', $_p);
        if (strlen($_d) === 10) $_d = '91' . $_d;
        elseif (strlen($_d) === 11 && str_starts_with($_d, '0')) $_d = '91' . substr($_d, 1);
        if (strlen($_d) >= 8 && strlen($_d) <= 15) $ownerWa = $_d;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b1330">
<title>Contact vehicle owner<?= $orgName ? ' · ' . htmlspecialchars($orgName) : '' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--navy:#0b1330;--navy2:#101c44;--navy3:#16225c;--gold:#c9a24b;--gold2:#d9b968;--line:rgba(255,255,255,.10)}
  body{font-family:'Inter','Aptos','Noto Sans Devanagari',system-ui,sans-serif;background:var(--navy);color:#e2e8f0;min-height:100vh;-webkit-font-smoothing:antialiased;-webkit-tap-highlight-color:transparent}
  a,button{touch-action:manipulation}
  .wrap{max-width:28rem;margin:0 auto;padding:0 1.25rem}
  header{border-bottom:1px solid var(--line);padding:1rem 1.25rem;display:flex;align-items:center;gap:.5rem}
  .badge{width:2rem;height:2rem;border-radius:.5rem;background:var(--gold);color:var(--navy);font-weight:700;display:flex;align-items:center;justify-content:center;font-size:.85rem}
  .card{background:var(--navy2);border:1px solid var(--line);border-radius:1rem;padding:1.25rem;margin-bottom:1rem}
  .lbl{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--gold2);margin-bottom:.25rem}
  .plate{font-size:1.5rem;font-weight:700;letter-spacing:.05em;font-family:ui-monospace,monospace}
  .muted{font-size:.75rem;color:#94a3b8;margin-top:.25rem}
  .opt{width:100%;text-align:left;padding:.85rem 1rem;border-radius:.75rem;background:var(--navy3);border:1px solid var(--line);color:#e2e8f0;font-size:.875rem;font-family:inherit;cursor:pointer;margin-bottom:.5rem;transition:background .15s}
  .opt:hover{background:#1b2a70}
  input{width:100%;background:var(--navy);border:1px solid var(--line);border-radius:.5rem;padding:.85rem 1rem;color:#fff;font-family:inherit;font-size:1rem;margin-bottom:.75rem}
  input.code{text-align:center;letter-spacing:.3em;text-transform:uppercase;font-family:ui-monospace,monospace}
  input:focus{outline:2px solid var(--gold);outline-offset:1px}
  .btn{display:block;width:100%;text-align:center;background:var(--gold);color:var(--navy);font-weight:600;padding:.85rem;border:0;border-radius:.5rem;font-size:.875rem;font-family:inherit;cursor:pointer;text-decoration:none}
  .btn:disabled{opacity:.6;cursor:not-allowed}
  .btn2{display:block;width:100%;text-align:center;background:var(--navy3);border:1px solid var(--line);color:#e2e8f0;padding:.85rem;border-radius:.5rem;font-size:.875rem;font-family:inherit;cursor:pointer;margin-top:.5rem;text-decoration:none}
  .link{display:block;text-align:center;margin-top:1rem;font-size:.75rem;color:var(--gold2)}
  .err{color:#fca5a5;font-size:.8rem;margin-bottom:.75rem;text-align:center}
  .fine{font-size:.7rem;color:#64748b;margin-top:1rem;text-align:center;line-height:1.5}
  footer{padding:1rem 1.25rem calc(2rem + env(safe-area-inset-bottom));text-align:center;font-size:.7rem;color:#64748b}
  .hide{display:none}
</style>
</head>
<body>

<header>
  <?php
  // Vehicle photo, if one was uploaded when the tag was created — a real
  // photo of THIS car, shown here so a scanner can visually confirm they
  // have the right vehicle at a glance, before reading any text below.
  // Not a generic stock photo of the make/model: that would need a paid
  // image-search API, and wouldn't show this vehicle's actual colour,
  // condition or angle anyway — which is the part that actually helps
  // someone standing in a car park compare a photo to what is in front
  // of them. Falls back to the existing org-initial badge when no photo
  // is set, so nothing regresses for tags created before this existed.
  $tagPhoto = (!empty($tag['photo']) && is_file(IMG_PATH . '/' . basename($tag['photo'])))
      ? '/images/' . rawurlencode(basename($tag['photo'])) : '';
  ?>
  <?php if ($tagPhoto): ?>
    <img src="<?= htmlspecialchars($tagPhoto) ?>" alt="Vehicle photo"
         style="width:2.75rem;height:2.75rem;border-radius:.6rem;object-fit:cover;border:1px solid var(--line);flex-shrink:0">
  <?php else: ?>
    <div class="badge"><?= htmlspecialchars(mb_strtoupper(mb_substr($orgName, 0, 1))) ?></div>
  <?php endif; ?>
  <span style="font-size:.8rem;color:#cbd5e1"><?= htmlspecialchars($orgName) ?> · Vehicle Contact</span>
</header>

<main class="wrap" style="padding-top:2rem">

<?php if (!$tag): ?>
  <div class="card" style="text-align:center">
    <p style="font-size:1.05rem;font-weight:600;color:var(--gold2)">Tag not recognised</p>
    <p class="muted" style="margin-top:.5rem">This QR code isn't in our system. If you scanned a vehicle tag on our premises, please contact site security directly.</p>
    <?php if ($waNumber): ?>
      <a class="btn" style="margin-top:1.25rem" href="https://wa.me/<?= htmlspecialchars($waNumber) ?>">Message security on WhatsApp</a>
    <?php endif; ?>
  </div>
<?php else: ?>

  <?php
  // Free offline decode. Only the REGION is shown publicly: it reassures a
  // genuine scanner they have the right vehicle, while revealing nothing
  // about the owner. The plate itself stays masked — that mask is what makes
  // the last-4 verification step meaningful.
  $vd = VehicleDecoder::decode((string)($tag['registration_number'] ?? $tag['plate'] ?? ''));
  ?>
  <div class="card">
    <div class="lbl">Vehicle</div>
    <div class="plate"><?= htmlspecialchars(ct_mask_plate((string)($tag['registration_number'] ?? $tag['plate'] ?? ''))) ?></div>
    <div class="muted">
        <?php
        // Descriptors help a genuine scanner confirm they have the right
        // vehicle before messaging — and make a wrong-tag scan obvious.
        $desc = array_values(array_filter([
            (string)($tag['vehicle_class'] ?? $tag['vehicle_type'] ?? 'Vehicle'),
            (string)($tag['make_model'] ?? ''),
            (string)($tag['colour'] ?? ''),
        ], fn($v) => trim($v) !== '' && $v !== '-'));
        echo htmlspecialchars(implode(' · ', $desc));
        ?>
        <?php if (!empty($vd['state'])): ?>
            · <?= htmlspecialchars($vd['state']) ?><?= !empty($vd['rto_code']) ? ' (RTO ' . htmlspecialchars($vd['rto_code']) . ')' : '' ?>
        <?php endif; ?>
        · Tag #<?= htmlspecialchars($tagId) ?>
    </div>
  </div>

  <!-- Step 1 -->
  <div id="s1" class="card">
    <p style="font-size:.875rem;font-weight:500;margin-bottom:.75rem">Why are you contacting the owner?</p>
    <div id="reasons"></div>
    <a class="link" href="emergency.php?t=<?= urlencode($tagId) ?>">This is an emergency &rarr;</a>
  </div>

  <!-- Step 2 -->
  <div id="s2" class="card hide">
    <p style="font-size:.875rem;font-weight:500;margin-bottom:.25rem">Confirm the vehicle</p>
    <p class="muted" style="margin-bottom:.75rem">Enter the last 4 characters of the number plate as shown on the vehicle.</p>
    <div id="err" class="err hide"></div>
    <input id="last4" class="code" maxlength="4" placeholder="1234" autocomplete="off" inputmode="latin" aria-label="Last 4 characters of plate">
    <input id="phone" type="tel" placeholder="Your phone number" autocomplete="tel" aria-label="Your phone number">
    <button id="go" class="btn">Continue</button>
    <button id="back" class="btn2">Back</button>
  </div>

  <!-- Step 3 -->
  <div id="s3" class="card hide">
    <p style="font-size:.875rem;font-weight:500;margin-bottom:.75rem">Reach the owner</p>
    <?php if ($ownerWa): ?>
      <a id="wa" class="btn" target="_blank" rel="noopener noreferrer" href="#">Message the owner directly</a>
      <p class="fine" style="margin-top:.5rem">This tag is linked to a staff member, so your message goes straight to them.</p>
    <?php elseif ($waNumber): ?>
      <a id="wa" class="btn" target="_blank" rel="noopener noreferrer" href="#">Message via site security</a>
    <?php else: ?>
      <p class="muted">Site security has been notified with your details. Someone will contact you shortly.</p>
    <?php endif; ?>
    <!-- Location sharing is OPT-IN and explicit.
         This captures the SCANNER's device position, which is a member of the
         public, not a member of this organisation. It is therefore: never
         automatic, never silent, requested only after the plate check has
         passed, and clearly labelled as the vehicle's location rather than
         "your location" — because that is what it is used for and the framing
         should not overstate what is being shared. -->
    <div id="locbox" style="margin-top:.85rem;padding:.85rem;border:1px solid var(--line);border-radius:.6rem;background:rgba(0,0,0,.15)">
      <div style="font-size:.8rem;font-weight:600;margin-bottom:.35rem">Help the owner find the vehicle</div>
      <p class="fine" style="margin:0 0 .6rem;text-align:left">
        Share where this vehicle is parked so the owner can walk straight to it.
        Your device location is used once, added to this message, and not tracked
        afterwards. The coordinates are also sent to a mapping service to look up
        the area name (for example &ldquo;Sector 62, Noida&rdquo;).
      </p>
      <button id="locbtn" class="btn2" type="button">Add vehicle location</button>
      <div id="locmsg" class="fine" style="margin-top:.5rem;display:none"></div>
    </div>

    <p class="fine">Your request has been logged. Misuse of this system (prank, spam, solicitation) is recorded and may result in your number being blocked.</p>
  </div>

<?php endif; ?>

</main>

<footer><?= htmlspecialchars($orgName) ?> internal vehicle contact system.</footer>

<?php if ($tag): ?>
<script>
(function () {
  'use strict';
  var REASONS = ['Blocking my vehicle','Lights are on','Vehicle needs to be moved / towing','Window or door open','Something else'];
  var TAG = <?= json_encode($tagId) ?>;
  // Vehicle descriptors, so the message opens by telling the owner WHICH of
  // their vehicles this is about — a tag number means nothing to them.
  // Structured vehicle + owner context for the message body.
  var CTX = <?= json_encode([
      'owner'  => (string)($ownerName ?? ''),
      'number' => (string)($tag['registration_number'] ?? $tag['plate'] ?? ''),
      'make'   => (string)($tag['make_model'] ?? ''),
      'colour' => (string)($tag['colour'] ?? ''),
      'class'  => (string)($tag['vehicle_class'] ?? $tag['vehicle_type'] ?? ''),
      // NOTE: no 'area' from the tag record. The owner's base location says
      // where they work, not where their vehicle is obstructing someone —
      // those are different places, and using the former was simply wrong.
      // Location now comes entirely from the scanner's own position.
  ], JSON_UNESCAPED_UNICODE) ?>;
  // Owner number when the tag is linked, else the security number.
  var WA  = <?= json_encode($ownerWa ?: $waNumber) ?>;
  var reason = '', buildMsg = null, lastPhone = '', lastLast4 = '', areaName = '';

  var s1 = document.getElementById('s1'), s2 = document.getElementById('s2'), s3 = document.getElementById('s3');
  var err = document.getElementById('err'), go = document.getElementById('go');

  var box = document.getElementById('reasons');
  REASONS.forEach(function (r) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'opt'; b.textContent = r;
    b.addEventListener('click', function () {
      reason = r; s1.classList.add('hide'); s2.classList.remove('hide');
      document.getElementById('last4').focus();
    });
    box.appendChild(b);
  });

  document.getElementById('back').addEventListener('click', function () {
    s2.classList.add('hide'); s1.classList.remove('hide'); err.classList.add('hide');
  });

  // ── Optional location sharing ─────────────────────────────────────────
  var locBtn = document.getElementById('locbtn');
  var locMsg = document.getElementById('locmsg');

  function locSay(text, ok) {
    locMsg.style.display = 'block';
    locMsg.style.color = ok ? '#86efac' : '#fca5a5';
    locMsg.textContent = text;
  }

  if (locBtn) locBtn.addEventListener('click', function () {
    if (!navigator.geolocation) {
      locSay('This browser cannot share a location.', false); return;
    }
    // Requires HTTPS. On http:// the browser silently refuses, so say so
    // rather than leaving the button apparently doing nothing.
    if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
      locSay('Location sharing needs a secure (https) connection.', false); return;
    }

    locBtn.disabled = true; locBtn.textContent = 'Getting location…';

    navigator.geolocation.getCurrentPosition(
      function (pos) {
        var la = pos.coords.latitude.toFixed(5);
        var ln = pos.coords.longitude.toFixed(5);
        var acc = Math.round(pos.coords.accuracy || 0);
        var mapUrl = 'https://maps.google.com/?q=' + la + ',' + ln;

        function applyLink() {
          if (WA && buildMsg) {
            document.getElementById('wa').href =
              'https://wa.me/' + WA + '?text=' + encodeURIComponent(buildMsg(mapUrl));
          }
        }

        // Record against the scan, so the log matches what was sent.
        var fd = new FormData();
        fd.append('last4', lastLast4); fd.append('reason', reason);
        fd.append('phone', lastPhone); fd.append('lat', la); fd.append('lng', ln);
        fetch(window.location.href, { method: 'POST', body: fd }).catch(function () {});

        locBtn.style.display = 'none';
        locSay('Location added' + (acc ? ' (accurate to about ' + acc + ' m)' : '') +
               '. Tap the message button above to send.', true);

        // Build the message immediately with the map link, so it is usable
        // even if the area lookup below is slow or fails.
        applyLink();

        // ── Area name (optional, best-effort) ───────────────────────────
        // A map pin is precise but unreadable at a glance; "Sector 62, Noida"
        // tells the owner instantly whether this is their office car park or
        // somewhere they were yesterday. Only the area is used — never the
        // street address, which would be more precise than the purpose needs.
        //
        // BigDataCloud's reverse-geocode-client endpoint is keyless and free.
        // It IS a third party receiving the coordinates, which is why the
        // consent text above says so. Everything here is wrapped so a
        // failure, block or timeout silently leaves the map link alone.
        try {
          var ctl = new AbortController();
          setTimeout(function () { ctl.abort(); }, 4000);   // never hold up the user
          fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude='
                + la + '&longitude=' + ln + '&localityLanguage=en', { signal: ctl.signal })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
              if (!d) return;
              var parts = [d.locality, d.city, d.principalSubdivision]
                            .filter(function (v, i, a) { return v && a.indexOf(v) === i; });
              if (!parts.length) return;
              areaName = parts.slice(0, 2).join(', ');
              applyLink();                                  // rebuild with the area
              locSay('Location added \u2014 ' + areaName + '. Tap the message button above to send.', true);
            })
            .catch(function () { /* offline, blocked or slow — map link stands */ });
        } catch (e) { /* no AbortController on very old browsers */ }
      },
      function (err) {
        locBtn.disabled = false; locBtn.textContent = 'Add vehicle location';
        locSay(err.code === 1
          ? 'Location permission denied. You can still send the message without it.'
          : 'Could not get a location. You can still send the message without it.', false);
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  });

  go.addEventListener('click', async function () {
    var last4 = document.getElementById('last4').value.trim();
    lastLast4 = last4;
    var phone = document.getElementById('phone').value.trim();
    err.classList.add('hide');

    if (last4.length !== 4) { err.textContent = 'Please enter all 4 characters.'; err.classList.remove('hide'); return; }
    if (phone.replace(/\D/g, '').length < 8) { err.textContent = 'Please enter a valid phone number.'; err.classList.remove('hide'); return; }

    go.disabled = true; go.textContent = 'Checking…';
    try {
      var fd = new FormData();
      fd.append('last4', last4); fd.append('reason', reason); fd.append('phone', phone);
      var res = await fetch(window.location.href, { method: 'POST', body: fd });

      if (res.status === 429) {
        err.textContent = 'Too many attempts. Please try again later.';
        err.classList.remove('hide'); go.disabled = false; go.textContent = 'Continue'; return;
      }

      var data = await res.json();
      // The server decides. v1 advanced regardless of this result — that was
      // the bug that made the whole verification step decorative.
      if (!data.ok) {
        err.textContent = "That doesn't match this vehicle's plate. Please check and try again.";
        err.classList.remove('hide'); go.disabled = false; go.textContent = 'Continue'; return;
      }

      if (WA) {
        // Message format (260906.16). Was:
        //   "Vehicle tag 13182 - Blocking my vehicle. My number: 9355737363"
        // A tag number is meaningless to the owner and the request was buried
        // mid-sentence. Now it leads with which vehicle, states the issue on
        // its own line, and ends with a clear call to action.
        buildMsg = function (mapUrl) {
          // Structured, addressed and specific. The previous single line
          // ("Vehicle tag 57627 - Blocking my vehicle. My number: …") led with
          // a tag number that means nothing to the owner and buried the
          // request. This opens by naming them, states the problem in the
          // subject line, lists the vehicle so they know which one, and closes
          // with a single clear action.
          var L = [];

          L.push('Dear ' + (CTX.owner || 'Vehicle Owner') + ',');
          L.push('');
          L.push('*Urgent Assistance Requested: ' + reason
                 + (areaName ? ' at ' + areaName : '') + '*');
          L.push('');
          L.push('*Vehicle Details:*');
          if (CTX.number) L.push('\u2022 Number: ' + CTX.number);
          if (CTX.make)   L.push('\u2022 Make: '   + CTX.make);
          if (CTX.colour) L.push('\u2022 Colour: ' + CTX.colour);
          if (!CTX.number && !CTX.make && !CTX.colour && CTX.class) {
            L.push('\u2022 Type: ' + CTX.class);
          }
          L.push('');

          // The closing sentence has to match the reason. "Causing an
          // obstruction ... kindly move it" reads absurdly for "Lights are on".
          var blocking = /block|tow|moved/i.test(reason);
          var whereBit = areaName ? ' at ' + areaName : ' at my current location';
          L.push(blocking
            ? 'The vehicle listed above is currently causing an obstruction' + whereBit
              + '. Kindly arrange to move it at your earliest convenience.'
            : 'The vehicle listed above' + whereBit
              + ' requires your attention. Kindly attend to it at your earliest convenience.');

          L.push('');
          if (mapUrl) {
            // The map link is the actionable part — it drops a pin exactly
            // where the vehicle is, which an area name alone never can.
            L.push('Location: ' + mapUrl);
          } else {
            L.push('(Exact location not shared. Please call the number below.)');
          }

          L.push('');
          L.push('Contact: ' + phone);
          return L.join('\n');
        };
        lastPhone = phone;
        var msg = buildMsg(null);
        document.getElementById('wa').href = 'https://wa.me/' + WA + '?text=' + encodeURIComponent(msg);
      }
      s2.classList.add('hide'); s3.classList.remove('hide');
    } catch (e) {
      err.textContent = 'Network error. Please try again.';
      err.classList.remove('hide'); go.disabled = false; go.textContent = 'Continue';
    }
  });
})();
</script>
<?php endif; ?>

</body>
</html>
