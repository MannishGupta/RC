<?php // tab_mobile_matrix.php — Version: 260916.14
if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit; ?>
<?php
// ── Mode-aware title helper ───────────────────────────────────────────────────
$_modeTitle    = ($_mcMode??'vedic')==='vedic'
    ? ($lang==='hi' ? 'वैदिक मोबाइल विश्लेषण'   : 'Vedic Mobile Analysis')
    : ($lang==='hi' ? 'चालदेय मोबाइल तुलना'      : 'Chaldean Mobile Comparison');
$_modeTitleShort = ($_mcMode??'vedic')==='vedic'
    ? ($lang==='hi' ? 'वैदिक विश्लेषण'            : 'Vedic Analysis')
    : ($lang==='hi' ? 'चालदेय तुलना'              : 'Chaldean Comparison');
$_modeIcon     = ($_mcMode??'vedic')==='vedic' ? 'fa-om' : 'fa-star-and-crescent';
$_modeColor    = ($_mcMode??'vedic')==='vedic' ? 'text-emerald-500' : 'text-indigo-500';
?>


<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- TAB: MOBILE MATRIX                                                      -->
<!-- Refactored V17.5: Moved from corrections tab into dedicated tab         -->
<!-- Contains:                                                               -->
<!--   §1 Active number display + suggested patterns (moved from corrections)-->
<!--   §2 Chaldean compare widget (mobileCompare — moved from corrections)   -->
<!--   §3 Bulk upload scorer (mobileMatrixTab — new in V17.5)                -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div x-show="activeTab==='mobile_matrix'" class="tab-section space-y-6 print-page-break">
    <h2 class="hidden print:block text-lg font-black text-slate-900 uppercase tracking-widest border-b pb-2">
        <?= $_modeTitle ?>
    </h2>

    <!-- §1 + §2: Active number analysis + comparison table (original corrections content) -->
        <div class="p-6 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm bg-white dark:bg-slate-800">
            <h3 class="font-bold text-slate-800 dark:text-slate-200 uppercase tracking-widest text-[10px] mb-5"><i class="fa-solid fa-mobile-screen text-blue-500 mr-2"></i> Mobile Number Matrix</h3>
            <?php if(($report['mobile']['display']??'')==='N/A'||empty($report['mobile']['display'])): ?>
            <p class="text-slate-400 text-sm italic"><?= $lang==='hi'?'कोई मोबाइल नंबर नहीं दिया गया।':'No mobile number provided.' ?></p>
            <?php else: ?>
            <div class="flex flex-wrap items-center justify-between bg-slate-50 dark:bg-slate-700 p-5 rounded-lg border dark:border-slate-600 mb-5 gap-4">
                <div>
                    <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= $lang==='hi'?'सक्रिय अनुक्रम':'Active Sequence' ?></div>
                    <div class="text-2xl font-black tracking-widest font-mono dark:text-white"><?= htmlspecialchars(chunk_split((string)$report['mobile']['display'],5,' ')) ?></div>
                    <?php foreach(($report['mobile']['observations']??[]) as $obs): ?><div class="mt-1 text-[10px] text-amber-700 dark:text-amber-400 font-semibold bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded inline-block mr-1"><?= htmlspecialchars((string)$obs) ?></div><?php endforeach; ?>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold text-slate-700 dark:text-slate-300"><?= $lang==='hi'?'यौगिक':'Compound' ?>: <span class="text-indigo-600"><?= (int)($report['mobile']['compound']??0) ?></span> | <?= $lang==='hi'?'मूल':'Root' ?>: <span class="text-indigo-600"><?= (int)($report['mobile']['root']??0) ?></span></div>
                    <span class="mt-2 inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-widest <?= getStatusColor($report['mobile']['compatibility']??'') ?>"><?= htmlspecialchars((string)($report['mobile']['compatibility']??'')) ?></span>
                </div>
            </div>
            <?php if(!empty($report['mobile']['patterns'])): ?>
            <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-3"><?= $lang==='hi'?'अनुशंसित अनुकूल अंत':'Suggested Compatible Endings' ?></h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">                <?php foreach($report['mobile']['patterns'] as $patt): ?>
                <?php
                // Jyotish lord for the root number
                $_lords = AppNumeroEngine::getData()['jyotish_lords'] ?? [];
                $_rootLord = $_lords[(string)(int)($patt['target_root']??0)] ?? [];
                ?>
                <div class="p-4 border dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 shadow-sm">
                    <div class="border-b dark:border-slate-600 pb-2 mb-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase text-slate-500"><?= $lang==='hi'?'मूल':'Root' ?></span>
                            <span class="text-indigo-600 dark:text-indigo-400 text-xl font-black leading-none"><?= (int)($patt['target_root']??0) ?></span>
                        </div>
                        <?php if (!empty($_rootLord['planet'])): ?>
                        <div class="mt-1 text-[9px] font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars((string)$_rootLord['planet']) ?></div>
                        <div class="text-[9px] text-slate-500 dark:text-slate-400 leading-snug mt-0.5"><?= htmlspecialchars((string)($_rootLord['areas'] ?? '')) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-2">
                    <?php foreach(($patt['suggested_endings']??[]) as $end):
                        $endStr   = is_array($end) ? (string)($end['ending']  ?? '') : (string)$end;
                        $endCombo = is_array($end) ? (string)($end['combo']   ?? '') : '';
                        $endBen   = is_array($end) ? (string)($end['benefit'] ?? '') : '';
                    ?>
                    <div class="rounded-lg transition-all duration-300 <?= $endBen ? 'bg-slate-50 dark:bg-slate-600/30 p-2' : '' ?>"
                         :class="result && ('<?= addslashes(htmlspecialchars((string)$endStr)) ?>') === (result.last2Str||String(result.last2Root).padStart(2,'0')) ? 'ring-2 ring-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 shadow-lg scale-105' : ''">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="bg-slate-100 dark:bg-slate-600 border dark:border-slate-500 text-slate-700 dark:text-slate-300 px-2 py-0.5 rounded text-sm font-mono font-bold">···<?= htmlspecialchars($endStr) ?></span>
                            <?php if ($endCombo): ?>
                            <span class="text-[9px] font-bold text-amber-600 dark:text-amber-400"><?= htmlspecialchars($endCombo) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($endBen): ?>
                        <div class="text-[9px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug"><?= htmlspecialchars($endBen) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

<?php
/* ── Mobile Compare — PHP injects all vars, JS lives below ─────────────── */
// All these vars are set by num_controller.php (V17.5+)
// For older controllers graceful fallbacks are provided.
$_mcDriver    = $core['driver']    ?? 1;
$_mcConductor = $core['conductor'] ?? 1;
$_mcPsychic   = (int)$_mcDriver;
$_mcDestiny   = (int)$_mcConductor;
if (!isset($_mcFriends))  $_mcFriends  = json_encode(array_map('intval', $report['lucky_driver']['friends'] ?? []));
if (!isset($_mcNum))      $_mcNum      = json_encode((string)($report['mobile']['display'] ?? 'N/A'));
if (!isset($_mcRoot))     $_mcRoot     = (int)($report['mobile']['root'] ?? 0);
if (!isset($_mcScore))    $_mcScore    = (int)($report['mobile']['score'] ?? 0);
if (!isset($_mcCompound)) $_mcCompound = (int)($report['mobile']['compound'] ?? 0);
if (!isset($_mcLast2))    $_mcLast2    = (string)($report['mobile']['last2'] ?? substr(preg_replace('/\D/','',($report['mobile']['display']??'')), -2));
if (!isset($_mcLast2Root))$_mcLast2Root= (int)($report['mobile']['last2Root'] ?? 0);
if (!isset($_mcPatterns)) $_mcPatterns = json_encode(array_map(function($p){
    return ['target_root'=>(int)($p['target_root']??0),'endings'=>array_map(function($e){ return ['ending'=>is_array($e)?($e['ending']??''):(string)$e,'combo'=>is_array($e)?($e['combo']??''):'','benefit'=>is_array($e)?($e['benefit']??''):'']; },$p['suggested_endings']??[])];
}, $report['mobile']['patterns']??[]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
if (!isset($_mcChaldean)) $_mcChaldean = class_exists('ChaldeaMobileEngine') ? json_encode(ChaldeaMobileEngine::getJsData($_mcPsychic,$_mcDestiny),JSON_UNESCAPED_UNICODE) : 'null';
if (!isset($_mcI18n))     $_mcI18n     = json_encode(['rootDriver'=>$lang==='hi'?'मूल अंक आपके चालक से संरेखित':'Root aligned with Driver','rootFriend'=>$lang==='hi'?'मूल अंक अनुकूल':'Root is Friendly','rootNeutral'=>$lang==='hi'?'मूल तटस्थ/विरोधी':'Root is Neutral/opposing','last2Driver'=>$lang==='hi'?'अंतिम 2 चालक से मेल':'Last 2 match Driver','last2Friend'=>$lang==='hi'?'अंतिम 2 अनुकूल':'Last 2 are Friendly','last2Neutral'=>$lang==='hi'?'अंतिम 2 सुधार योग्य':'Last 2 could improve','saturnDelay'=>$lang==='hi'?'शनि/राहु — देरी संभव':'Saturn/Rahu energy','strongVibe'=>$lang==='hi'?'मजबूत कंपन':'Strong vibration','malefic'=>$lang==='hi'?'अशुभ युगल':'Malefic pair','auspicious'=>$lang==='hi'?'शुभ युगल':'Auspicious pair','highlyRec'=>$lang==='hi'?'अत्यंत अनुशंसित':'Highly Recommended','acceptable'=>$lang==='hi'?'स्वीकार्य':'Acceptable','avoid'=>$lang==='hi'?'टालें':'Avoid','compatible' =>$lang==='hi'?'अनुकूल':'Supportive','neutral'    =>$lang==='hi'?'तटस्थ':'Neutral','challenging'=>$lang==='hi'?'चुनौतीपूर्ण':'Challenging','addedToComp'=>$lang==='hi'?'तुलना में जोड़ा':'Added to comparison','compFull'   =>$lang==='hi'?'तालिका पूर्ण':'Table full (max 5)'], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

// ── $_mm* vars — used by bulk scorer section (PDF export + header subtitle) ─
// These mirror $_mc* but also expose name/dob/gender for the PDF title line.
$_mmName    = htmlspecialchars((string)($p['name']   ?? ''), ENT_QUOTES);
$_mmDob     = htmlspecialchars((string)($p['dob_raw'] ?? ($p['dob'] ?? '')), ENT_QUOTES);
$_mmGender  = htmlspecialchars((string)($p['gender'] ?? ''), ENT_QUOTES);
$_mmDriver  = (int)($core['driver']    ?? 1);
$_mmCond    = (int)($core['conductor'] ?? 1);
$_mmPsychic = $_mmDriver;
$_mmDestiny = $_mmCond;
$_mmFriends = isset($_mcFriends) ? $_mcFriends
            : json_encode(array_map('intval', $report['lucky_driver']['friends'] ?? []));
?>
<script>
'use strict';
// ═══════════════════════════════════════════════════════════════════════
// UNIFIED CHALDEAN MOBILE SCORING ENGINE (V17.5)
// _MM_MODE forward-declaration: ensures evaluateMobile() always has a
// valid mode even if script-2 hasn't parsed yet (SSR edge cases).
// eslint-disable-next-line no-var
var _MM_MODE = <?= $_mcModeJson ?? '"vedic"' ?>;
// Single source of truth — mirrors ChaldeaMobileEngine::validate() PHP
// Used by: active number baseline, "Add to Compare" analysis, table delta
// ═══════════════════════════════════════════════════════════════════════

const _cdData = <?= $_mcChaldean ?? 'null' ?>;  // Chaldean rule tables from PHP

/**
 * Core Chaldean reduction (strict 1-9, no master numbers).
 */
function chaldeanReduce(n) {
    n = Math.abs(Math.round(n));
    if (n === 0) return 9;
    while (n > 9) {
        n = String(n).split('').reduce((s, d) => s + parseInt(d, 10), 0);
    }
    return n || 9;
}

/**
 * evaluateMobile() — Unified Chaldean scoring.
 * Identical formula used for EVERY number in the comparison table.
 *
 * @param {string} rawInput  — any format (spaces allowed)
 * @param {number} psychic   — Driver (day of birth, reduced 1-9)
 * @param {number} destiny   — Conductor (full DOB sum, reduced 1-9)
 * @param {number[]} friends — Friendly roots for the driver
 * @returns {object}         — full Chaldean evaluation
 */
function evaluateMobile(rawInput, psychic, destiny, friends) {
    const clean = String(rawInput || '').replace(/\D/g, '');
    if (clean.length < 6) return null;

    // Compound & total root
    const total    = clean.split('').reduce((s, d) => s + parseInt(d, 10), 0);
    const totalRoot= chaldeanReduce(total);

    // Last 2 digits
    const last2Str = clean.slice(-2).padStart(2, '0');
    const last2Int = parseInt(last2Str, 10);
    const last2Sum = parseInt(last2Str[0], 10) + parseInt(last2Str[1], 10);
    const last2Root= chaldeanReduce(last2Sum);

    // Last 4 sequence check
    const last4 = clean.slice(-4).split('').map(Number);
    let seqLabel = 'flat';
    if (last4.length === 4) {
        // Descending only if majority (≥2) of steps drop AND last digit ≤ first.
        // Oscillating (e.g. 9,8,9,8) → flat. Mirrors PHP checkSequence() exactly.
        const allSame = last4[0]===last4[1] && last4[1]===last4[2] && last4[2]===last4[3];
        const drops = (last4[1]<last4[0]?1:0)+(last4[2]<last4[1]?1:0)+(last4[3]<last4[2]?1:0);
        const rises = (last4[1]>last4[0]?1:0)+(last4[2]>last4[1]?1:0)+(last4[3]>last4[2]?1:0);
        seqLabel = allSame ? 'flat'
                 : (drops >= 2 && last4[3] <= last4[0]) ? 'descending'
                 : (rises >= 2 && last4[3] >= last4[0]) ? 'ascending'
                 : 'flat';
    }

    // ── Shared pair tables ──────────────────────────────────────────────────
    const maleficPairs = _cdData ? (_cdData.maleficPairs || []) : [18,81,28,82,24,42,34,43,48,84,98,89,44,88];
    const auspPairs    = _cdData ? (_cdData.auspiciousPairs || []) : [15,51,56,65,35,53,16,61,33,55,66];
    const isMalefic    = maleficPairs.includes(last2Int);
    const isAuspicious = auspPairs.includes(last2Int);
    const endsInZero   = last2Str.endsWith('0');
    const zeroCount    = (clean.match(/0/g) || []).length;
    const zeroOk       = !endsInZero && zeroCount <= 2;
    const seqOk        = seqLabel !== 'descending';

    // ── Mode-aware scoring ────────────────────────────────────────────────────
    let score, psychicOk, destinyOk, universalOk, highlyRec, acceptable, level;

    if (_MM_MODE === 'vedic') {
        // ── VEDIC: driver/friends based (mirrors PHP MobileEngine) ─────────
        // Base 50, +25 root=driver, +12 root in friends, -10 neither.
        // +15 last2Root=driver, +8 last2Root in friends, -5 neither.
        // Saturn/Rahu: -10 if root∈{4,8} AND driver∉{4,8}.
        // Strength bonus: +5 if root∈{1,3,5,6,9}.
        const rootIsDriver  = totalRoot === psychic;
        const rootIsFriend  = friends.includes(totalRoot);
        const l2IsDriver    = last2Root === psychic;
        const l2IsFriend    = friends.includes(last2Root);
        psychicOk   = rootIsDriver || rootIsFriend;   // driver or friend = favourable
        destinyOk   = last2Root === destiny || friends.includes(last2Root);
        universalOk = !([4,8].includes(totalRoot) && ![4,8].includes(psychic));
        score = 50;
        if (rootIsDriver)         score += 25;
        else if (rootIsFriend)    score += 12;
        else                      score -= 10;
        if (l2IsDriver)           score += 15;
        else if (l2IsFriend)      score += 8;
        else                      score -= 5;
        if (!universalOk)         score -= 10;  // Saturn/Rahu penalty (lighter than Chaldean)
        if ([1,3,5,6,9].includes(totalRoot)) score += 5;
        if (isAuspicious)         score += 8;
        if (isMalefic)            score -= 12;
        if (!zeroOk)              score -= 5;
        if (seqLabel==='ascending')  score += 5;
        if (seqLabel==='descending') score -= 8;
        score = Math.min(100, Math.max(0, score));
        highlyRec  = score >= 75 && universalOk && !isMalefic && zeroOk;
        acceptable = score >= 50 && !isMalefic;
        level      = highlyRec ? 'Highly Recommended' : acceptable ? 'Acceptable' : 'Avoid';

    } else {
        // ── CHALDEAN: BEST_SUMS / absolute rules (mirrors PHP ChaldeaMobileEngine) ──
        const psychicBest = _cdData ? (_cdData.psychicBest || []) : friends;
        const destinyBest = _cdData ? (_cdData.destinyBest || []) : friends;
        psychicOk   = psychicBest.includes(totalRoot);
        destinyOk   = destinyBest.includes(totalRoot);
        // Absolute rule: 4 and 8 always avoided regardless of driver.
        universalOk = ![4, 8].includes(totalRoot);
        score = 50;
        if (psychicOk)    score += 15;
        if (destinyOk)    score += 15;
        if (!universalOk) score -= 30;
        if (isAuspicious) score += 12;
        if (isMalefic)    score -= 20;
        if (!zeroOk)      score -= 8;
        if (seqLabel==='ascending')  score += 5;
        if (seqLabel==='descending') score -= 10;
        score = Math.min(100, Math.max(0, score));
        highlyRec  = psychicOk && destinyOk && universalOk && zeroOk && !isMalefic && seqOk;
        acceptable = universalOk && (psychicOk || destinyOk) && !isMalefic && zeroOk;
        level      = highlyRec ? 'Highly Recommended' : acceptable ? 'Acceptable' : 'Avoid';
    }

    // Malefic / auspicious labels
    const malLabel  = _cdData && isMalefic   ? (_cdData.maleficLabels[last2Int]    || null) : null;
    const auspLabel = _cdData && isAuspicious? (_cdData.auspiciousLabels[last2Int] || null) : null;
    const planets   = _cdData ? (_cdData.planets || {}) : {1:'Sun☉',2:'Moon☽',3:'Jupiter♃',4:'Rahu☊',5:'Mercury☿',6:'Venus♀',7:'Ketu☋',8:'Saturn♄',9:'Mars♂'};

    // Benefits & losses
    const t = <?= $_mcI18n ?>;
    const benefits = [], losses = [];
    if (_MM_MODE === 'vedic') {
        if (totalRoot === psychic)           benefits.push(t.rootDriver);
        else if (friends.includes(totalRoot)) benefits.push(t.rootFriend);
        else                                  losses.push(t.rootNeutral);
        if (last2Root === psychic)            benefits.push(t.last2Driver);
        else if (friends.includes(last2Root)) benefits.push(t.last2Friend);
        else                                  losses.push(t.last2Neutral);
        if (!universalOk) losses.push('Saturn/Rahu caution — ' + t.saturnDelay);
    } else {
        if (psychicOk)    benefits.push(t.rootDriver);
        else if (friends.includes(totalRoot)) benefits.push(t.rootFriend);
        else              losses.push(t.rootNeutral);
        if (last2Root === psychic) benefits.push(t.last2Driver);
        else if (friends.includes(last2Root)) benefits.push(t.last2Friend);
        else              losses.push(t.last2Neutral);
        if (!universalOk) losses.push('Universal 4/8 reject — ' + t.saturnDelay);
    }
    if (isMalefic && malLabel)  losses.push(t.malefic + ': ' + malLabel.yoga + ' — ' + malLabel.desc);
    if (isAuspicious && auspLabel) benefits.push(t.auspicious + ': ' + auspLabel.yoga + ' — ' + auspLabel.desc);
    if ([1,3,5,6,9].includes(totalRoot)) benefits.push(t.strongVibe);
    if (seqLabel === 'ascending') benefits.push('Final 4 digits ascending — growth energy');
    if (seqLabel === 'descending') losses.push('Final 4 digits descending — declining energy');

    return {
        raw: clean, total, totalRoot, last2Str, last2Int, last2Sum, last2Root,
        last4: last4, seqLabel, seqOk,
        psychicOk, destinyOk, universalOk,
        isMalefic, isAuspicious, zeroOk,
        malLabel, auspLabel,
        score, level, benefits, losses,
        planet:    planets[totalRoot]  || String(totalRoot),
        l2Planet:  planets[last2Root]  || String(last2Root),
        // Legacy compat fields
        isDriver:  totalRoot === psychic,
        isFriend:  friends.includes(totalRoot),
        l2IsDriver:last2Root === psychic,
        l2IsFriend:friends.includes(last2Root),
        compat:    score >= 70 ? t.compatible : score >= 45 ? t.neutral : t.challenging,
        modeLabel: _MM_MODE === 'vedic'
            ? '☸ Vedic (Driver-aligned scoring)'
            : '⛎ Chaldean (BEST_SUMS / absolute rules)',
        // Mismatch detection (for pattern highlight)
        rootMismatch: null,
    };
}

/* ═══════════════════════════════════════════════════════════════════════
   mobileCompare() — Alpine data factory for the full Mobile section
   ═══════════════════════════════════════════════════════════════════════ */
function mobileCompare() {
    const _init = {
        driver:      <?= (int)($_mcDriver ?? 1) ?>,
        conductor:   <?= (int)($_mcConductor ?? 1) ?>,
        psychic:     <?= (int)($_mcPsychic ?? 1) ?>,
        destiny:     <?= (int)($_mcDestiny ?? 1) ?>,
        friends:     <?= $_mcFriends ?? '[]' ?>,
        activeNum:   <?= $_mcNum ?? '"N/A"' ?>,
        activeRoot:  <?= (int)($_mcRoot ?? 0) ?>,
        activeScore: <?= (int)($_mcScore ?? 0) ?>,
        activeCompound: <?= (int)($_mcCompound ?? 0) ?>,
        activeLast2: '<?= addslashes((string)($_mcLast2 ?? '')) ?>',
        activeLast2Root: <?= (int)($_mcLast2Root ?? 0) ?>,
        patterns:    <?= $_mcPatterns ?? '[]' ?>,
        i18n:        <?= $_mcI18n ?>,
    };

    return {
        // ── State ─────────────────────────────────────────────────────────
        driver:      _init.driver,
        psychic:     _init.psychic,
        destiny:     _init.destiny,
        friends:     _init.friends,
        tryNum:      '',
        loading:     false,
        result:      null,          // current "try" evaluation
        flashAdded:  false,
        compTable:   [],            // comparison rows (max 5 incl active)
        activeNum:   _init.activeNum,
        activeRoot:  _init.activeRoot,
        activeScore: null,          // computed by Chaldean (may differ from PHP baseline)
        patterns:    _init.patterns,
        i18n:        _init.i18n,

        // ── Init ──────────────────────────────────────────────────────────
        init() {
            // Compute active number score via Chaldean engine
            if (_init.activeNum && _init.activeNum !== 'N/A') {
                const ev = evaluateMobile(_init.activeNum, this.psychic, this.destiny, this.friends);
                if (ev) {
                    this.activeScore = ev.score;
                    // Prime the comparison table with the active number
                    this.compTable = [{
                        label:   '<?= $lang==="hi" ? "सक्रिय" : "Active" ?>',
                        accent:  'amber',
                        num:     _init.activeNum,
                        total:   ev.total,
                        root:    ev.totalRoot,
                        last2:   ev.last2Str,
                        last2Root: ev.last2Root,
                        score:   ev.score,
                        level:   ev.level,
                        isActive:true,
                        ev:      ev,
                    }];
                }
            }
        },

        // ── Helpers ───────────────────────────────────────────────────────
        reduce(n) { return chaldeanReduce(n); },
        planetName(n) {
            const p = {1:'Sun☉',2:'Moon☽',3:'Jupiter♃',4:'Rahu☊',5:'Mercury☿',6:'Venus♀',7:'Ketu☋',8:'Saturn♄',9:'Mars♂'};
            return p[n] || String(n);
        },
        fmt() {
            let raw = this.tryNum.replace(/\D/g, '');
            if (raw.length > 10) raw = raw.slice(0, 10);
            this.tryNum = raw.length > 5 ? raw.slice(0,5) + ' ' + raw.slice(5) : raw;
            // Auto-analyse on 10 digits
            if (raw.length === 10) { this.$nextTick(() => this.addToCompare()); }
        },

        // ── Core: evaluate + add to comparison table ──────────────────────
        addToCompare() {
            const raw = this.tryNum.replace(/\D/g, '');
            if (raw.length < 10) return;

            // Run unified Chaldean evaluation
            const ev = evaluateMobile(raw, this.psychic, this.destiny, this.friends);
            if (!ev) return;

            // Check for pattern mismatch (suggested ending vs full root)
            let rootMismatch = null;
            const last2Str = raw.slice(-2);
            for (const pat of (this.patterns || [])) {
                for (const ending of (pat.endings || [])) {
                    if (String(ending.ending) === last2Str || String(ending.ending) === last2Str.replace(/^0/, '')) {
                        if (ev.totalRoot !== pat.target_root) {
                            const l2Sum  = parseInt(last2Str[0]) + parseInt(last2Str[1]);
                            const needed = ((((pat.target_root === 9 ? 0 : pat.target_root) - (l2Sum % 9)) + 9) % 9);
                            rootMismatch = {
                                suggestedRoot:   pat.target_root,
                                actualRoot:      ev.totalRoot,
                                ending:          ending.ending,
                                prefixSumNeeded: needed === 0 ? '9 or 18 or 27…' : `${needed} or ${needed+9} or ${needed+18}`,
                            };
                        }
                        break;
                    }
                }
                if (rootMismatch) break;
            }
            ev.rootMismatch = rootMismatch;
            this.result = ev;

            // Prevent duplicates in comparison table
            const exists = this.compTable.some(r => r.num.replace(/\D/g,'') === raw);
            if (exists) return;

            // Max 5 rows (1 active + 4 tried)
            const tried = this.compTable.filter(r => !r.isActive);
            if (tried.length >= 4) {
                this.flashAdded = false;
                setTimeout(() => {}, 0);
                alert(this.i18n.compFull);
                return;
            }

            this.compTable.push({
                label:   '#' + (tried.length + 1),
                accent:  ['blue','violet','rose','cyan'][tried.length % 4],
                num:     raw,
                total:   ev.total,
                root:    ev.totalRoot,
                last2:   ev.last2Str,
                last2Root: ev.last2Root,
                score:   ev.score,
                level:   ev.level,
                isActive:false,
                ev:      ev,
            });

            this.flashAdded = true;
            this.tryNum = '';
            this.result = ev;    // keep result visible for benefits/losses
            setTimeout(() => { this.flashAdded = false; }, 2200);
        },

        removeFromCompare(idx) {
            this.compTable.splice(idx, 1);
        },

        deltaVsActive(score) {
            if (this.activeScore === null) return null;
            return score - this.activeScore;
        },

        deltaLabel(score) {
            const d = this.deltaVsActive(score);
            if (d === null) return '';
            if (d > 0)  return '▲' + d + ' pts';
            if (d < 0)  return '▼' + Math.abs(d) + ' pts';
            return '= same';
        },
        deltaClass(score) {
            const d = this.deltaVsActive(score);
            if (d === null || d === 0) return 'text-slate-400';
            return d > 0 ? 'text-emerald-400' : 'text-rose-500';
        },

        levelColor(level) {
            if (level === 'Highly Recommended') return 'text-emerald-400';
            if (level === 'Acceptable')          return 'text-amber-400';
            return 'text-rose-400';
        },
        levelBg(level) {
            if (level === 'Highly Recommended') return 'bg-emerald-900/30 border-emerald-700';
            if (level === 'Acceptable')          return 'bg-amber-900/30 border-amber-700';
            return 'bg-rose-900/30 border-rose-700';
        },
    };
}
</script>
            <div class="mt-5 print:hidden"
                 x-data="mobileCompare()" x-init="init()">

                <!-- ── Input row ─────────────────────────────────────────── -->
                <div class="flex gap-2 flex-wrap items-center mb-1">
                    <div class="relative flex-1 min-w-[180px]">
                        <input x-model="tryNum" @input="fmt()" type="text" maxlength="11"
                               id="mobile-try-input"
                               placeholder="<?= $lang==='hi'?'10 अंक — स्वतः विश्लेषण होगा':'Enter 10 digits — auto-analyses' ?>"
                               class="w-full text-sm bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2.5 rounded-lg border border-blue-300 dark:border-blue-600 outline-none focus:border-blue-500 font-mono tracking-widest pr-24"
                               @keydown.enter="addToCompare()">
                        <!-- digit counter pill inside input -->
                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[9px] font-black px-1.5 py-0.5 rounded-full transition-colors"
                              :class="tryNum.replace(/\D/g,'').length===10?'bg-emerald-500 text-white':'bg-slate-200 dark:bg-slate-600 text-slate-500'"
                              x-text="tryNum.replace(/\D/g,'').length+'/10'"></span>
                    </div>
                    <button @click="addToCompare()"
                            :disabled="tryNum.replace(/\D/g,'').length < 10"
                            class="h-10 px-4 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg text-sm font-bold flex items-center gap-1.5 transition-colors flex-shrink-0">
                        <i class="fa-solid fa-plus text-[11px]"></i>
                        <?= $lang==='hi'?'तुलना में जोड़ें':'Add to Compare' ?>
                    </button>
                </div>
                <p class="text-[9px] text-slate-400 mb-4">
                    <i class="fa-solid fa-circle-info mr-1"></i>
                    <?= $lang==='hi'?'10 अंक पूरे होते ही स्वतः विश्लेषण होगा। अधिकतम 4 नंबर तुलना में जोड़ें।':'Auto-analyses at 10 digits. Add up to 4 numbers to the comparison table.' ?>
                </p>

                <!-- ── Flash: added confirmation ────────────────────────── -->
                <div x-show="flashAdded" x-transition:leave="transition duration-500 ease-in"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="mb-3 flex items-center gap-2 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                    <i class="fa-solid fa-circle-check"></i>
                    <span x-text="i18n.addedToComp"></span>
                </div>

                <!-- ── Chaldean Comparison Table ──────────────────────────── -->
                <div x-show="compTable.length > 0" x-transition
                     class="rounded-lg border border-slate-700 overflow-hidden bg-slate-900 mb-5">
                    <!-- Header -->
                    <div class="px-4 py-2.5 bg-slate-800 border-b border-slate-700 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-scale-balanced text-indigo-400 text-[11px]"></i>
                            <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest">
                                <?= $_modeTitle ?>
                            </span>
                        </div>
                        <span class="text-[8px] text-slate-500 font-mono">
                            Psychic: <?= (int)($_mcPsychic??1) ?> · Destiny: <?= (int)($_mcDestiny??1) ?>
                        </span>
                    </div>

                    <!-- Column headers -->
                    <div class="hidden sm:grid text-[8px] font-black text-slate-500 uppercase tracking-widest
                                px-3 py-1.5 border-b border-slate-800"
                         style="grid-template-columns:56px 1fr 54px 40px 48px 56px 72px 32px">
                        <span></span>
                        <span><?= $lang==='hi'?'मोबाइल नंबर':'Mobile No.' ?></span>
                        <span class="text-center"><?= $lang==='hi'?'कुल':'Total' ?></span>
                        <span class="text-center"><?= $lang==='hi'?'मूल':'Root' ?></span>
                        <span class="text-center"><?= $lang==='hi'?'अंत.2':'Last 2' ?></span>
                        <span class="text-center"><?= $lang==='hi'?'स्कोर':'Score' ?></span>
                        <span class="text-center"><?= $lang==='hi'?'vs सक्रिय':'vs Active' ?></span>
                        <span></span>
                    </div>

                    <!-- Rows -->
                    <template x-for="(row, idx) in compTable" :key="idx">
                        <div class="border-b border-slate-800/60 last:border-0">
                            <!-- Mobile-friendly full row -->
                            <div class="sm:grid items-center px-3 py-2.5 gap-1"
                                 style="grid-template-columns:56px 1fr 54px 40px 48px 56px 72px 32px"
                                 :class="row.isActive ? 'bg-slate-800/40' : ''">

                                <!-- Status label -->
                                <div class="flex items-center gap-1 mb-1 sm:mb-0">
                                    <i class="fa-solid text-[8px]"
                                       :class="row.isActive ? 'fa-star text-amber-400' : 'fa-arrow-right-arrow-left text-blue-400'"></i>
                                    <span class="text-[9px] font-black"
                                          :class="row.isActive ? 'text-amber-400' : 'text-blue-400'"
                                          x-text="row.label"></span>
                                </div>

                                <!-- Number -->
                                <span class="text-[11px] font-black text-white font-mono tracking-wider"
                                      x-text="row.num.replace(/(\d{5})(\d+)/,'$1 $2')"></span>

                                <!-- Total compound -->
                                <span class="hidden sm:block text-center text-[11px] font-bold text-slate-300"
                                      x-text="row.total"></span>

                                <!-- Root -->
                                <span class="hidden sm:block text-center text-base font-black"
                                      :class="row.ev?.isDriver?'text-amber-400':row.ev?.isFriend?'text-emerald-400':'text-slate-400'"
                                      x-text="row.root"></span>

                                <!-- Last 2 -->
                                <span class="hidden sm:block text-center text-[11px] font-black font-mono"
                                      :class="row.ev?.l2IsDriver?'text-amber-400':row.ev?.l2IsFriend?'text-emerald-400':'text-slate-400'"
                                      x-text="row.last2"></span>

                                <!-- Score -->
                                <span class="text-center text-[13px] font-black sm:block"
                                      :class="row.score>=70?'text-emerald-400':row.score>=45?'text-amber-400':'text-rose-400'"
                                      x-text="row.score+'/100'"></span>

                                <!-- Delta vs Active -->
                                <div class="text-center">
                                    <span x-show="!row.isActive"
                                          class="text-[11px] font-black"
                                          :class="deltaClass(row.score)"
                                          x-text="deltaLabel(row.score)"></span>
                                    <span x-show="row.isActive"
                                          class="text-[9px] text-slate-500 italic">base</span>
                                </div>

                                <!-- Remove button (non-active only) -->
                                <button x-show="!row.isActive"
                                        @click="removeFromCompare(idx)"
                                        class="w-6 h-6 rounded flex items-center justify-center
                                               text-slate-600 hover:text-rose-400 hover:bg-rose-900/30
                                               transition-colors ml-auto sm:ml-0">
                                    <i class="fa-solid fa-xmark text-[10px]"></i>
                                </button>
                            </div>

                            <!-- Level badge + planet under each row -->
                            <div x-show="!row.isActive"
                                 class="flex flex-wrap items-center gap-2 px-3 pb-2 text-[8px]">
                                <span class="px-1.5 py-0.5 rounded font-black border"
                                      :class="levelBg(row.level)"
                                      x-text="row.level"></span>
                                <span class="text-slate-500" x-text="row.ev?.planet"></span>
                                <span x-show="row.ev?.isAuspicious"
                                      class="text-emerald-400 font-bold"
                                      x-text="'✦ ' + (row.ev?.auspLabel?.yoga || 'Auspicious')"></span>
                                <span x-show="row.ev?.isMalefic"
                                      class="text-rose-400 font-bold"
                                      x-text="'⚠ ' + (row.ev?.malLabel?.yoga || 'Malefic')"></span>
                                <span x-show="row.ev?.rootMismatch"
                                      class="text-amber-400 font-bold">
                                    ⚡ <?= $lang==='hi'?'मूल बेमेल':'Root mismatch' ?>
                                </span>
                            </div>

                            <!-- Score bar -->
                            <div class="flex items-center gap-2 px-3 pb-2.5">
                                <div class="flex-1 bg-slate-800 rounded-full h-1 overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-700"
                                         :class="row.score>=70?'bg-emerald-500':row.score>=45?'bg-amber-400':'bg-rose-500'"
                                         :style="'width:'+row.score+'%'"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ── Result detail panel (current evaluation) ─────────────── -->
                <div x-show="result" x-transition>
                    <!-- 4 metric cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        <div class="p-3 rounded-lg border dark:border-slate-600 bg-white dark:bg-slate-700 text-center">
                            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1"><?= $lang==='hi'?'मूल अंक':'Root Number' ?></div>
                            <div class="text-3xl font-black"
                                 :class="result?.isDriver?'text-amber-500':result?.isFriend?'text-emerald-500':'text-slate-500'"
                                 x-text="result?.totalRoot"></div>
                            <div class="text-[9px] text-slate-400 mt-0.5" x-text="result?.planet"></div>
                        </div>
                        <div class="p-3 rounded-lg border dark:border-slate-600 bg-white dark:bg-slate-700 text-center">
                            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1"><?= $lang==='hi'?'कुल योग':'Digit Sum' ?></div>
                            <div class="text-3xl font-black text-blue-500" x-text="result?.total"></div>
                        </div>
                        <div class="p-3 rounded-lg border dark:border-slate-600 bg-white dark:bg-slate-700 text-center">
                            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1"><?= $lang==='hi'?'अंतिम 2 अंक':'Last 2 Digits' ?></div>
                            <div class="text-3xl font-black font-mono"
                                 :class="result?.l2IsDriver?'text-amber-500':result?.l2IsFriend?'text-emerald-500':'text-slate-500'"
                                 x-text="result?.last2Str"></div>
                            <div class="text-[9px] text-slate-400 mt-0.5"
                                 x-text="'Root: '+(result?.last2Root||0)+' · '+(result?.l2Planet||'')"></div>
                        </div>
                        <div class="p-3 rounded-lg border dark:border-slate-600 bg-white dark:bg-slate-700 text-center">
                            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1"><?= $lang==='hi'?'Chaldean स्कोर':'Chaldean Score' ?></div>
                            <div class="text-3xl font-black"
                                 :class="(result?.score||0)>=70?'text-emerald-500':(result?.score||0)>=45?'text-amber-500':'text-rose-500'"
                                 x-text="(result?.score||0)+'/100'"></div>
                            <div class="text-[9px] font-black mt-0.5" x-text="result?.level"
                                 :class="levelColor(result?.level||'')"></div>
                        </div>
                    </div>

                    <!-- Chaldean rule checks -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
                        <div class="p-2 rounded-lg border text-center text-[9px] font-bold"
                             :class="result?.psychicOk?'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400':'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-500'">
                            <i class="fa-solid" :class="result?.psychicOk?'fa-check-circle':'fa-times-circle'"></i>
                            <?= $lang==='hi'?'मनोवैज्ञानिक (#':'Psychic #' ?><span x-text="psychic"></span>)
                        </div>
                        <div class="p-2 rounded-lg border text-center text-[9px] font-bold"
                             :class="result?.destinyOk?'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400':'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-500'">
                            <i class="fa-solid" :class="result?.destinyOk?'fa-check-circle':'fa-times-circle'"></i>
                            <?= $lang==='hi'?'भाग्य #':'Destiny #' ?><span x-text="destiny"></span>)
                        </div>
                        <div class="p-2 rounded-lg border text-center text-[9px] font-bold"
                             :class="result?.isAuspicious?'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400':result?.isMalefic?'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-700 text-rose-600':'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-500'">
                            <i class="fa-solid" :class="result?.isAuspicious?'fa-star text-amber-500':result?.isMalefic?'fa-triangle-exclamation':'fa-minus'"></i>
                            <?= $lang==='hi'?'अंत युगल':'Ending Pair' ?>
                        </div>
                        <div class="p-2 rounded-lg border text-center text-[9px] font-bold"
                             :class="result?.universalOk?'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400':'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-700 text-rose-600'">
                            <i class="fa-solid" :class="result?.universalOk?'fa-check-circle':'fa-times-circle'"></i>
                            <?= $lang==='hi'?'4/8 नियम':'4/8 Rule' ?>
                        </div>
                    </div>

                    <!-- Benefits / Losses -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                        <div x-show="(result?.benefits||[]).length > 0"
                             class="p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg">
                            <div class="text-[9px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-2">
                                <i class="fa-solid fa-arrow-trend-up mr-1"></i> <?= $lang==='hi'?'लाभ':'Benefits' ?>
                            </div>
                            <ul class="space-y-1">
                                <template x-for="b in result.benefits" :key="b">
                                    <li class="flex items-start gap-1.5 text-[11px] text-emerald-800 dark:text-emerald-300">
                                        <i class="fa-solid fa-check text-[9px] mt-0.5 text-emerald-500 flex-shrink-0"></i>
                                        <span x-text="b"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div x-show="(result?.losses||[]).length > 0"
                             class="p-3 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-700 rounded-lg">
                            <div class="text-[9px] font-black text-rose-600 dark:text-rose-400 uppercase tracking-widest mb-2">
                                <i class="fa-solid fa-arrow-trend-down mr-1"></i> <?= $lang==='hi'?'हानि':'Losses' ?>
                            </div>
                            <ul class="space-y-1">
                                <template x-for="l in result.losses" :key="l">
                                    <li class="flex items-start gap-1.5 text-[11px] text-rose-800 dark:text-rose-300">
                                        <i class="fa-solid fa-xmark text-[9px] mt-0.5 text-rose-500 flex-shrink-0"></i>
                                        <span x-text="l"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <!-- Root mismatch warning -->
                    <div x-show="result?.rootMismatch"
                         class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg text-[11px]">
                        <div class="font-black text-amber-700 dark:text-amber-400 mb-1">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            <?= $lang==='hi'?'मूल बेमेल चेतावनी':'Root Mismatch Warning' ?>
                        </div>
                        <p class="text-amber-700 dark:text-amber-300" x-text="'Suggested ending '+result.rootMismatch?.ending+' targets Root '+result.rootMismatch?.suggestedRoot+', but this number totals Root '+result.rootMismatch?.actualRoot+'. Prefix digit-sum needed: '+result.rootMismatch?.prefixSumNeeded"></p>
                    </div>

                    <!-- Suggested ending highlight hint -->
                    <p class="text-[9px] text-slate-400 mt-2">
                        <i class="fa-solid fa-lightbulb text-amber-400 mr-1"></i>
                        <?= $lang==='hi'?'सुझाए गए अंत युगल हरे रंग में हाइलाइट होंगे यदि आपका नंबर मेल खाता है।':'Suggested ending pairs below highlight in emerald when your number matches.' ?>
                    </p>
                </div>
            </div>
        </div>

    <!-- ─── SECTION DIVIDER ────────────────────────────────────────────── -->
    <div class="border-t dark:border-slate-700 pt-5">
        <div class="flex items-center gap-2 mb-4">
            <i class="fa-solid fa-cloud-arrow-up text-indigo-400"></i>
            <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">
                <?= $lang==='hi'?'थोक नंबर स्कोरर — सूची अपलोड करें':'Bulk Number Scorer — Upload a List' ?>
            </h3>
        </div>
<div x-data="mobileMatrixTab()" x-init="init()"
         class="space-y-5">

        <!-- ── SECTION HEADER ─────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <h3 class="font-black text-slate-800 dark:text-slate-100 text-base flex items-center gap-2">
                    <i class="fa-solid fa-mobile-screen text-indigo-500"></i>
                    <?= $_modeTitle ?>
                    <span class="text-[10px] font-bold text-slate-400 border border-slate-300 dark:border-slate-600 px-1.5 py-0.5 rounded uppercase tracking-widest"><?= $lang==='hi'?'उन्नत':'Advanced' ?></span>
                </h3>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    <?= $lang==='hi'
                        ? 'एक सूची अपलोड करें या चिपकाएं — हर नंबर Driver '.$_mmDriver.' के लिए स्कोर किया जाएगा'
                        : 'Upload a list or paste numbers — each scored for Driver '.$_mmDriver.' · '.(implode(', ', json_decode($_mmFriends, true) ?? [])).' friendly' ?>
                </p>
            </div>
            <!-- Export PDF button -->
            <button @click="exportPdf()"
                    :disabled="rows.length === 0"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold
                           bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-40
                           disabled:cursor-not-allowed transition-all shadow-sm print:hidden">
                <i class="fa-solid fa-file-pdf"></i>
                <?= $lang==='hi'?'PDF निर्यात':'Export PDF' ?>
            </button>
        </div>

        <!-- ── INPUT PANEL ────────────────────────────────────────────────── -->
        <div class="print:hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- Upload zone -->
                <div class="relative border-2 border-dashed border-slate-300 dark:border-slate-600
                            hover:border-indigo-400 dark:hover:border-indigo-500
                            rounded-xl p-5 text-center transition-all cursor-pointer
                            bg-slate-50 dark:bg-slate-800/60"
                     :class="dragOver ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : ''"
                     @dragover.prevent="dragOver=true"
                     @dragleave="dragOver=false"
                     @drop.prevent="onDrop($event)"
                     @click="$refs.fileInput.click()">
                    <input type="file" x-ref="fileInput" accept=".txt,.csv,.tsv,.xlsx"
                           class="hidden" @change="onFileChange($event)">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400 dark:text-slate-500 mb-2"
                       :class="dragOver?'text-indigo-500':''"></i>
                    <div class="text-xs font-bold text-slate-600 dark:text-slate-400">
                        <?= $lang==='hi'?'TXT / CSV फ़ाइल अपलोड करें':'Upload TXT / CSV file' ?>
                    </div>
                    <div class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                        <?= $lang==='hi'?'प्रति पंक्ति एक नंबर, या अल्पविराम से अलग':'One number per line, or comma-separated' ?>
                    </div>
                    <div x-show="fileName" class="mt-2 text-[10px] font-bold text-indigo-600 dark:text-indigo-400"
                         x-text="'📎 ' + fileName"></div>
                </div>

                <!-- Paste area -->
                <div class="flex flex-col gap-2">
                    <textarea x-model="pasteText"
                              @input="onPasteInput()"
                              rows="5"
                              placeholder="<?= $lang==='hi'?'यहाँ मोबाइल नंबर पेस्ट करें (प्रति पंक्ति एक)…':'Paste mobile numbers here (one per line)…' ?>"
                              class="w-full text-xs bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200
                                     border border-slate-300 dark:border-slate-600 rounded-xl px-3 py-2.5
                                     placeholder-slate-400 dark:placeholder-slate-600
                                     outline-none focus:border-indigo-500 dark:focus:border-indigo-400
                                     font-mono resize-none transition-colors"></textarea>
                    <div class="flex items-center gap-2">
                        <button @click="processInput()"
                                class="flex-1 py-2 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700
                                       text-white transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-bolt-lightning"></i>
                            <?= $lang==='hi'?'स्कोर करें':'Score Numbers' ?>
                        </button>
                        <button @click="clearAll()"
                                x-show="rows.length > 0"
                                class="py-2 px-3 rounded-lg text-xs font-bold border border-slate-300 dark:border-slate-600
                                       text-slate-600 dark:text-slate-400 hover:border-rose-400 hover:text-rose-500
                                       transition-all">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Status bar -->
            <div x-show="statusMsg" x-transition
                 class="mt-3 flex items-center gap-2 text-[11px] font-semibold px-3 py-2 rounded-lg"
                 :class="statusType==='ok'?'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700'
                                         :'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-700'">
                <i class="fa-solid" :class="statusType==='ok'?'fa-circle-check':'fa-triangle-exclamation'"></i>
                <span x-text="statusMsg"></span>
            </div>
        </div>

        <!-- ── RESULTS TABLE ───────────────────────────────────────────────── -->
        <template x-if="rows.length > 0">
            <div>
                <!-- Print-only document header -->
                <div class="hidden print:block mb-6">
                    <div class="text-center pb-4 border-b-2 border-slate-300 mb-4">
                        <div class="text-xl font-black text-slate-900 tracking-wide">
                            <?= $_modeTitle ?>
                        </div>
                        <div class="text-sm font-semibold text-slate-600 mt-1">
                            <?= $lang==='hi'
                                ? 'के लिए: '.$_mmName.' · DoB: '.$_mmDob.' · '.($p['gender']==='Male'?'पुरुष':($p['gender']==='Female'?'महिला':'अन्य'))
                                : 'for '.$_mmName.' with '.$_mmDob.' &amp; '.$_mmGender ?>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1">
                            Driver <?= $_mmDriver ?> · Conductor <?= $_mmCond ?> · Friends: <?= implode(', ', json_decode($_mmFriends, true) ?? []) ?> · Generated <?= date('d M Y, g:i A') ?>
                        </div>
                    </div>
                </div>

                <!-- Mode explanation banner -->
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg text-[10px] font-semibold mb-3
                            <?= ($_mcMode??'chaldean')==='vedic'
                                ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700'
                                : 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700' ?>">
                    <?php if (($_mcMode??'chaldean') === 'vedic'): ?>
                        <i class="fa-solid fa-om"></i>
                        <?= $lang==='hi'
                            ? '☸ वैदिक पद्धति — चालक=+25, मित्र=+12, अंत-2=चालक+15। शनि/राहु: चालक 4/8 हो तो वर्जन नहीं।'
                            : '☸ Vedic — Root=Driver +25, Friend +12, Last2=Driver +15. Saturn/Rahu waived when driver is 4 or 8.' ?>
                    <?php else: ?>
                        <i class="fa-solid fa-star-and-crescent"></i>
                        <?= $lang==='hi'
                            ? '⛎ चालदेय पद्धति — BEST_SUMS तालिका। 4 और 8 सर्वदा वर्जित।'
                            : '⛎ Chaldean Mode — scored against BEST_SUMS table. Roots 4 & 8 universally avoided.' ?>
                    <?php endif; ?>
                </div>
                <!-- Summary stats row -->
                <div class="grid grid-cols-3 gap-3 mb-4 print:hidden">
                    <div class="p-3 rounded-xl border dark:border-slate-600 bg-white dark:bg-slate-800 text-center">
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest"><?= $lang==='hi'?'कुल नंबर':'Total Numbers' ?></div>
                        <div class="text-2xl font-black text-slate-800 dark:text-white" x-text="rows.length"></div>
                    </div>
                    <div class="p-3 rounded-xl border dark:border-slate-600 bg-white dark:bg-slate-800 text-center">
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest"><?= $lang==='hi'?'अत्यंत अनुशंसित':'Highly Recommended' ?></div>
                        <div class="text-2xl font-black text-emerald-500" x-text="rows.filter(r=>r.level==='Highly Recommended').length"></div>
                    </div>
                    <div class="p-3 rounded-xl border dark:border-slate-600 bg-white dark:bg-slate-800 text-center">
                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest"><?= $lang==='hi'?'सर्वोच्च स्कोर':'Top Score' ?></div>
                        <div class="text-2xl font-black text-indigo-500" x-text="rows[0]?.score + '/100'"></div>
                    </div>
                </div>

                <!-- Sort controls -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3 print:hidden">
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest"><?= $lang==='hi'?'क्रमबद्ध':'Sort by' ?></span>
                        <button @click="sortBy='score'; sortDir=-1; sortRows()"
                                :class="sortBy==='score'?'bg-indigo-600 text-white':'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400'"
                                class="px-2 py-1 rounded text-[10px] font-bold transition-all"><?= $lang==='hi'?'स्कोर':'Score' ?></button>
                        <button @click="sortBy='root'; sortDir=1; sortRows()"
                                :class="sortBy==='root'?'bg-indigo-600 text-white':'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400'"
                                class="px-2 py-1 rounded text-[10px] font-bold transition-all"><?= $lang==='hi'?'मूल':'Root' ?></button>
                        <button @click="sortBy='num'; sortDir=1; sortRows()"
                                :class="sortBy==='num'?'bg-indigo-600 text-white':'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400'"
                                class="px-2 py-1 rounded text-[10px] font-bold transition-all"><?= $lang==='hi'?'नंबर':'Number' ?></button>
                    </div>
                    <div class="text-[10px] text-slate-400 font-semibold" x-text="rows.length + ' <?= $lang==='hi'?'नंबर':'numbers' ?>'"></div>
                </div>

                <!-- THE TABLE -->
                <!-- MOBILE FIX: this table (unlike the comparison table above,
                     which correctly stacks below sm: via hidden/sm:grid) uses
                     an unconditional fixed-width CSS grid whose columns sum to
                     ~454px before even allocating space for the phone number
                     itself. Previously wrapped in overflow-hidden, which
                     silently clipped/squished content on any real phone.
                     overflow-x-auto + min-width now makes it scroll
                     horizontally on narrow viewports instead. -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-x-auto shadow-sm">
                    <!-- Table header -->
                    <div class="grid bg-slate-800 dark:bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest px-3 py-2.5"
                         style="grid-template-columns: 2.5rem 1fr 3.5rem 2.8rem 2.8rem 2.8rem 6.5rem 7.5rem; min-width: 640px">
                        <div class="text-slate-400">#</div>
                        <div><?= $lang==='hi'?'मोबाइल नंबर':'Mobile Number' ?></div>
                        <div class="text-center"><?= $lang==='hi'?'योग':'Sum' ?></div>
                        <div class="text-center"><?= $lang==='hi'?'मूल':'Root' ?></div>
                        <div class="text-center"><?= $lang==='hi'?'अंत-2':'Last 2' ?></div>
                        <div class="text-center">L2R</div>
                        <div class="text-center"><?= $lang==='hi'?'स्कोर':'Score' ?></div>
                        <div class="text-center"><?= $lang==='hi'?'स्तर':'Level' ?></div>
                    </div>

                    <!-- Table rows -->
                    <template x-for="(row, idx) in rows" :key="row.num">
                        <div class="grid items-center px-3 py-2.5 border-b dark:border-slate-700 last:border-0 transition-colors"
                             :class="idx===0 ? 'bg-emerald-50 dark:bg-emerald-900/10'
                                    : row.level==='Highly Recommended' ? 'bg-white dark:bg-slate-800 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/5'
                                    : row.level==='Acceptable' ? 'bg-white dark:bg-slate-800 hover:bg-amber-50/50 dark:hover:bg-amber-900/5'
                                    : 'bg-rose-50/30 dark:bg-rose-900/5 hover:bg-rose-50/60'"
                             style="grid-template-columns: 2.5rem 1fr 3.5rem 2.8rem 2.8rem 2.8rem 6.5rem 7.5rem; min-width: 640px">

                            <!-- Rank badge -->
                            <div class="flex items-center justify-center">
                                <span class="w-5 h-5 rounded-full text-[9px] font-black flex items-center justify-center"
                                      :class="idx===0 ? 'bg-amber-400 text-amber-900'
                                             : idx===1 ? 'bg-slate-300 text-slate-700'
                                             : idx===2 ? 'bg-amber-700 text-white'
                                             : 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400'"
                                      x-text="idx+1"></span>
                            </div>

                            <!-- Mobile number + planet + bars -->
                            <div class="min-w-0">
                                <div class="font-mono font-black text-sm text-slate-800 dark:text-slate-100 tracking-widest"
                                     x-text="fmtNum(row.num)"></div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-[9px] text-slate-400 font-semibold" x-text="row.planet"></span>
                                    <!-- score bar mini -->
                                    <div class="flex-1 max-w-[60px] h-1 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                             :class="row.score>=70?'bg-emerald-500':row.score>=45?'bg-amber-400':'bg-rose-500'"
                                             :style="'width:'+row.score+'%'"></div>
                                    </div>
                                </div>
                                <!-- flags row -->
                                <div class="flex flex-wrap gap-0.5 mt-0.5">
                                    <span x-show="row.isAuspicious"
                                          class="text-[8px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 px-1 rounded">✦ <?= $lang==='hi'?'शुभ':'Auspicious' ?></span>
                                    <span x-show="row.isMalefic"
                                          class="text-[8px] font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 px-1 rounded">⚠ <?= $lang==='hi'?'अशुभ':'Malefic' ?></span>
                                    <span x-show="!row.universalOk"
                                          class="text-[8px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 px-1 rounded">♄ 4/8</span>
                                    <span x-show="row.seqLabel==='ascending'"
                                          class="text-[8px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 px-1 rounded">↑ <?= $lang==='hi'?'आरोही':'Rising' ?></span>
                                    <span x-show="row.seqLabel==='descending'"
                                          class="text-[8px] font-bold text-slate-500 bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 px-1 rounded">↓ <?= $lang==='hi'?'अवरोही':'Falling' ?></span>
                                </div>
                            </div>

                            <!-- Digit sum (compound) -->
                            <div class="text-center">
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-300" x-text="row.total"></span>
                            </div>

                            <!-- Full root -->
                            <div class="text-center">
                                <span class="text-base font-black"
                                      :class="row.psychicOk||row.destinyOk ? 'text-emerald-600 dark:text-emerald-400'
                                             : row.universalOk ? 'text-slate-700 dark:text-slate-300'
                                             : 'text-rose-500'"
                                      x-text="row.totalRoot"></span>
                            </div>

                            <!-- Last 2 digits -->
                            <div class="text-center">
                                <span class="font-mono text-xs font-bold text-slate-600 dark:text-slate-300" x-text="row.last2Str"></span>
                            </div>

                            <!-- Last-2 root -->
                            <div class="text-center">
                                <span class="text-xs font-bold"
                                      :class="row.l2IsDriver ? 'text-amber-500' : row.l2IsFriend ? 'text-emerald-500' : 'text-slate-500'"
                                      x-text="row.last2Root"></span>
                            </div>

                            <!-- Score gauge -->
                            <div class="text-center">
                                <div class="inline-flex flex-col items-center gap-0.5">
                                    <span class="text-base font-black"
                                          :class="row.score>=70?'text-emerald-600 dark:text-emerald-400':row.score>=45?'text-amber-500':'text-rose-500'"
                                          x-text="row.score+'/100'"></span>
                                    <div class="w-12 h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full rounded-full"
                                             :class="row.score>=70?'bg-emerald-500':row.score>=45?'bg-amber-400':'bg-rose-500'"
                                             :style="'width:'+row.score+'%'"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Level pill -->
                            <div class="text-center">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wide"
                                      :class="row.level==='Highly Recommended'
                                             ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700'
                                             : row.level==='Acceptable'
                                             ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-700'
                                             : 'bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-700'"
                                      x-text="<?= $lang==='hi'
                                          ? "row.level==='Highly Recommended'?'अत्यंत अनुशंसित':row.level==='Acceptable'?'स्वीकार्य':'टालें'"
                                          : 'row.level' ?>"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Legend -->
                <div class="flex flex-wrap gap-3 mt-3 text-[9px] font-bold text-slate-400 print:hidden">
                    <span><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1"></span><?= $lang==='hi'?'≥70 — अत्यंत अनुशंसित':'≥70 — Highly Recommended' ?></span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-amber-400 mr-1"></span><?= $lang==='hi'?'45–69 — स्वीकार्य':'45–69 — Acceptable' ?></span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-rose-500 mr-1"></span><?= $lang==='hi'?'<45 — टालें':'< 45 — Avoid' ?></span>
                    <span class="ml-auto text-slate-300 dark:text-slate-600">L2R = Last-2 Root · ♄ = Saturn/Rahu 4/8</span>
                </div>
            </div>
        </template>

        <!-- Empty state -->
        <template x-if="rows.length === 0 && !processing">
            <div class="text-center py-14 text-slate-400 dark:text-slate-600 print:hidden">
                <i class="fa-solid fa-mobile-screen-button text-4xl mb-3 block opacity-30"></i>
                <div class="text-sm font-bold"><?= $lang==='hi'?'अभी तक कोई नंबर नहीं':'No numbers yet' ?></div>
                <div class="text-[11px] mt-1"><?= $lang==='hi'?'ऊपर एक फ़ाइल अपलोड करें या नंबर पेस्ट करें':'Upload a file above or paste numbers to start' ?></div>
            </div>
        </template>

        <!-- Processing spinner -->
        <template x-if="processing">
            <div class="text-center py-10 text-slate-400 print:hidden">
                <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block text-indigo-500"></i>
                <div class="text-xs font-semibold"><?= $lang==='hi'?'गणना हो रही है…':'Scoring numbers…' ?></div>
            </div>
        </template>

    </div><!-- /x-data mobileMatrixTab -->
    </div><!-- /bulk section divider -->

</div><!-- /tab mobile_matrix -->
<script>
'use strict';
(function() {

// PHP-injected identity constants
const _MM_NAME   = <?= json_encode($_mmName) ?>;
const _MM_DOB    = <?= json_encode($_mmDob) ?>;
const _MM_GENDER = <?= json_encode($_mmGender) ?>;
const _MM_DRIVER = <?= (int)$_mmDriver ?>;
const _MM_MODE   = <?= $_mcModeJson ?? '"chaldean"' ?>; // 'chaldean' | 'vedic'
const _MM_COND   = <?= (int)$_mmCond ?>;
const _MM_PSYCHIC= <?= (int)$_mmPsychic ?>;
const _MM_DEST   = <?= (int)$_mmDestiny ?>;
const _MM_FRIENDS= <?= $_mmFriends ?>;
const _MM_LANG   = <?= json_encode($lang) ?>;

// ─── Alpine data factory ────────────────────────────────────────────────
window.mobileMatrixTab = function() {
    return {
        // ── State ──────────────────────────────────────────────────────
        pasteText:  '',
        fileName:   '',
        dragOver:   false,
        processing: false,
        rows:       [],           // scored + sorted
        sortBy:     'score',
        sortDir:    -1,           // -1 = descending
        statusMsg:  '',
        statusType: 'ok',

        // ── Init ───────────────────────────────────────────────────────
        init() { /* nothing to pre-load */ },

        // ── File Handling ──────────────────────────────────────────────
        onFileChange(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.fileName = file.name;
            this._readFile(file);
        },
        onDrop(event) {
            this.dragOver = false;
            const file = event.dataTransfer.files[0];
            if (!file) return;
            this.fileName = file.name;
            this._readFile(file);
        },
        _readFile(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.pasteText = e.target.result;
                this.processInput();
            };
            reader.readAsText(file, 'UTF-8');
        },

        // ── Live paste ─────────────────────────────────────────────────
        onPasteInput() {
            // Auto-score if >3 valid-looking numbers detected
            const nums = this._extractNumbers(this.pasteText);
            if (nums.length >= 3) this.processInput();
        },

        // ── Extract phone numbers from raw text ────────────────────────
        _extractNumbers(text) {
            if (!text) return [];
            // Split by newlines and commas, then grab runs of 7–15 digits
            const raw = text.replace(/[,;\t|]/g, '\n').split('\n');
            const seen = new Set();
            const result = [];
            for (const line of raw) {
                const match = line.trim().replace(/[\s\-\.]/g, '').match(/\d{7,15}/);
                if (match) {
                    const n = match[0].replace(/^0+/, '');  // strip leading zeros
                    if (n.length >= 7 && !seen.has(n)) {
                        seen.add(n);
                        result.push(n);
                    }
                }
            }
            return result;
        },

        // ── Main scoring pass ──────────────────────────────────────────
        processInput() {
            const text = this.pasteText;
            const nums = this._extractNumbers(text);
            if (nums.length === 0) {
                this._status(_MM_LANG==='hi' ? 'कोई मान्य नंबर नहीं मिला।' : 'No valid numbers found.', 'warn');
                return;
            }
            if (nums.length > 500) {
                this._status(
                    _MM_LANG==='hi' ? 'अधिकतम 500 नंबर। पहले 500 स्कोर किए जा रहे हैं।'
                                    : 'Max 500 numbers. Scoring first 500.',
                    'warn'
                );
            }
            this.processing = true;
            this.rows = [];
            // defer so spinner renders before heavy computation
            setTimeout(() => {
                const scored = [];
                const toScore = nums.slice(0, 500);
                for (const n of toScore) {
                    const ev = evaluateMobile(n, _MM_PSYCHIC, _MM_DEST, _MM_FRIENDS);
                    if (!ev) continue;
                    scored.push({
                        num:        ev.raw || n,
                        total:      ev.total,
                        totalRoot:  ev.totalRoot,
                        last2Str:   ev.last2Str,
                        last2Root:  ev.last2Root,
                        score:      ev.score,
                        level:      ev.level,
                        planet:     ev.planet     || '',
                        l2Planet:   ev.l2Planet   || '',
                        psychicOk:  ev.psychicOk,
                        destinyOk:  ev.destinyOk,
                        universalOk:ev.universalOk,
                        isMalefic:  ev.isMalefic,
                        isAuspicious:ev.isAuspicious,
                        zeroOk:     ev.zeroOk,
                        seqLabel:   ev.seqLabel   || 'flat',
                        isDriver:   ev.isDriver,
                        isFriend:   ev.isFriend,
                        l2IsDriver: ev.l2IsDriver,
                        l2IsFriend: ev.l2IsFriend,
                    });
                }
                this.rows = scored;
                this.sortRows();
                this.processing = false;
                const hi = scored.filter(r => r.level === 'Highly Recommended').length;
                this._status(
                    _MM_LANG==='hi'
                        ? `${scored.length} नंबर स्कोर किए — ${hi} अत्यंत अनुशंसित`
                        : `${scored.length} numbers scored — ${hi} Highly Recommended`,
                    'ok'
                );
            }, 30);
        },

        // ── Sort ───────────────────────────────────────────────────────
        sortRows() {
            const key = this.sortBy;
            const dir = this.sortDir;
            this.rows = [...this.rows].sort((a, b) => {
                let av = a[key === 'score' ? 'score' : key === 'root' ? 'totalRoot' : 'num'];
                let bv = b[key === 'score' ? 'score' : key === 'root' ? 'totalRoot' : 'num'];
                if (typeof av === 'string') return dir * av.localeCompare(bv);
                return dir * (av - bv);
            });
        },

        // ── Clear all ──────────────────────────────────────────────────
        clearAll() {
            this.rows      = [];
            this.pasteText = '';
            this.fileName  = '';
            this.statusMsg = '';
        },

        // ── Format number for display ──────────────────────────────────
        fmtNum(n) {
            const s = String(n).replace(/\D/g, '');
            if (s.length === 10) return s.slice(0,5) + ' ' + s.slice(5);
            if (s.length > 10)   return s.slice(0,-5) + ' ' + s.slice(-5);
            return s;
        },

        // ── Status helper ──────────────────────────────────────────────
        _status(msg, type = 'ok') {
            this.statusMsg  = msg;
            this.statusType = type;
            setTimeout(() => { this.statusMsg = ''; }, 5000);
        },

        // ── Export PDF ─────────────────────────────────────────────────
        exportPdf() {
            if (this.rows.length === 0) return;

            const _modeTitle = _MM_MODE === 'vedic'
                ? (_MM_LANG === 'hi' ? 'वैदिक मोबाइल विश्लेषण' : 'Vedic Mobile Analysis')
                : (_MM_LANG === 'hi' ? 'चालदेय मोबाइल तुलना'  : 'Chaldean Mobile Comparison');
            const title = _MM_LANG === 'hi'
                ? `${_modeTitle} — ${_MM_NAME} (${_MM_DOB}, ${_MM_GENDER === 'Male' ? 'पुरुष' : _MM_GENDER === 'Female' ? 'महिला' : 'अन्य'})`
                : `${_modeTitle} for ${_MM_NAME} with ${_MM_DOB} & ${_MM_GENDER}`;

            const heading = _modeTitle;

            const subheading = _MM_LANG === 'hi'
                ? `के लिए: ${_MM_NAME} &nbsp;·&nbsp; DoB: ${_MM_DOB} &nbsp;·&nbsp; ${_MM_GENDER === 'Male' ? 'पुरुष' : _MM_GENDER === 'Female' ? 'महिला' : 'अन्य'}`
                : `for <strong>${_MM_NAME}</strong> &nbsp;·&nbsp; ${_MM_DOB} &nbsp;·&nbsp; ${_MM_GENDER}`;

            const planets = {1:'Sun☉',2:'Moon☽',3:'Jupiter♃',4:'Rahu☊',5:'Mercury☿',6:'Venus♀',7:'Ketu☋',8:'Saturn♄',9:'Mars♂'};

            const levelLabel = (lv) => {
                if (_MM_LANG !== 'hi') return lv;
                return lv === 'Highly Recommended' ? 'अत्यंत अनुशंसित'
                     : lv === 'Acceptable'          ? 'स्वीकार्य'
                     :                                'टालें';
            };
            const levelColor = (lv) =>
                lv === 'Highly Recommended' ? '#059669'
                : lv === 'Acceptable'        ? '#d97706'
                :                              '#dc2626';

            const scoreBar = (score) => {
                const color = score >= 70 ? '#059669' : score >= 45 ? '#d97706' : '#dc2626';
                return `<div style="background:#e5e7eb;border-radius:4px;height:6px;width:60px;overflow:hidden;display:inline-block;vertical-align:middle;margin-left:4px">
                            <div style="background:${color};height:100%;width:${score}%;border-radius:4px"></div>
                        </div>`;
            };

            const flags = (row) => {
                const f = [];
                if (row.isAuspicious)           f.push(`<span style="color:#059669;font-size:9px">✦ ${_MM_LANG==='hi'?'शुभ':'Auspicious'}</span>`);
                if (row.isMalefic)              f.push(`<span style="color:#dc2626;font-size:9px">⚠ ${_MM_LANG==='hi'?'अशुभ':'Malefic'}</span>`);
                if (!row.universalOk)           f.push(`<span style="color:#d97706;font-size:9px">♄ 4/8</span>`);
                if (row.seqLabel==='ascending') f.push(`<span style="color:#2563eb;font-size:9px">↑</span>`);
                if (row.seqLabel==='descending')f.push(`<span style="color:#9ca3af;font-size:9px">↓</span>`);
                return f.join(' &nbsp;');
            };

            const rows = this.rows;
            const tableRows = rows.map((row, i) => {
                const rankBg = i === 0 ? '#f59e0b' : i === 1 ? '#d1d5db' : i === 2 ? '#92400e' : '#f1f5f9';
                const rankFg = i <= 2 ? '#1e293b' : '#64748b';
                const rootColor = (row.psychicOk || row.destinyOk) ? '#059669'
                                : row.universalOk ? '#374151' : '#dc2626';
                const l2Color = row.l2IsDriver ? '#d97706' : row.l2IsFriend ? '#059669' : '#9ca3af';
                const fmtN = String(row.num).replace(/\D/g,'');
                const display = fmtN.length >= 10 ? fmtN.slice(0,-5)+' '+fmtN.slice(-5) : fmtN;
                return `<tr style="background:${i%2===0?'#f9fafb':'#ffffff'}; border-bottom:1px solid #e5e7eb">
                    <td style="padding:7px 10px; text-align:center">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:${rankBg};color:${rankFg};font-size:9px;font-weight:900">${i+1}</span>
                    </td>
                    <td style="padding:7px 10px; font-family:monospace; font-weight:900; font-size:12px; color:#1e293b">
                        ${display}
                        <div style="font-size:9px;color:#94a3b8;font-family:sans-serif;font-weight:600;margin-top:2px">${planets[row.totalRoot]||''} &nbsp; ${flags(row)}</div>
                    </td>
                    <td style="padding:7px 10px; text-align:center; font-weight:700; color:#475569">${row.total}</td>
                    <td style="padding:7px 10px; text-align:center; font-size:14px; font-weight:900; color:${rootColor}">${row.totalRoot}</td>
                    <td style="padding:7px 10px; text-align:center; font-family:monospace; font-weight:700; color:#475569">${row.last2Str}</td>
                    <td style="padding:7px 10px; text-align:center; font-weight:700; color:${l2Color}">${row.last2Root}</td>
                    <td style="padding:7px 10px; text-align:center">
                        <span style="font-size:13px;font-weight:900;color:${levelColor(row.level)}">${row.score}/100</span>
                        ${scoreBar(row.score)}
                    </td>
                    <td style="padding:7px 10px; text-align:center">
                        <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:900;
                                     background:${row.level==='Highly Recommended'?'#d1fae5':row.level==='Acceptable'?'#fef3c7':'#fee2e2'};
                                     color:${levelColor(row.level)};
                                     border:1px solid ${row.level==='Highly Recommended'?'#a7f3d0':row.level==='Acceptable'?'#fde68a':'#fecaca'}">${levelLabel(row.level)}</span>
                    </td>
                </tr>`;
            }).join('');

            const hi = rows.filter(r => r.level === 'Highly Recommended').length;
            const topScore = rows[0]?.score ?? 0;

            const html = `<!DOCTYPE html>
<html lang="${_MM_LANG}">
<head>
<meta charset="UTF-8">
<title>${title}</title>
<style>
  * { box-sizing:border-box; margin:0; padding:0 }
  body { font-family:'Segoe UI',Arial,sans-serif; background:#fff; color:#1e293b; padding:28px 32px; font-size:11px }
  h1  { font-size:16px; font-weight:900; color:#1e293b; letter-spacing:.04em }
  h2  { font-size:11px; font-weight:600; color:#64748b; margin-top:3px }
  table { width:100%; border-collapse:collapse; margin-top:16px }
  thead tr { background:#0f172a; color:#fff }
  thead th { padding:8px 10px; font-size:9px; font-weight:900; text-transform:uppercase; letter-spacing:.08em }
  tfoot tr { background:#f8fafc }
  tfoot td { padding:7px 10px; font-size:9px; color:#64748b }
  @media print { @page { size:A4 landscape; margin:15mm 12mm } }
</style>
</head>
<body>
<div style="border-bottom:3px solid #4f46e5;padding-bottom:12px;margin-bottom:4px">
  <h1>${heading}</h1>
  <h2>${subheading}</h2>
  <div style="margin-top:6px;font-size:9px;color:#94a3b8">
    Driver: ${_MM_DRIVER} &nbsp;·&nbsp; Conductor: ${_MM_COND} &nbsp;·&nbsp; Friends: ${_MM_FRIENDS.join(', ')} &nbsp;·&nbsp;
    Generated: ${new Date().toLocaleString()}
  </div>
</div>
<div style="display:flex;gap:24px;margin:10px 0 4px;font-size:10px">
  <span><strong style="color:#1e293b">${rows.length}</strong> ${_MM_LANG==='hi'?'कुल':'Total'}</span>
  <span style="color:#059669"><strong>${hi}</strong> ${_MM_LANG==='hi'?'अत्यंत अनुशंसित':'Highly Recommended'}</span>
  <span style="color:#4f46e5"><strong>${topScore}/100</strong> ${_MM_LANG==='hi'?'सर्वोच्च स्कोर':'Top Score'}</span>
</div>
<table>
  <thead>
    <tr>
      <th style="width:40px">#</th>
      <th style="text-align:left">${_MM_LANG==='hi'?'मोबाइल नंबर':'Mobile Number'}</th>
      <th>${_MM_LANG==='hi'?'योग':'Sum'}</th>
      <th>${_MM_LANG==='hi'?'मूल':'Root'}</th>
      <th>${_MM_LANG==='hi'?'अंत-2':'Last 2'}</th>
      <th>L2R</th>
      <th>${_MM_LANG==='hi'?'स्कोर':'Score'}</th>
      <th>${_MM_LANG==='hi'?'स्तर':'Level'}</th>
    </tr>
  </thead>
  <tbody>${tableRows}</tbody>
  <tfoot>
    <tr>
      <td colspan="8" style="text-align:center;padding-top:10px">
        ✦ ${_MM_LANG==='hi'?'≥70 अत्यंत अनुशंसित':'≥70 Highly Recommended'} &nbsp;·&nbsp;
        ${_MM_LANG==='hi'?'45–69 स्वीकार्य':'45–69 Acceptable'} &nbsp;·&nbsp;
        ${_MM_LANG==='hi'?'<45 टालें':'< 45 Avoid'} &nbsp;·&nbsp;
        L2R = Last-2 Root &nbsp;·&nbsp; ♄ = Saturn/Rahu 4/8 &nbsp;·&nbsp;
        Arthsathi Vedic Numerology
      </td>
    </tr>
  </tfoot>
</table>
<script>window.onload=function(){window.print();}<\/script>
</body>
</html>`;

            const w = window.open('', '_blank', 'width=1100,height=750');
            if (w) { w.document.write(html); w.document.close(); }
        },

    }; // end return
}; // end mobileMatrixTab

})(); // end IIFE
</script>
