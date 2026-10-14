<?php  
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    throw new RuntimeException('BASE_PATH not defined. MobileEvaluationEngine cannot execute safely.');
}

class MobileEvaluationEngine {

     
    private const COMPAT_THRESHOLDS = [
        87 => 'Highly Supportive',
        60 => 'Friendly',
        45 => 'Neutral',
    ];

     

    

    public static function evaluateMobile(string $mobile, int $driver, array $friends): array {
        return self::evaluate($mobile, $driver, $friends);
    }

    

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

         
        $sum  = array_sum(array_map('intval', str_split($clean)));
        $root = self::reduceChaldean($sum);

         
        $last2     = substr($clean, -2);
        $last2Sum  = array_sum(array_map('intval', str_split($last2)));
        $last2Root = self::reduceChaldean($last2Sum);

         
        $score = 50;

         
        if ($root === $driver)                             $score += 25;
        elseif (in_array($root, $friends, true))           $score += 12;
        else                                               $score -= 10;

         
        if ($last2Root === $driver)                        $score += 15;
        elseif (in_array($last2Root, $friends, true))      $score += 8;
        else                                               $score -= 5;

         
        if (in_array($root, [4, 8], true) && !in_array($driver, [4, 8], true)) {
            $score -= 10;
        }

         
        if (in_array($root, [1, 3, 5, 6, 9], true)) {
            $score += 5;
        }

        $score = max(0, min(100, $score));

         
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

     

    

    private static function reduceChaldean(int $num): int {
        if ($num === 0) return 0;
        while ($num > 9) {
            $num = array_sum(str_split((string)$num));
        }
        return $num;
    }
}
