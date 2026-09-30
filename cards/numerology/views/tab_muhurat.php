<?php // tab_muhurat.php — Version: 260916.14
 if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
    <div x-show="activeTab==='muhurat'" class="tab-section space-y-6 print-page-break">
        <?php
        $_muHas=!empty($muhuratData)&&array_sum(array_map('count',$muhuratData))>0;
        $_mLbl=['business_start'=>[$lang==='hi'?'व्यवसाय प्रारंभ':'Business Launch','fa-briefcase','indigo'],'name_correction'=>[$lang==='hi'?'नाम परिवर्तन':'Name Correction','fa-pen-nib','violet'],'property'=>[$lang==='hi'?'संपत्ति खरीद':'Property Purchase','fa-house','emerald'],'travel'=>[$lang==='hi'?'यात्रा / उद्यम':'Travel / Venture','fa-plane','blue'],'marriage'=>[$lang==='hi'?'विवाह / साझेदारी':'Marriage / Partnership','fa-heart','rose']];
        ?>
        <div class="flex items-center gap-4 p-5 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border border-amber-200 dark:border-amber-700 rounded-lg">
            <div class="w-12 h-12 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-calendar-check text-amber-600 dark:text-amber-400 text-xl"></i>
            </div>
            <div>
                <h3 class="text-sm font-black text-amber-800 dark:text-amber-200"><?= $lang==='hi'?'शुभ मुहूर्त':'Auspicious Muhurat' ?></h3>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5"><?= $lang==='hi'?'Driver '.(int)$core['driver'].' के लिए अगले 6 माह की सर्वोत्तम तारीखें':'Best dates for Driver '.(int)$core['driver'].' in the next 6 months' ?></p>
            </div>
        </div>
        <?php if (!$_muHas): ?>
        <div class="p-8 text-center bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
            <i class="fa-solid fa-calendar-check text-amber-400 text-4xl mb-3 block"></i>
            <p class="text-sm font-bold text-slate-700 dark:text-slate-300"><?= $lang==='hi'?'मुहूर्त की गणना हो रही है':'Calculating muhurat dates' ?></p>
            <div class="mt-2 text-[10px] font-mono text-slate-400"><?= class_exists('MuhuratEngine')?'Engine: loaded ✅':'Engine: not loaded ❌' ?> · Driver: <?= (int)($core['driver']??0) ?></div>
        </div>
        <?php else: ?>
        <?php foreach($_mLbl as $_mKey=>[$_mLabel,$_mIcon,$_mColor]): $dates=$muhuratData[$_mKey]??[]; if(empty($dates)) continue; ?>
        <div class="border border-<?= $_mColor ?>-200 dark:border-<?= $_mColor ?>-700 rounded-lg overflow-hidden bg-white dark:bg-slate-800 shadow-sm">
            <div class="bg-<?= $_mColor ?>-50 dark:bg-<?= $_mColor ?>-900/20 px-5 py-3 border-b border-<?= $_mColor ?>-100 dark:border-<?= $_mColor ?>-800 flex items-center gap-2.5">
                <div class="w-8 h-8 rounded bg-<?= $_mColor ?>-100 dark:bg-<?= $_mColor ?>-900/40 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid <?= $_mIcon ?> text-<?= $_mColor ?>-600 dark:text-<?= $_mColor ?>-400 text-sm"></i>
                </div>
                <span class="font-black text-<?= $_mColor ?>-800 dark:text-<?= $_mColor ?>-200 text-xs uppercase tracking-wider"><?= $_mLabel ?></span>
            </div>
            <div class="p-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <?php foreach($dates as $mdt): ?>
                <div class="text-center p-3 rounded-lg border <?= ($mdt['grade']==='Excellent'?'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-300 dark:border-emerald-700 shadow-sm':($mdt['grade']==='Good'?'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700':'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600')) ?>">
                    <div class="text-[10px] font-black text-slate-700 dark:text-slate-200 leading-tight"><?= htmlspecialchars($mdt['display']) ?></div>
                    <div class="text-[9px] text-slate-400 mt-0.5"><?= htmlspecialchars($mdt['day']) ?></div>
                    <div class="mt-1.5 text-[9px] font-black <?= ($mdt['grade']==='Excellent'?'text-emerald-600 dark:text-emerald-400':($mdt['grade']==='Good'?'text-blue-600 dark:text-blue-400':'text-slate-500')) ?>"><?= ($lang==='hi'?($mdt['grade']==='Excellent'?'उत्कृष्ट':($mdt['grade']==='Good'?'अच्छा':'अनुकूल')):$mdt['grade']) ?></div>
                    <div class="mt-0.5 text-[8px] text-slate-400"><?= $lang==='hi'?'मूल':'Root' ?> <?= (int)$mdt['date_root'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-700/50 px-5 py-3 border-b border-slate-200 dark:border-slate-600">
                <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest"><i class="fa-solid fa-moon mr-1.5 text-indigo-400"></i><?= $lang==='hi'?'तिथि — चंद्र दिवस शुभता':'Tithi — Lunar Day Guide' ?></h4>
            </div>
            <div class="p-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
            <?php $_tithis=AppNumeroEngine::getData()['tithi_compat']??[]; $_tFr=$report['lucky_driver']['friends']??[];
            foreach($_tithis as $_tn=>$_td): $_tG=in_array((int)($_td['root']??0),$_tFr,true)||(int)($_td['root']??0)===(int)$core['driver']; ?>
            <div class="p-2 rounded border text-center <?= ($_tG?'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-700':'bg-slate-50 dark:bg-slate-700/50 border-slate-200 dark:border-slate-600') ?>">
                <div class="text-[10px] font-bold <?= ($_tG?'text-emerald-700 dark:text-emerald-400':'text-slate-600 dark:text-slate-400') ?>"><?= $lang==='hi'?htmlspecialchars((string)$_td['hi']):htmlspecialchars($_tn) ?></div>
                <div class="text-[8px] text-slate-400 mt-0.5 leading-tight"><?= htmlspecialchars($_td['quality']) ?></div>
                <div class="mt-0.5 text-[8px] font-black <?= ($_tG?'text-emerald-600':'text-slate-400') ?>"><?= $lang==='hi'?'मूल ':'Root ' ?><?= (int)($_td['root']??0) ?></div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
