<?php // tab_corrections.php — Version: 260916.14
 if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
<?php if(($report['tier']??'')!=='free'): ?>
    <div x-show="activeTab==='corrections'" class="tab-section space-y-6 print-page-break">
        <h2 class="hidden print:block text-lg font-black text-slate-900 uppercase tracking-widest border-b pb-2"><?= $lang==='hi'?'यौगिक निदान और नाम सुधार':'Compound Diagnostics & Name Corrections' ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <?php
            $nameNumbers=[
                ['label'=>($lang==='hi'?'प्रथम नाम अभिव्यक्ति':'First Name Expression'),'data'=>$report['name_matrix']['first']??[],'color'=>'text-purple-600'],
                ['label'=>($lang==='hi'?'पूर्ण नाम अभिव्यक्ति':'Full Name Expression'),'data'=>$report['name_matrix']['full']??[],'color'=>'text-indigo-600'],
                ['label'=>($lang==='hi'?'आत्म प्रेरणा (स्वर)':'Soul Urge (Vowels)'),'data'=>$report['name_matrix']['soul_urge']??[],'color'=>'text-rose-600'],
                ['label'=>($lang==='hi'?'व्यक्तित्व (व्यंजन)':'Personality (Consonants)'),'data'=>$report['name_matrix']['personality']??[],'color'=>'text-emerald-600'],
            ];
            foreach($nameNumbers as $nn): $d=$nn['data']; ?>
            <div class="bg-slate-50 dark:bg-slate-700 p-5 rounded-lg border dark:border-slate-600">
                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1"><?= $nn['label'] ?></div>
                <div class="text-xl font-black text-slate-900 dark:text-white"><?= htmlspecialchars((string)($d['display']??$p['name']??'')) ?></div>
                <div class="text-sm font-bold <?= $nn['color'] ?> mb-3"><?= $lang==='hi'?'यौगिक':'Compound' ?>: <?= (int)($d['compound']??0) ?> | <?= $lang==='hi'?'मूल':'Root' ?>: <?= (int)($d['root']??0) ?></div>
                <?php if(!empty($d['compound_info'])): ?>
                <div class="flex items-start gap-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold whitespace-nowrap <?= getVerdictColor($d['compound_info']['verdict']??'Neutral') ?>"><?= htmlspecialchars((string)($d['compound_info']['verdict']??'Neutral')) ?></span>
                    <div class="text-xs text-slate-600 dark:text-slate-400"><strong><?= htmlspecialchars((string)($d['compound_info']['name']??'')) ?>:</strong> <?= htmlspecialchars((string)($d['compound_info']['meaning']??'')) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="p-5 bg-white dark:bg-slate-800 border dark:border-slate-600 rounded-lg flex justify-between items-center flex-wrap gap-3">
            <div><div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1"><?= $lang==='hi'?'नाम–ग्रह सामंजस्य':'Name–Planet Harmony' ?></div><div class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= htmlspecialchars((string)($report['name_matrix']['harmony']['desc']??'')) ?></div></div>
            <span class="px-4 py-2 rounded-lg text-sm font-bold uppercase tracking-widest <?= getStatusColor($report['name_matrix']['harmony']['status']??'') ?>"><?= htmlspecialchars((string)($report['name_matrix']['harmony']['status']??'')) ?></span>
        </div>
        <?php if(!empty($report['name_matrix']['conflict'])): ?>
        <div class="p-5 border dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800">
            <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4"><i class="fa-solid fa-scale-balanced mr-1.5 text-indigo-400"></i> Name–Grid Energy Alignment</h4>
            <div class="bg-slate-50 dark:bg-slate-700 p-4 rounded-lg border dark:border-slate-600 flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div class="flex-1">
                    <div class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars((string)($report['name_matrix']['conflict']['desc']??'')) ?></div>
                    <div class="text-xs text-slate-500 mt-1">
                        Energies Healed by Name: <span class="text-emerald-600 font-bold"><?= implode(', ',array_map('intval',$report['name_matrix']['conflict']['healed']??[]))?:'None' ?></span>
                        &nbsp;·&nbsp;
                        Creative Tension Points: <span class="text-amber-600 dark:text-amber-400 font-bold"><?= implode(', ',array_map('intval',$report['name_matrix']['conflict']['excess']??[]))?:'None' ?></span>
                    </div>
                </div>
                <?php
                $cl = $report['name_matrix']['conflict']['level'] ?? 'None';
                $clLabel = $cl==='None'?'Perfect Harmony':($cl==='High'?'High Growth Potential':'Creative Tension');
                $clStyle = $cl==='None'?'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300':($cl==='High'?'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300':'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300');
                ?>
                <span class="px-3 py-1 rounded text-xs font-bold uppercase tracking-widest <?= $clStyle ?>"><?= $clLabel ?></span>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($report['name_matrix']['name_is_optimal'])): ?>
        <div class="p-5 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-300 dark:border-emerald-700 rounded-lg flex items-start gap-4">
            <i class="fa-solid fa-circle-check text-emerald-500 text-xl mt-0.5 flex-shrink-0"></i>
            <div>
                <div class="text-sm font-black text-emerald-800 dark:text-emerald-200 mb-1"><?= $lang==='hi'?'नाम पहले से कंपनात्मक रूप से अनुकूलित':'Name Already Vibrationally Optimized' ?></div>
                <p class="text-xs text-emerald-700 dark:text-emerald-400"><?= $lang==='hi'?'पूर्ण नाम मूल':'Full Name root' ?> <strong><?= (int)($report['name_matrix']['full']['root']??0) ?></strong> <?= $lang==='hi'?'Driver के साथ संरेखित':'is aligned with Driver' ?> <?= (int)$core['driver'] ?> <?= $lang==='hi'?'और यौगिक है':'and compound is' ?> <strong><?= htmlspecialchars((string)($report['name_matrix']['full']['compound_info']['verdict']??'')) ?></strong>. <?= $lang==='hi'?'कोई सुधार आवश्यक नहीं।':'No correction needed.' ?></p>
            </div>
        </div>
        <?php elseif (!empty($report['name_matrix']['corrections'])): ?>
        <?php
        $_mmDefs = AppNumeroEngine::getData()['name_matrix_metrics'] ?? [];
        $mDefs = array_map(function($_d) use ($lang) {
            return ['label'=>($lang==='hi'?($_d['label_hi']??''):($_d['label_en']??'')),
                    'ok'=>$_d['ok'],'oc'=>$_d['oc'],'nk'=>$_d['nk'],'nc'=>$_d['nc'],
                    'w'=>$_d['weight'],'desc'=>($lang==='hi'?($_d['desc_hi']??''):($_d['desc_en']??''))];
        }, $_mmDefs);
        $rCols=array('indigo','purple','teal');
        ?>
        <div class="space-y-5">
        <?php foreach ($report['name_matrix']['corrections'] as $rank => $c): $rc=isset($rCols[$rank])?$rCols[$rank]:'slate'; ?>
            <div class="border border-<?= $rc ?>-200 dark:border-<?= $rc ?>-700 rounded-lg overflow-hidden shadow-sm">
                <div class="bg-<?= $rc ?>-50 dark:bg-<?= $rc ?>-900/20 px-5 py-3 border-b border-<?= $rc ?>-200 dark:border-<?= $rc ?>-700 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <?php if ($rank===0): ?><span class="text-[9px] font-bold bg-<?= $rc ?>-100 text-<?= $rc ?>-700 px-2 py-0.5 rounded-full mr-2">★ <?= $lang==='hi'?'सर्वोत्तम':'Optimal' ?></span><?php endif; ?>
                        <span class="font-bold text-lg text-<?= $rc ?>-800 dark:text-<?= $rc ?>-200"><?= htmlspecialchars((string)($c['name']??'')) ?></span>
                        <?php if (!empty($c['added_letters'])): ?>
                        <span class="text-[10px] text-slate-500 ml-2"><?= $lang==='hi'?'जोड़ें:':'Add:' ?> <strong class="text-emerald-600"><?= htmlspecialchars((string)$c['added_letters']) ?></strong></span>
                        <?php endif; ?>
                        <?php
                        // ✅ V15: "Try It" — open report with prescribed spelling
                        $tryParams = $_GET;
                        $tryParams['name'] = $c['name'] ?? '';
                        unset($tryParams['compare_name'], $tryParams['compare_dob'], $tryParams['compare_gender']);
                        $tryUrl = '?'.http_build_query($tryParams);
                        ?>
                        <a href="<?= htmlspecialchars($tryUrl) ?>"
                           class="ml-2 inline-flex items-center gap-1 text-[9px] font-black bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-0.5 rounded-full transition-all">
                            <i class="fa-solid fa-play text-[8px]"></i>
                            <?= $lang==='hi'?'आज़माएं':'Try It' ?>
                        </a>
                    </div>
                    <div class="flex gap-2 items-center">
                        <span class="text-sm font-black text-<?= $rc ?>-700"><?= $lang==='hi'?'स्कोर':'Score' ?> <?= (int)($c['score']??0) ?> <span class="text-xs text-emerald-600">+<?= (int)($c['score_improvement']??0) ?></span></span>
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold <?= getVerdictColor($c['compound_verdict']??'Neutral') ?>"><?= htmlspecialchars((string)($c['compound_verdict']??'')) ?></span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="bg-slate-50 dark:bg-slate-700 text-[9px] uppercase text-slate-500">
                            <th class="p-3 border-b dark:border-slate-600 text-left"><?= $lang==='hi'?'मेट्रिक':'Metric' ?></th>
                            <th class="p-3 border-b dark:border-slate-600 text-center"><?= $lang==='hi'?'वर्तमान':'Current' ?></th>
                            <th class="p-3 border-b dark:border-slate-600 text-center"><?= $lang==='hi'?'अनुशंसित':'Prescribed' ?></th>
                            <th class="p-3 border-b dark:border-slate-600 text-center w-10"><?= $lang==='hi'?'वजन':'Wt' ?></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <?php foreach ($mDefs as $md):
                            $oR=(int)(isset($c[$md['ok']])?$c[$md['ok']]:0); $nR=(int)(isset($c[$md['nk']])?$c[$md['nk']]:0);
                            $oC=(int)(isset($c[$md['oc']])?$c[$md['oc']]:0); $nC=(int)(isset($c[$md['nc']])?$c[$md['nc']]:0);
                            $drv=(int)$core['driver']; $frd=$report['lucky_driver']['friends']??array();
                            $oBg=($oR===$drv)?'bg-amber-100 text-amber-800':(in_array($oR,$frd,true)?'bg-emerald-100 text-emerald-800':'bg-slate-100 text-slate-500');
                            $nBg=($nR===$drv)?'bg-amber-100 text-amber-800':(in_array($nR,$frd,true)?'bg-emerald-100 text-emerald-800':'bg-slate-100 text-slate-500');
                            $oLbl=($oR===$drv)?($lang==='hi'?'चालक':'Driver'):(in_array($oR,$frd,true)?($lang==='hi'?'अनुकूल':'Friendly'):($lang==='hi'?'तटस्थ':'Neutral'));
                            $nLbl=($nR===$drv)?($lang==='hi'?'चालक':'Driver'):(in_array($nR,$frd,true)?($lang==='hi'?'अनुकूल':'Friendly'):($lang==='hi'?'तटस्थ':'Neutral'));
                        ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/20">
                                <td class="p-3"><div class="text-xs font-bold text-slate-700 dark:text-slate-200"><?= $md['label'] ?></div><div class="text-[9px] text-slate-400"><?= $md['desc'] ?></div></td>
                                <td class="p-3 text-center"><div class="text-xl font-black text-slate-500"><?= $oR ?></div><div class="text-[9px] text-slate-400">/<?= $oC ?></div><span class="text-[8px] px-1 py-0.5 rounded font-bold <?= $oBg ?>"><?= $oLbl ?></span></td>
                                <td class="p-3 text-center bg-<?= $rc ?>-50/30 relative"><?php if($nR!==$oR): ?><span class="absolute top-1 right-1 text-emerald-500 text-[10px]">↑</span><?php endif; ?><div class="text-xl font-black text-<?= $rc ?>-700 dark:text-<?= $rc ?>-300"><?= $nR ?></div><div class="text-[9px] text-slate-400">/<?= $nC ?></div><span class="text-[8px] px-1 py-0.5 rounded font-bold <?= $nBg ?>"><?= $nLbl ?></span></td>
                                <td class="p-3 text-center"><span class="text-[9px] font-black text-slate-500 bg-slate-100 px-1 py-0.5 rounded">x<?= $md['w'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($c['compound_name'])): ?><div class="px-5 py-2 bg-slate-50 dark:bg-slate-700/30 border-t dark:border-slate-700 text-[9px] text-slate-400"><?= $lang==='hi'?'यौगिक':'Compound' ?> <?= (int)($c['full_compound']??0) ?> = <em>"<?= htmlspecialchars((string)$c['compound_name']) ?>"</em></div><?php endif; ?>
            <?php if (!empty($c["planet_sig"]["areas"])): ?>
            <div class="px-5 py-4 bg-amber-50 dark:bg-amber-900/20 border-t border-amber-100 dark:border-amber-800">
                <div class="text-[9px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-widest mb-2"><i class="fa-solid fa-dharmachakra mr-1.5"></i><?= $lang==='hi'?'ज्योतिष ग्रह — Chaldean':'Jyotish Planet Analysis — Chaldean' ?></div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs mb-2">
                    <div class="bg-white dark:bg-slate-800 rounded-lg p-2.5 border border-amber-200 dark:border-amber-800"><div class="font-black text-amber-700 text-[10px] mb-1"><?= $lang==='hi'?'पूर्ण नाम ग्रह':'Full Name Planet' ?> — <?= htmlspecialchars((string)($c["planet_sig"]["planet"]??'')) ?> (<?= (int)($c["full_root"]??0) ?>)</div><div class="text-[10px] text-slate-500 leading-snug"><?= htmlspecialchars((string)($c["planet_sig"]["areas"]??'')) ?></div></div>
                    <div class="bg-white dark:bg-slate-800 rounded-lg p-2.5 border border-rose-200 dark:border-rose-800"><div class="font-black text-rose-600 text-[10px] mb-1"><?= $lang==='hi'?'आत्म ग्रह':'Soul Urge Planet' ?> — <?= htmlspecialchars((string)($c["su_planet"]["planet"] ?? "")) ?> (<?= (int)($c["su_root"]??0) ?>)</div><div class="text-[10px] text-slate-500 leading-snug"><?= htmlspecialchars((string)($c["su_planet"]["areas"] ?? "")) ?></div></div>
                    <div class="bg-white dark:bg-slate-800 rounded-lg p-2.5 border border-blue-200 dark:border-blue-800"><div class="font-black text-blue-600 text-[10px] mb-1"><?= $lang==='hi'?'व्यक्तित्व ग्रह':'Personality Planet' ?> — <?= htmlspecialchars((string)($c["p_planet"]["planet"] ?? "")) ?> (<?= (int)($c["p_root"]??0) ?>)</div><div class="text-[10px] text-slate-500 leading-snug"><?= htmlspecialchars((string)($c["p_planet"]["areas"] ?? "")) ?></div></div>
                </div>
                <div class="text-[9px] text-amber-600 italic"><i class="fa-solid fa-circle-info mr-1"></i><?= $lang==='hi'?'ये ऊर्जात्मक संभावनाएं हैं — गारंटी नहीं।':'Energetic potentials only — not guarantees. Outcomes depend on free will and karmic load.' ?></div>
            </div>
            <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg text-sm text-amber-800 dark:text-amber-300"><i class="fa-solid fa-circle-info mr-2"></i> <?= $lang==='hi'?'कोई सुधार 4 मेट्रिक्स में एक साथ सुधार नहीं करता। वर्तमान नाम पहले से लगभग इष्टतम हो सकता है।':'No spelling adjustment improves all 4 Chaldean metrics simultaneously. The current name may already be near-optimal.' ?></div>
        <?php endif; ?>
        <div class="mt-5 p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg text-xs text-indigo-800 dark:text-indigo-300 leading-relaxed">
            <i class="fa-solid fa-circle-info text-indigo-500 mr-2"></i>
            <strong><?= $lang==='hi'?'महत्वपूर्ण नोट':'Note' ?>:</strong>
            <?= $lang==='hi'?'जब तक अनुशंसित अक्षर जोड़ या हटाव आपके नाम के निर्दिष्ट भाग (First, Middle या Last) में रहे, आप दृश्य सौंदर्य और आरामदायकता के लिए इसकी सटीक स्थिति को समायोजित कर सकते हैं — इससे अंकशास्त्रीय परिणाम नहीं बदलेगा।':'As long as the recommended alphabet addition or deletion remains within the specified part of your name (First, Middle, or Last), you may adjust its exact placement for visual comfort and aesthetics without altering the numerological outcome.' ?>
        </div>

        <div class="p-5 border border-indigo-200 dark:border-indigo-700 rounded-lg bg-white dark:bg-slate-800 shadow-sm print:hidden"
             x-data="{
                corrName: '<?= htmlspecialchars(addslashes((string)($report['name_matrix']['corrections'][0]['name'] ?? $p['name'])), ENT_QUOTES) ?>',
                loading: false,
                result: null,
                error: null,
                chaldean: {A:1,B:2,C:3,D:4,E:5,F:8,G:3,H:5,I:1,J:1,K:2,L:3,M:4,N:5,O:7,P:8,Q:1,R:2,S:3,T:4,U:6,V:6,W:6,X:5,Y:1,Z:7},
                reduce(n) { while(n>9 && n!==11 && n!==22 && n!==33) n=String(n).split('').reduce((s,d)=>s+parseInt(d),0); return n; },
                analyse() {
                    const name = this.corrName.trim(); if(!name) return;
                    this.loading=true; this.result=null; this.error=null;
                    const clean = name.toUpperCase().replace(/[^A-Z]/g,'');
                    const vowels = 'AEIOU';
                    const fullVal   = clean.split('').reduce((s,c)=>s+(this.chaldean[c]||0),0);
                    const suVal     = clean.split('').filter(c=>vowels.includes(c)).reduce((s,c)=>s+(this.chaldean[c]||0),0);
                    const persVal   = clean.split('').filter(c=>!vowels.includes(c)).reduce((s,c)=>s+(this.chaldean[c]||0),0);
                    const fLetters  = name.trim().split(/\s+/)[0].toUpperCase().replace(/[^A-Z]/g,'');
                    const firstVal  = fLetters.split('').reduce((s,c)=>s+(this.chaldean[c]||0),0);
                    const driver    = <?= (int)($core['driver']??1) ?>;
                    const friends   = <?= json_encode(array_map('intval', $report['lucky_driver']['friends']??[])) ?>;
                    const mkEntry = (compound, label) => {
                        const root = this.reduce(compound);
                        let status = 'Neutral';
                        if(root===driver) status='Driver';
                        else if(friends.includes(root)) status='Friendly';
                        const verdicts = {14:'Fortunate',23:'Royal Star',32:'Communication',41:'Fortunate',5:'Uncertain',23:'Synergy',
                            4:'Foundation',8:'Saturn',13:'Change',16:'Warning',19:'Prince of Heaven',
                            26:'Warning',28:'Conflict',11:'Master',22:'Master',33:'Master'};
                        return { label, compound, root, status,
                            verdict: verdicts[compound] || (root===driver?'Aligned':(friends.includes(root)?'Friendly':'Neutral')) };
                    };
                    this.result = {
                        name,
                        full:    mkEntry(fullVal,  '<?= $lang==='hi'?'पूर्ण नाम':'Full Name' ?>'),
                        soul:    mkEntry(suVal,    '<?= $lang==='hi'?'आत्म प्रेरणा':'Soul Urge' ?>'),
                        persona: mkEntry(persVal,  '<?= $lang==='hi'?'व्यक्तित्व':'Personality' ?>'),
                        first:   mkEntry(firstVal, '<?= $lang==='hi'?'प्रथम नाम':'First Name' ?>'),
                    };
                    this.loading=false;
                }
             }"
             x-init="analyse()">

            <h3 class="font-bold text-slate-800 dark:text-slate-200 uppercase tracking-widest text-[10px] mb-4">
                <i class="fa-solid fa-flask-vial text-indigo-500 mr-2"></i>
                <?= $lang==='hi'?'कस्टम नाम विश्लेषण — लाइव':'Custom Name Analyser — Live' ?>
            </h3>

            <div class="flex gap-2 flex-wrap mb-4">
                <input x-model="corrName" type="text"
                       placeholder="<?= $lang==='hi'?'सुझाया गया या कोई भी नाम...':'Suggested or any name...' ?>"
                       class="flex-1 min-w-[200px] text-sm bg-slate-50 dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 rounded-lg border border-indigo-300 dark:border-indigo-600 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-800 font-semibold"
                       @keydown.enter="analyse()">
                <button @click="analyse()"
                        :disabled="loading"
                        class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors shadow-sm">
                    <i class="fa-solid" :class="loading?'fa-circle-notch fa-spin':'fa-magnifying-glass'"></i>
                    <span><?= $lang==='hi'?'विश्लेषण करें':'Analyse' ?></span>
                </button>
            </div>

            <div x-show="result" x-transition class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-700 text-[9px] uppercase text-slate-500">
                            <th class="p-2.5 border-b dark:border-slate-600 text-left"><?= $lang==='hi'?'मेट्रिक':'Metric' ?></th>
                            <th class="p-2.5 border-b dark:border-slate-600 text-center"><?= $lang==='hi'?'यौगिक':'Compound' ?></th>
                            <th class="p-2.5 border-b dark:border-slate-600 text-center"><?= $lang==='hi'?'मूल':'Root' ?></th>
                            <th class="p-2.5 border-b dark:border-slate-600 text-center"><?= $lang==='hi'?'स्थिति':'Status' ?></th>
                            <th class="p-2.5 border-b dark:border-slate-600 text-center"><?= $lang==='hi'?'निर्णय':'Verdict' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <template x-for="(row,key) in [result?.full, result?.soul, result?.persona, result?.first].filter(Boolean)" :key="key">
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/20 transition-colors">
                                <td class="p-2.5 text-xs font-bold text-slate-700 dark:text-slate-300" x-text="row.label"></td>
                                <td class="p-2.5 text-center">
                                    <span class="text-lg font-black text-indigo-600 dark:text-indigo-300" x-text="row.compound"></span>
                                </td>
                                <td class="p-2.5 text-center">
                                    <span class="text-lg font-black text-slate-800 dark:text-white" x-text="row.root"></span>
                                </td>
                                <td class="p-2.5 text-center">
                                    <span class="text-[9px] px-2 py-0.5 rounded font-bold"
                                          :class="{
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300': row.status==='Driver',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300': row.status==='Friendly',
                                            'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400': row.status==='Neutral'
                                          }"
                                          x-text="row.status"></span>
                                </td>
                                <td class="p-2.5 text-center">
                                    <span class="text-[9px] px-2 py-0.5 rounded font-bold"
                                          :class="{
                                            'bg-emerald-100 text-emerald-800': ['Aligned','Friendly','Fortunate','Royal Star','Communication','Prince of Heaven','Synergy'].includes(row.verdict),
                                            'bg-amber-100 text-amber-800': ['Neutral','Master'].includes(row.verdict),
                                            'bg-rose-100 text-rose-800': ['Warning','Conflict'].includes(row.verdict),
                                            'bg-slate-100 text-slate-600': !['Aligned','Friendly','Fortunate','Royal Star','Communication','Prince of Heaven','Synergy','Neutral','Master','Warning','Conflict'].includes(row.verdict)
                                          }"
                                          x-text="row.verdict"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="result" class="bg-indigo-50 dark:bg-indigo-900/20 px-4 py-2 flex items-center gap-2 border-t border-indigo-100 dark:border-indigo-800">
                    <i class="fa-solid fa-circle-info text-indigo-400 text-[10px]"></i>
                    <span class="text-[10px] text-indigo-700 dark:text-indigo-300 font-semibold">
                        <?= $lang==='hi'?'चालक':'Driver' ?> <?= (int)$core['driver'] ?> ·
                        <?= $lang==='hi'?'अनुकूल':'Friendly' ?>: <?= implode(', ', array_map('intval', $report['lucky_driver']['friends']??[])) ?>
                    </span>
                    <span class="ml-auto text-[9px] font-bold text-indigo-500 cursor-pointer hover:text-indigo-700"
                          @click="corrName=''; $nextTick(()=>document.querySelector('#custom-name-input')?.focus())"><?= $lang==='hi'?'रीसेट':'Reset' ?></span>
                </div>
            </div>
        </div>
        <?php $planetData=$report['lucky_driver']['data']??[]; ?>

        <?php if(!empty($planetData['lucky_matrix'])): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="p-5 bg-slate-50 dark:bg-slate-700 rounded-lg border dark:border-slate-600"><h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-3"><i class="fa-solid fa-palette mr-1.5"></i> <?= $lang==='hi'?'आभा रंग':'Aura Colors' ?></h4><ul class="space-y-2 text-xs"><li class="flex justify-between"><span class="font-semibold text-slate-600 dark:text-slate-400"><?= $lang==='hi'?'व्यावसायिक':'Professional' ?></span><span class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(implode(', ',$planetData['lucky_matrix']['colors']['professional']??[])) ?></span></li><li class="flex justify-between pt-1 border-t dark:border-slate-600"><span class="font-semibold text-slate-600 dark:text-slate-400"><?= $lang==='hi'?'दैनिक':'Daily' ?></span><span class="font-bold dark:text-white"><?= htmlspecialchars(implode(', ',$planetData['lucky_matrix']['colors']['daily']??[])) ?></span></li><li class="flex justify-between pt-1 border-t dark:border-slate-600 text-rose-600"><span class="font-semibold"><?= $lang==='hi'?'बचें':'Avoid' ?></span><span class="font-bold"><?= htmlspecialchars(implode(', ',$planetData['lucky_matrix']['colors']['avoid']??[])) ?></span></li></ul></div>
            <div class="p-5 bg-slate-50 dark:bg-slate-700 rounded-lg border dark:border-slate-600"><h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-3"><i class="fa-solid fa-calendar-week mr-1.5"></i> <?= $lang==='hi'?'शक्तिशाली दिन':'Power Days' ?></h4><ul class="space-y-2 text-xs"><li class="flex justify-between"><span class="font-semibold text-emerald-700"><?= $lang==='hi'?'शक्ति':'Power' ?></span><span class="font-bold text-emerald-900 dark:text-emerald-400"><?= htmlspecialchars(implode(', ',$planetData['lucky_matrix']['days']['power']??[])) ?></span></li><li class="flex justify-between pt-1 border-t dark:border-slate-600"><span class="font-semibold text-slate-600 dark:text-slate-400"><?= $lang==='hi'?'तटस्थ':'Neutral' ?></span><span class="font-bold dark:text-white"><?= htmlspecialchars(implode(', ',$planetData['lucky_matrix']['days']['neutral']??[])) ?></span></li><li class="flex justify-between pt-1 border-t dark:border-slate-600 text-rose-600"><span class="font-semibold"><?= $lang==='hi'?'टालें':'Postpone' ?></span><span class="font-bold"><?= htmlspecialchars(implode(', ',$planetData['lucky_matrix']['days']['avoid']??[])) ?></span></li></ul></div>
            <div class="p-5 bg-slate-50 dark:bg-slate-700 rounded-lg border dark:border-slate-600"><h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-3"><i class="fa-solid fa-hashtag mr-1.5"></i> <?= $lang==='hi'?'भाग्यशाली संख्याएं':'Lucky Numbers' ?></h4><div class="space-y-2 text-xs"><div class="flex items-center justify-between gap-2"><span class="font-semibold text-emerald-700 shrink-0"><?= $lang==='hi'?'प्राथमिक':'Primary' ?></span><div class="flex gap-1.5 flex-wrap justify-end"><?php foreach($planetData['lucky_matrix']['numbers']['primary']??[] as $n): ?><span class="w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-800 text-emerald-800 dark:text-emerald-200 font-black flex items-center justify-center text-sm"><?= (int)$n ?></span><?php endforeach; ?></div></div><div class="pt-1 border-t dark:border-slate-600 flex items-center justify-between gap-2"><span class="font-semibold text-slate-600 dark:text-slate-400 shrink-0"><?= $lang==='hi'?'द्वितीयक':'Secondary' ?></span><div class="flex gap-1.5 flex-wrap justify-end"><?php foreach($planetData['lucky_matrix']['numbers']['secondary']??[] as $n): ?><span class="w-7 h-7 rounded-full bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold flex items-center justify-center text-sm"><?= (int)$n ?></span><?php endforeach; ?></div></div><?php if(!empty($planetData['lucky_matrix']['numbers']['avoid'])): ?><div class="pt-1 border-t dark:border-slate-600 flex items-center justify-between gap-2"><span class="font-semibold text-rose-600 shrink-0"><?= $lang==='hi'?'बचें':'Avoid' ?></span><div class="flex gap-1.5 flex-wrap justify-end"><?php foreach($planetData['lucky_matrix']['numbers']['avoid'] as $n): ?><span class="w-7 h-7 rounded-full bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300 font-bold flex items-center justify-center text-sm"><?= (int)$n ?></span><?php endforeach; ?></div></div><?php endif; ?></div></div>
        </div>
        <?php endif; ?>

    </div><?php endif; ?>
