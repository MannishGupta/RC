<?php
/**
 * Version: 20260929.18
 * Full Open Graph / Twitter / canonical SEO for share previews
 * India distribution, physiological highlights, awareness action plan + compatibility chart
 * Blood Group Intelligence Report — team compatibility + positive lore
 * Educational / workplace fun only — NOT medical advice.
 */
declare(strict_types=1);

$base = __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}
// BUG FIX: checked for tenant_bootstrap.php at BASE_PATH root, which never
// exists there -- the real file lives at app/tenant_bootstrap.php, exactly
// where every other correctly-wired standalone entry point in this app
// requires it from (cards/signature.php, vehicle-tags/index.php,
// janam_patri.php, tools/diagnostics.php, and others, all fixed in an
// earlier audit pass for this identical mistake). is_file() on the wrong
// path always returned false, silently falling through to app/bootstrap.php
// alone -- which never resolves DATA_PATH per tenant, so it fell straight
// to the hardcoded BASE_PATH/data default. On a multi-tenant deployment
// this file was reading the wrong tenant's team data on every request.
if (is_file($base . '/app/tenant_bootstrap.php')) {
    require_once $base . '/app/tenant_bootstrap.php';
} elseif (is_file($base . '/app/bootstrap.php')) {
    require_once $base . '/app/bootstrap.php';
}
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}

date_default_timezone_set('Asia/Kolkata');

function bg_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bg_lang(): string {
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    $q = strtolower(trim((string)($_GET['lang'] ?? '')));
    if (in_array($q, ['en', 'en_in', 'english'], true)) {
        $lang = 'en';
    } elseif (in_array($q, ['hi', 'hi_in', 'hindi'], true)) {
        $lang = 'hi';
    } else {
        $c = strtolower(trim((string)($_COOKIE['rc_lang'] ?? '')));
        $lang = ($c === 'en') ? 'en' : 'hi';
    }
    if (!headers_sent()) {
        @setcookie('rc_lang', $lang, [
            'expires' => time() + 86400 * 400,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    return $lang;
}

function bg_is_hi(): bool {
    return bg_lang() === 'hi';
}

/** @param array{0:string,1:string}|string $pair */
function bg_t($pair): string {
    if (is_array($pair)) {
        return bg_is_hi() ? (string)($pair[1] ?? $pair[0] ?? '') : (string)($pair[0] ?? '');
    }
    return (string)$pair;
}

function bg_norm_group(?string $raw): string {
    $s = strtoupper(trim((string)$raw));
    $s = str_replace([' ', 'BLOOD', 'GROUP', 'TYPE', ':', '-'], '', $s);
    $s = str_replace('POSITIVE', '+', $s);
    $s = str_replace('NEGATIVE', '-', $s);
    if (preg_match('/^(A|B|AB|O)(\+|−|-)?$/', $s, $m)) {
        $abo = $m[1];
        $rh = $m[2] ?? '';
        if ($rh === '−' || $rh === '-') {
            return $abo . '-';
        }
        if ($rh === '+') {
            return $abo . '+';
        }
        return $abo; // ABO only
    }
    // loose: A+, B+, AB+, O+
    if (preg_match('/\b(A|B|AB|O)\s*([\+\-]|POS|NEG)?/i', (string)$raw, $m)) {
        $abo = strtoupper($m[1]);
        $rh = strtoupper($m[2] ?? '');
        if ($rh === '-' || $rh === 'NEG') {
            return $abo . '-';
        }
        if ($rh === '+' || $rh === 'POS' || $rh === '') {
            return $rh === '' ? $abo : $abo . '+';
        }
    }
    return '';
}

function bg_abo(string $g): string {
    return preg_replace('/[\+\-]$/', '', $g) ?? $g;
}

function bg_rh(string $g): string {
    if (str_ends_with($g, '+')) {
        return '+';
    }
    if (str_ends_with($g, '-')) {
        return '-';
    }
    return '';
}

/**
 * Canonical 8-type RBC donor→recipient matrix (educational textbook model).
 * Order matches common charts: O− O+ B− B+ A− A+ AB− AB+.
 */
function bg_type_order(): array {
    return ['O-', 'O+', 'B-', 'B+', 'A-', 'A+', 'AB-', 'AB+'];
}

/**
 * Normalize to one of the 8 chart types when possible (append + if Rh missing).
 */
function bg_chart_type(string $raw): string {
    $g = bg_norm_group($raw);
    if ($g === '') {
        return '';
    }
    if (in_array($g, bg_type_order(), true)) {
        return $g;
    }
    // ABO only → treat as positive for chart highlighting (common workplace data)
    $abo = bg_abo($g);
    if (in_array($abo . '+', bg_type_order(), true)) {
        return $abo . '+';
    }
    return '';
}

/** Educational RBC transfusion: can $donor give to $recipient? */
function bg_can_donate_to(string $donor, string $recipient): bool {
    $d = bg_chart_type($donor);
    $r = bg_chart_type($recipient);
    if ($d === '' || $r === '') {
        // Fall back to ABO-only if incomplete
        $d0 = bg_norm_group($donor);
        $r0 = bg_norm_group($recipient);
        if ($d0 === '' || $r0 === '') {
            return false;
        }
        $dA = bg_abo($d0);
        $rA = bg_abo($r0);
        return match ($dA) {
            'O' => true,
            'A' => in_array($rA, ['A', 'AB'], true),
            'B' => in_array($rA, ['B', 'AB'], true),
            'AB' => $rA === 'AB',
            default => false,
        };
    }
    // Exact matrix from standard chart (donor columns × recipient rows)
    // CODE QUALITY FIX: this table was previously assigned twice -- a first
    // block immediately overwritten by a second before anything ever read
    // it, pure dead code. Verified both held the exact same sets (only
    // reordered, which in_array() doesn't care about), so this was never a
    // logic bug -- the actually-used table was and is correct, checked
    // directly against the standard ABO+Rh donor compatibility chart for
    // all 8 types. Removed the dead first block so a future edit to one
    // table can't silently drift from the other while both look live.
    static $ok = null;
    if ($ok === null) {
        $ok = [
            'O-'  => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
            'O+'  => ['O+', 'A+', 'B+', 'AB+'],
            'A-'  => ['A-', 'A+', 'AB-', 'AB+'],
            'A+'  => ['A+', 'AB+'],
            'B-'  => ['B-', 'B+', 'AB-', 'AB+'],
            'B+'  => ['B+', 'AB+'],
            'AB-' => ['AB-', 'AB+'],
            'AB+' => ['AB+'],
        ];
    }
    return in_array($r, $ok[$d] ?? [], true);
}

function bg_can_receive_from(string $recipient, string $donor): bool {
    return bg_can_donate_to($donor, $recipient);
}

/** Render standard 8×8 chart; highlight focus type row+col and optional team types. */
function bg_render_standard_chart(string $focusType, array $teamTypes = []): void {
    $types = bg_type_order();
    $focusType = bg_chart_type($focusType);
    $teamSet = [];
    foreach ($teamTypes as $tt) {
        $c = bg_chart_type((string)$tt);
        if ($c !== '') {
            $teamSet[$c] = true;
        }
    }
    echo '<div class="bg-chart-wrap">';
    echo '<h2 class="bg-chart-title">' . bg_h(bg_t(['Compatibility of ', ''])) 
        . '<span style="color:#e11d48">' . bg_h(bg_t(['Blood Types', 'रक्त समूह अनुकूलता'])) . '</span></h2>';
    echo '<p class="meta" style="text-align:center;margin:0 0 .5rem">'
        . bg_h(bg_t(['Donor → top · Recipient → left · Drop = compatible', 'दाता → ऊपर · प्राप्तकर्ता → बाएँ · बूँद = अनुकूल']))
        . '</p>';
    echo '<div class="bg-chart-frame">';
    echo '<div class="bg-donor-label">' . bg_h(bg_t(['Donor', 'दाता'])) . '</div>';
    echo '<div class="bg-chart-row">';
    echo '<div class="bg-recip-label">' . bg_h(bg_t(['Recipient', 'प्राप्तकर्ता'])) . '</div>';
    echo '<table class="bg-std-chart" role="grid" aria-label="Blood type compatibility">';
    echo '<thead><tr><th class="corner" scope="col"></th>';
    foreach ($types as $d) {
        $cls = 'dh';
        if ($d === $focusType) {
            $cls .= ' hi-col';
        } elseif (isset($teamSet[$d])) {
            $cls .= ' team-col';
        }
        echo '<th class="' . $cls . '" scope="col">' . bg_h($d) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach (array_reverse($types) as $r) {
        $trCls = '';
        if ($r === $focusType) {
            $trCls = 'hi-row';
        } elseif (isset($teamSet[$r])) {
            $trCls = 'team-row';
        }
        echo '<tr class="' . $trCls . '">';
        $rhCls = 'rh';
        if ($r === $focusType) {
            $rhCls .= ' hi-col';
        } elseif (isset($teamSet[$r])) {
            $rhCls .= ' team-col';
        }
        echo '<th class="' . $rhCls . '" scope="row">' . bg_h($r) . '</th>';
        foreach ($types as $d) {
            $yes = bg_can_donate_to($d, $r);
            $cell = 'cell';
            if ($d === $focusType || $r === $focusType) {
                $cell .= ' hi-band';
            }
            if ($d === $focusType && $r === $focusType) {
                $cell .= ' hi-cross';
            }
            if ($yes) {
                echo '<td class="' . $cell . ' yes"><span class="drop" title="' . bg_h($d . ' → ' . $r) . '">✓</span></td>';
            } else {
                echo '<td class="' . $cell . ' no"></td>';
            }
        }
        echo '</tr>';
    }
    echo '</tbody></table></div></div>';
    if ($focusType !== '') {
        echo '<p class="bg-legend"><span class="lg hi"></span> '
            . bg_h(bg_t(['Focus type row & column', 'चयनित समूह की पंक्ति व स्तंभ']))
            . ' <strong>' . bg_h($focusType) . '</strong>';
        if ($teamSet !== []) {
            echo ' &nbsp;·&nbsp; <span class="lg team"></span> '
                . bg_h(bg_t(['Other types on this team', 'टीम के अन्य समूह']));
        }
        echo '</p>';
    }
    echo '</div>';
}


/**
 * India-aware positive facts (population / evolutionary context).
 * Not personality determinism; not personal medical advice.
 */
function bg_lore(string $group): array {
    $abo = bg_abo(bg_norm_group($group));
    $map = [
        'O' => [
            'title' => ['Type O — universal donor backbone & resilience', 'O समूह — यूनिवर्सल डोनर व लचीलापन'],
            'emoji' => '🩸',
            'india_share' => ['~36.5–37% of India (O+) — most common', 'भारत में ~36.5–37% (O+) — सबसे आम'],
            'traits' => [
                ['High prevalence: O+ donors form the backbone of emergency hospital supply nationwide.', 'उच्च प्रसार: O+ दाता देशभर में आपातकालीन रक्त आपूर्ति की रीढ़ हैं।'],
                ['Population studies often link group O with relatively lower risk of some cardiovascular conditions vs non-O groups.', 'जनसंख्या अध्ययनों में O समूह में कुछ हृदय संबंधी जोखिम अपेक्षाकृत कम बताए गए हैं (गैर-O की तुलना में)।'],
                ['Evolutionary research suggests partial protection against severe Plasmodium falciparum malaria — relevant in tropical Indian history.', 'विकासवादी शोध: गंभीर फाल्सिपेरम मलेरिया से आंशिक सुरक्षा — भारत के उष्ण कटिबंधीय इतिहास में प्रासंगिक।'],
                ['O− is especially precious: true “universal donor” for red cells in textbook models.', 'O− विशेष रूप से मूल्यवान: पाठ्य मॉडल में लाल रक्त कणिकाओं का यूनिवर्सल डोनर।'],
            ],
            'fun' => [
                ['In India, O+ alone is roughly one in three people — you are in excellent company for donor drives.', 'भारत में अकेले O+ लगभग हर तीन में एक — रक्तदान अभियानों में अच्छी संगति।'],
                ['Rh-negative types (including O−) are under ~5% combined — blood banks value regular registered donors.', 'Rh-नेगेटिव (O− सहित) कुल ~5% से कम — बैंक नियमित पंजीकृत दाताओं को महत्व देते हैं।'],
            ],
            'wit' => [
                ['Workplace metaphor: O ships help fast when the emergency channel lights up.', 'ऑफिस रूपक: आपात चैनल जलते ही O तेज़ मदद भेजता है।'],
                ['Your group is the hospital’s favourite “always useful” inventory line.', 'आपका ग्रुप अस्पताल की “हमेशा काम आए” इन्वेंटरी लाइन है।'],
            ],
        ],
        'B' => [
            'title' => ['Type B — metabolic adaptability (South Asia signature)', 'B समूह — चयापचय लचीलापन (दक्षिण एशिया पहचान)'],
            'emoji' => '🌾',
            'india_share' => ['~32–34% B+ in India — much higher than typical Western rates', 'भारत में ~32–34% B+ — पश्चिमी औसत से काफी अधिक'],
            'traits' => [
                ['India has one of the world’s highest concentrations of group B, especially in northern and central belts.', 'भारत में B समूह का विश्व-स्तरीय उच्च संकेन्द्रण, विशेषकर उत्तर–मध्य क्षेत्रों में।'],
                ['Often discussed with pastoral/nomadic ancestral narratives and adaptable digestive patterns on mixed dairy–grain–plant diets.', 'अक्सर चरवाहा/यायावर पूर्वजों व मिश्रित दूध–अनाज–वनस्पति आहार पर अनुकूल पाचन से जोड़ा जाता है।'],
                ['Framed as an “endurance profile”: vitality and resilience themes in everyday health conversations (not a clinical claim).', '“सहनशक्ति प्रोफ़ाइल”: दैनिक स्वास्थ्य चर्चा में ऊर्जा व लचीलापन (चिकित्सकीय दावा नहीं)।'],
                ['Strong presence means B donors and recipients are common teammates in Indian workplaces.', 'मजबूत उपस्थिति: भारतीय कार्यस्थलों में B दाता व प्राप्तकर्ता आम सहयोगी हैं।'],
            ],
            'fun' => [
                ['If blood groups were cricket teams, B would pack stadiums in North & Central India.', 'अगर ब्लड ग्रुप क्रिकेट टीम होते, B उत्तर–मध्य भारत में स्टेडियम भर देता।'],
                ['Traditional diverse Indian thalis map well to the “adaptable diet” story often told about B.', 'पारंपरिक विविध थाली उस “अनुकूल आहार” कथा से मेल खाती है जो B के बारे में कही जाती है।'],
            ],
            'wit' => [
                ['You are statistically very “Indian” in the ABO sense — and that is a feature.', 'ABO अर्थ में आप सांख्यिकीय रूप से बहुत “भारतीय” हैं — और यह खूबी है।'],
                ['Team chat energy: flexible, dairy-friendly, and hard to surprise with menu diversity.', 'टीम चैट: लचीले, डेयरी-फ्रेंडली, मेनू विविधता से कम हैरान।'],
            ],
        ],
        'A' => [
            'title' => ['Type A — metabolic precision & plant-rich harmony', 'A समूह — चयापचय सटीकता व वनस्पति-समृद्ध सामंजस्य'],
            'emoji' => '🥗',
            'india_share' => ['~20–22% A+ across India', 'भारत में ~20–22% A+'],
            'traits' => [
                ['Often associated with thriving on plant-rich, vegetarian-leaning diets — aligned with many Indian community food traditions.', 'अक्सर वनस्पति-प्रधान/शाकाहारी झुकाव वाले आहार से जोड़ा जाता है — कई भारतीय परंपराओं से मेल।'],
                ['Type A biochemistry discussions frequently mention efficient carbohydrate handling and steady energy on fibre-rich meals.', 'A जैव-रसायन चर्चा में फाइबर-युक्त भोजन पर स्थिर ऊर्जा व कार्बोहाइड्रेट प्रबंधन का ज़िक्र आता है।'],
                ['A useful reminder that “know your group” still matters for donation matching even when you are not the rarest type.', 'दुर्लभ न होने पर भी दान मिलान के लिए ग्रुप जानना महत्वपूर्ण है।'],
                ['A− remains uncommon; Rh-negative A is a valued donor category in Indian blood banks.', 'A− दुर्लभ; Rh-नेगेटिव A भारतीय रक्त बैंकों में मूल्यवान श्रेणी है।'],
            ],
            'fun' => [
                ['Vegetarian festival spreads and Type A “plant harmony” stories are a natural conversation pair.', 'शाकाहारी उत्सव थाली और A की “वनस्पति सामंजस्य” कथा प्राकृतिक जोड़ी हैं।'],
                ['About one in five Indians is A+ — common enough to meet, rare enough to still schedule donation consciously.', 'लगभग पाँच में एक भारतीय A+ — मिलना आम, दान की योजना फिर भी ज़रूरी।'],
            ],
            'wit' => [
                ['Your metabolic metaphor: prefer a clear recipe over a chaotic buffet.', 'रूपक: अराजक भोज से बेहतर साफ रेसिपी।'],
                ['Spreadsheet energy meets sabzi — organised and nourished.', 'स्प्रेडशीट ऊर्जा सब्ज़ी से मिलती है — व्यवस्थित और पोषित।'],
            ],
        ],
        'AB' => [
            'title' => ['Type AB — universal recipient & genetic synergy', 'AB समूह — यूनिवर्सल प्राप्तकर्ता व आनुवंशिक संयोजन'],
            'emoji' => '🤝',
            'india_share' => ['~7–8% AB+ — rarest major ABO group in India', 'भारत में ~7–8% AB+ — मुख्य ABO में दुर्लभतम'],
            'traits' => [
                ['AB+ is the textbook “universal recipient” for red cells — can receive A, B, AB, and O red cells in simplified models.', 'AB+ पाठ्य रूप में RBC यूनिवर्सल प्राप्तकर्ता — सरल मॉडल में A, B, AB, O से प्राप्त।'],
                ['Carries both A and B antigens — a unique combined immunological profile.', 'A और B दोनों एंटीजन — अनूठा संयुक्त प्रतिरक्षा प्रोफ़ाइल।'],
                ['Rarity makes AB donors (especially AB−) critical for plasma and specialised inventory planning.', 'दुर्लभता AB दाताओं (विशेषकर AB−) को प्लाज्मा व विशेष इन्वेंटरी के लिए महत्वपूर्ण बनाती है।'],
                ['Knowing AB status early helps emergency ID and family awareness.', 'AB स्थिति जल्दी जानना आपात ID व परिवार जागरूकता में मदद करता है।'],
            ],
            'fun' => [
                ['AB is the “exclusive club” of ABO in India — small membership, high awareness value.', 'भारत में ABO का विशेष क्लब — कम सदस्य, अधिक जागरूकता मूल्य।'],
                ['Universal-recipient story is about receiving red cells — plasma/platelet rules differ; always follow clinicians.', 'यूनिवर्सल प्राप्तकर्ता कथा RBC की है — प्लाज्मा/प्लेटलेट नियम अलग; चिकित्सक निर्देश मानें।'],
            ],
            'wit' => [
                ['You are the diplomatic passport of antigens — both stamps in one booklet.', 'आप एंटीजन का राजनयिक पासपोर्ट हैं — एक पुस्तिका में दोनों मुहरें।'],
                ['Team superpower metaphor: translate between A-camp and B-camp without choosing sides.', 'रूपक: A और B खेमे के बीच अनुवाद — बिना पक्ष लिए।'],
            ],
        ],
    ];
    $base = $map[$abo] ?? [
        'title' => ['Blood group profile', 'ब्लड ग्रुप प्रोफ़ाइल'],
        'emoji' => '🩸',
        'india_share' => ['Add A+/B+/O+/AB+ (±) on the team profile', 'टीम प्रोफ़ाइल पर A+/B+/O+/AB+ (±) जोड़ें'],
        'traits' => [
            ['Record your ABO and Rh for emergency and donor registry readiness.', 'आपात व दाता रजिस्ट्री के लिए ABO व Rh दर्ज करें।'],
        ],
        'fun' => [
            ['India’s mix is unique: high B, high O, scarce Rh-negatives — every registered donor helps.', 'भारत का मिश्रण अनूठा: उच्च B, उच्च O, दुर्लभ Rh-नेग — हर पंजीकृत दाता मददगार।'],
        ],
        'wit' => [
            ['Unknown group is the only “incompatible” status on this page — update the team card.', 'अज्ञात ग्रुप ही यहाँ असंगत है — टीम कार्ड अपडेट करें।'],
        ],
    ];
    return [
        'title' => bg_t($base['title']),
        'emoji' => $base['emoji'],
        'india_share' => bg_t($base['india_share']),
        'traits' => array_map(static fn($x) => bg_t($x), $base['traits']),
        'fun' => array_map(static fn($x) => bg_t($x), $base['fun']),
        'wit' => array_map(static fn($x) => bg_t($x), $base['wit']),
        'abo' => $abo !== '' ? $abo : '—',
    ];
}

function bg_render_india_context(): void {
    echo '<div class="card">';
    echo '<h2>🇮🇳 ' . bg_h(bg_t(['Blood groups in India — quick context', 'भारत में रक्त समूह — संक्षिप्त संदर्भ'])) . '</h2>';
    echo '<p class="meta" style="margin:0 0 .75rem">' . bg_h(bg_t([
        'Approximate national averages (ABO+Rh). Local and community rates vary. Figures are orientation, not a lab report.',
        'राष्ट्रीय औसत (लगभग)। स्थानीय/समुदाय दरें भिन्न। दिशासूचक आँकड़े — लैब रिपोर्ट नहीं।',
    ])) . '</p>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:.5rem">';
    $rows = [
        ['O+', '~36.5–37%', 'सबसे आम / Most common'],
        ['B+', '~32–34%', 'दक्षिण एशिया में उच्च / High in South Asia'],
        ['A+', '~20–22%', 'मध्यम / Moderate'],
        ['AB+', '~7–8%', 'दुर्लभतम मुख्य / Rarest major'],
        ['Rh−', '< ~5%', 'मिलकर दुर्लभ / Combined rare'],
    ];
    foreach ($rows as [$g, $pct, $note]) {
        echo '<div style="border:1px solid #fecdd3;border-radius:12px;padding:.55rem .65rem;background:#fff1f2">';
        echo '<div style="font-weight:900;color:#9f1239;font-size:1.05rem">' . bg_h($g) . '</div>';
        echo '<div style="font-weight:800;font-size:.9rem">' . bg_h($pct) . '</div>';
        echo '<div class="meta" style="font-size:.72rem;margin-top:.2rem">' . bg_h($note) . '</div></div>';
    }
    echo '</div>';
    echo '<p class="meta" style="margin:.75rem 0 0">' . bg_h(bg_t([
        'Blood type does not dictate personality. The notes below are population, evolutionary, and donor-system insights — not personal diagnoses.',
        'रक्त समूह व्यक्तित्व तय नहीं करता। नीचे जनसंख्या, विकासवादी व दाता-प्रणाली अंतर्दृष्टि है — व्यक्तिगत निदान नहीं।',
    ])) . '</p></div>';
}

function bg_render_action_plan(): void {
    echo '<div class="card">';
    echo '<h2>📋 ' . bg_h(bg_t(['Awareness action plan', 'जागरूकता कार्य-योजना'])) . '</h2>';
    echo '<ul style="margin:.35rem 0 0;padding-left:1.2rem;line-height:1.55">';
    $items = [
        ['Know your ABO + Rh and keep it on your digital profile / emergency medical ID.', 'अपना ABO + Rh जानें और डिजिटल प्रोफ़ाइल / आपात मेडिकल ID पर रखें।'],
        ['Rh-negative groups are scarce in India — consider registering with hospital donor registries or apps for critical calls.', 'Rh-नेगेटिव भारत में दुर्लभ — आपात कॉल के लिए अस्पताल रजिस्ट्री/ऐप पर पंजीकरण सोचें।'],
        ['Support local blood banks; regular donation (when eligible) beats one-off campaigns.', 'स्थानीय रक्त बैंकों का समर्थन करें; पात्र होने पर नियमित दान अभियानों से बेहतर।'],
        ['Prefer balanced whole foods, hydration, and sensible exercise over restrictive “blood-type diet” fads.', 'प्रतिबंधात्मक “ब्लड-टाइप डाइट” फैशन से बेहतर संतुलित भोजन, जल व व्यायाम।'],
    ];
    foreach ($items as $it) {
        echo '<li style="margin:.4rem 0">' . bg_h(bg_t($it)) . '</li>';
    }
    echo '</ul></div>';
}

function bg_load_team(): array {
    if (class_exists('AppDB') && method_exists('AppDB', 'read')) {
        $rows = AppDB::read('team');
        return is_array($rows) ? $rows : [];
    }
    $paths = [
        (defined('DATA_PATH') ? DATA_PATH : '') . '/team.json',
        BASE_PATH . '/data/team.json',
    ];
    foreach ($paths as $path) {
        if ($path && is_file($path)) {
            $j = json_decode((string)file_get_contents($path), true);
            if (is_array($j)) {
                return $j;
            }
        }
    }
    return [];
}

function bg_members_with_group(array $team): array {
    $out = [];
    foreach ($team as $m) {
        if (!is_array($m)) {
            continue;
        }
        $g = bg_norm_group((string)($m['blood_group'] ?? $m['blood'] ?? ''));
        $name = trim((string)($m['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $out[] = [
            'name' => $name,
            'slug' => (string)($m['slug'] ?? $m['id'] ?? ''),
            'designation' => (string)($m['designation_name'] ?? $m['designation'] ?? ''),
            'group' => $g,
            'group_raw' => (string)($m['blood_group'] ?? $m['blood'] ?? ''),
            'photo' => (string)($m['photo'] ?? ''),
        ];
    }
    usort($out, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}

function bg_css(): void {
    echo <<<'CSS'
<style>
:root{--bg-ink:#0f172a;--bg-muted:#64748b;--bg-card:#fff;--bg-line:#e2e8f0;--bg-accent:#be123c;--bg-ok:#15803d;--bg-warn:#b45309}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,sans-serif;background:linear-gradient(165deg,#0f172a 0%,#1e1b4b 40%,#4c0519 100%);color:var(--bg-ink);min-height:100vh}
.wrap{max-width:960px;margin:0 auto;padding:1rem 1.1rem 3rem}
.card{background:var(--bg-card);border:1px solid var(--bg-line);border-radius:16px;padding:1.1rem 1.2rem;margin:0 0 1rem;box-shadow:0 8px 28px rgba(15,23,42,.18)}
h1,h2{margin:0 0 .5rem;color:#9f1239}
.meta{color:var(--bg-muted);font-size:.85rem}
.chrome{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;justify-content:space-between;margin:0 0 1rem;padding:.65rem .85rem;background:rgba(255,241,242,.95);border:1.5px solid #fb7185;border-radius:14px}
.chrome-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.45rem .85rem;border-radius:999px;border:1px solid #e11d48;background:#fff1f2;color:#9f1239;font-weight:800;font-size:.78rem;text-decoration:none;cursor:pointer}
.chrome-btn.pri{background:linear-gradient(135deg,#e11d48,#be123c);color:#fff;border-color:#9f1239}
.badge{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.72rem;font-weight:800}
.badge.ok{background:#dcfce7;color:#166534;border:1px solid #86efac}
.badge.warn{background:#ffedd5;color:#9a3412;border:1px solid #fdba74}
.badge.muted{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
@media(max-width:720px){.grid2{grid-template-columns:1fr}}
.trait{display:flex;gap:.5rem;align-items:flex-start;padding:.4rem 0;border-bottom:1px dashed #fecdd3}
.trait:last-child{border-bottom:0}
.hero-group{font-size:2.4rem;font-weight:900;color:#9f1239;letter-spacing:.02em}
table.compat{width:100%;border-collapse:collapse;font-size:.78rem}
table.compat th,table.compat td{border:1px solid #e2e8f0;padding:.35rem .4rem;text-align:center}
table.compat th{background:#fff1f2;color:#9f1239;font-weight:800}
table.compat td.yes{background:#dcfce7;color:#14532d;font-weight:800}
table.compat td.no{background:#f8fafc;color:#94a3b8}
table.compat td.self{background:#e0e7ff;color:#3730a3;font-weight:800}
.member-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .65rem;border-radius:999px;background:#fdf2f8;border:1px solid #fbcfe8;margin:.2rem;font-size:.8rem;font-weight:700;text-decoration:none;color:#9f1239}
.disclaimer{font-size:.75rem;color:#64748b;border-top:1px solid #e2e8f0;margin-top:1rem;padding-top:.75rem}
.bg-chart-wrap{background:#fff5f5;border:1px solid #fecdd3;border-radius:16px;padding:1rem 1rem 1.1rem;margin:0 0 1rem}
.bg-chart-title{text-align:center;font-size:1.15rem;font-weight:800;color:#0f172a;margin:0 0 .25rem}
.bg-chart-title span,.bg-chart-title{/* Blood Types in red via nested */}
.bg-donor-label{text-align:center;font-weight:800;color:#e11d48;font-size:.9rem;margin:0 0 .35rem}
.bg-chart-frame{display:flex;flex-direction:column;align-items:center}
.bg-chart-row{display:flex;align-items:center;justify-content:center;gap:.35rem;max-width:100%}
.bg-recip-label{writing-mode:vertical-rl;transform:rotate(180deg);font-weight:800;color:#e11d48;font-size:.85rem;flex-shrink:0}
table.bg-std-chart{border-collapse:separate;border-spacing:5px;font-size:.78rem;margin:0 auto}
table.bg-std-chart th,table.bg-std-chart td{text-align:center;vertical-align:middle}
table.bg-std-chart .corner{width:1.2rem;border:none;background:transparent}
table.bg-std-chart .axis-label{font-weight:800;color:#e11d48;font-size:.85rem;padding-bottom:.15rem}
table.bg-std-chart .rh-label{writing-mode:vertical-rl;transform:rotate(180deg);font-weight:800;color:#e11d48;font-size:.85rem;padding:0 .2rem;border:none;background:transparent}
table.bg-std-chart .dh,table.bg-std-chart .rh{font-weight:800;color:#0f172a;padding:.35rem;min-width:2.4rem}
table.bg-std-chart .cell{width:2.5rem;height:2.5rem;border-radius:10px;background:#fff;border:1px solid #fce7f3}
table.bg-std-chart .cell.yes{background:#fff}
table.bg-std-chart .drop{display:inline-flex;align-items:center;justify-content:center;width:1.85rem;height:1.85rem;border-radius:50% 50% 50% 50%/60% 60% 40% 40%;background:#e11d48;color:#fff;font-size:.7rem;font-weight:900;box-shadow:0 2px 6px rgba(225,29,72,.35)}
table.bg-std-chart .empty{display:block;width:1.5rem;height:1.5rem;margin:0 auto;border-radius:8px;background:#fff}
table.bg-std-chart .hi-col,table.bg-std-chart .hi-row .rh{background:#ffe4e6!important;color:#9f1239!important;border-radius:8px}
table.bg-std-chart tr.hi-row .cell{background:#fff1f2!important;box-shadow:inset 0 0 0 2px #fb7185}
table.bg-std-chart .cell.hi-band{background:#fff1f2!important}
table.bg-std-chart .cell.hi-cross{box-shadow:inset 0 0 0 3px #e11d48!important;background:#fecdd3!important}
table.bg-std-chart .team-col,table.bg-std-chart tr.team-row .rh{background:#fef3c7!important;border-radius:8px}
table.bg-std-chart tr.team-row .cell:not(.hi-band){background:#fffbeb}
.bg-legend{text-align:center;font-size:.8rem;color:#64748b;margin:.65rem 0 0}
.bg-legend .lg{display:inline-block;width:.85rem;height:.85rem;border-radius:4px;vertical-align:middle;margin-right:.25rem}
.bg-legend .lg.hi{background:#fb7185}
.bg-legend .lg.team{background:#fbbf24}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
@media print{
  body{background:#fff!important;color:#000}
  .chrome,.no-print{display:none!important}
  .card{box-shadow:none;border:1pt solid #333;break-inside:avoid}
  .bg-chart-wrap{break-inside:avoid;border:1pt solid #999}
  @page{size:A4 portrait;margin:15mm}
}
</style>
CSS;
}

function bg_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https://' : 'http://') . $host;
}

/**
 * @param array{name?:string,group?:string,slug?:string,photo?:string,designation?:string} $ctx
 */
function bg_header(string $title, array $ctx = []): void {
    $lang = bg_lang();
    $other = $lang === 'hi' ? 'en' : 'hi';
    $params = $_GET;
    $params['lang'] = $other;
    $toggle = 'blood_report.php?' . http_build_query($params);

    $base = bg_base_url();
    $reqPath = (string)($_SERVER['REQUEST_URI'] ?? '/blood_report.php');
    // Canonical without fragment; prefer clean query for slug reports
    $canonQs = [];
    if (!empty($ctx['slug'])) {
        $canonQs['slug'] = (string)$ctx['slug'];
    }
    if (!empty($ctx['name'])) {
        $canonQs['name'] = (string)$ctx['name'];
    }
    if (!empty($ctx['group'])) {
        $canonQs['group'] = (string)$ctx['group'];
    }
    $canonQs['lang'] = $lang;
    $canonical = $base . '/blood_report.php' . ($canonQs !== [] ? ('?' . http_build_query($canonQs)) : '');

    $name = trim((string)($ctx['name'] ?? ''));
    $group = trim((string)($ctx['group'] ?? ''));
    $role = trim((string)($ctx['designation'] ?? ''));
    $desc = $name !== ''
        ? ($name . ($group !== '' ? ' · ' . $group : '') . ' — ' . (bg_is_hi()
            ? 'रक्त समूह जागरूकता रिपोर्ट (शैक्षिक)। अनुकूलता चार्ट व भारत-संदर्भ तथ्य। चिकित्सकीय सलाह नहीं।'
            : 'Blood group awareness report (educational). Compatibility chart and India context. Not medical advice.'))
        : (bg_is_hi()
            ? 'रक्त समूह अनुकूलता, भारत वितरण व जागरूकता — शैक्षिक रिपोर्ट।'
            : 'Blood group compatibility, India distribution and awareness — educational report.');

    // OG image: member photo → dynamic SVG card
    $photo = trim((string)($ctx['photo'] ?? ''));
    $ogImg = '';
    if ($photo !== '') {
        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            $ogImg = $photo;
        } else {
            $ogImg = $base . '/images/' . rawurlencode(basename($photo));
        }
    }
    if ($ogImg === '') {
        $ogImg = $base . '/tools/og_card.php?' . http_build_query([
            'name' => $name !== '' ? $name : 'Blood Report',
            'role' => $group !== '' ? ('Blood · ' . $group) : ($role !== '' ? $role : 'Blood group awareness'),
            'company' => 'Resource Centre',
            'kind' => bg_is_hi() ? 'ब्लड रिपोर्ट' : 'Blood Report',
        ]);
    }

    $robots = (!empty($ctx['slug']) || $group !== '') ? 'index, follow' : 'noindex, follow';
    $locale = $lang === 'hi' ? 'hi_IN' : 'en_IN';
    $site = 'Resource Centre · Arthsathi Limited';

    echo '<!DOCTYPE html><html lang="' . ($lang === 'hi' ? 'hi-IN' : 'en-IN') . '"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . bg_h($title) . '</title>';
    echo '<meta name="description" content="' . bg_h($desc) . '">';
    echo '<meta name="robots" content="' . bg_h($robots) . '">';
    echo '<link rel="canonical" href="' . bg_h($canonical) . '">';
    // Open Graph
    echo '<meta property="og:type" content="profile">';
    echo '<meta property="og:site_name" content="' . bg_h($site) . '">';
    echo '<meta property="og:locale" content="' . bg_h($locale) . '">';
    echo '<meta property="og:title" content="' . bg_h($title) . '">';
    echo '<meta property="og:description" content="' . bg_h($desc) . '">';
    echo '<meta property="og:url" content="' . bg_h($canonical) . '">';
    echo '<meta property="og:image" content="' . bg_h($ogImg) . '">';
    echo '<meta property="og:image:secure_url" content="' . bg_h($ogImg) . '">';
    echo '<meta property="og:image:width" content="1200">';
    echo '<meta property="og:image:height" content="630">';
    echo '<meta property="og:image:alt" content="' . bg_h($title) . '">';
    // Twitter
    echo '<meta name="twitter:card" content="summary_large_image">';
    echo '<meta name="twitter:title" content="' . bg_h($title) . '">';
    echo '<meta name="twitter:description" content="' . bg_h($desc) . '">';
    echo '<meta name="twitter:image" content="' . bg_h($ogImg) . '">';
    echo '<meta name="twitter:url" content="' . bg_h($canonical) . '">';
    bg_css();
    echo '</head><body><div class="wrap">';
    echo '<div class="chrome no-print"><div style="font-weight:800;color:#9f1239">' . bg_h(bg_t(['Blood Report', 'ब्लड रिपोर्ट'])) . '</div><div style="display:flex;gap:.4rem;flex-wrap:wrap">';
    echo '<a class="chrome-btn" href="' . bg_h($toggle) . '">' . ($lang === 'hi' ? '🇬🇧 English' : '🇮🇳 हिन्दी') . '</a>';
    echo '<button type="button" class="chrome-btn pri" onclick="window.print()">' . bg_h(bg_t(['Print / PDF', 'प्रिंट / PDF'])) . '</button>';
    echo '<a class="chrome-btn" href="/?tab=team">' . bg_h(bg_t(['Team', 'टीम'])) . '</a>';
    echo '</div></div>';
}

function bg_footer(): void {
    echo '<p class="disclaimer">' . bg_h(bg_t([
        'Educational & workplace awareness only. Compatibility chart is a simplified textbook RBC model — never for clinical decisions. India prevalence figures are approximate averages. Physiological notes summarise published population/evolutionary themes, not personal medical advice. Site developer: Arthsathi Limited.',
        'केवल शैक्षिक व कार्यस्थल जागरूकता। अनुकूलता चार्ट सरलीकृत RBC मॉडल है — चिकित्सकीय निर्णय हेतु नहीं। भारत प्रसार आँकड़े अनुमानित औसत हैं। शारीरिक नोट्स जनसंख्या/विकासवादी विषय हैं, व्यक्तिगत सलाह नहीं। साइट डेवलपर: Arthsathi Limited।',
    ])) . '</p></div></body></html>';
}

// ── Resolve focus person ──────────────────────────────────────────
$team = bg_load_team();
$members = bg_members_with_group($team);
$slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['slug'] ?? ''));
$nameQ = trim((string)($_GET['name'] ?? ''));
$groupQ = bg_norm_group((string)($_GET['group'] ?? $_GET['blood'] ?? ''));

$focus = null;
if ($slug !== '') {
    foreach ($members as $m) {
        if ($m['slug'] === $slug || strcasecmp($m['slug'], $slug) === 0) {
            $focus = $m;
            break;
        }
    }
}
if ($focus === null && $nameQ !== '') {
    foreach ($members as $m) {
        if (strcasecmp($m['name'], $nameQ) === 0) {
            $focus = $m;
            break;
        }
    }
}
if ($focus === null && $groupQ !== '') {
    $focus = [
        'name' => $nameQ !== '' ? $nameQ : bg_t(['Guest profile', 'अतिथि प्रोफ़ाइल']),
        'slug' => '',
        'designation' => '',
        'group' => $groupQ,
        'group_raw' => $groupQ,
        'photo' => '',
    ];
}
if ($focus === null && $members !== []) {
    // pick first with a group
    foreach ($members as $m) {
        if ($m['group'] !== '') {
            $focus = $m;
            break;
        }
    }
}
if ($focus === null) {
    $focus = [
        'name' => bg_t(['Add blood group on team profiles', 'टीम प्रोफ़ाइल पर ब्लड ग्रुप जोड़ें']),
        'slug' => '',
        'designation' => '',
        'group' => '',
        'group_raw' => '',
        'photo' => '',
    ];
}

$g = $focus['group'] !== '' ? $focus['group'] : bg_norm_group($focus['group_raw']);
$lore = bg_lore($g);
$title = $focus['name'] . ' — ' . bg_t(['Blood Report', 'ब्लड रिपोर्ट']);
if ($g !== '') {
    $title = $focus['name'] . ' · ' . $g . ' — ' . bg_t(['Blood Report', 'ब्लड रिपोर्ट']);
}

bg_header($title, [
    'name' => (string)$focus['name'],
    'group' => (string)$g,
    'slug' => (string)($focus['slug'] ?? ''),
    'photo' => (string)($focus['photo'] ?? ''),
    'designation' => (string)($focus['designation'] ?? ''),
]);

// Hero
echo '<div class="card">';
echo '<div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center">';
echo '<div><div class="hero-group">' . bg_h($g !== '' ? $g : '—') . '</div>';
echo '<div class="meta">' . bg_h($lore['emoji'] . ' ' . $lore['title']) . '</div></div>';
echo '<div style="flex:1;min-width:12rem">';
echo '<h1 style="font-size:1.35rem;margin:0">' . bg_h($focus['name']) . '</h1>';
if ($focus['designation'] !== '') {
    echo '<p class="meta" style="margin:.25rem 0 0">' . bg_h($focus['designation']) . '</p>';
}
echo '</div></div></div>';

// Positive traits
echo '<div class="grid2">';
echo '<div class="card"><h2>✨ ' . bg_h(bg_t(['Positive highlights', 'सकारात्मक झलक'])) . '</h2>';
foreach ($lore['traits'] as $tr) {
    echo '<div class="trait"><span aria-hidden="true">◆</span><span>' . bg_h($tr) . '</span></div>';
}
echo '</div>';
echo '<div class="card"><h2>🧠 ' . bg_h(bg_t(['Wit & workplace lore', 'विट व वर्कप्लेस लोर'])) . '</h2>';
foreach ($lore['wit'] as $w) {
    echo '<p style="margin:.4rem 0;font-size:.92rem;line-height:1.45">“' . bg_h($w) . '”</p>';
}
echo '</div></div>';

echo '<div class="card"><h2>📎 ' . bg_h(bg_t(['India & system facts', 'भारत व प्रणाली तथ्य'])) . '</h2>';
if (!empty($lore['india_share'])) {
    echo '<p style="margin:0 0 .5rem;font-weight:800;color:#9f1239">' . bg_h($lore['india_share']) . '</p>';
}
echo '<ul style="margin:.35rem 0 0;padding-left:1.2rem">';
foreach ($lore['fun'] as $f) {
    echo '<li style="margin:.35rem 0;line-height:1.45">' . bg_h($f) . '</li>';
}
echo '</ul></div>';

bg_render_india_context();
bg_render_action_plan();

// Standard chart (focus row + column highlighted)
$focusChart = bg_chart_type($g);
$teamTypeList = [];
foreach ($members as $_m) {
    if (!empty($_m['group'])) {
        $teamTypeList[] = $_m['group'];
    }
}
echo '<div class="card" style="padding:0;overflow:hidden;border:none;background:transparent;box-shadow:none">';
bg_render_standard_chart($focusChart, $teamTypeList);
echo '</div>';

// Team compatibility person matrix
$withGroup = array_values(array_filter($members, static fn($m) => $m['group'] !== ''));
echo '<div class="card"><h2>🩸 ' . bg_h(bg_t(['Team blood map', 'टीम ब्लड मैप'])) . '</h2>';

if ($withGroup === []) {
    echo '<p class="meta">' . bg_h(bg_t([
        'No blood groups on file yet. Edit team members and set Blood group (e.g. B+).',
        'अभी कोई ब्लड ग्रुप दर्ज नहीं। टीम सदस्य संपादित कर ब्लड ग्रुप (जैसे B+) जोड़ें।',
    ])) . '</p>';
} else {
    echo '<p class="meta" style="margin:0 0 .75rem">' . bg_h(bg_t([
        'Green = simplified “can donate to” (textbook RBC model). Not for medical use.',
        'हरा = सरलीकृत “किसे डोनेट कर सकता है” (पाठ्य मॉडल)। चिकित्सकीय उपयोग नहीं।',
    ])) . '</p>';

    // Pills
    echo '<div style="margin:0 0 1rem">';
    foreach ($withGroup as $m) {
        $href = 'blood_report.php?slug=' . rawurlencode($m['slug']) . '&lang=' . bg_lang();
        $isFocus = ($focus['slug'] !== '' && $m['slug'] === $focus['slug']) || ($m['name'] === $focus['name'] && $m['group'] === $g);
        echo '<a class="member-pill" href="' . bg_h($href) . '" style="' . ($isFocus ? 'outline:2px solid #e11d48' : '') . '">';
        echo bg_h($m['name']) . ' <span class="badge muted">' . bg_h($m['group']) . '</span></a>';
    }
    echo '</div>';

    // Matrix (limit size for readability)
    $matrix = array_slice($withGroup, 0, 24);
    if (count($withGroup) > 24) {
        echo '<p class="meta">' . bg_h(bg_t(['Showing first 24 members with groups.', 'ग्रुप वाले पहले 24 सदस्य।'])) . '</p>';
    }
    echo '<div style="overflow-x:auto"><table class="compat"><thead><tr><th>' . bg_h(bg_t(['Donor \\ Recipient', 'दाता \\ प्राप्तकर्ता'])) . '</th>';
    foreach ($matrix as $col) {
        echo '<th title="' . bg_h($col['name']) . '">' . bg_h((function_exists('mb_substr') ? mb_substr($col['name'], 0, 1, 'UTF-8') : substr($col['name'], 0, 1)) . '·' . $col['group']) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($matrix as $row) {
        echo '<tr><th style="text-align:left">' . bg_h($row['name'] . ' · ' . $row['group']) . '</th>';
        foreach ($matrix as $col) {
            if ($row['slug'] === $col['slug'] && $row['slug'] !== '') {
                echo '<td class="self">●</td>';
                continue;
            }
            $ok = bg_can_donate_to($row['group'], $col['group']);
            echo $ok ? '<td class="yes">✓</td>' : '<td class="no">·</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';

    // Highlight matches for focus person
    if ($g !== '') {
        $canGive = [];
        $canTake = [];
        foreach ($withGroup as $m) {
            if ($m['name'] === $focus['name'] && $m['group'] === $g) {
                continue;
            }
            if (bg_can_donate_to($g, $m['group'])) {
                $canGive[] = $m['name'] . ' (' . $m['group'] . ')';
            }
            if (bg_can_receive_from($g, $m['group'])) {
                $canTake[] = $m['name'] . ' (' . $m['group'] . ')';
            }
        }
        echo '<div class="grid2" style="margin-top:1rem">';
        echo '<div><h3 style="font-size:.9rem;color:#9f1239">' . bg_h(bg_t(['Focus can donate to (model)', 'फ़ोकस डोनेट कर सकता है (मॉडल)'])) . '</h3>';
        echo $canGive === [] ? '<p class="meta">—</p>' : '<p style="font-size:.85rem;line-height:1.5">' . bg_h(implode(' · ', array_slice($canGive, 0, 12))) . '</p>';
        echo '</div><div><h3 style="font-size:.9rem;color:#9f1239">' . bg_h(bg_t(['Focus can receive from (model)', 'फ़ोकस प्राप्त कर सकता है (मॉडल)'])) . '</h3>';
        echo $canTake === [] ? '<p class="meta">—</p>' : '<p style="font-size:.85rem;line-height:1.5">' . bg_h(implode(' · ', array_slice($canTake, 0, 12))) . '</p>';
        echo '</div></div>';
    }
}
echo '</div>';

// Quick picker if many members
echo '<div class="card no-print"><h2>' . bg_h(bg_t(['Open another member', 'अन्य सदस्य खोलें'])) . '</h2>';
echo '<form method="get" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">';
echo '<input type="hidden" name="lang" value="' . bg_h(bg_lang()) . '">';
echo '<select name="slug" style="flex:1;min-width:12rem;padding:.5rem;border-radius:10px;border:1px solid #e2e8f0">';
echo '<option value="">' . bg_h(bg_t(['Select team member…', 'टीम सदस्य चुनें…'])) . '</option>';
foreach ($members as $m) {
    $sel = ($focus['slug'] !== '' && $m['slug'] === $focus['slug']) ? ' selected' : '';
    $label = $m['name'] . ($m['group'] !== '' ? ' · ' . $m['group'] : ' · —');
    echo '<option value="' . bg_h($m['slug']) . '"' . $sel . '>' . bg_h($label) . '</option>';
}
echo '</select>';
echo '<button type="submit" class="chrome-btn pri">' . bg_h(bg_t(['Open', 'खोलें'])) . '</button>';
echo '</form>';
echo '<p class="meta" style="margin-top:.65rem">' . bg_h(bg_t([
    'Tip: set Blood group on each team card (A+, B−, O+, AB+…). Direct link: blood_report.php?slug=member-slug',
    'सुझाव: प्रत्येक टीम कार्ड पर ब्लड ग्रुप भरें। सीधा लिंक: blood_report.php?slug=member-slug',
])) . '</p></div>';

bg_footer();
