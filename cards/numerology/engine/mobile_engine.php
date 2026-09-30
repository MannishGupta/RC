<?php
// mobile_engine.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/mobile_engine.php — MobileEngine (Vedic pattern scoring & observation)

class MobileEngine {

    /**
     * Unified Vedic Mobile Evaluation — Single source of truth.
     * This method is used for: active number display, "Try Any" analysis,
     * and recommendation ranking. Identical to mobileCalcScore() in JS.
     *
     * Scoring model (max 100):
     *   Base 50 pts — always
     *   +25 root === driver
     *   +12 root in friends
     *   +15 last-2-root === driver
     *   +8  last-2-root in friends
     *   -10 root in [4,8] but driver not in [4,8]  (Saturn delay penalty — matches JS)
     *   +5  root in [1,3,5,6,9]  (strength bonus)
     *   (double-saturn rule removed in V2 — redundant with -10 penalty above)
     */
    public static function evaluateMobile(string $mobile, int $driver, array $friends): array {
        $clean = (string)preg_replace('/\D/', '', $mobile);
        if (strlen($clean) > 10) $clean = substr($clean, -10);
        if (empty($clean) || strlen($clean) < 6) {
            return ['display'=>'N/A','compound'=>0,'root'=>0,'last2'=>'','last2Root'=>0,
                    'compatibility'=>'Not Provided','score'=>0,'patterns'=>[],'observations'=>[]];
        }

        $sum  = 0;
        for ($i = 0; $i < strlen($clean); $i++) $sum += (int)$clean[$i];
        $root = AppNumeroEngine::reduceChaldean($sum, false);

        $last2Str  = str_pad(substr($clean, -2), 2, '0', STR_PAD_LEFT);
        $last2Sum  = (int)$last2Str[0] + (int)$last2Str[1];
        $last2Root = AppNumeroEngine::reduceChaldean($last2Sum, false);

        // Unified scoring — mirrors mobileCalcScore() JS exactly
        $score = 50;
        if ($root === $driver)                            $score += 25;
        elseif (in_array($root, $friends, true))          $score += 12;
        if ($last2Root === $driver)                       $score += 15;
        elseif (in_array($last2Root, $friends, true))     $score += 8;
        if (in_array($root, [4, 8], true) && !in_array($driver, [4, 8], true)) $score -= 10; // -10 matches JS Vedic mobileCalcScore()
        if (in_array($root, [1, 3, 5, 6, 9], true))      $score += 5;
        $score = max(0, min(100, $score));

        $compat = match(true) {
            $score >= 80  => 'Highly Supportive',
            $score >= 62  => 'Friendly',
            $score >= 45  => 'Neutral',
            default       => 'Challenging',
        };

        $obs = [];
        if (preg_match('/(\d)\1\1/', $clean, $m))     $obs[] = "Triple digit '{$m[1]}' — amplified energy vibration.";
        if (preg_match('/(\d)\1\1\1/', $clean, $m))  $obs[] = "Quad digit '{$m[1]}' — overwhelmingly amplified.";
        if (substr_count($clean, '0') >= 2)               $obs[] = "Multiple zeros — energy gaps present.";

        return [
            'display'       => $clean,
            'compound'      => $sum,
            'root'          => $root,
            'last2'         => $last2Str,
            'last2Root'     => $last2Root,
            'compatibility' => $compat,
            'score'         => $score,
            'patterns'      => [],
            'observations'  => $obs,
        ];
    }

    /**
     * Public API — backward-compatible wrapper used by generateReport().
     */
    public static function analyze(string $mobile, int $driver, array $friends): array {
        return self::evaluateMobile($mobile, $driver, $friends);
    }
}

