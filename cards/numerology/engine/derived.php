<?php
// derived.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/derived.php — Derived Systems: PersonalYear, Pinnacle, Cycle, Maturity, Timeline

class PersonalYearEngine {
    /**
     * Calculates Personal Year number.
     * PY changes on the person's own birthday (not January 1).
     * If today is before this year's birthday, calculate for (currentYear - 1).
     * If forYear is explicitly passed, use that year without adjustment.
     */
    public static function calculate(int $day, int $month, int $forYear = 0): array {
        if ($forYear > 0) {
            $year = $forYear;
        } else {
            $year = (int)date('Y');
            $todayMd = (int)date('md');
            $birthMd  = (int)(str_pad((string)$month, 2, '0', STR_PAD_LEFT) . str_pad((string)$day, 2, '0', STR_PAD_LEFT));
            if ($todayMd < $birthMd) {
                $year--; // Birthday hasn't occurred yet this year — still in last year's PY
            }
        }
        $yearSum = 0; foreach (str_split((string)$year) as $d) $yearSum += (int)$d;
        return ['year' => $year, 'number' => AppNumeroEngine::reduceChaldean($day + $month + $yearSum, false)];
    }
}

class PinnacleEngine {
    public static function calculate(int $d, int $m, int $y, int $c): array {
        $rM = AppNumeroEngine::reduceChaldean($m, false); $rD = AppNumeroEngine::reduceChaldean($d, false);
        $yS = 0; foreach (str_split((string)$y) as $ch) $yS += (int)$ch;
        $rY = AppNumeroEngine::reduceChaldean($yS, false);
        $p1 = AppNumeroEngine::reduceChaldean($rM + $rD, false); $p2 = AppNumeroEngine::reduceChaldean($rD + $rY, false);
        $p3 = AppNumeroEngine::reduceChaldean($p1 + $p2, false); $p4 = AppNumeroEngine::reduceChaldean($rM + $rY, false);
        $c1 = AppNumeroEngine::reduceChaldean((int)abs($rM - $rD), false); $c2 = AppNumeroEngine::reduceChaldean((int)abs($rD - $rY), false);
        $c3 = AppNumeroEngine::reduceChaldean((int)abs($c1 - $c2), false); $c4 = AppNumeroEngine::reduceChaldean((int)abs($rM - $rY), false);
        $lp = $c; while ($lp > 9) $lp = array_sum(str_split((string)$lp));
        $a1 = 36 - $lp; $a2 = $a1 + 9; $a3 = $a2 + 9;
        return [
            'pinnacles' => [['number'=>$p1,'phase'=>1,'age_range'=>"0 – {$a1}"],['number'=>$p2,'phase'=>2,'age_range'=>($a1+1)." – {$a2}"],['number'=>$p3,'phase'=>3,'age_range'=>($a2+1)." – {$a3}"],['number'=>$p4,'phase'=>4,'age_range'=>($a3+1)." – Life"]],
            'challenges' => [['number'=>$c1,'phase'=>1,'age_range'=>"0 – {$a1}"],['number'=>$c2,'phase'=>2,'age_range'=>($a1+1)." – {$a2}"],['number'=>$c3,'phase'=>3,'age_range'=>($a2+1)." – {$a3}"],['number'=>$c4,'phase'=>4,'age_range'=>($a3+1)." – Life"]]
        ];
    }
}

class PersonalCycleEngine {
    public static function calculate(string $dob): array {
        $ts = strtotime($dob); 
        if (!$ts) return ['personal_month'=>1,'personal_day'=>1,'today'=>date('d M Y')];
        $pYear = class_exists('PersonalYearEngine') ? PersonalYearEngine::calculate((int)date('d', $ts), (int)date('m', $ts))['number'] : 1;
        $pMonth = AppNumeroEngine::reduceChaldean($pYear + (int)date('n'), false);
        return [
            'personal_year'  => $pYear,
            'personal_month' => $pMonth,
            'personal_day'   => AppNumeroEngine::reduceChaldean($pMonth + (int)date('j'), false),
            'today'          => date('d M Y'),
            'month_name'     => date('F')
        ];
    }
}

class MaturityEngine {
    /**
     * Maturity Number = Expression (full name root) + Life Path (conductor), reduced.
     * Standard numerology: NOT driver + conductor. Driver = birth day number.
     * Expression = full Chaldean name root. These are different numbers.
     * Caller: generateReport() passes ($fullNameData['root'], $conductor).
     * Param $expressionRoot renamed from $driver — signature kept compatible.
     */
    public static function calculate(int $expressionRoot, int $conductor): array {
        $cDigit = $conductor;
        while ($cDigit > 9) $cDigit = array_sum(str_split((string)$cDigit));
        return [
            'number'       => AppNumeroEngine::reduceChaldean($expressionRoot + $cDigit, false),
            'activates_at' => 35,
            'note'         => 'Activates fully between ages 35–45 as the secondary life purpose emerges.',
        ];
    }
}

class TimelineEngine {
    public static function build(string $dob, int $windowYears = 11): array {
        $ts = strtotime($dob); if (!$ts) return [];
        $cY = (int)date('Y'); $tl = [];
        for ($y = $cY - 1; $y < $cY + $windowYears; $y++) {
            $tl[] = class_exists('PersonalYearEngine') 
                ? ['year' => $y, 'number' => PersonalYearEngine::calculate((int)date('d', $ts), (int)date('m', $ts), $y)['number'], 'is_current' => ($y === $cY)] 
                : ['year' => $y, 'number' => 1, 'is_current' => ($y === $cY)];
        }
        return $tl;
    }
}
