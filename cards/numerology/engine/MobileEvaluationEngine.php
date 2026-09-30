<?php // Version: 260916.14
declare(strict_types=1);
/**
 * MobileEvaluationEngine.php — Arthsathi Mobile Evaluation Engine V1.0
 *
 * Single Source of Truth for all mobile number scoring across the application.
 * All three layers (MobileEngine::analyze, generateMobilePatterns ranking,
 * MobileRecommendationEngine::scoreRoot, and the JS front-end widget) MUST
 * route through evaluateMobile() / evaluate() so "suggested" and "scored"
 * numbers are always on the same weight scale.
 *
 * Weight table (mirrors mobileCalcScore() in num_template.php):
 *   Base                                        50
 *   Full-number root = driver                  +25
 *   Full-number root = friend                  +12
 *   Full-number root = neutral/enemy           -10
 *   Last-2 root = driver                       +15
 *   Last-2 root = friend                        +8
 *   Last-2 root = neutral/enemy                 -5
 *   Saturn/Rahu penalty (root ∈ {4,8}, driver ∉ {4,8})  -10
 *   Strength bonus (root ∈ {1,3,5,6,9})         +5
 *   Clamp to [0, 100]
 *
 * V1.0: Extracted from num_engine.php as a standalone dependency.
 *       Added evaluateMobile() as a public alias for evaluate() to
 *       satisfy Task 1 API requirements — both return identical payloads
 *       so existing callers of evaluate() need no change.
 *       scoreCandidate() preserved for MobileRecommendationEngine delegation.
 */

if (!defined('BASE_PATH')) {
    throw new RuntimeException('BASE_PATH not defined. MobileEvaluationEngine cannot execute safely.');
}

class MobileEvaluationEngine {

    // ─── Compatibility label thresholds ──────────────────────────────────────
    private const COMPAT_THRESHOLDS = [
        87 => 'Highly Supportive',
        60 => 'Friendly',
        45 => 'Neutral',
    ];

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * evaluateMobile() — canonical public entry point (Task 1).
     *
     * Delegates to evaluate() and returns the identical structured payload.
     * All new code should call evaluateMobile(). Existing callers of evaluate()
     * continue to work unchanged.
     *
     * @param string $mobile  Raw mobile string (7–10 digits; non-digits stripped)
     * @param int    $driver  Chaldean driver number (1–9)
     * @param array  $friends Friendly planet roots for this driver
     * @return array {
     *   display, compound, root,
     *   last2 (raw string e.g. "33"), last2Sum, last2Root,
     *   score (0–100), compatibility
     * }
     */
    public static function evaluateMobile(string $mobile, int $driver, array $friends): array {
        return self::evaluate($mobile, $driver, $friends);
    }

    /**
     * evaluate() — internal implementation (kept for backward compatibility).
     *
     * Evaluate any 6–10 digit mobile string and return a full structured result.
     * This is the canonical scoring function used everywhere.
     */
    public static function evaluate(string $mobile, int $driver, array $friends): array {
        $clean = preg_replace('/\D/', '', $mobile);
        if (strlen($clean) > 10) $clean = substr($clean, -10);

        if (empty($clean) || strlen($clean) < 6) {
            return [
                'display'       => 'N/A',
                'compound'      => 0,
                'root'          => 0,
                'last2'         => '00',
                'last2Sum'      => 0,
                'last2Root'     => 0,
                'score'         => 0,
                'compatibility' => 'Not Provided',
            ];
        }

        // Full-number digit sum & Chaldean root
        $sum  = array_sum(array_map('intval', str_split($clean)));
        $root = self::reduceChaldean($sum);

        // Last-2 compound (preserved as string e.g. "33") + its root
        $last2     = substr($clean, -2);
        $last2Sum  = array_sum(array_map('intval', str_split($last2)));
        $last2Root = self::reduceChaldean($last2Sum);

        // ── Score calculation — identical weight table to JS mobileCalcScore() ──
        $score = 50;

        // Full-root alignment
        if ($root === $driver)                             $score += 25;
        elseif (in_array($root, $friends, true))           $score += 12;
        else                                               $score -= 10;

        // Last-2 root alignment
        if ($last2Root === $driver)                        $score += 15;
        elseif (in_array($last2Root, $friends, true))      $score += 8;
        else                                               $score -= 5;

        // Saturn/Rahu stability penalty
        if (in_array($root, [4, 8], true) && !in_array($driver, [4, 8], true)) {
            $score -= 10;
        }

        // Strength bonus
        if (in_array($root, [1, 3, 5, 6, 9], true)) {
            $score += 5;
        }

        $score = max(0, min(100, $score));

        // Compatibility label
        $compat = AppNumeroEngine::getLang() === 'hi' ? 'चुनौतीपूर्ण' : 'Challenging';
        foreach (self::COMPAT_THRESHOLDS as $threshold => $label) {
            if ($score >= $threshold) {
                $compat = $label;
                break;
            }
        }

        return [
            'display'       => $clean,
            'compound'      => $sum,
            'root'          => $root,
            'last2'         => $last2,
            'last2Sum'      => $last2Sum,
            'last2Root'     => $last2Root,
            'score'         => $score,
            'compatibility' => $compat,
        ];
    }

    /**
     * scoreCandidate() — ranks a root integer for recommendation ordering.
     *
     * Mirrors the full evaluate() logic without a real number string.
     * Used by MobileRecommendationEngine::scoreRoot() via this central
     * weight table so recommendation ranking and displayed scores are always
     * on the same scale.
     *
     * Note: base score starts at 0 (not 50) because we're measuring relative
     * strength, not an absolute out-of-100 score.
     */
    public static function scoreCandidate(int $candidate, int $driver, array $friends): int {
        $score = 0;

        if ($candidate === $driver)                             $score += 25;
        elseif (in_array($candidate, $friends, true))           $score += 12;
        else                                                    $score -= 10;

        if (in_array($candidate, [4, 8], true) && !in_array($driver, [4, 8], true)) {
            $score -= 10;
        }

        if (in_array($candidate, [1, 3, 5, 6, 9], true)) {
            $score += 5;
        }

        return $score;
    }

    // ─── Internal helper ──────────────────────────────────────────────────────

    /**
     * Pure Chaldean reduction (no master-number preservation).
     * Duplicates AppNumeroEngine::reduceChaldean() so this class has
     * zero circular dependency on AppNumeroEngine during early boot.
     */
    private static function reduceChaldean(int $num): int {
        if ($num === 0) return 0;
        while ($num > 9) {
            $num = array_sum(str_split((string)$num));
        }
        return $num;
    }
}
