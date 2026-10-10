<?php // footer_ui.php — Version: 260916.14
if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
<div class="border-t dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
        <div class="px-6 py-5 flex flex-wrap items-center justify-between gap-5">
            <div>
                <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                    <i class="fa-solid fa-dharmachakra mr-1.5 text-orange-500"></i> <?= $lang==='hi'?'अर्थसाथी वैदिक अंक इंजन':'Arthsathi Vedic Numero' ?> <?= htmlspecialchars(defined('APP_VERSION') ? (string)APP_VERSION : '') ?>
                        <?php if (!empty($validationReport['status'])): ?><span class="ml-2 text-[8px] px-1.5 py-0.5 rounded font-black uppercase tracking-widest <?= match($validationReport['status']??'Valid'){'Perfect'=>'bg-emerald-600 text-white','Valid'=>'bg-blue-600 text-white','Risky'=>'bg-amber-500 text-slate-900',default=>'bg-rose-600 text-white'} ?>" title="Validation: <?= htmlspecialchars(implode(', ',$validationReport['errors']??[])) ?>"><i class="fa-solid fa-shield-halved mr-0.5"></i><?= htmlspecialchars($validationReport['status']??'Valid') ?> <?= ($validationReport['score']??100) ?>/100</span><?php endif; ?>
                </div>
                <div class="text-[10px] text-slate-400 mt-1">
                    <?= htmlspecialchars((string)($p['name']??'')) ?> &nbsp;·&nbsp; <?= $lang==='hi'?'चालक':'Driver' ?> <?= (int)$core['driver'] ?> &nbsp;·&nbsp; <?= htmlspecialchars($lang==='hi'?(string)($report['lucky_driver']['hi_name']??$report['lucky_driver']['name']??''):(string)($report['lucky_driver']['name']??'')) ?> &nbsp;·&nbsp; <?= $lang==='hi'?'उत्पन्न':'Generated' ?> <?= date('d M Y, h:i A') ?> IST
                </div>
                <div class="flex gap-2 mt-3 print:hidden">
                    <a href="<?= htmlspecialchars((string)($whatsappUrl ?? '')) ?>" target="_blank"
                       class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all">
                        <i class="fa-brands fa-whatsapp"></i> <?= $lang==='hi'?'व्हाट्सएप पर शेयर करें':'Share on WhatsApp' ?>
                    </a>
                    <button onclick="NumeroUI.copyLink()"
                            class="flex items-center gap-1.5 bg-slate-700 hover:bg-slate-600 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all">
                        <i class="fa-solid fa-copy"></i> Copy Link
                    </button>
                </div>
                <div class="hidden print:block mt-1 text-[9px] text-slate-400 font-mono break-all max-w-xs">
                    <?= htmlspecialchars((string)($shareUrl ?? '')) ?>
                </div>
            </div>
            <div class="flex flex-col items-center gap-1.5 flex-shrink-0">
                <img src="<?= htmlspecialchars((string)($qrUrl ?? '')) ?>"
                     alt="<?= $lang==='hi'?'QR कोड — '.htmlspecialchars((string)($p['name']??'')):' QR Code — '.htmlspecialchars((string)($p['name']??'')) ?>"
                     class="w-24 h-24 rounded-lg border-2 border-slate-200 dark:border-slate-600 bg-white p-1 shadow-sm"
                     loading="lazy">
                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest text-center"><?= $lang==='hi'?'रिपोर्ट खोलने के लिए स्कैन करें':'Scan to open' ?></div>
            </div>
        </div>
    </div>
</div>

<div id="calc-widget" class="fixed z-40 print:hidden" style="bottom:4.5rem;right:1.5rem" x-data="{
    open: false,
    cName: '',
    cDob: '',
    result: null,
    loading: false,
    calc() {
        if (!this.cName || !this.cDob) return;
        this.loading = true;
        const url = '?card=numero&name=' + encodeURIComponent(this.cName) + '&dob=' + this.cDob; // ✅ .2: removed dead calc_only=1 param (no controller handler)
        // Quick local Chaldean reduce for instant preview
        const cm = {A:1,B:2,C:3,D:4,E:5,F:8,G:3,H:5,I:1,J:1,K:2,L:3,M:4,N:5,O:7,P:8,Q:1,R:2,S:3,T:4,U:6,V:6,W:6,X:5,Y:1,Z:7};
        const reduce = (n) => { while(n>9&&n!==11&&n!==22&&n!==33) { n=String(n).split('').reduce((s,d)=>s+parseInt(d),0); } return n; };
        const dob = this.cDob.replace(/-/g,'');
        const driver = reduce(parseInt(dob.substring(6,8)));
        const conductor = reduce(dob.split('').reduce((s,d)=>s+(parseInt(d)||0),0));
        const clean = this.cName.toUpperCase().replace(/[^A-Z]/g,'');
        const expr = reduce(clean.split('').reduce((s,c)=>s+(cm[c]||0),0));
        const vowels='AEIOU'; 
        const su = reduce(clean.split('').filter(c=>vowels.includes(c)).reduce((s,c)=>s+(cm[c]||0),0));
        this.result = { driver, conductor, expr, su };
        this.loading = false;
    }
}">
    <div x-show="open" x-transition
         class="w-72 bg-white dark:bg-slate-800 rounded-lg shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="bg-indigo-600 px-4 py-3 flex items-center justify-between">
            <span class="text-white font-black text-sm">
                <i class="fa-solid fa-calculator mr-1.5"></i>
                <?= $lang==='hi'?'त्वरित अंक गणना':'Quick Numerology Calc' ?>
            </span>
        </div>
        <div class="p-4 space-y-3">
            <input x-model="cName" type="text"
                   placeholder="<?= $lang==='hi'?'पूरा नाम':'Full Name' ?>"
                   class="w-full text-sm bg-slate-50 dark:bg-slate-700 dark:text-white border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
            <input x-model="cDob" type="date"
                   class="w-full text-sm bg-slate-50 dark:bg-slate-700 dark:text-white border border-slate-200 dark:border-slate-600 rounded-lg px-3 py-2 outline-none focus:border-indigo-500">
            <button @click="calc()"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm py-2 rounded-lg transition-colors">
                <i class="fa-solid fa-bolt mr-1"></i>
                <?= $lang==='hi'?'गणना करें':'Calculate' ?>
            </button>
            <div x-show="result" class="border border-slate-200 dark:border-slate-600 rounded-lg overflow-hidden">
                <div class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-600">
                    <div class="p-2 text-center">
                        <div class="text-[9px] font-black text-orange-500 uppercase"><?= $lang==='hi'?'चालक':'Driver' ?></div>
                        <div class="text-2xl font-black text-orange-600" x-text="result?.driver"></div>
                    </div>
                    <div class="p-2 text-center">
                        <div class="text-[9px] font-black text-purple-500 uppercase"><?= $lang==='hi'?'जीवन पथ':'Life Path' ?></div>
                        <div class="text-2xl font-black text-purple-600" x-text="result?.conductor"></div>
                    </div>
                    <div class="p-2 text-center">
                        <div class="text-[9px] font-black text-blue-500 uppercase"><?= $lang==='hi'?'अभिव्यक्ति':'Expression' ?></div>
                        <div class="text-2xl font-black text-blue-600" x-text="result?.expr"></div>
                    </div>
                    <div class="p-2 text-center">
                        <div class="text-[9px] font-black text-rose-500 uppercase"><?= $lang==='hi'?'आत्म':'Soul Urge' ?></div>
                        <div class="text-2xl font-black text-rose-600" x-text="result?.su"></div>
                    </div>
                </div>
                <div class="p-2 bg-slate-50 dark:bg-slate-700/50 border-t border-slate-200 dark:border-slate-600">
                    <a :href="'?card=numero&name='+encodeURIComponent(cName)+'&dob='+cDob+'<?= $lang==='hi'?'&lang=hi':'' ?>'"
                       class="w-full flex items-center justify-center gap-1.5 text-[10px] font-bold text-indigo-600 hover:text-indigo-700">
                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                        <?= $lang==='hi'?'पूरी रिपोर्ट देखें':'View Full Report' ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cachedWordHTML = null;
const shareUrl = <?= json_encode($shareUrl ?? '') ?>;
window.NumeroUI = {
    printAll: function() {
        const sections = document.querySelectorAll('.tab-section');
        const saved = [];
        sections.forEach(function(el,i){ saved.push(el.style.display); el.style.removeProperty('display'); });
        const bar = document.getElementById('action-bar');
        if(bar) bar.style.display='none';
        window.print();
        const restore=function(){ sections.forEach(function(el,i){ if(saved[i])el.style.display=saved[i]; else el.style.removeProperty('display'); }); if(bar)bar.style.removeProperty('display'); };
        if(window.onafterprint!==undefined){window.onafterprint=restore;}else{setTimeout(restore,1200);}
    },
    copyLink: function() {
        navigator.clipboard.writeText(shareUrl).then(function(){
            const btn = document.querySelector('[onclick="NumeroUI.copyLink()"]');
            if(btn){ const orig=btn.innerHTML; btn.innerHTML='<i class="fa-solid fa-check mr-1"></i>Copied!'; btn.classList.add('bg-emerald-600'); setTimeout(()=>{ btn.innerHTML=orig; btn.classList.remove('bg-emerald-600'); },2000); }
        }).catch(function(){ prompt('Copy this link:', shareUrl); });
    },
    downloadWord: function() {
        const preHtml = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'><head><meta charset='utf-8'><style>body{font-family:Arial,sans-serif;font-size:11pt;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ccc;padding:8px;} img{max-width:80pt;border-radius:6pt;}</style></head><body>";
        if(!cachedWordHTML){
            const sections=document.querySelectorAll('.tab-section');const saved=[];
            sections.forEach(function(el,i){saved.push(el.style.display);el.style.removeProperty('display');});
            cachedWordHTML=preHtml+document.getElementById('report-container').innerHTML+`</body></html>`;
            sections.forEach(function(el,i){if(saved[i])el.style.display=saved[i];else el.style.removeProperty('display');});
        }
        const blob=new Blob(['\ufeff',cachedWordHTML],{type:'application/msword'});
        const link=document.createElement("a");link.href=URL.createObjectURL(blob);
        link.download='NumeroReport_<?= preg_replace('/[^a-z0-9_]/i','',strtolower(str_replace(' ','_',(string)($p['name']??'')))) ?>_ .doc';
        link.click();
    }
};
</script>
<script>
/* ═══ Vastu AJAX — ✅ .2 FIX: render result in-place instead of window.location.href
       which was negating the async benefit by triggering a full page reload. ══════════ */
(function(){
    'use strict';
    const btn = document.getElementById('vastu-analyse-btn');
    if (!btn) return;
    btn.addEventListener('click', function(){
        const inp  = document.getElementById('vastu-test-name');
        const name = inp ? inp.value.trim() : '';
        if (!name) { if(inp) inp.focus(); return; }
        const driver = <?= (int)($core['driver']??1) ?>;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-[10px]"></i> <span><?= $lang==='hi'?'गणना...':'Analysing...' ?></span>';
        const u = new URL(window.location.href);
        u.searchParams.set('ajax','vastu'); u.searchParams.set('name',name); u.searchParams.set('driver',driver);
        fetch(u.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(function(r){ if(!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(function(d){
                if(d.error) throw new Error(d.error);
                btn.disabled=false;
                btn.innerHTML='<i class="fa-solid fa-check text-[10px]"></i><span><?= $lang==='hi'?'अपडेट हो गया':'Updated' ?></span>';
                // ── Update all Vastu score elements in-place ──────────────────
                const setTxt = function(id, val){ var el=document.getElementById(id); if(el) el.textContent=val; };
                const setHtml= function(id, val){ var el=document.getElementById(id); if(el) el.innerHTML=val; };
                setTxt('vastu-score-val',     d.score   ?? '—');
                setTxt('vastu-root-val',      d.root    ?? '—');
                setTxt('vastu-compound-val',  d.compound?? '—');
                setTxt('vastu-grade-val',     d.grade   ?? '—');
                setTxt('vastu-element-val',   d.element ?? '—');
                setTxt('vastu-direction-val', d.vastu_direction ?? '—');
                setTxt('vastu-name-display',  (name).toUpperCase());
                setTxt('vastu-compound-name', d.compound_name ?? '');
                setTxt('vastu-compound-verdict', d.compound_verdict ?? '');
                setTxt('vastu-owner-alignment',  d.owner_alignment ?? '');
                // Rebuild letter breakdown if container exists
                var lc = document.getElementById('vastu-letters-grid');
                if(lc && d.letters){
                    lc.innerHTML = d.letters.map(function(l){
                        var esc = function(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };
                        return '<div class="text-center bg-slate-50 dark:bg-slate-700/50 rounded px-3 py-2 border border-slate-200 dark:border-slate-600">'
                             + '<div class="text-lg font-black text-slate-800 dark:text-white leading-none">'+esc(l.letter)+'</div>'
                             + '<div class="text-xs text-indigo-600 dark:text-indigo-400 font-black">'+esc(l.value)+'</div>'
                             + '</div>';
                    }).join('');
                }
                // Update stroke arc score ring
                var arc=document.getElementById('vastu-score-arc');
                if(arc) arc.setAttribute('stroke-dasharray', (d.score??0)+' 100');
                // Re-colour grade badge
                var gradeEl=document.getElementById('vastu-grade-badge');
                if(gradeEl){
                    gradeEl.className='inline-block px-2 py-0.5 rounded text-[9px] font-bold '
                        +({'Excellent':'bg-emerald-100 text-emerald-800','Good':'bg-blue-100 text-blue-800',
                            'Neutral':'bg-amber-100 text-amber-800'}[d.grade]||'bg-rose-100 text-rose-800');
                    gradeEl.textContent=d.grade;
                }
                // After 2s restore button to allow re-analysis
                setTimeout(function(){
                    btn.innerHTML='<i class="fa-solid fa-magnifying-glass text-[10px]"></i><span><?= $lang==='hi'?'जाँचें':'Analyse' ?></span>';
                }, 2000);
            })
            .catch(function(err){
                btn.disabled=false;
                btn.innerHTML='<i class="fa-solid fa-magnifying-glass text-[10px]"></i><span><?= $lang==='hi'?'जाँचें':'Analyse' ?></span>';
                console.error('Vastu AJAX:',err);
                alert('<?= $lang==='hi'?'विश्लेषण विफल। कृपया पुनः प्रयास करें।':'Analysis failed. Please try again.' ?>');
            });
    });
}());

/* ═══ Print audit ═════════════════════════════════════════ */
window.addEventListener('beforeprint', function(){
    const p = new URLSearchParams({audit:'print',slug:'<?= htmlspecialchars((string)($slug??'')) ?>',tab:'<?= htmlspecialchars((string)($_GET['tab']??'executive')) ?>'});
    fetch('?'+p.toString()+'<?= $lang==="hi"?"&lang=hi":"" ?>', {method:'GET',keepalive:true}).catch(function(){});
});
</script>
<div id="disclaimer-modal"
     class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm print:hidden"
     onclick="if(event.target===this)this.classList.add('hidden'),this.classList.remove('flex')">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-2xl max-w-lg w-full border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="bg-slate-50 dark:bg-slate-700/40 px-6 py-4 border-b border-slate-200 dark:border-slate-600 flex items-center gap-3">
            <i class="fa-solid fa-circle-info text-slate-500 text-lg" aria-hidden="true"></i>
            <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm tracking-wide">
                <?= $lang==='hi'?'रिपोर्ट नोट':'About this report' ?>
            </h3>
        </div>
        <div class="p-6 space-y-4 text-sm text-slate-700 dark:text-slate-300 leading-relaxed overflow-y-auto max-h-[60vh]">
            <p>
                <?php if ($lang==='hi'): ?>
                यह रिपोर्ट <strong>वैदिक अंकशास्त्र</strong> के सिद्धांतों पर आधारित है। यह किसी भी प्रकार की पेशेवर वित्तीय, चिकित्सीय, कानूनी या मनोवैज्ञानिक सलाह का विकल्प नहीं है।
                <?php else: ?>
                <?= $lang==='hi'?'यह रिपोर्ट <strong>वैदिक अंकशास्त्र</strong> के सिद्धांतों पर आधारित है और केवल <strong>सूचनात्मक और मनोरंजन उद्देश्यों</strong> के लिए प्रदान की जाती है।':'This personal intelligence summary draws on classical <strong>Vedic numerology</strong> traditions. Treat it as reflective guidance, not professional advice.' ?>
                <?php endif; ?>
            </p>
            <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4 space-y-2 text-xs border dark:border-slate-600">
                <div class="flex gap-2"><i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <span><?= $lang==='hi'?'अंकों की गणना जन्म तिथि और नाम की चाल्डियन पद्धति पर आधारित है।':'Number calculations are based on birth date and Chaldean name analysis.' ?></span></div>
                <div class="flex gap-2"><i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <span><?= $lang==='hi'?'नाम की वर्तनी में मामूली बदलाव परिणाम बदल सकते हैं।':'Minor variations in name spelling may alter results.' ?></span></div>
                <div class="flex gap-2"><i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <span><?= $lang==='hi'?'रत्न और उपाय की सिफारिशें पारंपरिक ज्ञान पर आधारित हैं।':'Gem and remedy recommendations are based on traditional knowledge, not clinical evidence.' ?></span></div>
                <div class="flex gap-2"><i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <span><?= $lang==='hi'?'अनुकूलता स्कोर सांकेतिक हैं, अंतिम निर्णय नहीं।':'Compatibility scores are indicative only and should not be the sole basis for relationship decisions.' ?></span></div>
                <div class="flex gap-2"><i class="fa-solid fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <span><?= $lang==='hi'?'किसी भी गणना त्रुटि के लिए अर्थसाथी जिम्मेदार नहीं है।':'Arthsathi is not liable for any decisions made based on this report.' ?></span></div>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 italic">
                <?= '© '.date('Y').' Arthsathi. '.($lang==='hi'?'सर्वाधिकार सुरक्षित। प्लेटफ़ॉर्म: ':'All rights reserved. Platform: ').htmlspecialchars($clientName??'Arthsathi') ?>
            </p>
        </div>
        <div class="px-6 py-4 border-t dark:border-slate-700 flex justify-end">
            <button onclick="document.getElementById('disclaimer-modal').classList.add('hidden');document.getElementById('disclaimer-modal').classList.remove('flex');"
                    class="bg-slate-800 hover:bg-slate-700 dark:bg-slate-600 dark:hover:bg-slate-500 text-white px-5 py-2 rounded-lg text-sm font-bold">
                <?= $lang==='hi'?'समझ लिया':'I Understand' ?>
            </button>
        </div>
    </div>
</div>

<!-- ═══ .0: Unified 4-button FAB cluster ══════════════════════════════ -->
<!-- Alerts dropdown state and Settings dropdown state are on root x-data -->
<!-- Calculator panel (calc-widget) floats above this cluster; toggled by fab-calc-btn below -->

<!-- ─── ALERTS dropdown panel (positioned relative to cluster) ───────────── -->
<div x-show="alertsOpen" @click.outside="alertsOpen=false"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 translate-y-2 scale-95"
     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
     class="fixed z-[60] print:hidden cluster-dropdown right-6"
     style="bottom:5.5rem; top:auto; display:none; min-width:220px">
    <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest px-2 pb-1"><?= $lang==='hi'?'ट्रांजिट चेतावनी':'Transit Alerts' ?></div>
    <?php if (empty($transitAlerts)): ?>
    <div class="px-2 py-2 text-[11px] text-slate-500 italic"><?= $lang==='hi'?'कोई सक्रिय चेतावनी नहीं':'No active alerts' ?></div>
    <?php else: foreach ($transitAlerts as $ta): ?>
    <button @click="activeTab='forecast'; alertsOpen=false"
            class="cluster-btn text-left w-full hover:text-amber-400">
        <i class="fa-solid <?= htmlspecialchars((string)($ta['icon']??'fa-bell')) ?> text-amber-400"></i>
        <span class="leading-tight">
            <strong class="block text-amber-300"><?= htmlspecialchars((string)($ta['title']??'')) ?></strong>
            <span class="text-[10px] text-slate-500 font-normal"><?= htmlspecialchars(mb_substr((string)($ta['desc']??''),0,60)) ?>…</span>
        </span>
    </button>
    <?php endforeach; endif; ?>
</div>

<!-- ─── SETTINGS dropdown panel ─────────────────────────────────────────── -->
<div x-show="settingsOpen" @click.outside="settingsOpen=false"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 translate-y-2 scale-95"
     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
     class="fixed z-[60] print:hidden cluster-dropdown right-6"
     style="bottom:5.5rem; top:auto; display:none; min-width:220px">

    <div class="px-2 pb-1">
        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1.5"><?= $lang==='hi'?'रिपोर्ट स्तर':'Report Mode' ?></div>
        <div class="flex gap-1">
            <button @click="setMode('basic')"
                    :class="reportMode==='basic'?'bg-indigo-600 text-white':'bg-slate-800 text-slate-400 hover:text-white'"
                    class="mode-badge flex-1 py-1 rounded transition-all">
                <i class="fa-solid fa-chart-simple mr-1"></i><?= $lang==='hi'?'मूल':'Basic' ?>
            </button>
            <button @click="setMode('advanced')"
                    :class="reportMode==='advanced'?'bg-indigo-600 text-white':'bg-slate-800 text-slate-400 hover:text-white'"
                    class="mode-badge flex-1 py-1 rounded transition-all">
                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i><?= $lang==='hi'?'उन्नत':'Advanced' ?>
            </button>
        </div>
    </div>
    <div class="cluster-divider"></div>

    <button @click="shareOpen=!shareOpen; settingsOpen=false" class="cluster-btn">
        <i class="fa-solid fa-share-nodes text-emerald-400"></i>
        <?= htmlspecialchars((string)($T['share']??'Share')) ?>
    </button>

    <!-- Language toggle only in top action bar (avoid triple controls) -->

    <!-- fixed -->

    <button onclick="NumeroUI.printAll()" class="cluster-btn">
        <i class="fa-solid fa-file-pdf text-indigo-400"></i>
        <?= htmlspecialchars((string)($T['pdf']??'PDF / Print')) ?>
    </button>
</div>

<!-- ─── 4-button unified pill row ────────────────────────────────────────── -->
<nav class="nr-footer-dock print:hidden" aria-label="Report tools">
  <button type="button" onclick="NumeroUI.printAll && NumeroUI.printAll()" class="nr-dock-btn nr-dock-primary" title="Print / Save PDF">
    <i class="fa-solid fa-print" aria-hidden="true"></i><span>Print</span>
  </button>
  <button type="button" onclick="(function(){var m=document.getElementById('disclaimer-modal');if(m){m.classList.remove('hidden');m.classList.add('flex');}})()" class="nr-dock-btn" title="About this report">
    <i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>About</span>
  </button>
  <button type="button" @click="settingsOpen=!settingsOpen; alertsOpen=false" class="nr-dock-btn" :class="settingsOpen && 'nr-dock-on'" title="Settings">
    <i class="fa-solid fa-sliders" aria-hidden="true"></i><span>Settings</span>
  </button>
  <button type="button" id="fab-calc-btn" class="nr-dock-btn" title="Quick calculator"
    onclick="(function(){var el=document.getElementById('calc-widget');if(!el||!window.Alpine)return;try{var d=Alpine.$data(el);if(d)d.open=!d.open;}catch(e){}})()">
    <i class="fa-solid fa-calculator" aria-hidden="true"></i><span>Calc</span>
  </button>
</nav>
<style>
.nr-footer-dock{
  position:fixed; z-index:50; bottom:1.25rem; right:1.25rem; left:auto;
  display:flex; align-items:center; gap:6px;
  padding:6px; border-radius:14px;
  background:rgba(15,23,42,.92);
  border:1px solid rgba(148,163,184,.25);
  box-shadow:0 8px 28px rgba(15,23,42,.35);
  backdrop-filter:blur(8px);
}
.nr-dock-btn{
  display:inline-flex; align-items:center; gap:6px;
  height:36px; padding:0 12px; border-radius:10px;
  border:none; cursor:pointer;
  background:transparent; color:#e2e8f0;
  font-size:11px; font-weight:700; letter-spacing:.02em;
  transition:background .15s, color .15s;
}
.nr-dock-btn:hover{ background:rgba(255,255,255,.1); color:#fff; }
.nr-dock-btn.nr-dock-primary{ background:#2563eb; color:#fff; }
.nr-dock-btn.nr-dock-primary:hover{ background:#1d4ed8; }
.nr-dock-btn.nr-dock-on{ background:rgba(99,102,241,.35); color:#c7d2fe; }
.nr-dock-btn i{ font-size:12px; }
@media (max-width:480px){
  .nr-footer-dock{ left:12px; right:12px; justify-content:space-between; }
  .nr-dock-btn span{ display:none; }
  .nr-dock-btn{ padding:0 10px; justify-content:center; flex:1; }
}
</style>


</body>
</html>