<?php
/**
 * Super Admin — Enterprise Control Plane & Tenant Overview
 * Version stamp: 260926.09
 */
declare(strict_types=1);

$isSa = !empty($isSuperAdmin) || !empty($viewData['isSuperAdmin'])
    || (($_SESSION['user'] ?? '') === 'super_admin');
if (!$isSa) {
    echo '<div class="p-6 text-sm text-red-600">Super Admin access required.</div>';
    return;
}

$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$mapFile = BASE_PATH . '/tenants/map.json';
$map = is_file($mapFile) ? (json_decode((string)@file_get_contents($mapFile), true) ?: []) : [];
$exact = is_array($map['exact'] ?? null) ? $map['exact'] : [];

$tenantsDir = BASE_PATH . '/tenants';
$tenantIds = [];
if (!class_exists('TenantPaths') && is_file(BASE_PATH . '/app/TenantPaths.php')) {
    require_once BASE_PATH . '/app/TenantPaths.php';
}
if (class_exists('TenantPaths')) {
    $tenantIds = TenantPaths::listIds();
} elseif (is_dir($tenantsDir)) {
    foreach (scandir($tenantsDir) ?: [] as $d) {
        if ($d === '.' || $d === '..' || str_starts_with((string)$d, '.')) continue;
        if (preg_match('/\.(md|txt|json|htaccess)$/i', (string)$d)) continue;
        if (!@is_dir($tenantsDir . '/' . $d)) continue;
        $tenantIds[] = $d;
    }
}
sort($tenantIds);

/** Count records across common JSON stores */
$countRecords = static function (string $dataPath): int {
    $total = 0;
    $files = [
        'team.json', 'banking.json', 'documents.json',
        'events.json', 'locations.json', 'designations.json', 'departments.json',
        'statutory.json', 'cartags.json', 'company.json', 'runners_state.json',
    ];
    foreach ($files as $f) {
        $p = $dataPath . '/' . $f;
        if (!is_file($p)) continue;
        $j = json_decode((string)@file_get_contents($p), true);
        if (is_array($j)) {
            // object vs list
            if (array_is_list($j)) {
                $total += count($j);
            } else {
                // company-like object counts as 1 if non-empty
                $total += (count($j) > 0) ? max(1, count($j)) : 0;
            }
        }
    }
    return $total;
};

$readAnalytics = static function (string $dataPath): array {
    $p = $dataPath . '/analytics.json';
    $def = ['visits' => 0, 'share_clicks' => 0, 'print_clicks' => 0, 'last_visit_at' => null];
    if (!is_file($p)) return $def;
    $j = json_decode((string)@file_get_contents($p), true);
    return is_array($j) ? array_merge($def, $j) : $def;
};

$rows = [];
$sumVisits = 0;
$sumShare = 0;
$sumPrint = 0;
$sumRecords = 0;
foreach ($tenantIds as $tid) {
    $dataPath = $tenantsDir . '/' . $tid . '/data';
    if (!is_dir($dataPath) && function_exists('rc_storage_self_heal')) {
        @mkdir($dataPath, 0775, true);
        rc_storage_self_heal($dataPath);
    }
    $cfgFile = $tenantsDir . '/' . $tid . '/config.json';
    $label = $tid;
    if (is_file($cfgFile)) {
        $cfg = json_decode((string)@file_get_contents($cfgFile), true);
        if (is_array($cfg) && !empty($cfg['label'])) {
            $label = (string)$cfg['label'];
        }
    }
    // company name preferred
    $co = $dataPath . '/company.json';
    if (is_file($co)) {
        $cj = json_decode((string)@file_get_contents($co), true);
        if (is_array($cj) && !empty($cj['name'])) {
            $label = (string)$cj['name'];
        }
    }
    $hosts = [];
    foreach ($exact as $host => $mapped) {
        if ((string)$mapped === $tid) {
            $hosts[] = (string)$host;
        }
    }
    $an = $readAnalytics($dataPath);
    $rec = is_dir($dataPath) ? $countRecords($dataPath) : 0;
    $sumVisits += (int)$an['visits'];
    $sumShare += (int)$an['share_clicks'];
    $sumPrint += (int)$an['print_clicks'];
    $sumRecords += $rec;
    $rows[] = [
        'id' => $tid,
        'name' => $label,
        'hosts' => $hosts,
        'visits' => (int)$an['visits'],
        'share' => (int)$an['share_clicks'],
        'print' => (int)$an['print_clicks'],
        'records' => $rec,
        'writable' => is_dir($dataPath) && is_writable($dataPath),
        'last' => $an['last_visit_at'] ?? null,
    ];
}
$tenantCount = count($rows);
// 7-day visit trend (sum across tenants, IST dates)
$trendLabels = [];
$trendValues = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime('-' . $i . ' days'));
    $trendLabels[] = date('d M', strtotime($d));
    $sumDay = 0;
    foreach ($tenantIds as $tid) {
        $ap = $tenantsDir . '/' . $tid . '/data/analytics.json';
        if (!is_file($ap)) {
            continue;
        }
        $aj = json_decode((string)@file_get_contents($ap), true);
        if (!is_array($aj)) {
            continue;
        }
        $dv = $aj['daily_visits'] ?? [];
        if (is_array($dv)) {
            $sumDay += (int)($dv[$d] ?? 0);
        }
    }
    $trendValues[] = $sumDay;
}
$ver = defined('APP_VERSION') ? APP_VERSION : '260926.09';
$healthBad = 0; $healthOk = 0;
foreach ($rows as $_hr) {
    if (!empty($_hr['writable'])) $healthOk++; else $healthBad++;
}
?>
<div class="space-y-5 max-w-6xl">
  <div class="flex flex-wrap items-center gap-2 mb-2" id="rc-tenant-health">
    <?php if ($healthBad === 0): ?>
      <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold px-3 py-1"><span aria-hidden="true">●</span> All <?= (int)$healthOk ?> tenant storage roots writable</span>
    <?php else: ?>
      <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold px-3 py-1"><span aria-hidden="true">▲</span> <?= (int)$healthBad ?> tenant(s) need write permission</span>
    <?php endif; ?>
    <span class="text-[11px] text-slate-500">Alpine 3.17 · Chart.js 4.5 · Build <?= $h($ver) ?></span>
  </div>
  <!-- Executive header -->
  <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-white p-6 shadow-lg">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-indigo-200/90 m-0 mb-1">Enterprise Control Plane</p>
        <h2 class="text-2xl font-bold tracking-tight m-0">Tenant Overview</h2>
        <p class="text-sm text-slate-300 mt-2 mb-0">Global portfolio metrics across all managed Resource Centre instances.</p>
      </div>
      <div class="text-right">
        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Build</div>
        <div class="font-mono text-sm text-indigo-200"><?= $h($ver) ?></div>
        <div class="mt-3 text-[11px] text-slate-300 leading-snug">
          <span class="font-semibold text-white">Site Developer:</span><br>
          Arthsathi Limited
        </div>
      </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6">
      <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3">
        <div class="text-[10px] uppercase tracking-wide text-slate-300">Tenants</div>
        <div class="text-2xl font-bold tabular-nums"><?= (int)$tenantCount ?></div>
      </div>
      <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3">
        <div class="text-[10px] uppercase tracking-wide text-slate-300">Total records</div>
        <div class="text-2xl font-bold tabular-nums"><?= (int)$sumRecords ?></div>
      </div>
      <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3">
        <div class="text-[10px] uppercase tracking-wide text-slate-300">Visits</div>
        <div class="text-2xl font-bold tabular-nums"><?= (int)$sumVisits ?></div>
      </div>
      <div class="rounded-xl bg-white/10 border border-white/10 px-4 py-3">
        <div class="text-[10px] uppercase tracking-wide text-slate-300">Share + Print</div>
        <div class="text-2xl font-bold tabular-nums"><?= (int)($sumShare + $sumPrint) ?></div>
      </div>
    </div>
  </div>


  <!-- Super Admin graphical analytics -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" id="rc-sa-charts">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:bg-slate-900 dark:border-slate-700 rc-chart-card">
      <h3 class="text-sm font-bold text-slate-900 m-0 mb-1">Visits by tenant</h3>
      <p class="text-[11px] text-slate-500 m-0 mb-3">Portfolio traffic distribution</p>
      <div class="relative h-56"><canvas id="rcChartVisits" aria-label="Visits by tenant bar chart" role="img"></canvas></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:bg-slate-900 dark:border-slate-700 rc-chart-card">
      <h3 class="text-sm font-bold text-slate-900 m-0 mb-1">Records by tenant</h3>
      <p class="text-[11px] text-slate-500 m-0 mb-3">Data footprint across instances</p>
      <div class="relative h-56"><canvas id="rcChartRecords" aria-label="Records by tenant doughnut chart" role="img"></canvas></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:bg-slate-900 dark:border-slate-700 rc-chart-card lg:col-span-2">
      <h3 class="text-sm font-bold text-slate-900 m-0 mb-1">Engagement — share vs print</h3>
      <p class="text-[11px] text-slate-500 m-0 mb-3">Cross-tenant collaboration signals · dual-coded (colour + series labels)</p>
      <div class="relative h-56"><canvas id="rcChartEngage" aria-label="Share and print by tenant chart" role="img"></canvas></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:bg-slate-900 dark:border-slate-700 rc-chart-card lg:col-span-2">
      <h3 class="text-sm font-bold text-slate-900 m-0 mb-1">7-day visit trend</h3>
      <p class="text-[11px] text-slate-500 m-0 mb-3">Sum of session visits across all tenants (IST). Builds as traffic is recorded.</p>
      <div class="relative h-52"><canvas id="rcChartTrend" aria-label="Seven day visit trend line chart" role="img"></canvas></div>
    </div>
  </div>
  <style>
    @media print {
      #rc-sa-charts { break-inside: avoid; }
      #rc-sa-charts .rc-chart-card { box-shadow: none !important; border-color: #cbd5e1 !important; }
    }
    [data-theme="dark"] #rc-sa-charts .rc-chart-card,
    html.dark #rc-sa-charts .rc-chart-card {
      background: #0f172a !important;
      border-color: #334155 !important;
    }
    [data-theme="dark"] #rc-sa-charts h3,
    html.dark #rc-sa-charts h3 { color: #f1f5f9 !important; }
    [data-theme="dark"] #rc-sa-charts p,
    html.dark #rc-sa-charts p { color: #94a3b8 !important; }
  </style>
  <script>
  (function () {
    var chartLabels = <?= json_encode(array_map(static fn($r) => (string)(($r['name'] ?? '') !== '' ? $r['name'] : $r['id']), $rows), JSON_UNESCAPED_UNICODE) ?>;
    var chartVisits = <?= json_encode(array_map(static fn($r) => (int)$r['visits'], $rows)) ?>;
    var chartRecords = <?= json_encode(array_map(static fn($r) => (int)$r['records'], $rows)) ?>;
    var chartShare = <?= json_encode(array_map(static fn($r) => (int)$r['share'], $rows)) ?>;
    var chartPrint = <?= json_encode(array_map(static fn($r) => (int)$r['print'], $rows)) ?>;
    var trendLabels = <?= json_encode($trendLabels, JSON_UNESCAPED_UNICODE) ?>;
    var trendValues = <?= json_encode($trendValues) ?>;

    /* Wong / Okabe–Ito CVD-safe palette */
    var palette = ['#0072B2','#E69F00','#009E73','#CC79A7','#56B4E9','#D55E00','#F0E442','#999999','#332288','#88CCEE'];
    function colors(n) {
      var a = [];
      for (var i = 0; i < n; i++) a.push(palette[i % palette.length]);
      return a;
    }
    function isDarkTheme() {
      var t = document.documentElement.getAttribute('data-theme')
        || document.body.getAttribute('data-theme')
        || '';
      if (t === 'dark') return true;
      if (t === 'light') return false;
      if (document.documentElement.classList.contains('dark')) return true;
      try {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      } catch (e) { return false; }
    }
    function themeTokens() {
      var dark = isDarkTheme();
      return {
        dark: dark,
        text: dark ? '#e2e8f0' : '#334155',
        muted: dark ? '#94a3b8' : '#64748b',
        grid: dark ? 'rgba(148,163,184,0.18)' : 'rgba(148,163,184,0.28)',
        border: dark ? '#1e293b' : '#ffffff',
        tooltipBg: dark ? '#0f172a' : '#ffffff',
        tooltipTitle: dark ? '#f8fafc' : '#0f172a',
        tooltipBody: dark ? '#cbd5e1' : '#334155',
      };
    }

    var chartInstances = [];

    function loadScript(src) {
      return new Promise(function (resolve, reject) {
        var s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
      });
    }
    function loadChartStack() {
      var chain = Promise.resolve();
      if (!window.Chart) {
        chain = chain.then(function () {
          return loadScript('/assets/vendor/chart.umd.min.js').catch(function () {
            return loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js');
          });
        });
      }
      chain = chain.then(function () {
        if (window.ChartDataLabels) return;
        return loadScript('/assets/vendor/chartjs-plugin-datalabels.min.js').catch(function () {
          return loadScript('https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js');
        }).catch(function () { /* optional */ });
      });
      return chain;
    }

    function destroyCharts() {
      chartInstances.forEach(function (c) {
        try { c.destroy(); } catch (e) {}
      });
      chartInstances = [];
    }

    function paint() {
      if (!window.Chart || !chartLabels.length) {
        if (!chartLabels.length && document.getElementById('rcChartTrend')) {
          // still paint trend if only trend has data path
        } else if (!chartLabels.length) {
          return;
        }
      }
      destroyCharts();
      var tok = themeTokens();
      var printing = window.matchMedia && window.matchMedia('print').matches;

      if (window.ChartDataLabels) {
        Chart.register(ChartDataLabels);
      }
      Chart.defaults.color = tok.text;
      Chart.defaults.borderColor = tok.grid;
      Chart.defaults.font.family = 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
      Chart.defaults.font.size = 11;

      var common = {
        responsive: true,
        maintainAspectRatio: false,
        animation: printing ? false : { duration: 550, easing: 'easeOutQuart' },
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 12,
              usePointStyle: true,
              color: tok.text,
              font: { size: 11 }
            }
          },
          tooltip: {
            backgroundColor: tok.tooltipBg,
            titleColor: tok.tooltipTitle,
            bodyColor: tok.tooltipBody,
            borderColor: tok.grid,
            borderWidth: 1,
            padding: 10
          },
          datalabels: {
            display: function (ctx) {
              var v = ctx.dataset.data[ctx.dataIndex];
              return v != null && Number(v) > 0;
            },
            color: tok.text,
            font: { weight: '600', size: 10 },
            anchor: 'end',
            align: 'top',
            formatter: function (v) { return v; }
          }
        }
      };

      var elV = document.getElementById('rcChartVisits');
      var elR = document.getElementById('rcChartRecords');
      var elE = document.getElementById('rcChartEngage');
      var elT = document.getElementById('rcChartTrend');

      if (elV && chartLabels.length) {
        chartInstances.push(new Chart(elV, {
          type: 'bar',
          data: {
            labels: chartLabels,
            datasets: [{
              label: 'Visits',
              data: chartVisits,
              backgroundColor: colors(chartLabels.length),
              borderColor: tok.dark ? '#e2e8f0' : '#0f172a',
              borderWidth: 1,
              borderRadius: 6
            }]
          },
          options: Object.assign({}, common, {
            scales: {
              y: {
                beginAtZero: true,
                ticks: { precision: 0, color: tok.muted },
                grid: { color: tok.grid },
                title: { display: true, text: 'Visits', color: tok.muted }
              },
              x: {
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 }, color: tok.muted },
                grid: { display: false }
              }
            },
            plugins: Object.assign({}, common.plugins, {
              legend: { display: false },
              datalabels: Object.assign({}, common.plugins.datalabels, {
                anchor: 'end', align: 'top'
              })
            })
          })
        }));
      }

      if (elR && chartLabels.length) {
        chartInstances.push(new Chart(elR, {
          type: 'doughnut',
          data: {
            labels: chartLabels,
            datasets: [{
              data: chartRecords,
              backgroundColor: colors(chartLabels.length),
              borderWidth: 2,
              borderColor: tok.border
            }]
          },
          options: Object.assign({}, common, {
            plugins: Object.assign({}, common.plugins, {
              datalabels: {
                color: tok.dark ? '#f8fafc' : '#0f172a',
                font: { weight: '700', size: 10 },
                formatter: function (v, ctx) {
                  var sum = ctx.chart.data.datasets[0].data.reduce(function (a, b) { return a + Number(b || 0); }, 0);
                  if (!sum || !v) return '';
                  var pct = Math.round((Number(v) / sum) * 100);
                  return pct >= 8 ? (pct + '%') : '';
                }
              }
            })
          })
        }));
      }

      if (elE && chartLabels.length) {
        chartInstances.push(new Chart(elE, {
          type: 'bar',
          data: {
            labels: chartLabels,
            datasets: [
              {
                label: 'Share clicks',
                data: chartShare,
                backgroundColor: '#0072B2',
                borderColor: '#0072B2',
                borderWidth: 1,
                borderRadius: 4,
                // dual-code: solid fill
              },
              {
                label: 'Print clicks',
                data: chartPrint,
                backgroundColor: 'rgba(230,159,0,0.35)',
                borderColor: '#E69F00',
                borderWidth: 2,
                borderRadius: 4,
                borderDash: [0],
                // dual-code: amber outline + hatch via pattern-like border
              }
            ]
          },
          options: Object.assign({}, common, {
            scales: {
              y: {
                beginAtZero: true,
                ticks: { precision: 0, color: tok.muted },
                grid: { color: tok.grid },
                title: { display: true, text: 'Clicks', color: tok.muted }
              },
              x: {
                ticks: { font: { size: 10 }, color: tok.muted },
                grid: { display: false }
              }
            }
          })
        }));
      }

      if (elT) {
        chartInstances.push(new Chart(elT, {
          type: 'line',
          data: {
            labels: trendLabels,
            datasets: [{
              label: 'Visits (all tenants)',
              data: trendValues,
              borderColor: '#0072B2',
              backgroundColor: 'rgba(0,114,178,0.12)',
              fill: true,
              tension: 0.35,
              pointRadius: 4,
              pointHoverRadius: 6,
              pointStyle: 'circle',
              pointBackgroundColor: '#0072B2',
              pointBorderColor: tok.dark ? '#e2e8f0' : '#fff',
              pointBorderWidth: 2,
              borderWidth: 2
            }]
          },
          options: Object.assign({}, common, {
            scales: {
              y: {
                beginAtZero: true,
                ticks: { precision: 0, color: tok.muted },
                grid: { color: tok.grid },
                title: { display: true, text: 'Visits', color: tok.muted }
              },
              x: {
                ticks: { color: tok.muted },
                grid: { display: false }
              }
            },
            plugins: Object.assign({}, common.plugins, {
              legend: { display: true, labels: { color: tok.text } },
              datalabels: Object.assign({}, common.plugins.datalabels, {
                align: 'top',
                anchor: 'end'
              })
            })
          })
        }));
      }
    }

    function boot() {
      loadChartStack().then(paint).catch(function () {
        var box = document.getElementById('rc-sa-charts');
        if (box) box.insertAdjacentHTML('beforeend',
          '<p class="text-sm text-slate-500 px-1">Charts library could not load. Metrics table remains authoritative.</p>');
      });
    }
    boot();

    // Theme toggle sync
    var mo = new MutationObserver(function () { paint(); });
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });
    if (document.body) {
      mo.observe(document.body, { attributes: true, attributeFilter: ['data-theme', 'class'] });
    }
    try {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', paint);
    } catch (e) {}
    window.addEventListener('beforeprint', function () { paint(); });
    window.addEventListener('afterprint', function () { paint(); });
  })();
  </script>

  <!-- Tenant grid -->
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-2">
      <h3 class="text-sm font-bold text-slate-900 m-0">Managed tenants</h3>
      <span class="text-[10px] font-mono font-bold text-violet-600 bg-violet-50 border border-violet-100 rounded-full px-2.5 py-0.5">260926.09</span>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm" id="rc-tenant-table">
        <thead>
          <tr class="text-left text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-100 bg-slate-50">
            <th class="px-5 py-3 font-bold cursor-pointer select-none" data-sort="name">Name <span class="sort-ind opacity-40">↕</span></th>
            <th class="px-3 py-3 font-bold cursor-pointer select-none" data-sort="id">Tenant ID <span class="sort-ind opacity-40">↕</span></th>
            <th class="px-3 py-3 font-bold text-right cursor-pointer select-none" data-sort="records">Records <span class="sort-ind opacity-40">↕</span></th>
            <th class="px-3 py-3 font-bold text-right cursor-pointer select-none" data-sort="visits">Visits <span class="sort-ind opacity-40">↕</span></th>
            <th class="px-3 py-3 font-bold text-right cursor-pointer select-none" data-sort="share">Share <span class="sort-ind opacity-40">↕</span></th>
            <th class="px-3 py-3 font-bold text-right cursor-pointer select-none" data-sort="print">Print <span class="sort-ind opacity-40">↕</span></th>
            <th class="px-5 py-3 font-bold">Hosts</th>
            <th class="px-4 py-3 font-bold text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="px-5 py-8 text-center text-slate-500">No tenant folders under <code>tenants/</code>.</td></tr>
        <?php else: foreach ($rows as $r):
          $protected = in_array($r['id'], ['default'], true);
          $hostsStr = $r['hosts'] ? implode(', ', $r['hosts']) : '';
        ?>
          <tr class="border-b border-slate-100 hover:bg-slate-50"
              data-name="<?= $h(strtolower($r['name'])) ?>"
              data-id="<?= $h($r['id']) ?>"
              data-records="<?= (int)$r['records'] ?>"
              data-visits="<?= (int)$r['visits'] ?>"
              data-share="<?= (int)$r['share'] ?>"
              data-print="<?= (int)$r['print'] ?>">
            <td class="px-5 py-3 font-semibold text-slate-900"><?= $h($r['name']) ?></td>
            <td class="px-3 py-3 font-mono text-xs text-indigo-700"><?= $h($r['id']) ?></td>
            <td class="px-3 py-3 text-right tabular-nums font-semibold text-slate-800"><?= (int)$r['records'] ?></td>
            <td class="px-3 py-3 text-right tabular-nums text-slate-800"><?= (int)$r['visits'] ?></td>
            <td class="px-3 py-3 text-right tabular-nums text-slate-800"><?= (int)$r['share'] ?></td>
            <td class="px-3 py-3 text-right tabular-nums text-slate-800"><?= (int)$r['print'] ?></td>
            <td class="px-5 py-3 text-[11px] text-slate-600">
              <?php if ($r['hosts']): ?>
                <?= $h(implode(', ', $r['hosts'])) ?>
              <?php else: ?>
                <span class="text-slate-500">wildcard / unmapped</span>
              <?php endif; ?>
              <?php if (!$r['writable']): ?>
                <span class="ml-2 text-amber-700 font-semibold">· storage check</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button type="button" class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-xs font-semibold hover:bg-blue-50 hover:border-blue-300"
                data-edit-tenant
                data-tid="<?= $h($r['id']) ?>"
                data-tname="<?= $h($r['name']) ?>"
                data-thosts="<?= $h($hostsStr) ?>">
                <i class="fa-solid fa-pen text-[10px]"></i> Modify
              </button>
              <?php if (!$protected): ?>
              <button type="button" class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg border border-rose-200 bg-white text-rose-700 text-xs font-semibold hover:bg-rose-50"
                data-del-tenant
                data-tid="<?= $h($r['id']) ?>">
                <i class="fa-solid fa-trash text-[10px]"></i> Delete
              </button>
              <?php else: ?>
              <span class="text-[10px] text-slate-400 ml-1">protected</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Modify modal -->
    <div id="rc-tenant-edit-modal" class="fixed inset-0 hidden items-center justify-center p-4" style="background:rgba(15,23,42,0.55);z-index:2147483000;isolation:isolate">
      <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 shadow-2xl p-5 text-slate-900" role="dialog" aria-modal="true" aria-labelledby="rc-te-title">
        <h3 id="rc-te-title" class="text-base font-bold m-0 mb-1">Modify tenant</h3>
        <p class="text-xs text-slate-500 mt-0 mb-4">Update display name and mapped hostnames (comma-separated). DNS still must point those hosts to this server.</p>
        <input type="hidden" id="rc-te-id">
        <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 mb-1">Display name</label>
        <input id="rc-te-name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-900 mb-3" autocomplete="off">
        <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 mb-1">Hosts</label>
        <input id="rc-te-hosts" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono text-slate-900 mb-4" placeholder="rc.example.com, example.com" autocomplete="off">
        <p id="rc-te-msg" class="text-xs text-slate-500 min-h-[1.25rem] mb-3"></p>
        <div class="flex justify-end gap-2">
          <button type="button" id="rc-te-cancel" class="h-9 px-3 rounded-lg border border-slate-200 text-sm font-semibold text-slate-700 bg-white">Cancel</button>
          <button type="button" id="rc-te-save" class="h-9 px-4 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">Save changes</button>
        </div>
      </div>
    </div>

    <script>
    (function(){
      function csrf(){
        try {
          if (window.APP && window.APP.csrf) return window.APP.csrf;
          if (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) return window.__DASHBOARD_STATE__.csrf;
        } catch(e){}
        return '';
      }
      function post(body){
        var tok = csrf();
        return fetch('index.php', {
          method: 'POST',
          headers: {
            'Content-Type':'application/json',
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN': tok
          },
          credentials: 'same-origin',
          body: JSON.stringify(Object.assign({csrf_token: tok}, body))
        }).then(function(r){ return r.json(); });
      }
      var modal = document.getElementById('rc-tenant-edit-modal');
            function openEdit(tid, name, hosts){
        if (!modal) return;
        var side = document.querySelector('aside, [data-rc-sidebar], #rc-sidebar, nav[aria-label]');
        var leftInset = 0;
        if (side) {
          var r = side.getBoundingClientRect();
          if (r.width > 40 && r.left < 120) leftInset = Math.ceil(r.width);
        }
        if (leftInset < 56 && window.matchMedia('(min-width: 768px)').matches) leftInset = 72;
        modal.style.position = 'fixed';
        modal.style.left = leftInset + 'px';
        modal.style.right = '0';
        modal.style.top = '0';
        modal.style.bottom = '0';
        modal.style.width = 'calc(100% - ' + leftInset + 'px)';
        modal.style.zIndex = '2147483000';
        modal.style.display = 'flex';
        document.getElementById('rc-te-id').value = tid;
        document.getElementById('rc-te-name').value = name || '';
        document.getElementById('rc-te-hosts').value = hosts || '';
        var msgEl = document.getElementById('rc-te-msg');
        if (msgEl) { msgEl.textContent = ''; msgEl.className = 'text-xs text-slate-500 min-h-[1.25rem] mb-3'; }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        try { document.getElementById('rc-te-name').focus(); } catch(e){}
      }
function closeEdit(){
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.style.display = 'none';
        document.body.classList.remove('overflow-hidden');
      }
      document.getElementById('rc-te-cancel')?.addEventListener('click', closeEdit);
      modal?.addEventListener('click', function(e){ if(e.target===modal) closeEdit(); });
      document.getElementById('rc-te-save')?.addEventListener('click', function(){
        var tid = document.getElementById('rc-te-id').value;
        var msg = document.getElementById('rc-te-msg');
        msg.textContent = 'Saving…';
        post({
          action: 'tenant_update',
          tenant_id: tid,
          name: document.getElementById('rc-te-name').value,
          hosts: document.getElementById('rc-te-hosts').value
        }).then(function(j){
          if(!j){ msg.textContent='Empty response'; msg.className='text-xs text-rose-600 mb-3'; return; }
          if(j.status==='success'||j.status==='ok'){ msg.textContent = j.message||'Saved'; setTimeout(function(){ location.reload(); }, 400); }
          else { msg.textContent = j.message||j.error||'Update failed'; msg.className='text-xs text-rose-600 mb-3'; }
        }).catch(function(err){ msg.textContent='Network error: '+(err&&err.message?err.message:err); msg.className='text-xs text-rose-600 mb-3'; });
      });
      document.querySelectorAll('[data-edit-tenant]').forEach(function(btn){
        btn.addEventListener('click', function(){
          openEdit(btn.getAttribute('data-tid'), btn.getAttribute('data-tname'), btn.getAttribute('data-thosts'));
        });
      });
      document.querySelectorAll('[data-del-tenant]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var tid = btn.getAttribute('data-tid');
          var conf = prompt('Type tenant id "'+tid+'" to permanently delete folder and map entries:');
          if(conf===null) return;
          post({action:'tenant_delete', tenant_id: tid, confirm: conf}).then(function(j){
            alert(j.message||j.status);
            if(j.status==='success'||j.status==='ok') location.reload();
          }).catch(function(){ alert('Network error'); });
        });
      });
      // Column sort
      var table = document.getElementById('rc-tenant-table');
      var sortState = {key:null, dir:1};
      table?.querySelectorAll('th[data-sort]').forEach(function(th){
        th.addEventListener('click', function(){
          var key = th.getAttribute('data-sort');
          if(sortState.key===key) sortState.dir*=-1; else { sortState.key=key; sortState.dir=1; }
          var tbody = table.querySelector('tbody');
          var rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
          rows.sort(function(a,b){
            var va=a.getAttribute('data-'+key)||'';
            var vb=b.getAttribute('data-'+key)||'';
            if(['records','visits','share','print'].indexOf(key)>=0){
              return sortState.dir*(parseInt(va,10)-parseInt(vb,10));
            }
            return sortState.dir*String(va).localeCompare(String(vb), undefined, {sensitivity:'base'});
          });
          rows.forEach(function(r){ tbody.appendChild(r); });
        });
      });
    })();
    </script>
    <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 text-[11px] text-slate-500">
      Portfolio: <strong><?= (int)$tenantCount ?></strong> tenant(s).
      Analytics counters live in each tenant’s <code class="text-[10px] bg-white border border-slate-200 px-1 rounded">data/analytics.json</code>
      (visits auto-increment once per browser session; share/print via app actions).
    </div>
  </div>

  
  <div class="mt-8 rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm no-print">
    <h3 class="text-sm font-bold text-slate-900 m-0 mb-2">Provision tenant</h3>
    <p class="text-[11px] text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mb-3">Modify / Delete / Provision require an active <strong>Super Admin</strong> session (<code class="text-[10px]">blsbls</code>). Co. Admin cannot change tenants.</p>
    <p class="text-xs text-slate-500 mt-0 mb-3">Creates <code>tenants/{id}/data</code>, seed JSON, and maps the host in <code>tenants/map.json</code>. Point DNS A for the host to this server&rsquo;s DocumentRoot (same as other rc.* domains).</p>
    <form id="rcTenantProvision" class="grid gap-3 sm:grid-cols-2" onsubmit="return rcProvisionTenant(event)">
      <label class="block text-xs font-semibold text-slate-600">Host / domain
        <input name="host" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="rc.dhruvkhandelwal.in" autocomplete="off">
      </label>
      <label class="block text-xs font-semibold text-slate-600">Tenant ID <span class="font-normal text-slate-400">(optional)</span>
        <input name="tenant_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono" placeholder="dhruvkhandelwal" autocomplete="off">
      </label>
      <label class="block text-xs font-semibold text-slate-600 sm:col-span-2">Display name
        <input name="name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Dhruv Khandelwal">
      </label>
      <div class="sm:col-span-2 flex flex-wrap gap-2 items-center">
        <button type="submit" class="rounded-lg bg-indigo-600 text-white text-sm font-bold px-4 py-2 hover:bg-indigo-700">Provision Entity</button>
        <span id="rcTenantProvisionMsg" class="text-xs text-slate-500"></span>
      </div>
    </form>
  </div>
  <script>
  async function rcProvisionTenant(e){
    e.preventDefault();
    var f = e.target;
    var msg = document.getElementById('rcTenantProvisionMsg');
    msg.textContent = 'Provisioning…';
    var fd = new FormData(f);
    var body = { action: 'tenant_provision', host: fd.get('host'), tenant_id: fd.get('tenant_id'), name: fd.get('name') };
    try {
      var tok = (window.APP && window.APP.csrf) || (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) || '';
      var r = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': tok }, body: JSON.stringify(Object.assign({csrf_token: tok}, body)), credentials: 'same-origin' });
      var j = await r.json();
      if (j.status === 'success' || j.ok) {
        msg.textContent = j.message || ('Created ' + (j.tenant_id||''));
        setTimeout(function(){ location.reload(); }, 1000);
      } else {
        msg.textContent = j.message || j.error || 'Failed';
      }
    } catch (err) {
      msg.textContent = String(err);
    }
    return false;
  }
  </script>

<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm text-sm text-slate-600">
    <p class="m-0"><span class="font-bold text-slate-900">Site Developer: Arthsathi Limited</span>
      — platform engineering, multi-tenant Resource Centre, and enterprise control plane.</p>
    <p class="mt-2 mb-0 text-xs text-slate-400">Storage is self-healing: required folders and seed JSON are provisioned automatically at <code>0775</code> with no migration prompts.</p>
  </div>
</div>

<?php
$_mm = __DIR__ . '/../partials/modules_matrix.php';
if (is_file($_mm)) { require $_mm; }
?>
