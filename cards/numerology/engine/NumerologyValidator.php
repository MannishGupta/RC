<?php // Version: 260916.14
declare(strict_types=1);

/**
 * NumerologyValidator.php — Arthsathi Numerology Validation Engine V2.2
 *
 * Bug fixes vs V2.1:
 *  - FIX 1 (CRITICAL): checkCoreIntegrity now allows master-number conductors (11, 22).
 *                      Previously, any Life Path that reduced to 11 or 22 triggered a
 *                      false "violates strict 1–9 boundaries" error, causing
 *                      validation_failed for every master-number report.
 *  - FIX 2 (HIGH):     checkContradictions consolidated to a single harmony vocabulary
 *                      (Aligned / Supportive / Challenging) matching AppNumeroEngine::
 *                      evaluateHarmony(). The old dual-vocabulary system (one using
 *                      "Neutral" as a status that evaluateHarmony() never emits) caused
 *                      false-positive mismatch errors on valid reports.
 *  - FIX 3 (MINOR):    Soul Urge = 0 warning suppressed when the engine has already
 *                      applied its first-letter fallback — the warning only fires now
 *                      when the full compound itself is also 0 (genuinely empty name).
 */

if (!defined('BASE_PATH')) {
    throw new RuntimeException("BASE_PATH not defined. Validator cannot execute safely.");
}

class NumerologyValidator {

    /** Master numbers the engine preserves when keepMasters = true. */
    private const MASTER_NUMBERS = [11, 22];

    public static function validate(array $report): array {
        $state = [
            'errors'      => [],
            'warnings'    => [],
            'suggestions' => [],
            'conf'        => [
                'core'    => 100,
                'name'    => 100,
                'mobile'  => 100,
                'derived' => 100,
            ],
        ];

        self::checkCoreIntegrity($report, $state);
        self::checkNameLogic($report, $state);
        self::checkCrossSystemConsistency($report, $state);
        self::checkDerivedSystems($report, $state);
        self::checkContradictions($report, $state);

        $errorWeight   = 12;
        $warningWeight = 2;

        $score = 100
            - (count($state['errors'])   * $errorWeight)
            - (count($state['warnings']) * $warningWeight);
        $score = max(0, min(100, $score));

        $status = match (true) {
            $score === 100 => 'Perfect',
            $score >= 80   => 'Valid',
            $score >= 50   => 'Risky',
            default        => 'Critical Failure',
        };

        foreach ($state['conf'] as $key => $val) {
            $state['conf'][$key] = max(0, $val);
        }

        return [
            'score'                => $score,
            'status'               => $status,
            'errors'               => array_unique($state['errors']),
            'warnings'             => array_unique($state['warnings']),
            'confidence_map'       => $state['conf'],
            'auto_fix_suggestions' => array_unique($state['suggestions']),
        ];
    }

    // -------------------------------------------------------------------------
    // FIX 1: Accept 11 and 22 as valid conductor values.
    // The engine calculates conductor with keepMasters = true, so report['core']
    // ['conductor'] can legitimately be 11 or 22.
    // Driver is always reduced to 1–9 (baseDriverDigit) so its check stays as-is.
    // -------------------------------------------------------------------------
    private static function checkCoreIntegrity(array $report, array &$state): void {
        $driver    = $report['core']['driver']    ?? -1;
        $conductor = $report['core']['conductor'] ?? -1;

        if ($driver < 1 || $driver > 9) {
            $state['errors'][] = "Core Integrity: Driver ($driver) violates strict 1–9 boundaries.";
            $state['conf']['core'] -= 40;
        }

        $validConductor = ($conductor >= 1 && $conductor <= 9)
            || in_array($conductor, self::MASTER_NUMBERS, true);

        if (!$validConductor) {
            $state['errors'][] = "Core Integrity: Conductor ($conductor) is outside valid range (1–9, 11, 22).";
            $state['conf']['core'] -= 40;
        }
    }

    // -------------------------------------------------------------------------
    // FIX 3: Only warn about soul_urge = 0 when the name itself produced no
    // compound at all (fullC = 0), which signals a parsing/empty-name failure.
    // The engine applies a first-letter fallback that prevents soul_urge from
    // being 0 for any real name, so the warning was always noise.
    // -------------------------------------------------------------------------
    private static function checkNameLogic(array $report, array &$state): void {
        $nm = $report['name_matrix'] ?? [];
        if (empty($nm)) {
            $state['warnings'][] = "Name Logic: Matrix payload is empty or missing.";
            $state['conf']['name'] -= 20;
            return;
        }

        $fullC = $nm['full']['compound']       ?? 0;
        $fullR = $nm['full']['root']            ?? 0;
        $suC   = $nm['soul_urge']['compound']  ?? 0;
        $pC    = $nm['personality']['compound'] ?? 0;

        // FIX 3: Only warn when fullC is also 0 (truly empty/unparseable name).
        if ($suC === 0 && $fullC === 0) {
            $state['warnings'][] = "Soul Urge and Full compound are both zero — name appears empty or unparseable.";
            $state['conf']['name'] -= 10;
        }

        if ($fullC > 0 && $fullC < $fullR) {
            $state['errors'][] = "Name Logic: Compound ($fullC) is impossibly lower than Root ($fullR).";
            $state['conf']['name'] -= 30;
        }

        if ($fullC !== ($suC + $pC)) {
            $state['errors'][] = "Name Logic: Total Compound ($fullC) != Soul Urge ($suC) + Personality ($pC).";
            $state['suggestions'][] = "Re-evaluate vowel/consonant split mapping array.";
            $state['conf']['name'] -= 30;
        }

        $expectedRoot = self::pureReduce($fullC, false);
        if ($fullR !== $expectedRoot && $fullC > 0) {
            $state['errors'][] = "Name Logic: Declared Root ($fullR) breaks mathematical derivation from Compound ($fullC). Expected $expectedRoot.";
            $state['conf']['name'] -= 30;
        }
    }

    private static function checkCrossSystemConsistency(array $report, array &$state): void {
        $mobile = $report['mobile'] ?? [];
        if (!empty($mobile) && ($mobile['display'] ?? 'N/A') !== 'N/A') {
            $display    = $mobile['display'];
            $digitsOnly = preg_replace('/\D/', '', $display);

            if ($digitsOnly !== '') {
                $rawSum              = array_sum(str_split($digitsOnly));
                $expectedMobileRoot  = self::pureReduce($rawSum, false);
                $mRoot               = $mobile['root'] ?? 0;

                if ($expectedMobileRoot !== $mRoot) {
                    $state['errors'][]      = "Mobile root mismatch from raw digits. Display: $display, Expected: $expectedMobileRoot, Got: $mRoot.";
                    $state['suggestions'][] = "Recalculate mobile root directly from raw digit string.";
                    $state['conf']['mobile'] -= 50;
                }
            }
        }

        $missing = $report['grid']['missing'] ?? [];
        foreach ($missing as $m) {
            if ($m < 1 || $m > 9) {
                $state['errors'][] = "Cross-System: Grid void element ($m) is mathematically impossible.";
                $state['conf']['derived'] -= 20;
            }
        }

        if (count($missing) !== count(array_unique($missing))) {
            $state['warnings'][] = "Cross-System: Duplicate void elements detected in grid.";
            $state['conf']['derived'] -= 10;
        }
    }

    private static function checkDerivedSystems(array $report, array &$state): void {
        $pyNum = $report['personal_year']['number'] ?? 0;
        if ($pyNum !== 0 && ($pyNum < 1 || $pyNum > 9)) {
            $state['errors'][] = "Derived Systems: Personal Year ($pyNum) exhibits Master Number leakage. Must be 1–9.";
            $state['conf']['derived'] -= 30;
        }

        $pinnacles = $report['pinnacles']['pinnacles'] ?? [];
        $dobRaw    = $report['profile']['dob_raw']    ?? '';

        if (!empty($pinnacles) && count($pinnacles) >= 4 && $dobRaw) {
            $ts = strtotime($dobRaw);
            if ($ts) {
                $d    = (int)date('d', $ts);
                $m    = (int)date('m', $ts);
                $y    = date('Y', $ts);

                $rDay   = self::pureReduce($d, false);
                $rMonth = self::pureReduce($m, false);
                $ySum   = array_sum(str_split($y));
                $rYear  = self::pureReduce($ySum, false);

                $expectedP1 = self::pureReduce($rMonth + $rDay,              false);
                $expectedP2 = self::pureReduce($rDay   + $rYear,             false);
                $expectedP3 = self::pureReduce($expectedP1 + $expectedP2,    false);
                $expectedP4 = self::pureReduce($rMonth + $rYear,             false);

                $p1 = $pinnacles[0]['number'] ?? 0;
                $p2 = $pinnacles[1]['number'] ?? 0;
                $p3 = $pinnacles[2]['number'] ?? 0;
                $p4 = $pinnacles[3]['number'] ?? 0;

                if ($p1 !== $expectedP1 || $p2 !== $expectedP2 || $p3 !== $expectedP3 || $p4 !== $expectedP4) {
                    $state['errors'][]      = "Derived Systems: Pinnacle internal architecture is structurally broken vs raw DOB.";
                    $state['suggestions'][] = "Verify Pinnacle math: P1=M+D, P2=D+Y, P3=P1+P2, P4=M+Y.";
                    $state['conf']['derived'] -= 40;
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // FIX 2: Single harmony vocabulary — Aligned / Supportive / Challenging.
    //
    // Old code had two parallel computations ($expectedHarmony and $engineExpected)
    // using different status sets. $engineExpected emitted "Neutral" which
    // AppNumeroEngine::evaluateHarmony() never emits — causing false mismatches
    // whenever a name root was in the friends list but the compound verdict was
    // not Fortunate/Excellent (a very common situation).
    //
    // New logic: mirror exactly what evaluateHarmony() produces, then do one
    // clean comparison and one directional sanity check.
    // -------------------------------------------------------------------------
    private static function checkContradictions(array $report, array &$state): void {
        $harmonyStatus = $report['name_matrix']['harmony']['status'] ?? '';
        $nameRoot      = $report['name_matrix']['full']['root']      ?? 0;
        $driver        = $report['core']['driver']                   ?? 0;
        $friends       = $report['lucky_driver']['friends']          ?? [];

        // Mirror AppNumeroEngine::evaluateHarmony() exactly.
        $expectedHarmony = ($nameRoot === $driver)
            ? 'Aligned'
            : (in_array($nameRoot, $friends, true) ? 'Supportive' : 'Challenging');

        if ($harmonyStatus !== '' && $harmonyStatus !== $expectedHarmony) {
            $state['errors'][]      = "Harmony logic mismatch. Expected '$expectedHarmony', got '$harmonyStatus' (Name Root: $nameRoot, Driver: $driver).";
            $state['suggestions'][] = "Re-evaluate AppNumeroEngine::evaluateHarmony() call or verify friends array passed to report.";
            $state['conf']['name'] -= 30;
        }

        // Directional sanity: if harmony is positive, root must actually be friendly.
        if (in_array($harmonyStatus, ['Aligned', 'Supportive'], true)) {
            $isFriendlyOrDriver = ($nameRoot === $driver || in_array($nameRoot, $friends, true));
            if (!$isFriendlyOrDriver) {
                $state['errors'][] = "Contradiction: Harmony tagged '$harmonyStatus', but Name Root ($nameRoot) is mathematically hostile/neutral to Driver ($driver).";
                $state['conf']['name'] -= 30;
            }
        }
    }

    private static function pureReduce(int $num, bool $keepMasters): int {
        if ($num === 0) return 0;
        while ($num > 9) {
            if ($keepMasters && ($num === 11 || $num === 22)) break;
            $num = array_sum(str_split((string)$num));
        }
        return $num;
    }
}