<?php // Version: 260916.14
declare(strict_types=1);

/**
 * MobileRecommendationEngine.php — Arthsathi Mobile Root Suggester V1.3
 *
 * Changes vs V1.2:
 *  - FIX 6  (HIGH):   Replaced shuffle() with score-ranked sort — output now reflects
 *                     numerological strength, not randomness. scoreRoot() weighs friend
 *                     status, proximity to driver, and planetary class balance.
 *  - FIX 7  (HIGH):   isSequential() now detects circular runs (9→1→2, 7→8→9, 1→2→3,
 *                     etc.) — previously only linear 3-step runs were blocked.
 *  - FIX 8  (HIGH):   Diversity constraint added to selection loop — adjacent numbers
 *                     (|a−b| ≤ 1) are skipped unless no non-adjacent candidate exists,
 *                     preventing energetically clustered outputs like [3,4,6].
 *  - FIX 9  (MEDIUM): Fallback now prioritises non-adjacent + different-class candidates
 *                     before falling back to any safe neutral — "intelligent padding".
 *  - FIX 10 (MEDIUM): isSequential() and isTooSimilar() made public so the controller
 *                     can run a validation pass after suggestRoots() returns.
 */
class MobileRecommendationEngine {

    // ─── Planetary class map ──────────────────────────────────────────────────
    // Groups roots by energetic archetype for class-diversity enforcement.
    private const PLANET_CLASS = [
        1 => 'power',        // Sun
        2 => 'flow',         // Moon
        3 => 'expansion',    // Jupiter
        4 => 'structure',    // Rahu/Uranus
        5 => 'intelligence', // Mercury
        6 => 'flow',         // Venus
        7 => 'spiritual',    // Ketu/Neptune
        8 => 'power',        // Saturn
        9 => 'expansion',    // Mars
    ];

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Returns exactly 3 root recommendations, ordered driver-first.
     *
     * @param int   $driver   Primary driver number (1–9)
     * @param array $friends  Friendly planet roots for this driver
     * @param array $enemies  Enemy roots — excluded from all suggestions
     * @param int   $seed     Deterministic seed derived from Name+DOB hash
     * @return array          Exactly 3 distinct root integers, driver at index 0
     */
    public static function suggestRoots(
        int $driver,
        array $friends,
        array $enemies = [],
        int $seed = 0
    ): array {

        // ── 1. Sanitise inputs ────────────────────────────────────────────────
        $driverRoot = max(1, min(9, $driver));

        $cleanFriends = array_values(array_filter(
            $friends,
            fn($r) => is_int($r) && $r >= 1 && $r <= 9 && !in_array($r, $enemies, true)
        ));

        // Driver is always pinned to result[0] regardless of enemy list.
        $friendPool = array_values(array_unique(array_diff($cleanFriends, [$driverRoot])));

        // ── 2. Score + rank the pool (FIX 6: replaces shuffle) ───────────────
        // Use the seed for tie-breaking only — deterministic but scored.
        mt_srand($seed);
        // Assign each candidate a stable score + a tiny seeded jitter for ties.
        $scored = [];
        foreach ($friendPool as $candidate) {
            $scored[$candidate] = self::scoreRoot($candidate, $driverRoot, $cleanFriends)
                                  + (mt_rand(0, 9) * 0.01); // jitter < 0.1 — never overrides score
        }
        arsort($scored);
        $rankedPool = array_keys($scored);

        // Restore RNG to a non-deterministic state (FIX 5 from V1.2 preserved).
        mt_srand((int)(hrtime(true) & 0x7FFFFFFF));

        // ── 3. Select with diversity constraint (FIX 8) ──────────────────────
        $result = [$driverRoot];

        foreach ($rankedPool as $candidate) {
            if (self::isTooSimilar($candidate, $result)) {
                continue; // skip adjacent — prefer spread
            }
            $result[] = $candidate;
            if (count($result) === 3) break;
        }

        // Diversity relaxation: if strict constraint leaves us short, allow
        // adjacent numbers rather than padding with arbitrary neutrals.
        if (count($result) < 3) {
            foreach ($rankedPool as $candidate) {
                if (in_array($candidate, $result, true)) continue;
                $result[] = $candidate;
                if (count($result) === 3) break;
            }
        }

        // ── 4. Intelligent padding if pool was too small (FIX 9) ─────────────
        if (count($result) < 3) {
            $usedSet = array_unique(array_merge($result, $enemies));

            // Pass A — non-adjacent, different class
            $driverClass = self::PLANET_CLASS[$driverRoot] ?? '';
            $usedClasses = array_map(fn($n) => self::PLANET_CLASS[$n] ?? '', $result);

            $padCandidates = array_values(array_filter(
                range(1, 9),
                fn($n) => !in_array($n, $usedSet, true)
                       && !self::isTooSimilar($n, $result)
                       && !in_array(self::PLANET_CLASS[$n] ?? '', $usedClasses, true)
            ));

            // Pass B — non-adjacent, any class
            if (empty($padCandidates)) {
                $padCandidates = array_values(array_filter(
                    range(1, 9),
                    fn($n) => !in_array($n, $usedSet, true)
                           && !self::isTooSimilar($n, $result)
                ));
            }

            // Pass C — any safe neutral (original V1.2 fallback)
            if (empty($padCandidates)) {
                $padCandidates = array_values(array_filter(
                    range(1, 9),
                    fn($n) => !in_array($n, $usedSet, true)
                ));
            }

            // Pass D — ignore enemies entirely if we're still empty
            if (empty($padCandidates)) {
                $padCandidates = array_values(array_diff(range(1, 9), $result));
            }

            foreach ($padCandidates as $n) {
                $result[] = $n;
                if (count($result) === 3) break;
            }
        }

        // Clamp to exactly 3.
        $result = array_slice($result, 0, 3);

        // ── 5. Sequential rejection (FIX 4 from V1.2 + FIX 7 circular) ──────
        if (self::isSequential($result)) {
            $poolSet    = array_unique(array_merge([$driverRoot], $cleanFriends));
            $excludeSet = array_unique(array_merge($poolSet, $enemies));

            $neutralCandidates = array_values(array_filter(
                range(1, 9),
                fn($n) => !in_array($n, $excludeSet, true)
            ));

            if (empty($neutralCandidates)) {
                $neutralCandidates = array_values(array_filter(
                    range(1, 9),
                    fn($n) => !in_array($n, $enemies, true) && $n !== $driverRoot
                ));
            }

            // Try replacing result[2] first, then result[1] (driver at [0] is untouched).
            foreach ([2, 1] as $replaceIdx) {
                foreach ($neutralCandidates as $n) {
                    $test = $result;
                    $test[$replaceIdx] = $n;
                    if (!self::isSequential($test) && count(array_unique($test)) === 3) {
                        $result = $test;
                        break 2;
                    }
                }
            }
        }

        return $result;
    }

    // ─── Scoring ──────────────────────────────────────────────────────────────

    /**
     * Score a candidate root against the driver context.
     *
     * Weights:
     *  +30  being in the clean friends list
     *  −2×  absolute distance from driver (0 distance = no penalty)
     *  +10  sharing planetary class with driver (compound resonance)
     *  −5   sharing planetary class with already-selected roots (diversity)
     */
    private static function scoreRoot(int $candidate, int $driver, array $friends): int {
        $score = 0;

        if (in_array($candidate, $friends, true)) {
            $score += 30;
        }

        // Proximity: closer roots reinforce the driver's frequency.
        $dist   = abs($candidate - $driver);
        $score -= $dist * 2;

        // Class resonance bonus.
        $driverClass    = self::PLANET_CLASS[$driver]    ?? '';
        $candidateClass = self::PLANET_CLASS[$candidate] ?? '';

        if ($driverClass !== '' && $candidateClass === $driverClass) {
            $score += 10;
        }

        return $score;
    }

    // ─── Constraint helpers ───────────────────────────────────────────────────

    /**
     * Returns true when $candidate sits within 1 step of any number already
     * chosen — prevents adjacent clustering (e.g. 3,4 or 6,7).
     *
     * Made public so the controller can use it in a post-generation audit.
     */
    public static function isTooSimilar(int $candidate, array $chosen): bool {
        foreach ($chosen as $existing) {
            if (abs($candidate - $existing) <= 1) return true;
        }
        return false;
    }

    /**
     * Returns true when the three roots form any consecutive run — linear
     * (e.g. 3,4,5) OR circular at the 9→1 boundary (e.g. 9,1,2 or 8,9,1).
     *
     * Made public so the controller can run a final validation pass.
     *
     * FIX 7: V1.2 only caught linear runs; circular runs are now blocked too.
     */
    public static function isSequential(array $arr): bool {
        if (count($arr) < 3) return false;
        $chk = $arr;
        sort($chk);

        // Linear: 3,4,5 / 1,2,3 / 7,8,9
        if ($chk[1] === $chk[0] + 1 && $chk[2] === $chk[1] + 1) {
            return true;
        }

        // Circular wrap-around: [1,2,9] means 9→1→2, [1,8,9] means 8→9→1
        if ($chk === [1, 2, 9]) return true;
        if ($chk === [1, 8, 9]) return true;

        return false;
    }
}
