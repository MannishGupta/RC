<?php
// Version: 3.1.0
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    exit;
}
if (!class_exists('NoticeStore') && is_file(BASE_PATH . '/app/Compliance/NoticeStore.php')) {
    require_once BASE_PATH . '/app/Compliance/NoticeStore.php';
}
if (class_exists('NoticeStore')) {
    NoticeStore::ensureDefaults();
}
$company = class_exists('AppDB') ? (AppDB::read('company') ?: []) : [];
$name = trim((string) ($company['name'] ?? 'Organization'));
$gro = trim((string) ($company['dpdp']['grievance_officer'] ?? $company['grievance_officer'] ?? 'Not designated yet'));
$groEmail = trim((string) ($company['dpdp']['grievance_email'] ?? $company['email'] ?? ''));
$privacy = class_exists('NoticeStore') ? NoticeStore::get('privacy', 'en') : null;
?>
<section class="rc-card" style="max-width:720px;padding:1.25rem;background:var(--rc-card);border:1px solid var(--rc-border);border-radius:12px;color:var(--rc-ink)">
  <h1 style="font-size:1.25rem;margin:0 0 .5rem">Data principal rights</h1>
  <p style="color:var(--rc-ink-muted);font-size:.9rem">Under the Digital Personal Data Protection Act, 2023, you may exercise the rights below with <strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong> as Data Fiduciary.</p>
  <ul style="line-height:1.6">
    <li><strong>Access</strong> — request a copy of personal data we hold about you in this Resource Centre tenant.</li>
    <li><strong>Correction</strong> — request correction of inaccurate data.</li>
    <li><strong>Erasure</strong> — request deletion where legally applicable.</li>
    <li><strong>Nomination</strong> — nominate another individual to exercise rights in case of death or incapacity.</li>
  </ul>
  <h2 style="font-size:1rem;margin:1.25rem 0 .35rem">Grievance Officer</h2>
  <p style="margin:0"><?= htmlspecialchars($gro, ENT_QUOTES, 'UTF-8') ?>
    <?php if ($groEmail !== ''): ?>
      · <a href="mailto:<?= htmlspecialchars($groEmail, ENT_QUOTES, 'UTF-8') ?>" style="color:var(--rc-accent)"><?= htmlspecialchars($groEmail, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
  </p>
  <p style="font-size:.85rem;color:var(--rc-ink-muted);margin-top:.75rem">Email the officer with subject “DPDP rights request”. Target acknowledgement within 72 hours (organisational SLA).</p>
  <?php if ($privacy): ?>
  <h2 style="font-size:1rem;margin:1.25rem 0 .35rem"><?= htmlspecialchars($privacy['title'], ENT_QUOTES, 'UTF-8') ?></h2>
  <p style="font-size:.9rem;white-space:pre-wrap"><?= htmlspecialchars($privacy['body'], ENT_QUOTES, 'UTF-8') ?></p>
  <p style="font-size:.75rem;color:var(--rc-ink-muted)">Notice version <?= htmlspecialchars($privacy['version'], ENT_QUOTES, 'UTF-8') ?><?= $privacy['published_at'] !== '' ? ' · ' . htmlspecialchars($privacy['published_at'], ENT_QUOTES, 'UTF-8') : '' ?></p>
  <?php endif; ?>
  <p style="margin-top:1rem">
    <button type="button" class="rc-consent-withdraw" style="min-height:44px;padding:8px 14px;border-radius:6px;border:1px solid var(--rc-border);background:var(--rc-paper);cursor:pointer"
      onclick="try{localStorage.removeItem('rc_consent_v1');alert('Optional consent cleared on this device.');}catch(e){}">
      Withdraw optional consent (this device)
    </button>
  </p>
</section>
