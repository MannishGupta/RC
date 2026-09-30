<?php // tab_remedies.php — Version: 260916.14
 if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
    <div x-show="activeTab==='remedies'" class="tab-section space-y-6 print-page-break">
        <h2 class="hidden print:block text-lg font-black text-slate-900 uppercase tracking-widest border-b pb-2"><?= $lang==='hi'?'उपाय और रत्न नुस्खा':'Remedies & Gem Prescription' ?></h2>
        <?php $gem=$planetData['gem_prescription']??[];$rem=$lang==='hi'?($planetData['hi_remedies']??$planetData['remedies']??[]):($planetData['remedies']??[]); ?>
        <?php if(!empty($gem)): ?>
        <div class="border border-amber-200 dark:border-amber-700 rounded-lg overflow-hidden shadow-sm">
            <div class="bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-900/30 dark:to-yellow-900/20 px-6 py-4 border-b border-amber-200 dark:border-amber-700 flex items-center gap-3"><i class="fa-solid fa-gem text-amber-500 text-xl"></i><div><h3 class="font-black text-amber-900 dark:text-amber-300 text-sm uppercase tracking-widest"><?= $lang==='hi'?'रत्न नुस्खा':'Gem Prescription' ?></h3><p class="text-xs text-amber-700 dark:text-amber-400"><?= $lang==='hi'?'हेतु':'For' ?> <?= htmlspecialchars($lang==='hi'?(string)($report['lucky_driver']['hi_name']??$report['lucky_driver']['name']??''):(string)($report['lucky_driver']['name']??'')) ?></p></div></div>
            <div class="p-6 grid grid-cols-2 md:grid-cols-3 gap-5 bg-white dark:bg-slate-800">
                <?php
                $_gfDefs = AppNumeroEngine::getData()['gem_display_fields'] ?? [];
                $gemFields = array_map(function($_gf) use ($gem, $lang) {
                    return ['label' => ($lang==='hi' ? ($_gf['hi']??'') : ($_gf['en']??'')),
                            'val'   => $gem[$_gf['key']] ?? '—',
                            'icon'  => $_gf['icon'], 'color' => $_gf['color']];
                }, $_gfDefs);
                foreach($gemFields as $gf): $c=$gf['color']; ?>
                <div class="bg-<?= $c ?>-50 dark:bg-<?= $c ?>-900/20 border border-<?= $c ?>-100 dark:border-<?= $c ?>-800 rounded-lg p-4"><i class="fa-solid <?= $gf['icon'] ?> text-<?= $c ?>-500 text-sm mb-2 block"></i><div class="text-[9px] uppercase font-bold text-<?= $c ?>-600 tracking-widest mb-1"><?= $gf['label'] ?></div><div class="text-sm font-black text-<?= $c ?>-900 dark:text-<?= $c ?>-200"><?= htmlspecialchars((string)($gf['val']??'')) ?></div></div>
                <?php endforeach; ?>
            </div>
            <?php if(!empty($gem['safety_check'])): ?><div class="px-6 py-4 bg-rose-50 dark:bg-rose-900/20 border-t border-rose-200 dark:border-rose-700 flex gap-3 items-start"><i class="fa-solid fa-shield-halved text-rose-500 mt-0.5 flex-shrink-0"></i><div><div class="text-[9px] uppercase font-bold text-rose-700 dark:text-rose-400 tracking-widest mb-1"><?= $lang==='hi'?'सुरक्षा मार्गदर्शन':'Safety Guidance' ?></div><p class="text-xs text-rose-900 dark:text-rose-300"><?= htmlspecialchars((string)$gem['safety_check']) ?></p></div></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if(!empty($rem)): ?>
        <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden bg-white dark:bg-slate-800">
            <div class="bg-slate-50 dark:bg-slate-700 px-6 py-4 border-b dark:border-slate-600"><h3 class="font-bold text-slate-700 dark:text-slate-200 text-xs uppercase tracking-widest"><i class="fa-solid fa-hands-praying text-indigo-500 mr-2"></i> <?= $lang==='hi'?'दैनिक ग्रहीय उपाय':'Daily Planetary Remedies' ?></h3></div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800 rounded-lg p-4"><div class="text-[9px] uppercase font-bold text-emerald-700 dark:text-emerald-400 tracking-widest mb-2"><i class="fa-solid fa-circle-check mr-1"></i> <?= $lang==='hi'?'प्राथमिकता कार्य':'Priority Action' ?></div><p class="text-sm text-emerald-900 dark:text-emerald-300 leading-relaxed"><?= htmlspecialchars((string)($rem['priority']??'')) ?></p></div>
                <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-100 dark:border-rose-800 rounded-lg p-4"><div class="text-[9px] uppercase font-bold text-rose-700 dark:text-rose-400 tracking-widest mb-2"><i class="fa-solid fa-ban mr-1"></i> <?= $lang==='hi'?'बिल्कुल परहेज करें':'Strictly Avoid' ?></div><p class="text-sm text-rose-900 dark:text-rose-300 leading-relaxed"><?= htmlspecialchars((string)($rem['avoid']??'')) ?></p></div>
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-lg p-4"><div class="text-[9px] uppercase font-bold text-amber-700 dark:text-amber-400 tracking-widest mb-2"><i class="fa-solid fa-star mr-1"></i> <?= $lang==='hi'?'ऊर्जा वस्तु':'Energetic Item' ?></div><p class="text-sm text-amber-900 dark:text-amber-300 leading-relaxed"><?= htmlspecialchars((string)($rem['item']??'')) ?></p></div>
            </div>
        </div>
        <?php endif; ?>
        <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden bg-white dark:bg-slate-800">
            <div class="bg-slate-50 dark:bg-slate-700 px-6 py-4 border-b dark:border-slate-600"><h3 class="font-bold text-slate-700 dark:text-slate-200 text-xs uppercase tracking-widest"><i class="fa-solid fa-table mr-2 text-slate-500"></i> <?= $lang==='hi'?'सभी ग्रहीय रत्न संदर्भ':'All Planetary Gem Reference' ?></h3></div>
            <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead><tr class="bg-slate-100 dark:bg-slate-700 text-[10px] uppercase text-slate-500"><th class="p-3 border-b dark:border-slate-600">#</th><th class="p-3 border-b dark:border-slate-600"><?= $lang==='hi'?'ग्रह':'Planet' ?></th><th class="p-3 border-b dark:border-slate-600"><?= $lang==='hi'?'रत्न':'Gem' ?></th><th class="p-3 border-b dark:border-slate-600"><?= $lang==='hi'?'दिन':'Day' ?></th><th class="p-3 border-b dark:border-slate-600"><?= $lang==='hi'?'रंग':'Colors' ?></th></tr></thead>
            <tbody><?php $allP=$report['planetInfo']??[];for($pn=1;$pn<=9;$pn++):$pd=$allP[(string)$pn]??[];if(empty($pd))continue;$isD=$pn===AppNumeroEngine::baseRoot((int)$core['driver']); ?>
            <tr class="<?= $isD?'bg-amber-50 dark:bg-amber-900/10 font-bold':'hover:bg-slate-50 dark:hover:bg-slate-700/30' ?> transition-colors border-b dark:border-slate-700">
                <td class="p-3 font-black text-lg <?= $pd['color']??'text-slate-700' ?>"><?= $pn ?></td>
                <td class="p-3"><span class="mr-1"><?= $pd['icon']??'' ?></span><?= htmlspecialchars($lang==='hi'?(string)($pd['hi_name']??$pd['name']??''):(string)($pd['name']??'')) ?></td>
                <td class="p-3 font-semibold dark:text-slate-300"><?= htmlspecialchars((string)($pd['gem']??'—')) ?></td>
                <td class="p-3 dark:text-slate-300"><?= htmlspecialchars((string)($pd['day']??'—')) ?></td>
                <td class="p-3 text-xs dark:text-slate-400"><?= htmlspecialchars((string)($pd['lucky_col']??'—')) ?></td>
            </tr><?php endfor; ?></tbody></table></div>
        </div>
    </div>
