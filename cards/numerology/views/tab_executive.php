<?php // tab_executive.php — Version: 260921.03
// Executive Summary — premium card layout. All data preserved.
if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit;
$isHi = ($lang === 'hi');
$pyNum = (string)(int)($report['personal_year']['number'] ?? 0);
?>
    <div x-show="activeTab==='executive'" class="tab-section nr-stack" role="tabpanel" aria-label="<?= $isHi ? 'कार्यकारी सारांश' : 'Executive Summary' ?>">
        <h2 class="hidden print:block text-lg font-black text-slate-900 uppercase tracking-widest border-b pb-2"><?= $isHi ? 'कार्यकारी सारांश' : 'Executive Summary' ?></h2>

        <?php if (!empty($report['interpretation'])): ?>
        <section class="nr-card nr-card-blueprint">
            <div class="nr-card-icon" aria-hidden="true"
                 style="background:linear-gradient(135deg,<?= htmlspecialchars((string)(($report['planetInfo'][AppNumeroEngine::baseRoot((int)$core['driver'])] ?? [])['color'] ?? '#6366f1')) ?>33,<?= htmlspecialchars((string)(($report['planetInfo'][AppNumeroEngine::baseRoot((int)$core['driver'])] ?? [])['color'] ?? '#6366f1')) ?>55)">
                <?= htmlspecialchars((string)(($report['planetInfo'][AppNumeroEngine::baseRoot((int)$core['driver'])] ?? [])['icon'] ?? '☉'), ENT_QUOTES) ?>
            </div>
            <div class="nr-card-body">
                <div class="nr-card-kicker">
                    <span><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> <?= $isHi ? 'आपका ग्रह ब्लूप्रिंट' : 'Your Planetary Blueprint' ?></span>
                    <span class="nr-pill nr-pill-emerald"><?= $isHi ? 'चालक' : 'Driver' ?> <?= (int)$core['driver'] ?> · <?= htmlspecialchars((string)($report['lucky_driver']['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <p class="nr-lead"><?= htmlspecialchars((string)($report['interpretation']['driver_summary'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <p class="nr-body"><?= htmlspecialchars((string)($report['interpretation']['missing_summary'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
        </section>
        <?php endif; ?>

        <div class="nr-grid-2">
            <section class="nr-card nr-card-score">
                <div class="nr-card-kicker"><?= $isHi ? 'कंपन संरेखण' : 'Vibrational Alignment' ?></div>
                <div class="nr-score-row">
                    <div class="nr-score-num"><?= (int)($report['summary']['score'] ?? 0) ?><span class="nr-score-den">/100</span></div>
                    <div class="nr-score-bar" role="progressbar" aria-valuenow="<?= (int)($report['summary']['score'] ?? 0) ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="nr-score-fill" style="width:<?= (int)($report['summary']['score'] ?? 0) ?>%"></div>
                    </div>
                </div>
                <div class="nr-score-breakdown">
                    <div>
                        <span class="nr-muted"><?= $isHi ? 'ग्रिड' : 'Grid' ?></span>
                        <strong class="nr-pos">+<?= (int)($report['summary']['breakdown']['grid'] ?? 0) ?></strong>
                    </div>
                    <div>
                        <span class="nr-muted"><?= $isHi ? 'योग' : 'Yogas' ?></span>
                        <strong class="nr-pos">+<?= (int)($report['summary']['breakdown']['yogas'] ?? 0) ?></strong>
                    </div>
                    <div>
                        <span class="nr-muted"><?= $isHi ? 'विकास' : 'Growth' ?></span>
                        <strong class="nr-warn"><?= isset($report['summary']['risk_index']) ? (int)$report['summary']['risk_index'] . ' pts' : '—' ?></strong>
                    </div>
                </div>
            </section>

            <section class="nr-card nr-card-year">
                <div class="nr-card-kicker">
                    <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                    <?= $isHi ? 'व्यक्तिगत वर्ष' : 'Personal Year' ?> <?= (int)($report['personal_year']['year'] ?? 0) ?>
                    · <?= $isHi ? 'संख्या' : 'Number' ?> <?= (int)($report['personal_year']['number'] ?? 0) ?>
                </div>
                <h3 class="nr-h3"><?= htmlspecialchars((string)($isHi ? ($HI_PY[$pyNum]['title'] ?? $report['personal_year']['title'] ?? '') : ($report['personal_year']['title'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                <p class="nr-theme"><?= htmlspecialchars((string)($isHi ? ($HI_PY[$pyNum]['theme'] ?? $report['personal_year']['theme'] ?? '') : ($report['personal_year']['theme'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <div class="nr-callouts">
                    <div class="nr-callout nr-callout-act">
                        <strong><i class="fa-solid fa-bolt" aria-hidden="true"></i> <?= $isHi ? 'अभी करें' : 'Leverage now' ?></strong>
                        <span><?= htmlspecialchars((string)($isHi ? ($HI_PY[$pyNum]['action_timing'] ?? $report['personal_year']['action_timing'] ?? '') : ($report['personal_year']['action_timing'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    </div>
                    <div class="nr-callout nr-callout-avoid">
                        <strong><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <?= $isHi ? 'संयम रखें' : 'Conserve energy' ?></strong>
                        <span><?= htmlspecialchars((string)($isHi ? ($HI_PY[$pyNum]['avoid'] ?? $report['personal_year']['avoid'] ?? '') : ($report['personal_year']['avoid'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    </div>
                </div>
            </section>
        </div>

        <section class="nr-card nr-card-maturity">
            <div class="nr-maturity-badge">
                <span class="nr-maturity-num"><?= (int)($report['maturity']['number'] ?? 0) ?></span>
                <span class="nr-maturity-lbl"><?= $isHi ? 'परिपक्वता' : 'Maturity' ?></span>
            </div>
            <div class="nr-card-body">
                <div class="nr-card-kicker">
                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                    <?= $isHi ? 'परिपक्वता संख्या' : 'Maturity Number' ?>
                    (<?= $isHi ? 'अभिव्यक्ति + जीवन पथ' : 'Expression + Life Path' ?> =
                    <?= (int)($report['name_matrix']['full']['root'] ?? 0) ?> + <?= (int)$core['conductor'] ?>)
                </div>
                <p class="nr-body-strong">
                    <?= htmlspecialchars((string)(($report['planetInfo'][$report['maturity']['number'] ?? 0] ?? ['name' => 'Master Vibration'])['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    <?= $isHi ? 'कंपन आपके माध्यमिक जीवन उद्देश्य के रूप में सक्रिय होती है उम्र के बाद' : 'vibration activates as your secondary life purpose after age' ?>
                    <?= (int)($report['maturity']['activates_at'] ?? 0) ?>.
                </p>
                <p class="nr-muted"><?= htmlspecialchars((string)($report['maturity']['note'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
        </section>

        <section class="nr-card nr-card-strength">
            <strong class="nr-strength-title"><i class="fa-solid fa-star text-amber-400" aria-hidden="true"></i> <?= $isHi ? 'आपकी शक्ति ब्लूप्रिंट' : 'Your Strength Blueprint' ?></strong>
            <p class="nr-body">
            <?php
            $finalScore = $report['summary']['score'] ?? 0;
            $bd = $report['summary']['breakdown'] ?? [];
            $pos = []; $neg = [];
            if (($bd['grid'] ?? 0) > 0)    $pos[] = $isHi ? "संरचनात्मक पूर्णता (+{$bd['grid']})" : "structural completeness (+{$bd['grid']})";
            if (($bd['strong'] ?? 0) > 0)  $pos[] = $isHi ? "प्रमुख शक्तियाँ (+{$bd['strong']})" : "dominant strengths (+{$bd['strong']})";
            if (($bd['mobile'] ?? 0) > 0)  $pos[] = $isHi ? "सहायक मोबाइल (+{$bd['mobile']})" : "supportive mobile (+{$bd['mobile']})";
            if (($bd['yogas'] ?? 0) > 0)   $pos[] = $isHi ? "सक्रिय योग (+{$bd['yogas']})" : "active yogas (+{$bd['yogas']})";
            if (($bd['mobile'] ?? 0) < 0)  $neg[] = $isHi ? "मोबाइल नंबर में विकास क्षमता ({$bd['mobile']})" : "mobile number with growth potential ({$bd['mobile']})";
            if (($bd['penalties'] ?? 0) < -9 || (($report['diagnostics']['risk_index'] ?? 0) >= 10))
                $neg[] = $isHi ? 'उच्च-विकास सक्रियण क्षेत्र' : 'high-growth activation zone';
            elseif (($bd['penalties'] ?? 0) < 0)
                $neg[] = $isHi ? "विकास भंडार ({$bd['penalties']})" : "growth reservoirs ({$bd['penalties']})";
            if (($bd['name_conflict'] ?? 0) < 0) $neg[] = $isHi ? "नाम-ग्रिड रचनात्मक तनाव ({$bd['name_conflict']})" : "name-grid creative tension ({$bd['name_conflict']})";

            if ($isHi) {
                echo "आपका संरेखण स्कोर {$finalScore}/100 है, जो अद्भुत जन्मजात क्षमता को दर्शाता है। ";
                if (!empty($pos)) echo 'आपका ब्लूप्रिंट इन शक्तियों से सक्रिय रूप से प्रवर्धित है: ' . implode(', ', $pos) . '। ';
                if (!empty($neg)) echo 'आपके सबसे बड़े विकास अवसर — ' . implode(' और ', $neg) . ' — ऐसे सक्रियण बिंदु हैं जो, एक बार जागृत होने पर, इस स्कोर को काफी ऊपर ले जाएंगे।';
                else echo 'आपका मैट्रिक्स कोई संरचनात्मक कटौती नहीं दर्शाता — यह एक असाधारण रूप से स्वच्छ और शक्तिशाली विन्यास है।';
            } else {
                echo "Your alignment score of {$finalScore}/100 reflects tremendous innate potential. ";
                if (!empty($pos)) echo 'Your blueprint is actively amplified by: ' . implode(', ', $pos) . '. ';
                if (!empty($neg)) echo 'Your greatest growth levers — ' . implode(' and ', $neg) . ' — are activation opportunities that, once unlocked, will push this score significantly higher.';
                else echo 'Your matrix shows no structural deductions — an exceptionally clean and powerful configuration.';
            }
            ?>
            </p>
        </section>

        <?php
        // ── Client positive aspects (logos / symbols) ─────────────────
        $nrPositives = [];
        $isHi = !empty($isHi);
        $core = $report['core'] ?? [];
        $pInfo = $report['planetInfo'] ?? [];
        $driver = (int)($core['driver'] ?? 0);
        $dRoot = class_exists('AppNumeroEngine') ? AppNumeroEngine::baseRoot($driver) : $driver;
        $dMeta = $pInfo[$dRoot] ?? $pInfo[(string)$dRoot] ?? [];
        $dIcon = (string)($dMeta['icon'] ?? '☉');
        $dName = (string)($dMeta['name'] ?? ('Number ' . $driver));
        $nrPositives[] = [
            'icon' => $dIcon,
            'title' => $isHi ? ('चालक शक्ति · ' . $dName) : ('Driver strength · ' . $dName),
            'text' => $isHi
                ? (string)($dMeta['hi_attributes']['career'] ?? $dMeta['attributes']['career'] ?? 'जन्म संख्या आपकी मुख्य कार्य-ऊर्जा को दर्शाती है।')
                : (string)($dMeta['attributes']['career'] ?? 'Your birth number channels a signature work-and-life energy.'),
        ];
        foreach (['expression' => ['en' => 'Expression', 'hi' => 'अभिव्यक्ति', 'icon' => '✍️'],
                  'soul_urge' => ['en' => 'Soul Urge', 'hi' => 'आत्मा की इच्छा', 'icon' => '💜'],
                  'personality' => ['en' => 'Personality', 'hi' => 'व्यक्तित्व', 'icon' => '🪞'],
                  'conductor' => ['en' => 'Conductor', 'hi' => 'संवाहक', 'icon' => '🧭']] as $ck => $cl) {
            $val = (int)($core[$ck] ?? $core[str_replace('_', '', $ck)] ?? 0);
            if ($val <= 0) continue;
            $r = class_exists('AppNumeroEngine') ? AppNumeroEngine::baseRoot($val) : $val;
            $meta = $pInfo[$r] ?? [];
            $nrPositives[] = [
                'icon' => (string)($meta['icon'] ?? $cl['icon']),
                'title' => ($isHi ? $cl['hi'] : $cl['en']) . ' · ' . $val,
                'text' => $isHi
                    ? (string)($meta['hi_attributes']['relationships'] ?? $meta['attributes']['relationships'] ?? 'यह अंक आपके स्वभाव का एक सकारात्मक स्तंभ है।')
                    : (string)($meta['attributes']['relationships'] ?? 'This number reinforces a constructive facet of your nature.'),
            ];
        }
        $harmony = (string)($report['name_matrix']['harmony']['status'] ?? '');
        if (in_array($harmony, ['Aligned', 'Supportive', 'Excellent', 'Fortunate'], true)) {
            $nrPositives[] = [
                'icon' => '✅',
                'title' => $isHi ? ('नाम सामंजस्य · ' . $harmony) : ('Name harmony · ' . $harmony),
                'text' => $isHi
                    ? 'नाम की कंपन जन्म संख्या के साथ सहायक है — पहचान और दिशा में सहज प्रवाह।'
                    : 'Name vibration supports the birth number — smoother identity and direction themes.',
            ];
        }
        $score = (int)($report['summary']['score'] ?? 0);
        if ($score >= 70) {
            $nrPositives[] = [
                'icon' => '🏆',
                'title' => $isHi ? ("संरेखण स्कोर · {$score}/100") : ("Alignment score · {$score}/100"),
                'text' => $isHi
                    ? 'उच्च संरेखण जन्मजात क्षमता और सक्रिय शक्तियों का संकेत है।'
                    : 'A strong alignment score points to innate capacity and active strengths.',
            ];
        }
        $yogas = $report['yogas'] ?? $report['grid']['yogas'] ?? [];
        if (is_array($yogas)) {
            foreach (array_slice($yogas, 0, 4) as $yg) {
                if (!is_array($yg)) continue;
                $yt = (string)($yg['name'] ?? $yg['title'] ?? '');
                if ($yt === '') continue;
                $nrPositives[] = [
                    'icon' => (string)($yg['icon'] ?? '🕉️'),
                    'title' => $isHi ? ('योग · ' . $yt) : ('Yoga · ' . $yt),
                    'text' => (string)($isHi ? ($yg['hi_desc'] ?? $yg['description'] ?? $yg['desc'] ?? 'सक्रिय योग — शुभ संरचनात्मक संकेत।') : ($yg['description'] ?? $yg['desc'] ?? 'Active yoga — constructive structural signal.')),
                ];
            }
        }
        $bd = $report['summary']['breakdown'] ?? [];
        if ((int)($bd['grid'] ?? 0) > 0) {
            $nrPositives[] = [
                'icon' => '🔷',
                'title' => $isHi ? 'लो शू ग्रिड पूर्णता' : 'Lo Shu grid completeness',
                'text' => $isHi ? 'संरचनात्मक पूर्णता आपके मैट्रिक्स में संतुलन बढ़ाती है।' : 'Structural completeness supports balance across your matrix.',
            ];
        }
        if ((int)($bd['strong'] ?? 0) > 0) {
            $nrPositives[] = [
                'icon' => '💪',
                'title' => $isHi ? 'प्रमुख शक्तियाँ' : 'Dominant strengths',
                'text' => $isHi ? 'आपके अंक विन्यास में स्पष्ट शक्ति-क्षेत्र सक्रिय हैं।' : 'Clear strength zones are active in your number configuration.',
            ];
        }
        // Always at least driver + overall positive frame
        $nrPositives[] = [
            'icon' => '🌟',
            'title' => $isHi ? 'समग्र सकारात्मक दृष्टि' : 'Overall positive frame',
            'text' => $isHi
                ? 'यह खंड केवल रचनात्मक शक्तियों पर केंद्रित है। उपाय विकास के द्वार हैं, दोष नहीं।'
                : 'This panel focuses on constructive strengths. Remedies are growth doors, not defects.',
        ];
        ?>
        <section class="nr-card nr-positives-grid" aria-label="<?= $isHi ? 'सकारात्मक पहलू' : 'Positive aspects' ?>">
            <header class="nr-card-header">
                <h3 class="nr-h3"><i class="fa-solid fa-sun" aria-hidden="true"></i> <?= $isHi ? 'सभी सकारात्मक पहलू · प्रतीक सहित' : 'All positive aspects · with symbols' ?></h3>
                <p class="nr-muted"><?= $isHi ? 'क्लाइंट की शक्तियाँ, ग्रह प्रतीक और शुभ संकेत — स्पष्ट और उत्साहवर्धक।' : 'Client strengths, planetary symbols, and favourable signals — clear and encouraging.' ?></p>
            </header>
            <div class="nr-pos-tiles" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));gap:0.75rem;margin-top:0.75rem">
                <?php foreach ($nrPositives as $np): ?>
                <article class="nr-pos-tile" style="border:1px solid #cbd5e1;border-radius:12px;padding:0.85rem;background:linear-gradient(160deg,#fffbeb,#ffffff);box-shadow:0 2px 10px rgba(15,23,42,0.06)">
                    <div style="font-size:1.6rem;line-height:1;margin-bottom:0.35rem" aria-hidden="true"><?= htmlspecialchars((string)($np['icon'] ?? '✦'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <h4 class="nr-h4" style="margin:0 0 0.3rem;font-weight:800;color:#0f172a"><?= htmlspecialchars((string)($np['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h4>
                    <p class="nr-body" style="margin:0;font-size:0.85rem;color:#334155;line-height:1.45"><?= htmlspecialchars((string)($np['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!empty($report['karmic_debt'])): ?>
        <section class="nr-card nr-card-karmic">
            <header class="nr-card-header">
                <h3 class="nr-h3"><i class="fa-solid fa-star-and-crescent" aria-hidden="true"></i> <?= $isHi ? 'कार्मिक उपहार और त्वरक' : 'Karmic Gifts & Accelerators' ?></h3>
                <p class="nr-muted"><?= $isHi ? 'ये ब्रह्मांड का आपके विकास में सीधा निवेश हैं — बोझ नहीं, बल्कि चुने हुए विकास द्वार।' : 'These are the Universe\'s direct investment in your accelerated evolution — not burdens, but chosen growth portals.' ?></p>
            </header>
            <?php foreach ($report['karmic_debt'] as $kd): ?>
            <article class="nr-karmic-row">
                <div class="nr-karmic-num">
                    <?= (int)$kd['number'] ?>
                    <span><?= htmlspecialchars((string)($kd['source'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <div class="nr-karmic-body">
                    <h4 class="nr-h4"><?= htmlspecialchars($isHi ? (string)($kd['hi_name'] ?? $kd['name'] ?? '') : (string)($kd['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h4>
                    <div class="nr-callout nr-callout-gift">
                        <strong><i class="fa-solid fa-gem" aria-hidden="true"></i> <?= $isHi ? 'निर्मित हो रहा उपहार' : 'The Gift Being Forged' ?></strong>
                        <span><?= htmlspecialchars($isHi ? (string)($kd['hi_lesson'] ?? $kd['lesson'] ?? '') : (string)($kd['lesson'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    </div>
                    <p class="nr-body"><strong class="nr-warn"><?= $isHi ? 'विकास द्वार:' : 'Growth gateway:' ?></strong> <?= htmlspecialchars($isHi ? (string)($kd['hi_challenge'] ?? $kd['challenge'] ?? '') : (string)($kd['challenge'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <div class="nr-callout nr-callout-act">
                        <strong><i class="fa-solid fa-bolt" aria-hidden="true"></i> <?= $isHi ? 'सक्रियण:' : 'Activation:' ?></strong>
                        <span><?= htmlspecialchars($isHi ? (string)($kd['hi_prescription'] ?? $kd['prescription'] ?? '') : (string)($kd['prescription'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>
