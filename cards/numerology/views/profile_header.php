<?php // profile_header.php — Version: 260921.03
// Executive-level profile + Key Metrics grid. All core data preserved.
if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit;

$isHi = ($lang === 'hi');
$gRaw = strtolower(trim((string)($p['gender'] ?? '')));
$genderLabel = htmlspecialchars(
    $isHi
        ? (($gRaw === 'male' || $gRaw === 'm') ? 'पुरुष' : (($gRaw === 'female' || $gRaw === 'f') ? 'महिला' : (string)($p['gender'] ?? 'अन्य')))
        : (string)($p['gender'] ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$driverName = htmlspecialchars(
    (string)($isHi
        ? ($report['lucky_driver']['hi_name'] ?? $report['lucky_driver']['name'] ?? '')
        : ($report['lucky_driver']['name'] ?? '')),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$coreNums = [
    ['key' => 'driver',      'label' => $isHi ? 'चालक' : 'Driver',       'val' => $core['driver'] ?? 0,                              'tone' => 'amber'],
    ['key' => 'conductor',   'label' => $isHi ? 'जीवन पथ' : 'Conductor',  'val' => $core['conductor'] ?? 0,                           'tone' => 'violet'],
    ['key' => 'expression',  'label' => $isHi ? 'अभिव्यक्ति' : 'Expression','val' => $report['name_matrix']['full']['root'] ?? 0,        'tone' => 'sky'],
    ['key' => 'soul',        'label' => $isHi ? 'आत्म प्रेरणा' : 'Soul Urge','val' => $report['name_matrix']['soul_urge']['root'] ?? 0,  'tone' => 'rose'],
    ['key' => 'personality', 'label' => $isHi ? 'व्यक्तित्व' : 'Personality','val' => $report['name_matrix']['personality']['root'] ?? 0,'tone' => 'emerald'],
    ['key' => 'maturity',    'label' => $isHi ? 'परिपक्वता' : 'Maturity',  'val' => $report['maturity']['number'] ?? 0,               'tone' => 'blue'],
];
?>

    <header class="nr-hero" aria-label="<?= $isHi ? 'प्रोफ़ाइल और मुख्य मीट्रिक' : 'Profile and key metrics' ?>">
        <div class="nr-hero-top">
            <?php if (!empty($absPhoto)): ?>
            <button type="button" class="nr-avatar sparkle-wrap photo-wrap" @click="openPhoto = true"
                    title="<?= $isHi ? 'बड़ा देखें' : 'Click to enlarge' ?>" aria-label="<?= $isHi ? 'फ़ोटो बड़ा करें' : 'Enlarge photo' ?>">
                <span class="sp1" aria-hidden="true"></span><span class="sp2" aria-hidden="true"></span><span class="sp3" aria-hidden="true"></span>
                <img src="<?= htmlspecialchars((string)$absPhoto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                     width="88" height="88" fetchpriority="high" decoding="async"
                     alt="<?= htmlspecialchars((string)$p['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                     data-lightbox-src="<?= htmlspecialchars((string)$absPhoto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                     data-lightbox-name="<?= htmlspecialchars((string)$p['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </button>
            <div x-show="openPhoto"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-90"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-90"
                 @click="openPhoto = false"
                 @keydown.escape.window="openPhoto = false"
                 class="fixed inset-0 z-[200] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 print:hidden"
                 style="display:none" role="dialog" aria-modal="true">
                <div class="relative max-w-sm w-full" @click.stop>
                    <img src="<?= htmlspecialchars((string)$absPhoto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                         class="photo-lightbox-img shadow-2xl border-4 border-white/20 object-contain"
                         width="320" height="320" loading="lazy" decoding="async"
                         alt="<?= htmlspecialchars((string)$p['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <div class="mt-3 text-center">
                        <div class="text-white font-black text-lg"><?= htmlspecialchars((string)$p['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        <div class="text-white/60 text-xs mt-0.5"><?= htmlspecialchars((string)$p['dob'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · Driver <?= (int)$core['driver'] ?></div>
                    </div>
                    <button type="button" @click="openPhoto = false"
                            class="absolute -top-3 -right-3 w-8 h-8 bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white rounded-full flex items-center justify-center text-sm shadow-xl"
                            aria-label="Close">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                    <div class="mt-3 flex justify-center print:hidden">
                        <button type="button"
                                @click="PhotoLightbox._save('<?= htmlspecialchars((string)$absPhoto, ENT_QUOTES) ?>', '<?= preg_replace('/[^a-zA-Z0-9 ]/', '', ($p['name'] ?? 'photo')) . '.jpg' ?>', $el)"
                                class="flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow">
                            <i class="fa-solid fa-download text-[10px]" aria-hidden="true"></i> Save As
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="nr-avatar nr-avatar-fallback sparkle-wrap" aria-hidden="true">
                <span class="sp1"></span><span class="sp2"></span><span class="sp3"></span>
                <span class="nr-avatar-initial"><?= mb_strtoupper(mb_substr((string)$p['name'], 0, 1)) ?></span>
            </div>
            <?php endif; ?>

            <div class="nr-identity min-w-0 flex-1">
                <div class="print-profile-line mb-1">
                    <span class="font-black text-slate-900"><?= htmlspecialchars((string)$p['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <span class="sep">·</span>
                    <span><?= htmlspecialchars((string)$p['dob'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <span class="sep">·</span>
                    <span><?= $genderLabel ?></span>
                    <span class="sep">·</span>
                    <span class="text-indigo-700"><?= htmlspecialchars($driverName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <p class="nr-eyebrow print:hidden"><?= $isHi ? 'वैदिक अंकशास्त्र रिपोर्ट' : 'Personal Intelligence Report' ?></p>
                <h1 class="nr-name print:hidden"><?= htmlspecialchars((string)$p['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
                <?php if (!empty($validationReport['status'])):
                    $_vs = (string)($validationReport['status'] ?? 'Valid');
                    $_sc = (int)($validationReport['score'] ?? 100);
                    $_vc = match ($_vs) {
                        'Perfect' => 'nr-badge-perfect',
                        'Valid'   => 'nr-badge-valid',
                        'Risky'   => 'nr-badge-risky',
                        default   => 'nr-badge-fail',
                    };
                ?>
                <div class="mt-2 print:hidden">
                    <span class="nr-badge <?= $_vc ?>"
                          title="<?= htmlspecialchars(implode('; ', array_merge($validationReport['errors'] ?? [], $validationReport['warnings'] ?? [])), ENT_QUOTES) ?>">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        Validator · <?= htmlspecialchars($_vs, ENT_QUOTES) ?> · <?= $_sc ?>/100
                    </span>
                </div>
                <?php endif; ?>
                <ul class="nr-meta nr-meta-a11y print:hidden" aria-label="Profile details">
                    <li><i class="fa-solid fa-calendar" aria-hidden="true"></i> <?= htmlspecialchars((string)$p['dob'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                    <li><i class="fa-solid fa-venus-mars" aria-hidden="true"></i> <?= $genderLabel ?></li>
                    <li class="nr-meta-planet"><i class="fa-solid fa-circle-nodes" aria-hidden="true"></i> <?= htmlspecialchars($driverName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                </ul>
            </div>
        </div>

        <section class="nr-metrics" aria-label="<?= $isHi ? 'मुख्य मीट्रिक' : 'Key metrics' ?>">
            <h2 class="nr-section-label"><?= $isHi ? 'मुख्य मीट्रिक' : 'Key Metrics' ?></h2>
            <div class="nr-metrics-grid">
                <?php foreach ($coreNums as $cn):
                    $pRoot = AppNumeroEngine::baseRoot((int)$cn['val']);
                    $pName = $report['planetInfo'][$pRoot]['name'] ?? '';
                    if ($isHi) $pName = $report['planetInfo'][$pRoot]['hi_name'] ?? $pName;
                    $pName = trim(preg_replace('/\(.*?\)/', '', (string)$pName));
                    $pIcon = $report['planetInfo'][$pRoot]['icon'] ?? '';
                ?>
                <article class="nr-metric nr-metric-<?= htmlspecialchars($cn['tone'], ENT_QUOTES) ?>">
                    <div class="nr-metric-label"><?= $cn['label'] ?></div>
                    <div class="nr-metric-value"><?= (int)$cn['val'] ?></div>
                    <div class="nr-metric-planet" title="<?= htmlspecialchars($pName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <?php if ($pIcon !== ''): ?><span class="nr-metric-icon" aria-hidden="true"><?= htmlspecialchars($pIcon, ENT_QUOTES) ?></span><?php endif; ?>
                        <span><?= htmlspecialchars($pName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
    </header>
