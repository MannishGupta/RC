<?php // tab_compatibility.php — Version: 260916.14
 if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
    <div x-show="activeTab==='compatibility'" class="tab-section space-y-6 print-page-break">
        <h2 class="hidden print:block text-lg font-black text-slate-900 uppercase tracking-widest border-b pb-2"><?= $lang==='hi'?'अनुकूलता विश्लेषण':'Compatibility Analysis' ?></h2>

        <div class="p-5 border-2 border-dashed border-indigo-200 dark:border-indigo-700 bg-indigo-50/50 dark:bg-indigo-900/20 rounded-lg print:hidden">
            <div class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest mb-3"><i class="fa-solid fa-people-arrows mr-1.5"></i> <?= $lang==='hi'?'व्यक्ति B दर्ज करें':'Enter Person B to Compare' ?></div>
            <?php $formAction = strtok($_SERVER['REQUEST_URI'] ?? '/', '?'); ?>
            <form method="GET" action="<?= htmlspecialchars((string)$formAction) ?>" class="flex flex-wrap gap-3 items-end">
                <?php foreach ($_GET as $gk => $gv): if (in_array($gk,['compare_name','compare_dob','compare_gender','compare_relation','tab'])) continue; ?>
                <input type="hidden" name="<?= htmlspecialchars((string)$gk) ?>" value="<?= htmlspecialchars((string)$gv) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="tab" value="compatibility">
                <?php
                // Load team directory for quick-select
                $teamList = [];
                if (class_exists('AppDB')) {
                    $allTeam = method_exists('AppDB','read') ? (AppDB::read('team') ?? []) : [];
                    foreach ($allTeam as $tm) {
                        if (!empty($tm['name']) && !empty($tm['dob'])) $teamList[] = $tm;
                    }
                }
                ?>
                <?php
                $userGender  = strtolower($p['gender'] ?? 'unknown');
                $oppGender   = ($userGender === 'male') ? 'Female' : (($userGender === 'female') ? 'Male' : 'Female');
                $cmpGender   = $_GET['compare_gender'] ?? $oppGender;
                $selRel = $_GET['compare_relation'] ?? 'spouse';
                $_relData = AppNumeroEngine::getData()['relationship_types'] ?? [];
                $relations = [];
                foreach ($_relData as $_rk => $_rv) {
                    $relations[$_rk] = $lang==='hi' ? ($_rv['hi'] ?? $_rv['en']) : $_rv['en'];
                }
                ?>
                <div class="w-full flex flex-wrap gap-3 items-end">
                    <?php if (!empty($teamList)): ?>
                    <div class="flex-1 min-w-[200px]">
                        <label class="text-[9px] font-bold text-indigo-600 uppercase tracking-widest block mb-1">
                            <i class="fa-solid fa-address-book mr-1"></i><?= $lang==='hi'?'डायरेक्टरी से चुनें':'Select from Directory' ?>
                        </label>
                        <select id="compat-team-select"
                                onchange="(function(s){
                                    var opt=s.options[s.selectedIndex];
                                    var d={};try{d=JSON.parse(opt.dataset.member||'{}');}catch(e){}
                                    if(d.name){ var fn=document.getElementById('compare_name_field'); if(fn) fn.value=d.name; }
                                    if(d.dob){ var fd=document.getElementById('compare_dob_field'); if(fd) fd.value=d.dob; }
                                    if(d.gender){
                                        var gs=document.getElementById('compare_gender_field');
                                        if(gs) for(var i=0;i<gs.options.length;i++) gs.options[i].selected=(gs.options[i].value===d.gender);
                                    }
                                })(this)"
                                class="w-full text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 rounded-lg border border-indigo-200 dark:border-indigo-600 outline-none focus:border-indigo-500">
                            <option value=""><?= $lang==='hi'?'— डायरेक्टरी से चुनें —':'— Pick from directory —' ?></option>
                            <?php foreach ($teamList as $tm):
                                if (($tm['slug']??'') === ($slug??'')) continue;
                                $dobFmt    = date('Y-m-d', strtotime(str_replace('/','-',(string)$tm['dob'])));
                                $mJson     = json_encode(['name'=>$tm['name'],'dob'=>$dobFmt,'gender'=>$tm['gender']??'Unknown'], JSON_HEX_QUOT);
                            ?>
                            <option value="<?= htmlspecialchars((string)($tm['slug']??$tm['name'])) ?>"
                                    data-member="<?= htmlspecialchars((string)$mJson) ?>"
                                    <?= (($_GET['compare_name']??'')===$tm['name'])?'selected':'' ?>>
                                <?= htmlspecialchars((string)$tm['name']) ?><?php if (!empty($tm['designation'])): ?> — <?= htmlspecialchars((string)$tm['designation']) ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="text-[9px] font-bold text-indigo-600 uppercase tracking-widest block mb-1"><?= $lang==='hi'?'संबंध प्रकार':'Relationship Type' ?></label>
                        <select name="compare_relation" class="text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 rounded border border-indigo-200 dark:border-indigo-600 outline-none focus:border-indigo-500">
                            <?php foreach ($relations as $rv => $rl): ?>
                            <option value="<?= $rv ?>" <?= $selRel===$rv?'selected':'' ?>><?= $rl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex-1 min-w-[160px]">
                    <label class="text-[9px] font-bold text-indigo-600 uppercase tracking-widest block mb-1"><?= $lang==='hi'?'पूरा नाम':'Full Name' ?></label>
                    <input id="compare_name_field" name="compare_name" placeholder="e.g. Priya Sharma" value="<?= htmlspecialchars((string)($_GET['compare_name'] ?? '')) ?>"
                           class="w-full text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 rounded-lg border border-indigo-200 dark:border-indigo-600 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-indigo-600 uppercase tracking-widest block mb-1"><?= $lang==='hi'?'जन्म तिथि':'Date of Birth' ?></label>
                    <input id="compare_dob_field" name="compare_dob" type="date" value="<?= htmlspecialchars((string)($_GET['compare_dob'] ?? '')) ?>"
                           class="text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 rounded-lg border border-indigo-200 dark:border-indigo-600 outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-indigo-600 uppercase tracking-widest block mb-1"><?= $lang==='hi'?'लिंग':'Gender' ?></label>
                    <select id="compare_gender_field" name="compare_gender" class="text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 rounded border border-indigo-200 dark:border-indigo-600 outline-none focus:border-indigo-500">
                        <option value="Female" <?= $cmpGender==='Female'?'selected':'' ?>>Female</option>
                        <option value="Male"   <?= $cmpGender==='Male'  ?'selected':'' ?>>Male</option>
                        <option value="Other"  <?= $cmpGender==='Other' ?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded text-sm font-bold flex items-center gap-2 shadow-sm self-end">
                    <i class="fa-solid fa-people-arrows"></i> <?= $lang==='hi'?'विश्लेषण करें':'Analyse' ?>
                </button>
                <?php if (!empty($compareReport)): ?>
                <a href="?<?= htmlspecialchars(http_build_query(array_diff_key($_GET, array_flip(['compare_name','compare_dob','compare_gender'])))) ?>"
                   class="bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-300 px-4 py-2 rounded-lg text-sm font-bold">
                   <i class="fa-solid fa-xmark mr-1"></i>Clear
                </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($compareReport) || ($compareReport['status']??'') === 'error'): ?>

        <div class="p-12 text-center border border-slate-100 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-800/50">
            <div class="text-5xl mb-4">☯</div>
            <h3 class="text-xl font-black text-slate-700 dark:text-slate-300 mb-2"><?= $lang==='hi'?'ऊपर व्यक्ति B दर्ज करें':'Enter Person B above' ?></h3>
            <p class="text-sm text-slate-500 max-w-md mx-auto"><?= $lang==='hi'?'पूरी 9-अक्ष कंपन अनुकूलता रिपोर्ट, संयुक्त उपाय, रिश्ते के समय और साथ-साथ ग्रिड तुलना के लिए नाम और जन्म तिथि दर्ज करें।':'Enter a name and date of birth to generate a full 9-axis vibrational compatibility report with joint remedies, relationship timing, and side-by-side grid comparison.' ?></p>
        </div>
        <?php else: ?>

        <div class="grid grid-cols-2 gap-5">
            <?php foreach([['r'=>$report,'label'=>($lang==='hi'?'व्यक्ति A ✦':'Person A ✦'),'accent'=>'indigo'],['r'=>$compareReport,'label'=>($lang==='hi'?'व्यक्ति B ✦':'Person B ✦'),'accent'=>'rose']] as $cp):
                $cr=$cp['r']; $acc=$cp['accent']; ?>
            <div class="p-5 border border-<?= $acc ?>-200 dark:border-<?= $acc ?>-700 rounded-lg bg-white dark:bg-slate-800 text-center">
                <div class="text-[9px] font-bold text-<?= $acc ?>-500 uppercase tracking-widest mb-1"><?= $cp['label'] ?></div>
                <div class="text-xl font-black text-slate-900 dark:text-white"><?= htmlspecialchars((string)($cr['profile']['name']??'')) ?></div>
                <div class="text-xs text-slate-500 mb-3"><?= htmlspecialchars((string)($cr['profile']['dob']??'')) ?> · <?= htmlspecialchars((string)($cr['profile']['gender']??'')) ?></div>
                <div class="flex justify-center gap-3 flex-wrap">
                    <?php foreach([['l'=>'Driver','v'=>($cr['core']['driver']??0),'c'=>'orange'],['l'=>'Conductor','v'=>($cr['core']['conductor']??0),'c'=>'purple'],['l'=>'Expression','v'=>($cr['name_matrix']['full']['root']??0),'c'=>'blue'],['l'=>'Soul','v'=>($cr['name_matrix']['soul_urge']['root']??0),'c'=>'rose']] as $cv): ?>
                    <div class="text-center"><div class="text-[8px] font-bold text-<?= $cv['c'] ?>-500 uppercase"><?= $cv['l'] ?></div><div class="text-xl font-black text-<?= $cv['c'] ?>-600"><?= (int)$cv['v'] ?></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 text-[10px] font-semibold text-<?= $acc ?>-600"><?= htmlspecialchars($lang==='hi'?(string)($cr['lucky_driver']['hi_name']??$cr['lucky_driver']['name']??''):(string)($cr['lucky_driver']['name']??'')) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="p-6 bg-slate-900 dark:bg-slate-950 rounded-lg text-white text-center">
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1"><?= $lang==='hi'?'सामंजस्य स्कोर':'Harmony Score' ?></div>
                <div class="text-6xl font-black mt-1 <?= (($compatibilityData['overall_score']??0)>=70?'text-emerald-400':(($compatibilityData['overall_score']??0)>=45?'text-amber-400':'text-rose-400')) ?>"><?= (int)($compatibilityData['overall_score']??0) ?><span class="text-lg text-slate-500">/100</span></div>
                <div class="mt-2 h-2 bg-slate-700 rounded-full overflow-hidden"><div class="h-full rounded-full <?= (($compatibilityData['overall_score']??0)>=70?'bg-emerald-400':(($compatibilityData['overall_score']??0)>=45?'bg-amber-400':'bg-rose-400')) ?>" style="width:<?= (int)($compatibilityData['overall_score']??0) ?>%"></div></div>
                <div class="mt-3 text-[10px] text-slate-400 font-semibold"><?= $lang==='hi'?'6-अक्ष कंपन विश्लेषण पर आधारित':'Based on 6-axis vibrational analysis' ?></div>
            </div>
            <div class="md:col-span-2 p-5 border dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1"><?= $lang==='hi'?'चालक जोड़ी ·':'Driver Pair ·' ?> <?= (int)($report['core']['driver']??0) ?> ↔ <?= (int)($compareReport['core']['driver']??0) ?></div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="text-lg font-black text-slate-900 dark:text-white"><?= htmlspecialchars((string)($compatPairInsight['verdict'] ?? 'Neutral')) ?></span>
                    <span class="planet-pill <?= match($compatPairInsight['verdict']??''){
                        'Complementary','Synergistic','Powerful'=>'bg-emerald-100 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-300',
                        'Mirror','Dynamic','Magnetic','Compatible','Spiritual'=>'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300',
                        'Tense','Challenging','Adversarial'=>'bg-rose-100 dark:bg-rose-900 text-rose-800 dark:text-rose-300',
                        default=>'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'
                    } ?>"><?= htmlspecialchars((string)($report['lucky_driver']['name']??'')) ?> + <?= htmlspecialchars((string)($compareReport['lucky_driver']['name']??'')) ?></span>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400 mb-3"><?= htmlspecialchars((string)($compatPairInsight['description'] ?? '')) ?></p>
                <div class="bg-emerald-50 dark:bg-emerald-900/20 border-l-2 border-emerald-500 p-3 rounded-r-lg text-xs text-emerald-900 dark:text-emerald-300">
                    <strong class="block mb-1"><i class="fa-solid fa-hands-praying mr-1"></i> <?= $lang==='hi'?'संयुक्त उपाय':'Joint Remedy' ?>:</strong>
                    <?= htmlspecialchars((string)($compatPairInsight['joint_remedy'] ?? '')) ?>
                </div>
            </div>
        </div>

        <?php if (!empty($compatTiming)): ?>
        <div class="p-5 border border-violet-200 dark:border-violet-700 bg-violet-50 dark:bg-violet-900/20 rounded-lg flex flex-wrap gap-5 items-start">
            <div class="w-14 h-14 bg-violet-100 dark:bg-violet-800 rounded-lg flex flex-col items-center justify-center flex-shrink-0">
                <div class="text-2xl font-black text-violet-700 dark:text-violet-200"><?= (int)($compatTiming['joint_py']??0) ?></div>
                <div class="text-[8px] font-bold text-violet-500 uppercase"><?= $lang==='hi'?'संयुक्त वर्ष':'Joint Yr' ?></div>
            </div>
            <div class="flex-1">
                <div class="text-[10px] font-bold text-violet-600 uppercase tracking-widest mb-1"><i class="fa-solid fa-calendar-check mr-1"></i> <?= $lang==='hi'?('संबंध वर्ष '.date('Y').' — संयुक्त व्यक्तिगत वर्ष '.(int)($compatTiming['joint_py']??0)):('Relationship Year '.date('Y').' — Joint Personal Year '.(int)($compatTiming['joint_py']??0)) ?></div>
                <p class="text-sm text-slate-700 dark:text-slate-300"><?= htmlspecialchars($lang==='hi' ? (string)($compatTiming['hi_advice'] ?? $compatTiming['advice'] ?? '') : (string)($compatTiming['advice'] ?? '')) ?></p>
                <div class="mt-1 text-[10px] text-slate-500"><?= htmlspecialchars((string)($report['profile']['name']??'')) ?><?= $lang==='hi'?' का वर्ष ':' is in Year ' ?><?= (int)($compatTiming['py_a']??0) ?> &nbsp;·&nbsp; <?= htmlspecialchars((string)($compareReport['profile']['name']??'')) ?><?= $lang==='hi'?' का वर्ष ':' is in Year ' ?><?= (int)($compatTiming['py_b']??0) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
            <div class="lg:col-span-3 p-6 border dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
                <h3 class="font-bold text-slate-800 dark:text-slate-200 text-xs uppercase tracking-widest mb-4"><i class="fa-solid fa-chart-line text-indigo-500 mr-2"></i> 6-Axis Vibrational Comparison</h3>
                <canvas id="radarChart" height="280"></canvas>
                <script>
                (function(){
                    const ctx = document.getElementById('radarChart');
                    if(!ctx) return;
                    new Chart(ctx, {
                        type: 'radar',
                        data: {
                            labels: <?= json_encode($compatibilityData['labels']??[]) ?>,
                            datasets: [
                                { label:'<?= addslashes(htmlspecialchars((string)($report['profile']['name']??''))) ?>', data:<?= json_encode($compatibilityData['metrics_a']??[]) ?>, backgroundColor:'rgba(0,114,178,0.12)', borderColor:'rgba(0,114,178,1)', pointBackgroundColor:'#0072B2', pointStyle:'circle', borderWidth:2, pointRadius:5, borderDash:[] },
                                { label:'<?= addslashes(htmlspecialchars((string)($compareReport['profile']['name']??''))) ?>', data:<?= json_encode($compatibilityData['metrics_b']??[]) ?>, backgroundColor:'rgba(213,94,0,0.12)', borderColor:'rgba(213,94,0,1)', pointBackgroundColor:'#D55E00', pointStyle:'triangle', borderWidth:2, pointRadius:5, borderDash:[6,4] }
                            ]
                        },
                        options: { responsive:true, plugins:{ legend:{ position:'bottom', labels:{ color:'#94a3b8', font:{ size:11, weight:'bold' } } } }, scales:{ r:{ min:0, max:100, ticks:{ stepSize:25, color:'#94a3b8', backdropColor:'transparent' }, grid:{ color:'rgba(148,163,184,0.2)' }, pointLabels:{ color:'#94a3b8', font:{ size:11, weight:'bold' } }, angleLines:{ color:'rgba(148,163,184,0.2)' } } } }
                    });
                })();
                </script>
            </div>

            <div class="lg:col-span-2 p-5 border dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800">
                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-[10px] uppercase tracking-widest mb-4 text-center"><i class="fa-solid fa-table-cells mr-1.5 text-orange-500"></i> Grid Comparison</h4>
                <div class="grid grid-cols-2 gap-4">
                    <?php foreach([['r'=>$report,'label'=>$report['profile']['name']??'','col'=>'indigo'],['r'=>$compareReport,'label'=>$compareReport['profile']['name']??'','col'=>'rose']] as $gc):
                        $gcr=$gc['r']; ?>
                    <div>
                        <div class="text-[9px] font-bold text-<?= $gc['col'] ?>-500 uppercase tracking-widest text-center mb-2 truncate"><?= htmlspecialchars((string)$gc['label']) ?></div>
                        <div class="grid grid-cols-3 gap-1">
                            <?php foreach(GridEngine::LAYOUT as $cn):
                                $cc=(int)($gcr['grid']['counts'][$cn]??0);
                                $cm=LoShuCellMeta::get($cn);
                                $cpc=$cm['color']??'#64748b';
                                $isEmpty2=($cc===0);
                            ?>
                            <div class="rounded-lg aspect-square flex flex-col items-center justify-center text-center p-0.5 border"
                                 style="<?= $isEmpty2?'background:rgba(254,226,226,0.5);border-color:#fca5a5;':'background:'.($cc>=2?'rgba('.implode(',',array_slice(sscanf($cpc,'#%02x%02x%02x')??[100,100,100],0,3)).',0.25)':'rgba('.implode(',',array_slice(sscanf($cpc,'#%02x%02x%02x')??[100,100,100],0,3)).',0.1)').';border-color:'.$cpc.'33;' ?>">
                                <div class="text-sm font-black <?= $isEmpty2?'text-red-300':'text-slate-700 dark:text-white' ?>"><?= (int)$cn ?></div>
                                <?php if(!$isEmpty2): ?><div class="text-[8px] font-black" style="color:<?= $cpc ?>"><?= str_repeat('·',$cc) ?></div><?php else: ?><div class="text-[8px] text-red-400">✗</div><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php
                $sharedStrong = array_intersect($report['grid']['strong'] ?? [], $compareReport['grid']['strong'] ?? []);
                $sharedMissing = array_intersect($report['grid']['missing'] ?? [], $compareReport['grid']['missing'] ?? []);
                ?>
                <div class="mt-4 pt-3 border-t dark:border-slate-700 space-y-2 text-xs">
                    <?php if(!empty($sharedStrong)): ?>
                    <div class="flex gap-2 items-start"><span class="text-emerald-600 font-bold shrink-0"><?= $lang==='hi'?'साझा शक्तियां:':'Shared Strong:' ?></span><span class="text-emerald-700 dark:text-emerald-400 font-bold"><?= implode(', ',$sharedStrong) ?></span></div>
                    <?php endif; ?>
                    <?php if(!empty($sharedMissing)): ?>
                    <div class="flex gap-2 items-start"><span class="text-rose-600 font-bold shrink-0"><?= $lang==='hi'?'साझा शून्य:':'Shared Voids:' ?></span><span class="text-rose-700 dark:text-rose-400 font-bold"><?= implode(', ',$sharedMissing) ?> — <?= $lang==='hi'?'दोनों को इन उपायों की जरूरत है।':'Both need these remedies.' ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($compatAxisRemedies)): ?>
        <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-700 px-6 py-3 border-b dark:border-slate-600">
                <h3 class="font-bold text-slate-800 dark:text-slate-200 text-xs uppercase tracking-widest"><i class="fa-solid fa-list-check text-indigo-500 mr-2"></i> <?= $lang==='hi'?'प्रति-अक्ष अनुकूलता उपाय':'Per-Axis Compatibility Remedies' ?></h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                <?php foreach ($compatAxisRemedies as $ar): ?>
                <div class="p-4 flex flex-wrap md:flex-nowrap gap-4 items-start hover:bg-slate-50/50 dark:hover:bg-slate-700/20 transition-colors">
                    <div class="flex-shrink-0 w-full md:w-40">
                        <div class="font-bold text-xs text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars((string)($ar['axis']??'')) ?></div>
                        <div class="flex items-center gap-2">
                            <div class="text-center">
                                <div class="text-[8px] text-indigo-500 font-bold">A</div>
                                <div class="text-sm font-black text-indigo-600"><?= (int)($ar['a']??0) ?></div>
                            </div>
                            <div class="flex-1 h-1.5 bg-slate-100 dark:bg-slate-600 rounded-full overflow-hidden relative">
                                <div class="absolute left-0 h-full bg-indigo-400 rounded-full" style="width:<?= (int)($ar['a']??0) ?>%"></div>
                                <div class="absolute right-0 h-full bg-rose-400 rounded-full" style="width:<?= (int)($ar['b']??0) ?>%; left:auto"></div>
                            </div>
                            <div class="text-center">
                                <div class="text-[8px] text-rose-500 font-bold">B</div>
                                <div class="text-sm font-black text-rose-600"><?= (int)($ar['b']??0) ?></div>
                            </div>
                        </div>
                        <div class="mt-1 text-center">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full <?= ($ar['status']??'')==='Aligned'?'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300':'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' ?>"><?= htmlspecialchars((string)($ar['status']??'')) ?></span>
                        </div>
                    </div>
                    <div class="flex-1 text-xs text-slate-600 dark:text-slate-400 leading-relaxed"><?= htmlspecialchars((string)($ar['advice']??'')) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // ✅ V16.2: Render CompositeEngine output — previously calculated in controller but never shown.
        // Shows the "soul" of the relationship: its own Driver, Conductor, Expression, and combined grid.
        if (!empty($compositeReport)):
            $cR   = $compositeReport;
            $cDrv = (int)($cR['composite_driver'] ?? 0);
            $cCon = (int)($cR['composite_conductor'] ?? 0);
            $cExp = (int)($cR['composite_expression'] ?? 0);
            $cPY  = (int)($cR['composite_py'] ?? 0);
            $nameA = htmlspecialchars((string)($cR['person_a']['name'] ?? ''));
            $nameB = htmlspecialchars((string)($cR['person_b']['name'] ?? ''));
        ?>
        <div class="border border-violet-300 dark:border-violet-700 rounded-lg overflow-hidden bg-white dark:bg-slate-800 shadow-sm">
            <div class="bg-gradient-to-r from-violet-50 to-purple-50 dark:from-violet-900/30 dark:to-purple-900/20 px-6 py-4 border-b border-violet-200 dark:border-violet-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-violet-100 dark:bg-violet-900/50 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-infinity text-violet-600 dark:text-violet-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-violet-800 dark:text-violet-200 uppercase tracking-widest">
                            <?= $lang==='hi' ? 'साझेदारी की आत्मा' : 'The Soul of Your Partnership' ?>
                        </h3>
                        <p class="text-[10px] text-violet-600 dark:text-violet-400 mt-0.5">
                            <?= $lang==='hi'
                                ? $nameA . ' + ' . $nameB . ' की संयुक्त सत्ता — एक तीसरी ऊर्जा जो केवल आप दोनों के मिलने पर प्रकट होती है।'
                                : $nameA . ' + ' . $nameB . ' — the composite entity that exists only when you two are together.' ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-6 space-y-5">
                <!-- Core Composite Numbers -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <?php foreach ([
                        ['label'=> $lang==='hi'?'संयुक्त Driver':'Composite Driver',   'val'=>$cDrv, 'color'=>'violet', 'icon'=>'fa-sun'],
                        ['label'=> $lang==='hi'?'संयुक्त Conductor':'Composite Conductor','val'=>$cCon, 'color'=>'purple', 'icon'=>'fa-road'],
                        ['label'=> $lang==='hi'?'संयुक्त Expression':'Composite Expression','val'=>$cExp, 'color'=>'indigo','icon'=>'fa-signature'],
                        ['label'=> $lang==='hi'?'संयुक्त Personal Year':'Joint PY',        'val'=>$cPY,  'color'=>'fuchsia','icon'=>'fa-calendar'],
                    ] as $_cn): $c=$_cn['color']; ?>
                    <div class="text-center bg-<?= $c ?>-50 dark:bg-<?= $c ?>-900/20 border border-<?= $c ?>-100 dark:border-<?= $c ?>-800 rounded-lg p-4">
                        <i class="fa-solid <?= $_cn['icon'] ?> text-<?= $c ?>-400 text-sm mb-2 block"></i>
                        <div class="text-[9px] font-black text-<?= $c ?>-600 dark:text-<?= $c ?>-400 uppercase tracking-widest mb-1 leading-tight"><?= $_cn['label'] ?></div>
                        <div class="text-3xl font-black text-<?= $c ?>-700 dark:text-<?= $c ?>-300"><?= $_cn['val'] ?: '—' ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Planetary Insight -->
                <?php if (!empty($cR['entity_planet']) || !empty($cR['entity_insight'])): ?>
                <div class="flex gap-4 items-start p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg">
                    <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                        <i class="fa-solid fa-dharmachakra text-amber-500 text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[9px] font-black text-amber-700 dark:text-amber-400 uppercase tracking-widest mb-1">
                            <?= $lang==='hi' ? 'संयुक्त ग्रह — ' : 'Partnership Planet — ' ?>
                            <?= htmlspecialchars((string)($cR['entity_planet'] ?? '')) ?>
                            <?php if (!empty($cR['entity_gem'])): ?>
                            &nbsp;·&nbsp; <span class="text-amber-600"><?= $lang==='hi'?'रत्न: ':'Gem: ' ?><?= htmlspecialchars((string)$cR['entity_gem']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-amber-900 dark:text-amber-200 leading-relaxed">
                            <?= htmlspecialchars((string)($lang==='hi' ? ($cR['entity_insight_hi'] ?: $cR['entity_insight']) : $cR['entity_insight'])) ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Combined Grid Heatmap -->
                <?php if (!empty($cR['combined_grid'])): ?>
                <div>
                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-3">
                        <i class="fa-solid fa-table-cells mr-1.5 text-violet-400"></i>
                        <?= $lang==='hi' ? 'संयुक्त ऊर्जा ग्रिड' : 'Combined Energy Grid' ?>
                    </div>
                    <div class="grid grid-cols-3 gap-2 max-w-xs mx-auto">
                        <?php foreach (GridEngine::LAYOUT as $_gn): $cgCell=(int)($cR['combined_grid'][$_gn]??0); ?>
                        <div class="aspect-square flex flex-col items-center justify-center rounded-lg border text-center py-2 <?= getHeatmapClass($cgCell) ?>">
                            <span class="text-xs font-black leading-none"><?= $_gn ?></span>
                            <?php if ($cgCell > 0): ?><span class="text-[8px] mt-0.5 opacity-80"><?= str_repeat('·', min($cgCell, 5)) ?></span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($cR['combined_missing'])): ?>
                    <p class="text-center text-[9px] text-slate-400 mt-2">
                        <?= $lang==='hi' ? 'संयुक्त विकास क्षेत्र: ' : 'Joint growth zones: ' ?>
                        <strong class="text-rose-500"><?= implode(', ', array_map('intval', $cR['combined_missing'])) ?></strong>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; // end compositeReport ?>

        <?php endif; // end if (empty($compareReport)) else ?>
    </div>
