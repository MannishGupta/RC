<?php
// chaldean_mobile.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/chaldean_mobile.php — ChaldeaMobileEngine (strict Chaldean mobile validation)

// =========================================================================
// CHALDEAN MOBILE VALIDATION ENGINE V1.0
// Implements the complete Chaldean Mobile Number Suggestion & Analysis System
// as per the Arthsathi Vedic Numerology framework.
// =========================================================================
class ChaldeaMobileEngine {

    // ── Compatibility matrix: driver/conductor → best total sums ─────────
    private const BEST_SUMS = [
        1 => [1, 3, 5, 6],
        2 => [1, 3, 5],
        3 => [1, 3, 5, 9],
        4 => [1, 5, 6],
        5 => [1, 5, 6],
        6 => [1, 5, 6],
        7 => [1, 3, 5, 6],
        8 => [3, 5, 6],
        9 => [1, 3, 5],
    ];

    // ── Sums to strictly avoid per core number ────────────────────────────
    private const AVOID_SUMS = [
        1 => [4, 8],
        2 => [4, 8, 9],
        3 => [4, 6, 8],
        4 => [2, 4, 8, 9],
        5 => [2, 4, 8],
        6 => [3, 4, 8],
        7 => [4, 8, 9],
        8 => [1, 2, 4, 8, 9],
        9 => [2, 4, 6, 8],
    ];

    // ── Malefic last-2-digit pairs (int values) ───────────────────────────
    private const MALEFIC_PAIRS = [18, 81, 28, 82, 24, 42, 34, 43, 48, 84, 98, 89, 44, 88];

    // ── Malefic pair explanations ─────────────────────────────────────────
    private const MALEFIC_LABELS = [
        18 => ['yoga' => 'Ego Clash', 'desc' => 'Creates friction with superiors, delays in promotions, and unnecessary arguments.'],
        81 => ['yoga' => 'Ego Clash', 'desc' => 'Creates friction with superiors, delays in promotions, and unnecessary arguments.'],
        28 => ['yoga' => 'Vish Yoga', 'desc' => 'Leads to emotional depression, severe mental stress, and consistently delayed payments.'],
        82 => ['yoga' => 'Vish Yoga', 'desc' => 'Leads to emotional depression, severe mental stress, and consistently delayed payments.'],
        24 => ['yoga' => 'Grahan Yoga', 'desc' => 'Causes overthinking, illusions, mood swings, and deception by trusted individuals.'],
        42 => ['yoga' => 'Grahan Yoga', 'desc' => 'Causes overthinking, illusions, mood swings, and deception by trusted individuals.'],
        34 => ['yoga' => 'Guru Chandal Yoga', 'desc' => 'Results in poor financial judgment, loss of social reputation, and misguided decisions.'],
        43 => ['yoga' => 'Guru Chandal Yoga', 'desc' => 'Results in poor financial judgment, loss of social reputation, and misguided decisions.'],
        48 => ['yoga' => 'Intense Friction', 'desc' => 'Attracts sudden setbacks, legal hurdles, chronic delays, and accident-prone energies.'],
        84 => ['yoga' => 'Intense Friction', 'desc' => 'Attracts sudden setbacks, legal hurdles, chronic delays, and accident-prone energies.'],
        98 => ['yoga' => 'Aggression Clash', 'desc' => 'Causes hot tempers, physical accidents, litigation, and aggressive friction with others.'],
        89 => ['yoga' => 'Aggression Clash', 'desc' => 'Causes hot tempers, physical accidents, litigation, and aggressive friction with others.'],
        44 => ['yoga' => 'Amplified Illusion', 'desc' => 'Creates extreme sudden fluctuations, high instability, and unpredictable downfalls.'],
        88 => ['yoga' => 'Amplified Burden', 'desc' => 'Brings life to a standstill. Extreme delays, heavy burdens, and exceedingly slow progress.'],
    ];

    // ── Auspicious last-2-digit pairs ─────────────────────────────────────
    private const AUSPICIOUS_PAIRS = [15, 51, 56, 65, 35, 53, 16, 61, 33, 55, 66];

    // ── Auspicious pair explanations ──────────────────────────────────────
    private const AUSPICIOUS_LABELS = [
        15 => ['yoga' => 'Budhaditya Yoga', 'desc' => 'Combines leadership with sharp intellect. Excellent for business management and government work.'],
        51 => ['yoga' => 'Budhaditya Yoga', 'desc' => 'Combines leadership with sharp intellect. Excellent for business management and government work.'],
        56 => ['yoga' => 'Laxmi Narayan Energy', 'desc' => 'The ultimate combination for wealth, luxury, smooth cash flow, and media success.'],
        65 => ['yoga' => 'Laxmi Narayan Energy', 'desc' => 'The ultimate combination for wealth, luxury, smooth cash flow, and media success.'],
        35 => ['yoga' => 'Wisdom + Commerce', 'desc' => 'Perfect for consultants, bankers, and advisors. Converts knowledge into continuous wealth.'],
        53 => ['yoga' => 'Wisdom + Commerce', 'desc' => 'Perfect for consultants, bankers, and advisors. Converts knowledge into continuous wealth.'],
        16 => ['yoga' => 'Authority + Luxury', 'desc' => 'Brings high social status, magnetic personality, fame, and authority combined with material comfort.'],
        61 => ['yoga' => 'Authority + Luxury', 'desc' => 'Brings high social status, magnetic personality, fame, and authority combined with material comfort.'],
        33 => ['yoga' => 'Master Expansion', 'desc' => 'Amplifies wisdom, respect, and universal luck. Highly protective and expansive.'],
        55 => ['yoga' => 'Master Communication', 'desc' => 'Hyper-stimulates trade, rapid networking, quick business turnover, and sharp memory.'],
        66 => ['yoga' => 'Master Luxury', 'desc' => 'Attracts extreme material comfort, artistic success, and high-end luxury experiences.'],
    ];

    // ── Planet names ──────────────────────────────────────────────────────
    private const PLANETS = [
        1=>'Sun ☉', 2=>'Moon ☽', 3=>'Jupiter ♃', 4=>'Rahu ☊',
        5=>'Mercury ☿', 6=>'Venus ♀', 7=>'Ketu ☋', 8=>'Saturn ♄', 9=>'Mars ♂',
    ];

    /**
     * Strict Chaldean reduction — no master numbers preserved.
     */
    public static function reduce(int $n): int {
        if ($n < 0)  $n = abs($n); // normalise negative inputs
        if ($n === 0) return 0;        // 0 remains 0 (e.g. digit sum of '00')
        while ($n > 9) $n = array_sum(str_split((string)$n));
        return max(1, $n);
    }

    /**
     * Calculate Psychic (Driver) and Destiny (Conductor) from DOB.
     * Psychic = sum of day digits, reduced 1-9.
     * Destiny = sum of ALL DOB digits, reduced 1-9.
     */
    public static function coreFromDOB(string $dob): array {
        $ts = strtotime(str_replace('/', '-', $dob));
        if (!$ts) return ['psychic' => 0, 'destiny' => 0];
        $day    = (int)date('d', $ts);
        $dobStr = date('dmY', $ts);
        $psychic  = self::reduce((int)array_sum(str_split((string)$day)));
        $destSum  = 0;
        for ($i = 0; $i < strlen($dobStr); $i++) $destSum += (int)$dobStr[$i];
        $destiny  = self::reduce($destSum);
        return ['psychic' => $psychic, 'destiny' => $destiny];
    }

    /**
     * Complete validation of a 10-digit mobile number.
     *
     * Returns a detailed result array including:
     *   - chaldean_total, total_root
     *   - last2 (int), last2_str
     *   - last4 sequence check
     *   - psychic_ok, destiny_ok, universal_ok
     *   - malefic/auspicious pair data
     *   - recommendation level: Highly Recommended / Acceptable / Avoid
     *   - explanations[] — layman sentences for client display
     *   - chaldean_score (0-100)
     */
    public static function validate(string $mobile, int $psychic, int $destiny): array {
        $clean = preg_replace('/\D/', '', $mobile);
        if (strlen($clean) > 10) $clean = substr($clean, -10);
        $len = strlen($clean);
        if ($len < 10) {
            return ['valid' => false, 'error' => 'Number must be exactly 10 digits.', 'display' => $clean];
        }

        // ── Total sum ────────────────────────────────────────────────────
        $total = 0;
        for ($i = 0; $i < $len; $i++) $total += (int)$clean[$i];
        $totalRoot = self::reduce($total);
        $planet    = self::PLANETS[$totalRoot] ?? 'Unknown';

        // ── Last 2 digits ────────────────────────────────────────────────
        $last2Str = substr($clean, -2);
        $last2Int = (int)$last2Str;
        $last2Sum = (int)$last2Str[0] + (int)$last2Str[1];
        $last2Root= self::reduce($last2Sum);

        // ── Last 4 digits sequence ───────────────────────────────────────
        $last4 = substr($clean, -4);
        $last4Digits = array_map('intval', str_split($last4));
        $seqCheck = self::checkSequence($last4Digits);

        // ── Rule A: Total sum mutually friendly to psychic AND destiny ───
        $psychicBest  = self::BEST_SUMS[$psychic]  ?? [];
        $destinyBest  = self::BEST_SUMS[$destiny]  ?? [];
        $psychicOk    = in_array($totalRoot, $psychicBest, true);
        $destinyOk    = in_array($totalRoot, $destinyBest, true);
        $universalOk  = !in_array($totalRoot, [4, 8], true); // auto-reject 4 or 8

        // ── Rule B: Zero check ───────────────────────────────────────────
        $endsInZero   = ($last2Str[-1] === '0');
        $zeroCount    = substr_count($clean, '0');
        $zeroOk       = !$endsInZero && $zeroCount <= 2;

        // ── Rule C: Malefic pair ─────────────────────────────────────────
        $isMalefic    = in_array($last2Int, self::MALEFIC_PAIRS, true);
        $maleficData  = $isMalefic ? (self::MALEFIC_LABELS[$last2Int] ?? []) : null;

        // ── Rule D: Auspicious pair ──────────────────────────────────────
        $isAuspicious = in_array($last2Int, self::AUSPICIOUS_PAIRS, true);
        $auspiciousData = $isAuspicious ? (self::AUSPICIOUS_LABELS[$last2Int] ?? []) : null;

        // ── Rule E: Last-4 sequence ──────────────────────────────────────
        $sequenceOk   = ($seqCheck !== 'descending');

        // ── Recommendation level ─────────────────────────────────────────
        $highlyRecommended = $psychicOk && $destinyOk && $universalOk
                          && $zeroOk && !$isMalefic && $sequenceOk;
        $acceptable = $universalOk && ($psychicOk || $destinyOk)
                   && !$isMalefic && $zeroOk;

        $level = $highlyRecommended
            ? 'Highly Recommended'
            : ($acceptable ? 'Acceptable' : 'Avoid');

        // ── Chaldean score (0-100) ────────────────────────────────────────
        // Architecture: compatible with mobileCalcScore() JS formula at the core,
        // with Chaldean layer adding rule-based bonuses/penalties.
        $score = 50;
        if ($psychicOk)   $score += 15;
        if ($destinyOk)   $score += 15;
        if (!$universalOk) $score -= 30;  // 4 or 8 universal reject
        if ($isAuspicious) $score += 12;
        if ($isMalefic)    $score -= 20;
        if (!$zeroOk)      $score -= 8;
        if ($seqCheck === 'ascending') $score += 5;
        if ($seqCheck === 'descending') $score -= 10;
        $score = max(0, min(100, $score));

        // ── Client explanations ──────────────────────────────────────────
        $explanations = [];

        // Total sum explanation
        $explanations[] = "This number totals to {$totalRoot} ({$planet}), " .
            ($psychicOk && $destinyOk
                ? "which harmonises perfectly with both your Birth Number ({$psychic}) and Destiny Number ({$destiny})."
                : ($psychicOk ? "which aligns with your Birth Number ({$psychic}) but not your Destiny Number ({$destiny})."
                              : ($destinyOk ? "which aligns with your Destiny Number ({$destiny}) but not your Birth Number ({$psychic})."
                                           : "which does not harmonise with your core numbers.")));

        if (!$universalOk) {
            $explanations[] = "Warning: Any number totalling to 4 or 8 is universally avoided in Chaldean Numerology — this number totals {$totalRoot}.";
        }

        if ($isAuspicious && $auspiciousData) {
            $explanations[] = "This number ends in {$last2Str}, forming the {$auspiciousData['yoga']} yoga. {$auspiciousData['desc']}";
        }

        if ($isMalefic && $maleficData) {
            $explanations[] = "Caution: This number ends in {$last2Str} ({$maleficData['yoga']}). {$maleficData['desc']}";
        }

        if (!$zeroOk) {
            if ($endsInZero) $explanations[] = "This number ends in 0, which dissipates energy at the critical final position — avoid.";
            elseif ($zeroCount > 2) $explanations[] = "This number contains {$zeroCount} zeros — more than two zeros create energy voids.";
        }

        if ($seqCheck === 'ascending') {
            $explanations[] = "The final four digits flow upwards, symbolising continuous growth and progress in your career.";
        } elseif ($seqCheck === 'descending') {
            $explanations[] = "The final four digits are descending — this symbolises energy decline and is not recommended.";
        } elseif ($seqCheck === 'flat') {
            $explanations[] = "The final four digits are balanced and steady, indicating stability.";
        }

        return [
            'valid'           => true,
            'display'         => $clean,
            'chaldean_total'  => $total,
            'total_root'      => $totalRoot,
            'planet'          => $planet,
            'last2'           => $last2Str,
            'last2_int'       => $last2Int,
            'last2_root'      => $last2Root,
            'last2_planet'    => self::PLANETS[$last2Root] ?? 'Unknown',
            'last4_sequence'  => $seqCheck,
            'psychic'         => $psychic,
            'destiny'         => $destiny,
            'psychic_ok'      => $psychicOk,
            'destiny_ok'      => $destinyOk,
            'universal_ok'    => $universalOk,
            'zero_ok'         => $zeroOk,
            'is_malefic'      => $isMalefic,
            'malefic_data'    => $maleficData,
            'is_auspicious'   => $isAuspicious,
            'auspicious_data' => $auspiciousData,
            'sequence_ok'     => $sequenceOk,
            'chaldean_score'  => $score,
            'level'           => $level,
            'explanations'    => $explanations,
        ];
    }

    /**
     * Batch validate a list of mobile numbers.
     * Returns array sorted by chaldean_score descending.
     */
    public static function validateBatch(array $mobiles, int $psychic, int $destiny): array {
        $results = [];
        foreach ($mobiles as $mobile) {
            $r = self::validate((string)$mobile, $psychic, $destiny);
            if ($r['valid']) $results[] = $r;
        }
        usort($results, fn($a, $b) => ($b['chaldean_score'] ?? 0) - ($a['chaldean_score'] ?? 0));
        return $results;
    }

    /**
     * Sequence check: ascending/descending/flat for last-4 digits.
     * Rule: descending only when majority (≥2) of steps fall AND last digit ≤ first.
     *       Oscillating patterns (e.g. 9,8,9,8) are classified as flat.
     * Ascending: majority rise AND last ≥ first.
     */
    private static function checkSequence(array $d4): string {
        if (count($d4) < 4) return 'unknown';
        if ($d4[0] === $d4[1] && $d4[1] === $d4[2] && $d4[2] === $d4[3]) return 'flat';
        $drops = ($d4[1] < $d4[0] ? 1 : 0) + ($d4[2] < $d4[1] ? 1 : 0) + ($d4[3] < $d4[2] ? 1 : 0);
        $rises = ($d4[1] > $d4[0] ? 1 : 0) + ($d4[2] > $d4[1] ? 1 : 0) + ($d4[3] > $d4[2] ? 1 : 0);
        if ($drops >= 2 && $d4[3] <= $d4[0]) return 'descending'; // majority drops, ends lower
        if ($rises >= 2 && $d4[3] >= $d4[0]) return 'ascending';  // majority rises, ends higher
        return 'flat';
    }

    /**
     * Get the "Best Sums" array for a given core number (1-9).
     * Used by JS frontend for the Chaldean scoring display.
     */
    public static function getBestSums(int $n): array {
        return self::BEST_SUMS[$n] ?? [];
    }

    /**
     * Export all static data as JSON for JS frontend.
     */
    public static function getJsData(int $psychic, int $destiny): array {
        return [
            'psychic'        => $psychic,
            'destiny'        => $destiny,
            'psychicBest'    => self::BEST_SUMS[$psychic]  ?? [],
            'destinyBest'    => self::BEST_SUMS[$destiny]  ?? [],
            'psychicAvoid'   => self::AVOID_SUMS[$psychic] ?? [],
            'destinyAvoid'   => self::AVOID_SUMS[$destiny] ?? [],
            'maleficPairs'   => self::MALEFIC_PAIRS,
            'auspiciousPairs'=> self::AUSPICIOUS_PAIRS,
            'maleficLabels'  => self::MALEFIC_LABELS,
            'auspiciousLabels'=> self::AUSPICIOUS_LABELS,
            'planets'        => self::PLANETS,
        ];
    }
}
