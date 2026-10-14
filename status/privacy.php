<?php

declare(strict_types=1);

require_once __DIR__ . '/lib.php';
AppLocale::boot();

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   isHttps() ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name(cfg('site.cookie_prefix', 'fusion') . '_public_session');
session_start();

if (empty($_SESSION['_consent_nonce'])) {
    $_SESSION['_consent_nonce'] = bin2hex(random_bytes(16));
}
$nonce = (string)$_SESSION['_consent_nonce'];

header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
     . "script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
     . "font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; "
     . "base-uri 'self'; form-action 'self'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

$company   = (string)cfg('dpdp.fiduciary_name', cfg('brand.company', ''));
$email     = (string)cfg('dpdp.fiduciary_email', '');
$address   = (string)cfg('dpdp.fiduciary_address', '');
$gOfficer  = (string)cfg('dpdp.grievance_officer', 'Grievance Officer');
$gEmail    = (string)cfg('dpdp.grievance_email', $email);
$gPhone    = (string)cfg('dpdp.grievance_phone', '');
$retention = (int)cfg('dpdp.retention_days', 180);
$updated   = (string)cfg('dpdp.notice_updated', '');
$granted   = analyticsConsentGiven();

$flash = '';
if (isset($_GET['consent'])) {
    $flash = $_GET['consent'] === 'withdrawn'
        ? 'Your consent has been withdrawn, and the analytics records held against your visitor ID have been erased.'
        : 'Your consent preference has been saved.';
} elseif (isset($_GET['erased'])) {
    $n = max(0, (int)$_GET['erased']);
    $flash = $n > 0
        ? "Erased {$n} analytics record(s) held against that visitor ID."
        : 'No analytics records were found for that visitor ID. Nothing to erase.';
}

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="<?= $e(AppLocale::lang()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, follow">
<title>Privacy Notice — <?= $e($company) ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= @filemtime(__DIR__ . '/assets/app.css') ?: time() ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-nav fixed-top d-flex align-items-center justify-content-between px-3 px-md-4">
    <a href="<?= $e(appUrl('index.php')) ?>" class="d-inline-flex align-items-center gap-2">
        <span class="site-nav-title">&larr; Construction Progress Dashboard</span>
    </a>
</header>

<main id="main" class="legal-wrap">
    <h1>Privacy Notice</h1>
    <p class="legal-meta">
        Issued under the Digital Personal Data Protection Act, 2023 (India).
        <?php if ($updated !== ''): ?>Last updated <?= $e($updated) ?>.<?php endif; ?>
    </p>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-info" role="status"><?= $e($flash) ?></div>
    <?php endif; ?>

    <h2>1. Who processes your data</h2>
    <p>
        <strong><?= $e($company) ?></strong> is the Data Fiduciary for this
        Construction Progress Dashboard.
        <?php if ($address !== ''): ?><br><?= $e($address) ?><?php endif; ?>
        <?php if ($email !== ''): ?><br>Email: <a href="mailto:<?= $e($email) ?>"><?= $e($email) ?></a><?php endif; ?>
    </p>

    <h2>2. What we collect, and why</h2>
    <p>
        This dashboard publishes construction progress. It has no login, no sign-up
        and no payment, so it does not ask you for your name, address, phone number
        or email. What it does record is limited to the following.
    </p>
    <table>
        <thead>
            <tr><th scope="col">Data</th><th scope="col">Purpose</th><th scope="col">Basis</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>A randomly generated visitor ID and session ID, stored in your browser</td>
                <td>To count unique visits without identifying you</td>
                <td>Your consent</td>
            </tr>
            <tr>
                <td>Your IP address, stored only as an irreversible salted hash — never in full</td>
                <td>To distinguish visitors and limit abuse of the page</td>
                <td>Your consent</td>
            </tr>
            <tr>
                <td>A shortened browser/device description, the page you viewed, and the site you arrived from</td>
                <td>To understand which towers and updates people look at</td>
                <td>Your consent</td>
            </tr>
            <tr>
                <td>Which tower filters, photographs and videos you open, and time spent on the page</td>
                <td>To improve what we publish and how often</td>
                <td>Your consent</td>
            </tr>
            <tr>
                <td>A cookie recording your consent choice</td>
                <td>To remember your decision and stop asking</td>
                <td>Necessary for the service — set only once you choose</td>
            </tr>
        </tbody>
    </table>
    <p>
        <strong>Nothing in the first four rows is recorded until you consent.</strong>
        If you choose "Essential only", no analytics request is ever sent.
    </p>

    <h2>3. What we do not do</h2>
    <ul>
        <li>We do not sell, rent or share this data with advertisers or data brokers.</li>
        <li>We do not use it for advertising, profiling or automated decision-making.</li>
        <li>We do not place third-party advertising or social tracking cookies.</li>
        <li>We do not knowingly collect data from children. The dashboard is intended for
            prospective and existing purchasers and other adult stakeholders.</li>
        <li>We do not store your IP address in a readable form.</li>
    </ul>

    <h2>4. How long we keep it</h2>
    <p>
        Analytics records are automatically deleted <strong><?= (int)$retention ?> days</strong>
        after they are created. Deletion is automatic, not a manual process. Your consent
        choice is remembered for 180 days, after which you will be asked again.
    </p>

    <h2>5. Where it is stored</h2>
    <p>
        On the server that hosts this dashboard, located in India. The data is not
        transferred outside India by this application.
    </p>

    <h2>6. Your rights as a Data Principal</h2>
    <p>Under the Act you may:</p>
    <ul>
        <li><strong>Withdraw consent</strong> at any time — use the controls below. It is
            as easy to withdraw as it was to give.</li>
        <li><strong>Access</strong> a summary of the personal data we hold about you and
            how it is processed.</li>
        <li><strong>Correct, complete or update</strong> inaccurate data.</li>
        <li><strong>Erase</strong> your data when it is no longer needed — use the control
            below, or contact us.</li>
        <li><strong>Nominate</strong> another individual to exercise these rights on your
            behalf in the event of death or incapacity.</li>
        <li><strong>Raise a grievance</strong> with our Grievance Officer, and escalate to
            the Data Protection Board of India if you remain unsatisfied.</li>
    </ul>
    <p>
        Because we deliberately hold no name or contact details, the only handle that
        links records to you is the random visitor ID in your own browser. The tools
        below read it from your browser so you can act on it directly.
    </p>

    <h2>7. Your controls</h2>
    <div class="card card-fluent p-4 mb-4">
        <p class="mb-3">
            Current status:
            <strong><?= $granted ? 'Analytics consent granted' : 'Essential only — analytics not collected' ?></strong>
        </p>
        <p class="hk-note mb-3">
            Your visitor ID: <code id="vidOut">reading from your browser…</code>
        </p>

        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$granted): ?>
                <form method="POST" action="consent.php">
                    <input type="hidden" name="_nonce" value="<?= $e($nonce) ?>">
                    <input type="hidden" name="action" value="grant">
                    <button type="submit" class="btn btn-primary">Allow analytics</button>
                </form>
            <?php else: ?>
                <form method="POST" action="consent.php" class="js-vid-form">
                    <input type="hidden" name="_nonce" value="<?= $e($nonce) ?>">
                    <input type="hidden" name="action" value="withdraw">
                    <input type="hidden" name="visitor_id" value="" class="js-vid">
                    <button type="submit" class="btn btn-outline-danger">Withdraw consent and erase my data</button>
                </form>
            <?php endif; ?>

            <form method="POST" action="consent.php" class="js-vid-form">
                <input type="hidden" name="_nonce" value="<?= $e($nonce) ?>">
                <input type="hidden" name="action" value="erase">
                <input type="hidden" name="visitor_id" value="" class="js-vid">
                <button type="submit" class="btn">Erase my analytics records</button>
            </form>
        </div>
        <p class="consent-note">
            Erasure applies to records held against the visitor ID shown above. If you have
            cleared your browser storage since visiting, that ID is gone and the remaining
            records can no longer be linked to you by anyone, including us.
        </p>
    </div>

    <h2>8. Grievance Officer</h2>
    <p>
        <strong><?= $e($gOfficer) ?></strong><br>
        <?php if ($gEmail !== ''): ?>Email: <a href="mailto:<?= $e($gEmail) ?>"><?= $e($gEmail) ?></a><br><?php endif; ?>
        <?php if ($gPhone !== ''): ?>Telephone: <?= $e(AppLocale::phone($gPhone)) ?><br><?php endif; ?>
        We aim to respond to grievances within 30 days.
    </p>
    <p>
        If you are not satisfied with our response, you may escalate to the
        <strong>Data Protection Board of India</strong>.
    </p>

    <h2>9. Security</h2>
    <p>
        The dashboard is served over HTTPS. IP addresses are hashed with a secret salt
        before storage. Administrative access is password-protected, rate-limited and
        subject to an idle timeout. In the event of a personal data breach we will notify
        the Data Protection Board and affected Data Principals as the Act requires.
    </p>

    <h2>10. Changes to this notice</h2>
    <p>
        If we change what we collect or why, we will update this notice and ask for your
        consent again where the Act requires it.
    </p>

    <p class="legal-meta mt-5">
        <a href="<?= $e(appUrl('index.php')) ?>">&larr; Back to the dashboard</a>
    </p>
</main>

<script>
(function () {
  'use strict';
  // The visitor ID lives only in the visitor's own browser. Surface it so they
  // can exercise erasure without us holding any identifier for them.
  var vid = '';
  try { vid = localStorage.getItem('br_vid') || ''; } catch (e) { vid = ''; }

  var out = document.getElementById('vidOut');
  if (out) { out.textContent = vid || 'none found — nothing is stored for this browser'; }

  document.querySelectorAll('.js-vid').forEach(function (f) { f.value = vid; });

  document.querySelectorAll('.js-vid-form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!vid) {
        ev.preventDefault();
        alert('No visitor ID was found in this browser, so there is nothing linked to you to erase.');
      }
    });
  });
})();
</script>
</body>
</html>
