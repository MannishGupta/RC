<?php // tab_forecast.php — Version: 260916.14
 if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
    <div class="tab-section" x-show="activeTab === 'forecast'">
        <div class="p-6 space-y-6">

        <?php if (!empty($transitAlerts)): ?>
        <div>
            <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">
                <i class="fa-solid fa-triangle-exclamation text-red-500 mr-2"></i>
                <?= $lang==='hi'?'पारगमन चेतावनी':'Transit Alerts' ?>
            </h3>
            <div class="space-y-2">
            <?php foreach ($transitAlerts as $alert):
                $aBg  = $alert['level']==='high'?'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-800':'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800';
                $aTxt = $alert['level']==='high'?'text-red-700 dark:text-red-300':'text-amber-700 dark:text-amber-300';
            ?>
            <div class="flex items-start gap-3 p-4 rounded-lg border <?= $aBg ?>">
                <i class="fa-solid <?= $alert['icon'] ?> <?= $aTxt ?> text-lg mt-0.5 flex-shrink-0"></i>
                <div>
                    <div class="text-sm font-black <?= $aTxt ?>"><?= htmlspecialchars((string)$alert['title']) ?></div>
                    <div class="text-xs <?= $aTxt ?> mt-0.5 opacity-80"><?= htmlspecialchars((string)$alert['desc']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-3 gap-3">
            <?php
            $c3 = [
                ['label'=>$lang==='hi'?'व्यक्तिगत वर्ष':'Personal Year',  'n'=>$report['personal_year']['number']??0,             'clr'=>'orange'],
                ['label'=>$lang==='hi'?'व्यक्तिगत माह':'Personal Month', 'n'=>$report['personal_cycle']['personal_month']??0, 'clr'=>'purple'],
                ['label'=>$lang==='hi'?'व्यक्तिगत दिन':'Personal Day',   'n'=>$report['personal_cycle']['personal_day']??0,   'clr'=>'blue'],
            ];
            foreach ($c3 as $cyc): ?>
            <div class="p-4 rounded-lg border dark:border-slate-700 bg-white dark:bg-slate-800 text-center shadow-sm">
                <div class="text-[9px] font-black text-<?= $cyc['clr'] ?>-600 uppercase tracking-widest"><?= $cyc['label'] ?></div>
                <div class="text-4xl font-black text-<?= $cyc['clr'] ?>-600 my-1"><?= (int)$cyc['n'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php
        $pyNum   = (int)($report['personal_year']['number']??0);
        $annMap  = $_numData['annual_months_map'] ?? [];
        $curMo   = (int)date('n');
        $moEn    = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $moHi    = ['जन.','फर.','मार्च','अप्र.','मई','जून','जुल.','अग.','सित.','अक्.','नव.','दिस.'];
        $moNames = $lang==='hi' ? $moHi : $moEn;
        ?>
        <div>
            <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">
                <i class="fa-solid fa-calendar-days text-indigo-500 mr-2"></i>
                <?= $lang==='hi'?'वार्षिक 12-माह पूर्वानुमान — PY '.$pyNum:'Annual 12-Month Forecast — Personal Year '.$pyNum ?>
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php for ($mo = 1; $mo <= 12; $mo++):
                $pmN  = AppNumeroEngine::reduceChaldean($pyNum + $mo);
                $key  = $pyNum.'-'.$pmN;
                $thm  = $annMap[$key] ?? 'Growth & alignment';
                $isCur= ($mo === $curMo);
                $isPast= ($mo < $curMo);
            ?>
            <div class="p-3 rounded-lg border <?= ($isCur?'border-indigo-500 ring-2 ring-indigo-400 bg-indigo-50 dark:bg-indigo-950/30':($isPast?'border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 opacity-50':'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800')) ?> relative">
                <?php if ($isCur): ?>
                <span class="absolute top-1.5 right-2 text-[8px] font-black text-indigo-500 uppercase"><?= $lang==='hi'?'अभी':'Now' ?></span>
                <?php endif; ?>
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="text-[10px] font-black text-slate-500 uppercase"><?= $moNames[$mo-1] ?></span>
                    <span class="w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 text-[10px] font-black flex items-center justify-center"><?= $pmN ?></span>
                </div>
                <div class="text-[10px] text-slate-600 dark:text-slate-400 leading-snug"><?= htmlspecialchars((string)$thm) ?></div>
            </div>
            <?php endfor; ?>
            </div>
        </div>

        </div>
    </div>


