<?php // Version: 260916.14
declare(strict_types=1);
// vehicle-tags/emergency.php — PUBLIC escalation page for accidents / urgent issues.
//
// FIXES vs the supplied v1:
//  * HARDCODED ABSOLUTE REDIRECT: v1 did
//      header('Location: /car-tags/emergency.php?t=...')
//    which breaks if the module is ever installed anywhere but the exact
//    document root path /car-tags/. Now uses a relative self-redirect.
//  * NO RATE LIMIT: v1 accepted unlimited POSTs, so this form could be used
//    to flood the scan log (and the duty manager) with junk. Now throttled
//    per IP, sharing the same throttle store as index.php.
//  * NO INPUT BOUNDS: free-text detail was written to the log at whatever
//    length was posted. Now length-capped and tag-stripped.
//  * Data moved to AppDB (data/cartag_logs.json) to match the rest of the app.
//  * Added noindex; kept the 112 helpline prominent.

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

function ct_find_tag(string $tagId): ?array {
    if ($tagId === '') return null;
    foreach (AppDB::read('cartags') ?: [] as $t) {
        if ((string)($t['tag_id'] ?? '') === $tagId) return $t;
    }
    return null;
}

function ct_log(array $entry): void {
    $logs = AppDB::read('cartag_logs') ?: [];
    $entry['id'] = bin2hex(random_bytes(6));
    $entry['ts'] = date('c');
    $logs[] = $entry;
    if (count($logs) > 5000) $logs = array_slice($logs, -5000);
    AppDB::save('cartag_logs', $logs);
}

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
    return $rec['n'] <= 12;
}

$tagId = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string)($_GET['t'] ?? '')));
$tag   = ct_find_tag($tagId);
$sent  = isset($_GET['sent']);
$limited = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $tag) {
    if (!ct_rate_ok()) {
        $limited = true;
    } else {
        ct_log([
            'tag_id'        => $tagId,
            'reason'        => 'EMERGENCY: ' . mb_substr(trim(strip_tags((string)($_POST['detail'] ?? ''))), 0, 500),
            'scanner_phone' => mb_substr(preg_replace('/[^0-9+]/', '', (string)($_POST['phone'] ?? '')), 0, 20),
            'ip'            => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'verified'      => true,
            'emergency'     => true,
        ]);
        // Relative redirect — v1 hardcoded /car-tags/ which broke on any
        // other install path.
        header('Location: ?t=' . urlencode($tagId) . '&sent=1');
        exit;
    }
}

$company  = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$orgName  = (string)($company['name'] ?? 'Vehicle Contact');
$waNumber = preg_replace('/\D/', '', (string)($company['security_whatsapp'] ?? $company['phone'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#450a0a">
<title>Emergency<?= $orgName ? ' · ' . htmlspecialchars($orgName) : '' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter','Aptos','Noto Sans Devanagari',system-ui,sans-serif;background:#450a0a;color:#fee2e2;min-height:100vh;-webkit-font-smoothing:antialiased;-webkit-tap-highlight-color:transparent}
  .wrap{max-width:28rem;margin:0 auto;padding:2rem 1.25rem calc(2rem + env(safe-area-inset-bottom))}
  .card{background:rgba(127,29,29,.45);border:1px solid rgba(248,113,113,.4);border-radius:1rem;padding:1.25rem}
  h1{font-size:1.1rem;font-weight:700;color:#fecaca}
  p.sub{font-size:.8rem;color:rgba(254,226,226,.8);margin:.5rem 0 1rem;line-height:1.6}
  textarea,input{width:100%;background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.12);border-radius:.5rem;padding:.85rem 1rem;color:#fff;font-family:inherit;font-size:1rem;margin-bottom:.75rem}
  textarea:focus,input:focus{outline:2px solid #f87171;outline-offset:1px}
  .btn{display:block;width:100%;text-align:center;background:#dc2626;color:#fff;font-weight:600;padding:.9rem;border:0;border-radius:.5rem;font-size:.9rem;font-family:inherit;cursor:pointer}
  .link{display:block;text-align:center;margin-top:.85rem;font-size:.75rem;color:#fecaca}
  .ok{font-size:.875rem;color:#86efac;line-height:1.6}
  .helpline{text-align:center;font-size:.8rem;color:#fca5a5;margin-top:1.25rem}
  .helpline strong{font-size:1.35rem;color:#fff;display:block;margin-top:.35rem;letter-spacing:.05em}
  .warn{background:rgba(0,0,0,.25);border:1px solid rgba(248,113,113,.3);border-radius:.5rem;padding:.75rem;font-size:.8rem;color:#fecaca;margin-bottom:.75rem}
</style>
</head>
<body>
<main class="wrap">
  <div class="card">
    <h1>Emergency contact</h1>
    <p class="sub">Use this only for a genuine emergency — an accident, a medical need, or urgently reaching the vehicle owner or their family. All submissions are logged.</p>

    <?php if ($sent): ?>
      <p class="ok">Duty manager notified. If this is life-threatening, call <strong>112</strong> now — do not wait for a callback.</p>
    <?php elseif ($limited): ?>
      <div class="warn">Too many submissions from this device. If this is a real emergency, call <strong>112</strong> or contact site security directly.</div>
    <?php elseif ($tag): ?>
      <form method="post">
        <textarea name="detail" required rows="3" maxlength="500" placeholder="What is the emergency?" aria-label="Describe the emergency"></textarea>
        <input name="phone" type="tel" required placeholder="Your phone number" autocomplete="tel" aria-label="Your phone number">
        <button class="btn" type="submit">Notify duty manager now</button>
      </form>
      <?php if ($waNumber): ?>
      <a class="link" href="https://wa.me/<?= htmlspecialchars($waNumber) ?>?text=<?= rawurlencode('EMERGENCY - Vehicle tag ' . $tagId) ?>" target="_blank" rel="noopener noreferrer">Or message security directly on WhatsApp</a>
      <?php endif; ?>
    <?php else: ?>
      <p class="ok" style="color:#fecaca">Tag not recognised. If this is an emergency, call 112 immediately.</p>
    <?php endif; ?>
  </div>

  <p class="helpline">All-India emergency helpline<strong>112</strong></p>
</main>
</body>
</html>
