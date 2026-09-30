<?php // tab_personality.php — Version: 260916.14
 if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
    <div x-show="activeTab==='personality'" class="tab-section space-y-6 print-page-break">
        <h2 class="hidden print:block text-lg font-black text-slate-900 uppercase tracking-widest border-b pb-2"><?= $lang==='hi'?'व्यक्तित्व और ब्लूप्रिंट':'Personality & Blueprint' ?></h2>
        <?php $planetData=$report['lucky_driver']['data']??[]; ?>
        <div class="p-6 border border-slate-200 dark:border-slate-700 rounded-lg bg-gradient-to-b from-white to-slate-50 dark:from-slate-800 dark:to-slate-800 text-center">
            <div class="text-5xl mb-3"><?= htmlspecialchars((string)($planetData['icon']??'☉')) ?></div>
            <h3 class="text-2xl font-black text-slate-900 dark:text-white"><?= htmlspecialchars((string)($report['lucky_driver']['name']??'')) ?></h3>
            <div class="flex flex-wrap justify-center gap-1.5 mt-2 mb-4">
                <?php foreach(explode(',',($lang==='hi'?(string)($planetData['hi_keywords']??$planetData['keywords']??''):(string)($planetData['keywords']??''))) as $kw): ?>
                <span class="planet-pill bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-300"><?= htmlspecialchars(trim((string)$kw)) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="max-w-2xl mx-auto space-y-3">
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800 rounded-lg p-3 text-left">
                    <div class="text-[9px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-1"><i class="fa-solid fa-trophy mr-1"></i> <?= $lang==='hi'?'आपकी मूल शक्ति':'Your Core Strength' ?></div>
                    <p class="text-sm text-emerald-900 dark:text-emerald-200 font-semibold leading-relaxed"><?= htmlspecialchars($lang==='hi'?(string)($planetData['hi_keywords']??$planetData['keywords']??''):(string)($planetData['keywords']??'')) ?> —<?= $lang==='hi'?' — आपके पास दुर्लभ कंपन उपहार हैं जिन्हें अधिकांश लोग पूरे जीवन विकसित करने की कोशिश में बिताते हैं। ये आपके जन्मसिद्ध अधिकार हैं।':' you carry rare vibrational gifts that most people spend their entire life trying to develop. These are yours by birthright.' ?></p>
                </div>
                <details class="text-left group">
                    <summary class="cursor-pointer text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest select-none hover:text-indigo-500 transition-colors">
                        <i class="fa-solid fa-lightbulb mr-1 text-amber-400"></i> <?= $lang==='hi'?'आपका विकास किनारा — जहाँ आपकी सबसे बड़ी शक्ति छिपी है ▸':'Your Growth Edge — where your greatest power is hiding ▸' ?>
                    </summary>
                    <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-lg">
                        <p class="text-sm text-amber-900 dark:text-amber-200 leading-relaxed"><?= htmlspecialchars($lang==='hi'?(string)($planetData['hi_blind_spot']??$planetData['blind_spot']??''):(string)($planetData['blind_spot']??'')) ?></p>
                    </div>
                </details>
            </div>
        </div>
        <?php if(!empty($planetData['attributes'])): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <?php foreach([['key'=>'career','label'=>($lang==='hi'?'करियर और व्यवसाय':'Career & Vocation'),'icon'=>'fa-briefcase','color'=>'blue'],['key'=>'wealth','label'=>($lang==='hi'?'धन और वित्त':'Wealth & Finance'),'icon'=>'fa-coins','color'=>'amber'],['key'=>'relationships','label'=>($lang==='hi'?'रिश्ते':'Relationships'),'icon'=>'fa-heart','color'=>'rose']] as $attr): $text=($lang==='hi'?(string)($planetData['hi_attributes'][$attr['key']]??$planetData['attributes'][$attr['key']]??''):(string)($planetData['attributes'][$attr['key']]??'')); $c=$attr['color']; ?>
            <div class="p-5 border rounded-lg bg-<?= $c ?>-50 dark:bg-<?= $c ?>-900/20 border-<?= $c ?>-100 dark:border-<?= $c ?>-800">
                <div class="w-9 h-9 bg-<?= $c ?>-100 dark:bg-<?= $c ?>-800 rounded-lg flex items-center justify-center mb-3"><i class="fa-solid <?= $attr['icon'] ?> text-<?= $c ?>-600 dark:text-<?= $c ?>-400 text-sm"></i></div>
                <div class="text-[10px] uppercase font-bold text-<?= $c ?>-600 tracking-widest mb-2"><?= $attr['label'] ?></div>
                <p class="text-sm text-<?= $c ?>-900 dark:text-<?= $c ?>-200 leading-relaxed"><?= htmlspecialchars((string)$text) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        // ✅ V16.2: Render Hidden Passion, Balance Number, and Subconscious Self —
        //           data calculated by engine since V13 but previously not displayed.
        $_hp  = $report['name_matrix']['hidden_passion']  ?? [];
        $_bal = $report['name_matrix']['balance']          ?? [];
        $_ss  = $report['name_matrix']['subconscious_self']?? 0;
        $_hasSecondary = (!empty($_hp['number']) && (int)$_hp['number'] > 0)
                       || (!empty($_bal['root']) && (int)$_bal['root'] > 0)
                       || ((int)$_ss > 0);
        ?>
        <?php if ($_hasSecondary): ?>
        <div class="border border-violet-200 dark:border-violet-700 rounded-lg overflow-hidden bg-white dark:bg-slate-800 shadow-sm">
            <div class="bg-violet-50 dark:bg-violet-900/20 px-6 py-4 border-b border-violet-100 dark:border-violet-800">
                <h3 class="text-xs font-black text-violet-700 dark:text-violet-300 uppercase tracking-widest">
                    <i class="fa-solid fa-brain mr-2 text-violet-500"></i>
                    <?= $lang==='hi' ? 'द्वितीयक व्यक्तित्व परतें' : 'Secondary Personality Layers' ?>
                </h3>
                <p class="text-[10px] text-violet-500 dark:text-violet-400 mt-0.5">
                    <?= $lang==='hi' ? 'Chaldean नाम विश्लेषण से निकाले गए — भावनात्मक कोर, अनकही शक्तियाँ और संकट-प्रबंधन क्षमता।'
                                     : 'Derived from Chaldean name analysis — emotional core, unspoken strengths, and crisis-management capacity.' ?>
                </p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">

                <?php if (!empty($_hp['number']) && (int)$_hp['number'] > 0): ?>
                <div class="bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-100 dark:border-fuchsia-800 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-10 h-10 bg-fuchsia-100 dark:bg-fuchsia-900/50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-xl font-black text-fuchsia-600 dark:text-fuchsia-400"><?= (int)$_hp['number'] ?></span>
                        </div>
                        <div>
                            <div class="text-[9px] font-black text-fuchsia-600 dark:text-fuchsia-400 uppercase tracking-widest">
                                <?= $lang==='hi' ? 'छिपा जुनून' : 'Hidden Passion' ?>
                            </div>
                            <div class="text-[9px] text-slate-400">
                                <?= $lang==='hi' ? 'सर्वाधिक दोहराई गई Chaldean ऊर्जा' : 'Most repeated Chaldean energy' ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($_hp['all']) && count($_hp['all']) > 1): ?>
                    <div class="flex gap-1 flex-wrap mb-2">
                        <?php foreach ($_hp['all'] as $_hpn): ?>
                        <span class="text-[9px] px-2 py-0.5 rounded bg-fuchsia-100 dark:bg-fuchsia-900 text-fuchsia-700 dark:text-fuchsia-300 font-bold"><?= (int)$_hpn ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <p class="text-xs text-fuchsia-900 dark:text-fuchsia-200 leading-relaxed">
                        <?= $lang==='hi'
                            ? 'यह संख्या आपके नाम में '.(int)$_hp['count'].' बार प्रकट होती है — यह वह ऊर्जा है जो आप स्वाभाविक रूप से संसार में प्रवाहित करते हैं, भले ही आप इसके प्रति सचेत न हों।'
                            : 'This number appears '.(int)$_hp['count'].' times in your name — the energy you naturally radiate, often without conscious effort.' ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if (!empty($_bal['root']) && (int)$_bal['root'] > 0): ?>
                <div class="bg-sky-50 dark:bg-sky-900/20 border border-sky-100 dark:border-sky-800 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-10 h-10 bg-sky-100 dark:bg-sky-900/50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-xl font-black text-sky-600 dark:text-sky-400"><?= (int)$_bal['root'] ?></span>
                        </div>
                        <div>
                            <div class="text-[9px] font-black text-sky-600 dark:text-sky-400 uppercase tracking-widest">
                                <?= $lang==='hi' ? 'संतुलन संख्या' : 'Balance Number' ?>
                            </div>
                            <div class="text-[9px] text-slate-400">
                                <?= $lang==='hi' ? 'यौगिक ' : 'Compound ' ?><?= (int)$_bal['compound'] ?>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-sky-900 dark:text-sky-200 leading-relaxed">
                        <?= $lang==='hi'
                            ? 'प्रत्येक नाम खंड के प्रथम अक्षर से निर्मित — यह दर्शाता है कि आप जीवन के असंतुलित या संकट-काल में अपनी ऊर्जा को सबसे स्वाभाविक रूप से किस दिशा में केंद्रित करते हैं।'
                            : 'Derived from the first letter of each name part — shows the energy direction you instinctively draw on when life feels off-balance or under pressure.' ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ((int)$_ss > 0): ?>
                <div class="bg-teal-50 dark:bg-teal-900/20 border border-teal-100 dark:border-teal-800 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-10 h-10 bg-teal-100 dark:bg-teal-900/50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-xl font-black text-teal-600 dark:text-teal-400"><?= (int)$_ss ?></span>
                        </div>
                        <div>
                            <div class="text-[9px] font-black text-teal-600 dark:text-teal-400 uppercase tracking-widest">
                                <?= $lang==='hi' ? 'अवचेतन स्व' : 'Subconscious Self' ?>
                            </div>
                            <div class="text-[9px] text-slate-400">
                                <?= $lang==='hi' ? '9 − लुप्त संख्याएं = ' : '9 − missing numbers = ' ?><?= (int)$_ss ?>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-teal-900 dark:text-teal-200 leading-relaxed">
                        <?php
                        $ssDesc = [
                            9=>'You respond to any situation with full-spectrum confidence — no energy domain is unknown to you.',
                            8=>'High unconscious competence across most life areas. One blind spot remains.',
                            7=>'Strong instinctive responses in 7 of 9 domains — two growth areas await activation.',
                            6=>'Solid instinctive ground with meaningful room for new experiential wisdom.',
                            5=>'Balanced between mastery and growth — equal parts confidence and openness.',
                            4=>'Deep specialisation; instinctive responses concentrated in fewer, stronger domains.',
                            3=>'Highly specialised energy; powerful in your niche, transformative growth possible elsewhere.',
                            2=>'Rare concentrated blueprint — profound depth; intentional broadening amplifies reach.',
                            1=>'The most specialised pattern possible — master depth energy with extraordinary focus.',
                        ];
                        $ssDescHi = [
                            9=>'आप किसी भी परिस्थिति में पूर्ण आत्मविश्वास के साथ प्रतिक्रिया करते हैं।',
                            8=>'अधिकांश जीवन क्षेत्रों में उच्च अवचेतन क्षमता।',
                            7=>'7 क्षेत्रों में सहज प्रतिक्रिया — दो विकास क्षेत्र सक्रियण की प्रतीक्षा में।',
                            6=>'विकास की सार्थक संभावना के साथ ठोस सहज आधार।',
                            5=>'महारत और विकास के बीच संतुलन।',
                            4=>'गहरी विशेषज्ञता — शक्तिशाली केंद्रित ऊर्जा।',
                            3=>'अत्यधिक विशेषीकृत ऊर्जा — अपने क्षेत्र में शक्तिशाली।',
                            2=>'दुर्लभ संकेंद्रित ब्लूप्रिंट — गहन गहराई।',
                            1=>'सर्वाधिक विशेषीकृत पैटर्न — असाधारण फोकस।',
                        ];
                        echo htmlspecialchars((string)(
                            $lang==='hi'
                            ? ($ssDescHi[(int)$_ss] ?? 'आपकी अवचेतन प्रतिक्रिया क्षमता दर्शाता है।')
                            : ($ssDesc[(int)$_ss]   ?? 'Indicates your instinctive response capacity.')
                        ));
                        ?>
                    </p>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; // end $_hasSecondary ?>

        <?php
        // ✅ V16.2: Bridge Number display — Expression ↔ Life Path gap.
        // Calculated since V14 in calcBridge() and stored in $report['bridge']
        // (top-level key added V16.2) and $report['name_matrix']['bridge'],
        // but never rendered until now.
        $_brg = (int)($report['bridge'] ?? $report['name_matrix']['bridge'] ?? 0);
        if ($_brg > 0):
        ?>
        <div class="border border-amber-200 dark:border-amber-700 rounded-lg bg-white dark:bg-slate-800 shadow-sm overflow-hidden">
            <div class="flex items-center gap-4 px-6 py-4">
                <div class="w-14 h-14 flex-shrink-0 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg flex flex-col items-center justify-center">
                    <span class="text-2xl font-black text-amber-600 dark:text-amber-400 leading-none"><?= $_brg ?></span>
                    <span class="text-[8px] text-amber-500 font-bold uppercase mt-0.5"><?= $lang==='hi'?'सेतु':'Bridge' ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-[9px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-1">
                        <i class="fa-solid fa-bridge mr-1.5"></i>
                        <?= $lang==='hi' ? 'सेतु संख्या — अभिव्यक्ति ↔ जीवन पथ अंतर' : 'Bridge Number — Expression ↔ Life Path Gap' ?>
                    </div>
                    <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                        <?php
                        $brgDesc = [
                            1=>['en'=>'Assert your independence more — your name energy wants to lead, but your Life Path may still hold back.',
                                'hi'=>'अपनी स्वतंत्रता अधिक व्यक्त करें — नाम ऊर्जा नेतृत्व चाहती है।'],
                            2=>['en'=>'Cultivate cooperation. Your Expression craves partnership; blend it consciously with your Life Path.',
                                'hi'=>'सहयोग को बढ़ावा दें — नाम ऊर्जा साझेदारी चाहती है।'],
                            3=>['en'=>'Express yourself creatively. Suppressing joy or humour creates friction between your name and life purpose.',
                                'hi'=>'रचनात्मकता से खुद को व्यक्त करें — हास्य और खुशी को दबाना घर्षण पैदा करता है।'],
                            4=>['en'=>'Build disciplined structures. Your life purpose demands order; your name energy may resist it.',
                                'hi'=>'अनुशासित संरचना बनाएं — जीवन उद्देश्य व्यवस्था मांगता है।'],
                            5=>['en'=>'Embrace flexibility. Your Expression may feel constrained by the Life Path — allow change to flow.',
                                'hi'=>'लचीलापन अपनाएं — परिवर्तन को बहने दें।'],
                            6=>['en'=>'Take on responsibility with love. This gap heals through service, family, or creative nurturing.',
                                'hi'=>'प्रेम के साथ जिम्मेदारी लें — सेवा और परिवार से यह अंतर भरता है।'],
                            7=>['en'=>'Seek inner wisdom. Study, reflection, or solitude bridges the gap between how you project and your deeper life purpose.',
                                'hi'=>'आंतरिक ज्ञान खोजें — अध्ययन और एकांत इस अंतर को पाटता है।'],
                            8=>['en'=>'Step into material authority. Your expression may downplay ambition — your Life Path demands you own your power.',
                                'hi'=>'भौतिक अधिकार में कदम रखें — जीवन पथ आपकी शक्ति की मांग करता है।'],
                            9=>['en'=>'Expand your impact universally. The bridge here asks you to move from personal to collective purpose.',
                                'hi'=>'सार्वभौमिक प्रभाव बढ़ाएं — व्यक्तिगत से सामूहिक उद्देश्य की ओर बढ़ें।'],
                        ];
                        $bd = $brgDesc[$_brg] ?? ['en'=>'Consciously align your name vibration with your Life Path purpose.','hi'=>'नाम कंपन को जीवन पथ उद्देश्य के साथ संरेखित करें।'];
                        echo htmlspecialchars((string)($lang==='hi' ? $bd['hi'] : $bd['en']));
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
