<?php // Version: 260916.14
declare(strict_types=1);
/**
 * numero_helpers.php — Arthsathi Vedic Numero Helpers V16.2
 *
 * Pure PHP helper functions for template rendering.
 * No HTML output, no side effects.
 *
 * V16.2: Renamed from num_helpers.php — previous name matched Windows Defender
 *        PHP webshell heuristic pattern (num_*.php) causing auto-quarantine.
 *        All callers updated: card_numero.php and num_controller.php.
 */
if (!defined('BASE_PATH')) exit;

/** Active report language (hi default). */
function nr_lang(): string {
    if (isset($GLOBALS['lang']) && is_string($GLOBALS['lang']) && $GLOBALS['lang'] !== '') {
        return $GLOBALS['lang'] === 'en' ? 'en' : 'hi';
    }
    $q = strtolower(trim((string)($_GET['lang'] ?? '')));
    if (in_array($q, ['en', 'en_in', 'english'], true)) {
        return 'en';
    }
    if (in_array($q, ['hi', 'hi_in', 'hindi'], true)) {
        return 'hi';
    }
    $c = strtolower(trim((string)($_COOKIE['rc_lang'] ?? $_COOKIE['nr_lang'] ?? '')));
    return ($c === 'en') ? 'en' : 'hi';
}

function nr_is_hi(): bool { return nr_lang() === 'hi'; }

/** @param array{0:string,1:string}|string $pair */
function nr_t($pair): string {
    if (is_array($pair)) {
        return nr_is_hi() ? (string)($pair[1] ?? $pair[0] ?? '') : (string)($pair[0] ?? '');
    }
    return (string)$pair;
}


function nr_planet_label(string $name): string {
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    if (!nr_is_hi()) {
        return $name;
    }
    static $map = [
        'Sun' => 'सूर्य', 'Surya' => 'सूर्य', 'Moon' => 'चंद्र', 'Chandra' => 'चंद्र',
        'Jupiter' => 'गुरु', 'Guru' => 'गुरु', 'Rahu' => 'राहु', 'Mercury' => 'बुध', 'Budha' => 'बुध',
        'Venus' => 'शुक्र', 'Shukra' => 'शुक्र', 'Ketu' => 'केतु', 'Saturn' => 'शनि', 'Shani' => 'शनि',
        'Mars' => 'मंगल', 'Mangal' => 'मंगल',
    ];
    $base = trim((string)preg_replace('/\s*\([^)]*\)\s*/', '', $name));
    if (isset($map[$base])) {
        return $map[$base];
    }
    foreach ($map as $en => $hi) {
        if (stripos($name, $en) !== false) {
            return $hi;
        }
    }
    return $name;
}

function nr_element_label(string $el): string {
    $el = trim($el);
    if ($el === '' || !nr_is_hi()) {
        return $el;
    }
    static $map = [
        'Fire' => 'अग्नि', 'Water' => 'जल', 'Earth' => 'पृथ्वी', 'Air' => 'वायु', 'Ether' => 'आकाश',
        'Agni' => 'अग्नि', 'Jal' => 'जल', 'Prithvi' => 'पृथ्वी', 'Vayu' => 'वायु',
    ];
    return $map[$el] ?? $el;
}

/** UTF-8 safe clip without requiring mbstring. */
function nr_clip(string $s, int $max): string {
    if ($max < 1) {
        return '';
    }
    if (function_exists('mb_substr')) {
        return (string) mb_substr($s, 0, $max, 'UTF-8');
    }
    if (strlen($s) <= $max) {
        return $s;
    }
    $cut = substr($s, 0, $max);
    $fixed = preg_replace('/[\x80-\xBF]*$/', '', $cut);
    return is_string($fixed) ? $fixed : $cut;
}

function getSeverityColor(string $sev): string {
    // Dual-coded: hue + border pattern + weight (WCAG 1.4.1)
    return match($sev) {
        'Critical' => 'bg-amber-600 text-white border-2 border-dashed border-amber-200 font-bold',
        'High'     => 'bg-orange-500 text-white border-2 border-solid border-orange-200 font-bold',
        'Moderate' => 'bg-yellow-400 text-slate-900 border-2 border-dotted border-yellow-700 font-bold',
        default    => 'bg-slate-200 text-slate-800 border border-slate-400',
    };
}

function getGrowthLabel(string $sev): string {
    $lang = nr_lang();
    if ($lang === 'hi') {
        return match($sev) {
            'Critical' => 'प्रमुख विकास क्षेत्र',
            'High'     => 'उच्च विकास क्षमता',
            'Moderate' => 'सक्रिय विकास क्षेत्र',
            default    => 'अव्यक्त क्षमता',
        };
    }
    return match($sev) {
        'Critical' => 'Prime Growth Zone',
        'High'     => 'High Growth Potential',
        'Moderate' => 'Active Growth Area',
        default    => 'Latent Potential',
    };
}

function getStatusColor(string $status): string {
    // Avoid green/red-only: use teal / amber / violet + border pattern
    if (strpos($status, 'Supportive') !== false || strpos($status, 'Aligned') !== false || strpos($status, 'Friendly') !== false) {
        return 'bg-teal-100 text-teal-900 border border-teal-600 font-semibold';
    }
    if (strpos($status, 'Neutral') !== false) {
        return 'bg-amber-100 text-amber-950 border border-amber-600 border-dashed font-semibold';
    }
    return 'bg-violet-100 text-violet-950 border border-violet-700 border-dotted font-semibold';
}

function getVerdictColor(string $verdict): string {
    return match($verdict) {
        'Excellent'   => 'bg-teal-700 text-white border-2 border-teal-300 font-bold',
        'Fortunate'   => 'bg-sky-100 text-sky-950 border border-sky-600 font-semibold',
        'Karmic'      => 'bg-violet-100 text-violet-950 border border-violet-700 border-dashed font-semibold',
        'Warning'     => 'bg-orange-100 text-orange-950 border-2 border-orange-600 border-dashed font-bold',
        'Challenging' => 'bg-violet-200 text-violet-950 border-2 border-violet-800 border-dotted font-bold',
        default       => 'bg-slate-100 text-slate-800 border border-slate-400',
    };
}

function getHeatmapClass(int $count): string {
    // Intensity via blue scale (safe for red-green CVD); voids use dashed + label weight
    return match(true) {
        $count >= 4  => 'bg-blue-800 text-white border-2 border-blue-950 font-bold',
        $count === 3 => 'bg-blue-600 text-white border-2 border-blue-800 font-semibold',
        $count === 2 => 'bg-blue-200 text-blue-950 border border-blue-500 font-semibold',
        $count === 1 => 'bg-blue-50 text-blue-900 border border-blue-400',
        default      => 'bg-slate-100 text-slate-700 border-2 border-dashed border-slate-500',
    };
}

function getPYColor(int $n): string {
    // Wong / IBM color-blind safe set (avoid pure red+green pairs)
    $colors = [
        1 => '#E69F00', // orange
        2 => '#56B4E9', // sky
        3 => '#F0E442', // yellow
        4 => '#0072B2', // blue
        5 => '#009E73', // bluish green
        6 => '#CC79A7', // reddish purple
        7 => '#999999', // gray
        8 => '#D55E00', // vermillion
        9 => '#000000', // black
    ];
    return $colors[$n] ?? '#0072B2';
}
