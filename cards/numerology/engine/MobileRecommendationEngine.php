<?php  
declare(strict_types=1);

class MobileRecommendationEngine {

     
     
    private const PLANET_CLASS = [
        1 => 'power',         
        2 => 'flow',          
        3 => 'expansion',     
        4 => 'structure',     
        5 => 'intelligence',  
        6 => 'flow',          
        7 => 'spiritual',     
        8 => 'power',         
        9 => 'expansion',     
    ];

     

    

    public static function suggestRoots(
        int $driver,
        array $friends,
        array $enemies = [],
        int $seed = 0
    ): array {

         
        $driverRoot = max(1, min(9, $driver));

        $cleanFriends = array_values(array_filter(
            $friends,
            fn($r) => is_int($r) && $r >= 1 && $r <= 9 && !in_array($r, $enemies, true)
        ));

         
        $friendPool = array_values(array_unique(array_diff($cleanFriends, [$driverRoot])));

         
         
        mt_srand($seed);
         
        $scored = [];
        foreach ($friendPool as $candidate) {
            $scored[$candidate] = self::scoreRoot($candidate, $driverRoot, $cleanFriends)
                                  + (mt_rand(0, 9) * 0.01);  
        }
        arsort($scored);
        $rankedPool = array_keys($scored);

         
        mt_srand((int)(hrtime(true) & 0x7FFFFFFF));

         
        $result = [$driverRoot];

        foreach ($rankedPool as $candidate) {
            if (self::isTooSimilar($candidate, $result)) {
                continue;  
            }
            $result[] = $candidate;
            if (count($result) === 3) break;
        }

         
         
        if (count($result) < 3) {
            foreach ($rankedPool as $candidate) {
                if (in_array($candidate, $result, true)) continue;
                $result[] = $candidate;
                if (count($result) === 3) break;
            }
        }

         
        if (count($result) < 3) {
            $usedSet = array_unique(array_merge($result, $enemies));

             
            $driverClass = self::PLANET_CLASS[$driverRoot] ?? '';
            $usedClasses = array_map(fn($n) => self::PLANET_CLASS[$n] ?? '', $result);

            $padCandidates = array_values(array_filter(
                range(1, 9),
                fn($n) => !in_array($n, $usedSet, true)
                       && !self::isTooSimilar($n, $result)
                       && !in_array(self::PLANET_CLASS[$n] ?? '', $usedClasses, true)
            ));

             
            if (empty($padCandidates)) {
                $padCandidates = array_values(array_filter(
                    range(1, 9),
                    fn($n) => !in_array($n, $usedSet, true)
                           && !self::isTooSimilar($n, $result)
                ));
            }

             
            if (empty($padCandidates)) {
                $padCandidates = array_values(array_filter(
                    range(1, 9),
                    fn($n) => !in_array($n, $usedSet, true)
                ));
            }

             
            if (empty($padCandidates)) {
                $padCandidates = array_values(array_diff(range(1, 9), $result));
            }

            foreach ($padCandidates as $n) {
                $result[] = $n;
                if (count($result) === 3) break;
            }
        }

         
        $result = array_slice($result, 0, 3);

         
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

     

    

    private static function scoreRoot(int $candidate, int $driver, array $friends): int {
        $score = 0;

        if (in_array($candidate, $friends, true)) {
            $score += 30;
        }

         
        $dist   = abs($candidate - $driver);
        $score -= $dist * 2;

         
        $driverClass    = self::PLANET_CLASS[$driver]    ?? '';
        $candidateClass = self::PLANET_CLASS[$candidate] ?? '';

        if ($driverClass !== '' && $candidateClass === $driverClass) {
            $score += 10;
        }

        return $score;
    }

     

    

    public static function isTooSimilar(int $candidate, array $chosen): bool {
        foreach ($chosen as $existing) {
            if (abs($candidate - $existing) <= 1) return true;
        }
        return false;
    }

    

    public static function isSequential(array $arr): bool {
        if (count($arr) < 3) return false;
        $chk = $arr;
        sort($chk);

         
        if ($chk[1] === $chk[0] + 1 && $chk[2] === $chk[1] + 1) {
            return true;
        }

         
        if ($chk === [1, 2, 9]) return true;
        if ($chk === [1, 8, 9]) return true;

        return false;
    }
}
