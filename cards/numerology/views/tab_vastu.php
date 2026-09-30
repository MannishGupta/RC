<?php // tab_vastu.php — Version: 260916.14
 if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
    <div x-show="activeTab==='vastu'" class="tab-section space-y-6 print-page-break">
        <div class="flex items-center gap-4 p-5 bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg">
            <div class="w-12 h-12 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-house-chimney text-emerald-600 dark:text-emerald-400 text-xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-emerald-800 dark:text-emerald-200"><?= $lang==='hi'?'वास्तु नाम विश्लेषण':'Vastu Name Analysis' ?></h3>
                <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-0.5"><?= $lang==='hi'?'Chaldean तत्व और वास्तु दिशा विश्लेषण':'Chaldean element and Vastu direction for the name' ?></p>
            </div>
        </div>
        <?php if(empty($vastuScore)||!empty($vastuScore['error'])||empty($vastuScore['root'])): ?>
        <div class="p-8 text-center bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
            <i class="fa-solid fa-house-chimney text-slate-400 text-4xl mb-3 block"></i>
            <p class="text-sm font-bold text-slate-700 dark:text-slate-300"><?= $lang==='hi'?'वास्तु स्कोर लोड हो रहा है':'Loading Vastu score' ?></p>
            <div class="mt-2 text-[10px] font-mono text-slate-400"><?= class_exists('VastuNameEngine')?'Engine: loaded ✅':'Engine: not loaded ❌' ?></div>
        </div>
        <?php else: $vg=$vastuScore['grade']??'Neutral'; $vColor=match($vg){'Excellent'=>'emerald','Good'=>'blue','Neutral'=>'amber',default=>'rose'}; ?>
        <div class="p-6 bg-white dark:bg-slate-800 border border-<?= $vColor ?>-200 dark:border-<?= $vColor ?>-700 rounded-lg shadow-sm">
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="relative w-28 h-28 flex-shrink-0">
                    <svg class="w-28 h-28 -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-100 dark:text-slate-700"/>
                        <circle id="vastu-score-arc" cx="18" cy="18" r="15.9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-dasharray="<?= (int)$vastuScore['score'] ?> 100" stroke-linecap="round" class="text-<?= $vColor ?>-500"/>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span id="vastu-score-val" class="text-2xl font-black text-<?= $vColor ?>-600 dark:text-<?= $vColor ?>-400 leading-none"><?= (int)$vastuScore['score'] ?></span>
                        <span class="text-[9px] text-slate-400 font-semibold">/ 100</span>
                    </div>
                </div>
                <div class="flex-1 text-center sm:text-left">
                    <div id="vastu-name-display" class="text-lg font-black text-slate-800 dark:text-white"><?= htmlspecialchars(mb_strtoupper((string)($vastuScore['name']??$p['name']??''))) ?></div>
                    <span id="vastu-grade-badge" class="inline-flex items-center gap-1.5 mt-1 px-3 py-1 rounded text-xs font-black bg-<?= $vColor ?>-100 dark:bg-<?= $vColor ?>-900/40 text-<?= $vColor ?>-700 dark:text-<?= $vColor ?>-300"><i class="fa-solid fa-circle-check text-[9px]"></i> <span id="vastu-grade-val"><?= htmlspecialchars($vg) ?></span></span>
                    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-2">
                        <?php foreach([
                            ['l'=>$lang==='hi'?'मूल':'Root',      'v'=>(int)$vastuScore['root'],                                            'id'=>'vastu-root-val'],
                            ['l'=>$lang==='hi'?'यौगिक':'Compound','v'=>(int)$vastuScore['compound'],                                         'id'=>'vastu-compound-val'],
                            ['l'=>$lang==='hi'?'तत्व':'Element',  'v'=>$lang==='hi'?($vastuScore['hi_element']??''):($vastuScore['element']??''), 'id'=>'vastu-element-val'],
                            ['l'=>$lang==='hi'?'दिशा':'Direction','v'=>$vastuScore['vastu_direction']??'',                                   'id'=>'vastu-direction-val'],
                        ] as $vc): ?>
                        <div class="p-2 bg-slate-50 dark:bg-slate-700/50 rounded text-center"><div class="text-[9px] text-slate-400 font-bold uppercase"><?= $vc['l'] ?></div><div id="<?= $vc['id'] ?>" class="text-sm font-black text-slate-700 dark:text-slate-200"><?= htmlspecialchars((string)$vc['v']) ?></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if(!empty($vastuScore['compound_name'])): ?>
        <div class="p-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg flex items-center gap-4">
            <div class="w-12 h-12 bg-violet-100 dark:bg-violet-900/30 rounded flex items-center justify-center flex-shrink-0"><span id="vastu-compound-val-badge" class="text-xl font-black text-violet-600 dark:text-violet-400"><?= (int)$vastuScore['compound'] ?></span></div>
            <div><div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= $lang==='hi'?'यौगिक अर्थ':'Compound Meaning' ?></div><div id="vastu-compound-name" class="font-black text-slate-800 dark:text-white"><?= htmlspecialchars($vastuScore['compound_name']) ?></div><span id="vastu-compound-verdict" class="inline-block mt-1 text-[9px] px-2 py-0.5 rounded font-bold <?= getVerdictColor($vastuScore['compound_verdict']??'Neutral') ?>"><?= htmlspecialchars($vastuScore['compound_verdict']??'') ?></span></div>
        </div>
        <?php endif; ?>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-700/50 px-5 py-3 border-b border-slate-200 dark:border-slate-600"><h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest"><i class="fa-solid fa-chart-simple mr-1.5 text-indigo-400"></i><?= $lang==='hi'?'अक्षर विश्लेषण (Chaldean)':'Letter Analysis (Chaldean)' ?></h4></div>
            <div id="vastu-letters-grid" class="p-4 flex flex-wrap gap-2 items-end">
                <?php foreach($vastuScore['letters'] as $vlt): ?><div class="text-center bg-slate-50 dark:bg-slate-700/50 rounded px-3 py-2 border border-slate-200 dark:border-slate-600"><div class="text-lg font-black text-slate-800 dark:text-white leading-none"><?= htmlspecialchars($vlt['letter']) ?></div><div class="text-xs text-indigo-600 dark:text-indigo-400 font-black"><?= (int)$vlt['value'] ?></div></div><?php endforeach; ?>
                <div class="text-center bg-indigo-50 dark:bg-indigo-900/30 rounded px-3 py-2 border border-indigo-200 dark:border-indigo-700"><div class="text-sm font-black text-indigo-500">=</div><div class="text-base font-black text-indigo-700 dark:text-indigo-300"><?= (int)$vastuScore['chaldean_total'] ?></div></div>
            </div>
        </div>
        <div class="bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-lg p-5 print:hidden">
            <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3"><i class="fa-solid fa-flask-vial mr-1.5 text-indigo-400"></i><?= $lang==='hi'?'किसी भी नाम का परीक्षण करें':'Test Any Business / Brand Name' ?></h4>
            <div class="flex gap-2 flex-wrap">
                <input type="text" id="vastu-test-name" value="<?= htmlspecialchars((string)($_GET['vastu_test']??$vastuScore['name']??'')) ?>" placeholder="<?= $lang==='hi'?'व्यवसाय का नाम...':'Enter business name...' ?>" class="flex-1 min-w-[180px] text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 rounded border border-slate-300 dark:border-slate-600 outline-none focus:border-indigo-500">
                <button id="vastu-analyse-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm font-bold flex items-center gap-1.5 transition-colors"><i class="fa-solid fa-magnifying-glass text-[10px]"></i><span><?= $lang==='hi'?'जाँचें':'Analyse' ?></span></button>
            </div>
        </div>
        <?php endif; ?>
    </div>
