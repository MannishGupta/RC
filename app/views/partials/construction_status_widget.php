<?php
/**
 * Project Construction Status — compact embed for Team (and similar) screens.
 * Read-only mirror of /status/data JSON. Version: 20261002.02
 */
declare(strict_types=1);
if (!defined('BASE_PATH')) {
    return;
}

$statusDir  = BASE_PATH . '/status';
$statusData = $statusDir . '/data';
if (!is_dir($statusData)) {
    $statusData = $statusDir;
}

$stRead = static function (string $file) use ($statusData): array {
    $p = rtrim($statusData, '/\\') . '/' . $file;
    if (!is_readable($p)) {
        return [];
    }
    $d = json_decode((string) @file_get_contents($p), true);
    return is_array($d) ? $d : [];
};

$projects = $stRead('projects.json');
$components = $stRead('components.json');
$updates = $stRead('updates.json');
$installed = is_dir($statusDir) && ($projects !== [] || is_dir(BASE_PATH . '/status/data'));

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$origin = $host !== '' ? (($https ? 'https://' : 'http://') . $host) : '';
$portalUrl = is_dir($statusDir) ? ($origin !== '' ? $origin . '/status/' : 'status/') : 'https://fusionlimited.in/status/';

$stTs = static function (string $d): int {
    $d = trim($d);
    if ($d === '') {
        return 0;
    }
    $t = strtotime(str_replace('-', ' ', $d));
    return $t ?: 0;
};

$activeProjects = [];
foreach ($projects as $p) {
    if (!is_array($p)) {
        continue;
    }
    $activeProjects[] = $p;
}
usort($activeProjects, static function ($a, $b) {
    return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});
$activeProjects = array_slice($activeProjects, 0, 4);

$recent = array_values(array_filter($updates, 'is_array'));
usort($recent, static fn($a, $b) => $stTs((string) ($b['date'] ?? '')) <=> $stTs((string) ($a['date'] ?? '')));
$recent = array_slice($recent, 0, 3);

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$pctFor = static function (array $project) use ($components): int {
    $pid = (string) ($project['id'] ?? '');
    if ($pid === '') {
        return (int) ($project['progress'] ?? $project['pct'] ?? 0);
    }
    $list = [];
    foreach ($components as $c) {
        if (!is_array($c)) {
            continue;
        }
        if ((string) ($c['project_id'] ?? '') === $pid) {
            $list[] = (int) ($c['progress'] ?? $c['pct'] ?? 0);
        }
    }
    if ($list === []) {
        return max(0, min(100, (int) ($project['progress'] ?? $project['pct'] ?? 0)));
    }
    return (int) round(array_sum($list) / count($list));
};
?>
<section id="rc-construction-status" class="rc-construct-widget w-full max-w-full mb-4" aria-label="Project construction status">
  <div class="rc-construct-inner">
    <div class="rc-construct-head">
      <div class="rc-construct-title-block">
        <span class="rc-construct-kicker">Programme delivery</span>
        <h2 class="rc-construct-title">Project Construction Status</h2>
      </div>
      <div class="rc-construct-actions">
        <a class="rc-construct-link" href="?tab=status">Open status tab</a>
        <a class="rc-construct-link rc-construct-link-ext" href="<?= $h($portalUrl) ?>" target="_blank" rel="noopener">Full portal ↗</a>
      </div>
    </div>

    <?php if (!$installed || $activeProjects === []): ?>
      <p class="rc-construct-empty">No construction projects are published yet. When the status portal has projects, progress cards appear here.</p>
    <?php else: ?>
      <div class="rc-construct-grid">
        <?php foreach ($activeProjects as $proj):
            $name = trim((string) ($proj['name'] ?? $proj['title'] ?? 'Project'));
            $loc = trim((string) ($proj['location'] ?? $proj['city'] ?? ''));
            $pct = $pctFor($proj);
            $pct = max(0, min(100, $pct));
            ?>
          <article class="rc-construct-card">
            <div class="rc-construct-card-top">
              <h3 class="rc-construct-name"><?= $h($name) ?></h3>
              <span class="rc-construct-pct"><?= (int) $pct ?>%</span>
            </div>
            <?php if ($loc !== ''): ?>
              <p class="rc-construct-loc"><?= $h($loc) ?></p>
            <?php endif; ?>
            <div class="rc-construct-bar" role="progressbar" aria-valuenow="<?= (int) $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progress <?= (int) $pct ?> percent">
              <span class="rc-construct-bar-fill" style="width:<?= (int) $pct ?>%"></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ($recent !== []): ?>
        <ul class="rc-construct-updates">
          <?php foreach ($recent as $u):
              $title = trim((string) ($u['title'] ?? $u['note'] ?? $u['text'] ?? 'Update'));
              $date = trim((string) ($u['date'] ?? ''));
              ?>
            <li>
              <span class="rc-construct-upd-title"><?= $h($title) ?></span>
              <?php if ($date !== ''): ?><time class="rc-construct-upd-date"><?= $h($date) ?></time><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<style>
.rc-construct-widget { --rc-c-border: color-mix(in srgb, var(--rc-border, #334155) 80%, transparent); }
.rc-construct-inner {
  border: 1px solid var(--rc-border, #e2e8f0);
  border-radius: var(--rc-radius, 0.75rem);
  background: var(--rc-surface, #fff);
  padding: 0.875rem 1rem 1rem;
  box-shadow: var(--rc-shadow, 0 1px 2px rgb(0 0 0 / 0.05));
}
html[data-theme="dark"] .rc-construct-inner,
html.theme-dark .rc-construct-inner {
  background: #1e293b;
  border-color: #334155;
  color: #f1f5f9;
}
html[data-theme="reserve"] .rc-construct-inner,
html.theme-reserve .rc-construct-inner {
  background: rgba(26, 10, 12, 0.92);
  border-color: rgba(201, 162, 39, 0.35);
  color: #faf6f0;
}
.rc-construct-head {
  display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
  gap: 0.5rem 1rem; margin-bottom: 0.75rem;
}
.rc-construct-kicker {
  display: block; font-size: 0.625rem; font-weight: 700; letter-spacing: 0.08em;
  text-transform: uppercase; color: #64748b;
}
html[data-theme="reserve"] .rc-construct-kicker { color: #c9a227; }
.rc-construct-title {
  margin: 0; font-size: 0.95rem; font-weight: 800; letter-spacing: -0.02em;
  color: inherit;
}
.rc-construct-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.rc-construct-link {
  font-size: 0.6875rem; font-weight: 700; text-decoration: none;
  padding: 0.35rem 0.65rem; border-radius: 999px;
  border: 1px solid var(--rc-border, #cbd5e1); color: inherit; opacity: 0.95;
}
.rc-construct-link:hover { opacity: 1; border-color: #6366f1; }
.rc-construct-empty { margin: 0; font-size: 0.8125rem; color: #64748b; }
.rc-construct-grid {
  display: grid; gap: 0.65rem;
  grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
}
.rc-construct-card {
  border-radius: 0.65rem; padding: 0.65rem 0.75rem;
  background: rgba(148, 163, 184, 0.08);
  border: 1px solid rgba(148, 163, 184, 0.2);
}
.rc-construct-card-top { display: flex; justify-content: space-between; gap: 0.5rem; align-items: baseline; }
.rc-construct-name { margin: 0; font-size: 0.8125rem; font-weight: 700; line-height: 1.3; }
.rc-construct-pct { font-size: 0.75rem; font-weight: 800; font-variant-numeric: tabular-nums; color: #059669; }
html[data-theme="reserve"] .rc-construct-pct { color: #c9a227; }
.rc-construct-loc { margin: 0.2rem 0 0.45rem; font-size: 0.6875rem; opacity: 0.75; }
.rc-construct-bar {
  height: 0.35rem; border-radius: 999px; background: rgba(148, 163, 184, 0.25); overflow: hidden;
}
.rc-construct-bar-fill {
  display: block; height: 100%; border-radius: inherit;
  background: linear-gradient(90deg, #059669, #34d399);
}
html[data-theme="reserve"] .rc-construct-bar-fill {
  background: linear-gradient(90deg, #8b6914, #c9a227);
}
.rc-construct-updates {
  list-style: none; margin: 0.75rem 0 0; padding: 0.5rem 0 0;
  border-top: 1px solid rgba(148, 163, 184, 0.25);
  display: flex; flex-direction: column; gap: 0.35rem;
}
.rc-construct-updates li {
  display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.35rem 0.75rem;
  font-size: 0.75rem;
}
.rc-construct-upd-title { font-weight: 600; }
.rc-construct-upd-date { opacity: 0.65; font-variant-numeric: tabular-nums; }
</style>
