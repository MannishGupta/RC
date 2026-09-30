<?php // tab_structural.php — Version: 260916.14
 if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
    <div x-show="activeTab==='structural'" class="tab-section space-y-6 print-page-break">
        <h2 class="hidden print:block text-lg font-black text-slate-900 uppercase tracking-widest border-b pb-2"><?= $lang==='hi'?'संरचनात्मक मैट्रिक्स':'Structural Matrix' ?></h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="p-6 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm bg-white dark:bg-slate-800">
                <h3 class="font-bold uppercase tracking-widest text-[10px]" style="color:#0f172a!important mb-1 text-center"><i class="fa-solid fa-table-cells text-orange-500 mr-2"></i> <?= $lang==='hi'?'लो शू — वैदिक अंक ग्रिड':'Lo Shu — Vedic Numerology Grid' ?></h3>
                <p class="text-[9px] text-slate-400 text-center mb-4 font-bold uppercase tracking-widest">लो शू — वैदिक अंक ग्रिड</p>

                <table class="nr-loshu" role="grid" aria-label="<?= nr_is_hi() ? 'लो शू वैदिक अंक ग्रिड' : 'Lo Shu Vedic Numerology Grid' ?>">
                    <tbody>
                    <?php
                    $loshuRows = array_chunk(GridEngine::LAYOUT, 3);
                    foreach ($loshuRows as $rowNums):
                    ?>
                    <tr>
                    <?php foreach ($rowNums as $n):
                        $cnt  = (int)($report['grid']['counts'][$n] ?? 0);
                        $meta = LoShuCellMeta::get($n);
                        $planetColor = $meta['color'] ?? '#64748b';
                        $isDom = ($cnt >= 4);
                        $isEmpty = ($cnt === 0);
                        $rgb = @sscanf($planetColor, '#%02x%02x%02x') ?: [100, 116, 139];
                        if ($isEmpty) {
                            $bgStyle = 'background:rgba(0,0,0,0.10);border-color:rgba(0,0,0,0.22);';
                        } else {
                            $a = $cnt >= 3 ? min(0.55, 0.18 * $cnt) : min(0.35, 0.12 * $cnt);
                            $bgStyle = sprintf(
                                'background:rgba(%d,%d,%d,%.2f);border-color:%s;',
                                $rgb[0], $rgb[1], $rgb[2], $a, $planetColor
                            );
                        }
                        $domExtra = $isDom ? "box-shadow:inset 0 0 0 2px {$planetColor};" : '';
                    ?>
                        <td class="nr-loshu-cell<?= $isEmpty ? ' is-void' : '' ?><?= $isDom ? ' is-dom' : '' ?>"
                            style="<?= htmlspecialchars($bgStyle . $domExtra, ENT_QUOTES) ?>"
                            title="<?= htmlspecialchars(nr_planet_label((string)($meta['planet'] ?? '')) . ' · ' . nr_element_label((string)($meta['element'] ?? '')), ENT_QUOTES) ?>">
                            <span class="nr-loshu-num" style="color:<?= $isEmpty ? '#475569' : htmlspecialchars($planetColor, ENT_QUOTES) ?>"><?= (int)$n ?></span>
                            <span class="nr-loshu-cnt"><?php
                                if ($isEmpty) {
                                    echo '—';
                                } else {
                                    echo '×' . $cnt;
                                }
                            ?></span>
                            <span class="nr-loshu-meta"><?= htmlspecialchars(nr_clip(nr_planet_label((string)($meta['planet'] ?? '')), 8), ENT_QUOTES) ?></span>
                            <span class="nr-loshu-el" style="color:<?= htmlspecialchars($planetColor, ENT_QUOTES) ?>"><?= htmlspecialchars(nr_clip(nr_element_label((string)($meta['element'] ?? '')), 6), ENT_QUOTES) ?></span>
                        </td>
                    <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="nr-loshu-legend">Void cells · 10% grey fill · Traditional order 4-9-2 / 3-5-7 / 8-1-6</p>

                <?php
                $allArrows = AppNumeroEngine::getData()['arrows'] ?? [];
                $gc = $report['grid']['counts'] ?? [];
                // All 8 planes in correct sequence
                $planeKeys   = ['4-9-2','3-5-7','8-1-6','4-3-8','9-5-1','2-7-6','4-5-6','2-5-8'];
                $planeColors = ['blue','rose','amber','purple','emerald','pink','yellow','teal'];
                $completedPlanes = []; $partialPlanes = []; $missingPlanes = [];
                foreach ($planeKeys as $pk) {
                    $nums = array_map('intval', explode('-', $pk));
                    $present = array_filter($nums, function($n) use ($gc){ return ((int)($gc[$n] ?? 0)) > 0; });
                    if (count($present) === 3) $completedPlanes[] = $pk;
                    elseif (count($present) > 0) $partialPlanes[] = $pk;
                    else $missingPlanes[] = $pk;
                }
                $planeCount = count($completedPlanes);
                ?>
                <div class="mt-3 flex items-center justify-center gap-2">
                    <span class="text-[9px] font-black text-slate-500 uppercase"><?= $lang==='hi'?'पूर्ण तल':'Planes Complete' ?></span>
                    <span class="text-base font-black <?= $planeCount >= 4 ? 'text-emerald-500' : ($planeCount >= 2 ? 'text-amber-500' : 'text-rose-500') ?>"><?= $planeCount ?>/8</span>
                </div>
                <?php if (!empty($completedPlanes)): ?>
                <div class="mt-2 space-y-1 max-w-[270px] mx-auto">
                    <?php foreach ($completedPlanes as $ci => $pk):
                        $ad = $allArrows[$pk] ?? [];
                        $nums = explode('-', $pk);
                        $clr  = $planeColors[array_search($pk, $planeKeys)] ?? 'indigo';
                        $planeName = $lang==='hi' ? ($ad['hi_plane'] ?? $ad['plane'] ?? $pk) : ($ad['plane'] ?? $pk);
                        $benefit   = $lang==='hi' ? ($ad['hi_benefit'] ?? $ad['benefit'] ?? '') : ($ad['benefit'] ?? '');
                    ?>
                    <div class="bg-<?= $clr ?>-50 dark:bg-<?= $clr ?>-900/20 border border-<?= $clr ?>-200 dark:border-<?= $clr ?>-700 rounded-lg px-2 py-1.5">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-[9px] font-black text-<?= $clr ?>-700 dark:text-<?= $clr ?>-400 uppercase"><?= htmlspecialchars((string)$planeName) ?></span>
                            <span class="text-[8px] font-bold text-slate-400"><?= implode(' · ', $nums) ?> <span class="text-emerald-500">✓</span></span>
                        </div>
                        <?php if ($benefit): ?><div class="text-[8px] text-<?= $clr ?>-600 dark:text-<?= $clr ?>-400 leading-snug"><?= htmlspecialchars((string)$benefit) ?></div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($partialPlanes)): ?>
                <div class="mt-1 space-y-1 max-w-[270px] mx-auto">
                    <?php foreach ($partialPlanes as $pk):
                        $ad = $allArrows[$pk] ?? [];
                        $nums = explode('-', $pk);
                        $presentN = array_filter($nums, function($n) use ($gc){ return ((int)($gc[(int)$n] ?? 0)) > 0; });
                        $clr  = $planeColors[array_search($pk, $planeKeys)] ?? 'slate';
                        $planeName = $lang==='hi' ? ($ad['hi_plane'] ?? $ad['plane'] ?? $pk) : ($ad['plane'] ?? $pk);
                        $partial   = $lang==='hi' ? ($ad['hi_partial'] ?? '') : ($ad['partial_benefit'] ?? '');
                    ?>
                    <div class="bg-slate-50 dark:bg-slate-700/30 border border-dashed border-<?= $clr ?>-200 dark:border-<?= $clr ?>-800 rounded-lg px-2 py-1.5">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-[9px] font-bold text-slate-500 uppercase"><?= htmlspecialchars((string)$planeName) ?></span>
                            <span class="text-[8px] text-slate-400"><?= implode(' · ', array_map(function($n) use ($presentN){ return in_array($n,$presentN)?'<span class="text-amber-500 font-black">'.$n.'</span>':'<span class="text-slate-300">'.$n.'</span>'; }, $nums)) ?></span>
                        </div>
                        <?php if ($partial): ?><div class="text-[8px] text-slate-500 dark:text-slate-400 leading-snug italic"><?= htmlspecialchars((string)$partial) ?></div><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="mt-5 pt-4 border-t dark:border-slate-700">
                    <h4 class="text-[9px] uppercase font-bold text-slate-400 tracking-widest mb-3 text-center"><?= $lang==='hi'?'ग्रह · तत्व मानचित्र':'Planet · Element Map' ?></h4>
                    <div class="grid grid-cols-3 gap-1.5">
                        <?php for($pn=1;$pn<=9;$pn++):
                            $m = LoShuCellMeta::get($pn);
                            $cnt2 = (int)($report['grid']['counts'][$pn] ?? 0);
                        ?>
                        <div class="flex items-center gap-1.5 text-[10px]">
                            <span class="text-base leading-none" style="color:<?= $m['color']??'#666' ?>"><?= $m['symbol']??'' ?></span>
                            <div class="leading-tight">
                                <div class="font-bold" style="color:#0f172a!important"><?= (int)$pn ?> · <?= htmlspecialchars(nr_planet_label((string)($m['planet']??''))) ?></div>
                                <div style="color:#334155!important;font-weight:600"><?= htmlspecialchars((string)($m['tattva']??'')) ?></div>
                            </div>
                            <?php if($cnt2===0): ?><span class="ml-auto text-[8px] font-black text-red-400">✗</span><?php elseif($cnt2>=3): ?><span class="ml-auto text-[8px] font-black text-emerald-500">●●</span><?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="p-6 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm lg:col-span-2 bg-white dark:bg-slate-800">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-seedling text-amber-600 dark:text-amber-400 text-sm"></i>
                    </div>
                    <div>
                        <h3 class="font-bold uppercase tracking-widest text-[10px]" style="color:#0f172a!important"><?= $lang==='hi'?'विकास क्षेत्र — सक्रियण क्षेत्र':'Growth Reservoirs — Activation Zones' ?></h3>
                        <p class="text-[10px] font-medium mt-0.5" style="color:#334155!important"><?= $lang==='hi'?'ये निष्क्रिय संख्याएं आपके सबसे बड़े अवसर हैं। प्रत्येक एक सुप्त महाशक्ति है।':'These unactivated numbers are your greatest opportunities. Each is a dormant superpower.' ?></p>
                    </div>
                </div>
                <?php if(empty($report['missing'])): ?>
                <div class="p-8 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-emerald-800 dark:text-emerald-300 rounded-lg text-center font-bold">
                    <i class="fa-solid fa-circle-check text-2xl mb-2 block"></i>
                    <?= $lang==='hi'?'पूर्ण ग्रिड संतुलन — सभी ग्रहीय ऊर्जाएं आपके ब्लूप्रिंट में पूर्णतः सक्रिय हैं।':'Perfect Grid Balance — All planetary energies are fully active in your blueprint.' ?>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach($report['missing'] as $num => $details):
                        $meta = LoShuCellMeta::get((int)$num);
                        $planetColor = $meta['color'] ?? '#f97316';
                    ?>
                    <div class="nr-growth-card bg-white border-2 border-slate-300 rounded-lg overflow-hidden shadow-sm flex flex-col" style="background:#ffffff!important;color:#0f172a!important">
                        <div class="px-4 py-3 border-b border-slate-200 flex justify-between items-center gap-2" style="background:#f8fafc!important">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-black shrink-0" style="background:#0f172a!important;color:#fde68a!important;border:2px solid #64748b"><?= (int)$num ?></span>
                                <div class="min-w-0">
                                    <div class="text-xs font-black" style="color:#0f172a!important"><?= htmlspecialchars(nr_planet_label((string)($meta['planet'] ?? ''))) ?> <?= htmlspecialchars((string)($meta['symbol'] ?? '')) ?></div>
                                    <div class="text-[10px] font-bold uppercase tracking-wide" style="color:#334155!important"><?= htmlspecialchars($lang==='hi'?(string)($details['hi_area']??$details['area']??''):(string)($details['area']??'')) ?></div>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest rounded shrink-0 <?= getSeverityColor($details['severity']??'') ?>"><?= getGrowthLabel($details['severity']??'') ?></span>
                        </div>
                        <div class="p-4 flex-grow space-y-3">
                            <div class="flex items-start gap-2">
                                <i class="fa-solid fa-fire-flame-curved text-amber-400 mt-0.5 flex-shrink-0 text-sm"></i>
                                <p class="text-sm leading-snug font-medium">
                                    <span class="font-black text-amber-600 dark:text-amber-400"><?= $lang==='hi'?'अप्रयुक्त क्षमता:':'Untapped potential:' ?></span>
                                    <?= $lang==='hi'?'सक्रिय करने पर '.htmlspecialchars((string)($meta['planet']??'')).(' की ऊर्जा आपके '.htmlspecialchars((string)($details['hi_area']??$details['area']??'')).' को नाटकीय रूप से मजबूत करेगी।'):'Activating '.htmlspecialchars((string)($meta['planet']??'Number '.$num)).' energy will dramatically strengthen your '.htmlspecialchars(strtolower((string)($details['area']??''))).'.' ?>
                                </p>
                            </div>
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 border-l-2 border-emerald-500 p-2.5 rounded-r-lg">
                                <div class="text-[9px] uppercase font-bold tracking-widest mb-1" style="color:#0f766e!important"><i class="fa-solid fa-bolt mr-1"></i><?= $lang==='hi'?'सक्रियण अभ्यास':'Activation Practice' ?></div>
                                <div class="text-xs leading-snug font-semibold" style="color:#134e4a!important"><?= htmlspecialchars($lang==='hi'?(string)($details['hi_remedy']??$details['remedy']??''):(string)($details['remedy']??'')) ?></div>
                            </div>
                            <details class="group">
                                <summary class="text-[9px] font-bold uppercase tracking-widest cursor-pointer" style="color:#475569!important select-none hover:text-slate-600 dark:hover:text-slate-300 transition-colors"><?= $lang==='hi'?'विस्तृत पैटर्न दिखाएं ▸':'Show detailed pattern ▸' ?></summary>
                                <p class="mt-2 text-xs italic leading-snug" style="color:#334155!important"><?= htmlspecialchars($lang==='hi'?(string)($details['hi_trait']??$details['trait']??''):(string)($details['trait']??'')) ?></p>
                            </details>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
