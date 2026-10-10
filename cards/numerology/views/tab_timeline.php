<?php // tab_timeline.php — Version: 260921.03
// Timeline & Life Phases — card rows, Act/Avoid callouts. All data preserved.
if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit;
$isHi = ($lang === 'hi');
$pinBorderColors = ['nr-phase-orange', 'nr-phase-violet', 'nr-phase-sky', 'nr-phase-emerald'];
$pinnaclesArr = $report['pinnacles']['pinnacles'] ?? [];
?>
    <div x-show="activeTab==='timeline'" class="tab-section nr-stack print-page-break" role="tabpanel" aria-label="<?= $isHi ? 'दशा' : 'Dasha' ?>">
      <header class="nr-card-header" style="margin-bottom:1rem">
        <p class="nr-eyebrow"><?= $isHi ? 'अंकशास्त्रीय दशा' : 'Numerology Dasha' ?></p>
        <h3 class="nr-h3"><?= $isHi ? 'जीवन काल और वर्तमान चक्र' : 'Life Periods & Current Cycles' ?></h3>
        <p class="nr-muted"><?= $isHi
             ? 'पिनाकल (मैक्रो-दशा) आयु-सीमा सहित, और वर्तमान व्यक्तिगत वर्ष/माह/दिन (माइक्रो-दशा)।'
             : 'Pinnacle periods (macro-dasha) with age ranges, plus the current Personal Year / Month / Day (micro-dasha).' ?></p>
      </header>
        <h2 class="hidden print:block text-lg font-black text-slate-900 uppercase tracking-widest border-b pb-2"><?= $isHi ? 'अंकशास्त्रीय दशा — जीवन काल और चक्र' : 'Numerology Dasha — Life Periods & Cycles' ?></h2>

        <div class="nr-grid-3">
            <?php
            $cycles = [
                ['label' => $isHi ? 'व्यक्तिगत वर्ष' : 'Personal Year', 'n' => $report['personal_cycle']['personal_year'] ?? 0,  'tone' => 'indigo', 'data' => $report['personal_year'] ?? []],
                ['label' => $isHi ? 'व्यक्तिगत माह (' . ($report['personal_cycle']['month_name'] ?? '') . ')' : 'Personal Month (' . ($report['personal_cycle']['month_name'] ?? '') . ')', 'n' => $report['personal_cycle']['personal_month'] ?? 0, 'tone' => 'amber', 'data' => $report['personal_cycle']['month_data'] ?? []],
                ['label' => $isHi ? 'व्यक्तिगत दिन (आज)' : 'Personal Day (Today)', 'n' => $report['personal_cycle']['personal_day'] ?? 0, 'tone' => 'emerald', 'data' => $report['personal_cycle']['day_data'] ?? []],
            ];
            foreach ($cycles as $cyc):
                $cData  = $cyc['data'];
                $cNum   = (string)(int)$cyc['n'];
                $cTitle = $isHi ? ($HI_PY[$cNum]['title'] ?? ($cData['title'] ?? '')) : ($cData['title'] ?? '');
                $cTheme = $isHi ? ($HI_PY[$cNum]['theme'] ?? ($cData['theme'] ?? '')) : ($cData['theme'] ?? '');
                $cAct   = $isHi ? ($HI_PY[$cNum]['action_timing'] ?? ($cData['action_timing'] ?? '')) : ($cData['action_timing'] ?? '');
                $cAvoid = $isHi ? ($HI_PY[$cNum]['avoid'] ?? ($cData['avoid'] ?? '')) : ($cData['avoid'] ?? '');
            ?>
            <article class="nr-card nr-cycle nr-cycle-<?= htmlspecialchars($cyc['tone'], ENT_QUOTES) ?>">
                <header class="nr-cycle-head">
                    <div>
                        <div class="nr-card-kicker"><?= $cyc['label'] ?></div>
                        <div class="nr-cycle-title"><?= htmlspecialchars((string)$cTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    </div>
                    <div class="nr-cycle-num" aria-hidden="true"><?= (int)$cyc['n'] ?></div>
                </header>
                <div class="nr-cycle-body">
                    <p class="nr-theme"><?= htmlspecialchars((string)$cTheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <?php if ($cAct !== ''): ?>
                    <div class="nr-callout nr-callout-act"><strong><?= $isHi ? 'करें' : 'Act' ?>:</strong> <span><?= htmlspecialchars((string)$cAct, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                    <?php if ($cAvoid !== ''): ?>
                    <div class="nr-callout nr-callout-avoid"><strong><?= $isHi ? 'बचें' : 'Avoid' ?>:</strong> <span><?= htmlspecialchars((string)$cAvoid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <section class="nr-card">
            <h3 class="nr-h3"><i class="fa-solid fa-chart-bar text-indigo-400" aria-hidden="true"></i> <?= $isHi ? 'जीवन पथ समयरेखा — व्यक्तिगत वर्ष पूर्वानुमान' : 'Life Path Timeline — Personal Year Forecast' ?></h3>
            <p class="nr-muted mb-4"><?= $isHi ? 'प्रत्येक बार उस कैलेंडर वर्ष के लिए आपका व्यक्तिगत वर्ष कंपन दर्शाता है।' : 'Each bar shows your Personal Year vibration for that calendar year.' ?></p>
            <canvas id="timelineChart" height="160" aria-label="Personal year forecast chart"></canvas>
            <?php
            $tlData    = $report['timeline'] ?? [];
            $tlLabels  = array_column($tlData, 'year');
            $tlNumbers = array_column($tlData, 'number');
            $tlColors  = array_map('getPYColor', $tlNumbers);
            $tlCurrent = array_keys(array_filter($tlData, function ($y) { return !empty($y['is_current']); }));
            $tlCurrentIdx = !empty($tlCurrent) ? $tlCurrent[0] : 0;
            ?>
            <script>
            (function () {
                function paint() {
                    var el = document.getElementById('timelineChart');
                    if (!el || typeof Chart === 'undefined') {
                        if (typeof Chart === 'undefined') setTimeout(paint, 40);
                        return;
                    }
                    var labels  = <?= json_encode($tlLabels) ?>;
                    var numbers = <?= json_encode($tlNumbers) ?>;
                    var colors  = <?= json_encode($tlColors) ?>;
                    var curIdx  = <?= (int)$tlCurrentIdx ?>;
                    // Dual-code current year: thicker border + hatch via pattern, not hue alone
                    var bgColors = colors.map(function (c, i) {
                        if (i === curIdx) return c;
                        // lighten non-current for contrast against current
                        return c.length === 7 ? c + 'CC' : c;
                    });
                    var borderColors = colors.map(function (c, i) { return i === curIdx ? '#0f172a' : '#334155'; });
                    var bw = colors.map(function (c, i) { return i === curIdx ? 4 : 1; });
                    new Chart(el, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Personal Year Number',
                                data: numbers,
                                backgroundColor: bgColors,
                                borderColor: borderColors,
                                borderWidth: bw,
                                borderRadius: 6,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            var yr = ctx.parsed.y;
                                            var yearNames = ['', 'Pioneer', 'Diplomat', 'Creator', 'Builder', 'Adventurer', 'Nurturer', 'Seeker', 'Achiever', 'Humanitarian'];
                                            return ' Year ' + yr + ' — ' + (yearNames[yr] || yr) + (ctx.dataIndex === curIdx ? ' ◄ Current' : '');
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { min: 0, max: 10, ticks: { stepSize: 1, color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.12)' } },
                                x: { ticks: { color: '#94a3b8' }, grid: { display: false } }
                            }
                        }
                    });
                }
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', paint);
                else paint();
            })();
            </script>
        </section>

        <section class="nr-card">
            <header class="nr-card-header">
                <h3 class="nr-h3"><i class="fa-solid fa-mountain text-orange-400" aria-hidden="true"></i> <?= $isHi ? 'शिखर और विकास द्वार — चार जीवन चरण' : 'Pinnacles & Growth Gateways — Four Life Phases' ?></h3>
                <p class="nr-muted"><?= $isHi ? 'शिखर प्रत्येक जीवन चरण की प्रमुख ऊर्जा का उपहार दर्शाते हैं। विकास द्वार अनलॉक होने की प्रतीक्षा में छिपी शक्ति को प्रकट करते हैं।' : 'Pinnacles show the dominant energy gift of each life phase. Growth Gateways reveal the hidden strength waiting to be unlocked.' ?></p>
            </header>

            <!-- Desktop table (print-friendly) -->
            <div class="nr-table-wrap print:block">
                <table class="nr-table">
                    <thead>
                        <tr>
                            <th><?= $isHi ? 'चरण' : 'Phase' ?></th>
                            <th><?= $isHi ? 'आयु सीमा' : 'Age Range' ?></th>
                            <th><?= $isHi ? 'शिखर उपहार' : 'Pinnacle Gift' ?></th>
                            <th><?= $isHi ? 'अवसर' : 'Opportunity' ?></th>
                            <th><?= $isHi ? 'विकास द्वार' : 'Growth Gateway' ?></th>
                            <th><?= $isHi ? 'आपके भीतर उपहार' : 'Your Gift Within' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pinnaclesArr as $i => $pin):
                            $cha = $report['pinnacles']['challenges'][$i] ?? [];
                            $pd  = $pin['data'] ?? [];
                            $cd  = $cha['data'] ?? [];
                        ?>
                        <tr>
                            <td><span class="nr-phase-chip <?= $pinBorderColors[$i] ?? '' ?>"><?= $isHi ? 'चरण' : 'Phase' ?> <?= (int)($pin['phase'] ?? 0) ?></span></td>
                            <td class="nr-nowrap"><?= htmlspecialchars((string)($pin['age_range'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            <td>
                                <div class="nr-pin-num"><?= (int)($pin['number'] ?? 0) ?></div>
                                <div class="nr-muted"><?= htmlspecialchars((string)($pd['theme'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                            </td>
                            <td><?= htmlspecialchars((string)($pd['opportunity'] ?? $pd['focus'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                            <td>
                                <div class="nr-gw-num"><?= (int)($cha['number'] ?? 0) ?></div>
                                <div class="nr-warn"><?= htmlspecialchars((string)($cd['theme'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                            </td>
                            <td class="nr-pos-text"><?= htmlspecialchars((string)($cd['prescription'] ?? $cd['lesson'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile / screen card rows -->
            <div class="nr-phase-cards print:hidden">
                <?php foreach ($pinnaclesArr as $i => $pin):
                    $pd = $pin['data'] ?? [];
                    if (empty($pd)) continue;
                    $cha = $report['pinnacles']['challenges'][$i] ?? [];
                    $cd  = $cha['data'] ?? [];
                ?>
                <article class="nr-phase-card <?= $pinBorderColors[$i] ?? '' ?>">
                    <header class="nr-phase-head">
                        <div>
                            <div class="nr-card-kicker">Phase <?= (int)($pin['phase'] ?? 0) ?> · <?= htmlspecialchars((string)($pin['age_range'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                            <div class="nr-phase-title"><?= (int)($pin['number'] ?? 0) ?> — <?= htmlspecialchars((string)($pd['theme'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        </div>
                        <?php if (!empty($pd['planet'])): ?>
                        <span class="planet-pill nr-planet-pill"><?= htmlspecialchars((string)$pd['planet'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </header>
                    <div class="nr-callout nr-callout-act"><strong><?= $isHi ? 'अवसर' : 'Opportunity' ?>:</strong> <span><?= htmlspecialchars((string)($pd['opportunity'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                    <div class="nr-callout nr-callout-avoid"><strong><?= $isHi ? 'सावधानी' : 'Caution' ?>:</strong> <span><?= htmlspecialchars((string)($pd['caution'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                    <?php if (!empty($cd)): ?>
                    <div class="nr-callout nr-callout-gift">
                        <strong><?= $isHi ? 'विकास द्वार' : 'Growth Gateway' ?> <?= (int)($cha['number'] ?? 0) ?>:</strong>
                        <span><?= htmlspecialchars((string)($cd['prescription'] ?? $cd['lesson'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
