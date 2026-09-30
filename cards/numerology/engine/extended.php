<?php
// extended.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/extended.php — Extended Systems: MuhuratEngine, VastuNameEngine, CompositeEngine

class MuhuratEngine {
    public static function bestDates(int $driver, array $friendlyRoots, string $activity, string $fromDate='', string $toDate='', int $limit=5): array {
        $muhData = AppNumeroEngine::getData()['muhurat'][$activity] ?? [];
        if (empty($muhData)) return [];
        $ideal = $muhData['ideal_drivers'] ?? [1, 3, 6]; $avoid = $muhData['avoid_drivers'] ?? []; $avoidDow = $muhData['avoid_days_of_week'] ?? [];
        $from = $fromDate ? strtotime($fromDate) : time(); $to = $toDate ? strtotime($toDate) : strtotime('+6 months', $from);
        $results = []; $dt = $from;
        while ($dt <= $to && count($results) < $limit) {
            // Correct date root: sum ALL individual digits of day+month+year, then reduce once
            $dateDigits = date('j', $dt) . date('n', $dt) . date('Y', $dt); // e.g. "5 1 2025" → "512025"
            $dateDigitSum = 0;
            for ($di = 0; $di < strlen($dateDigits); $di++) $dateDigitSum += (int)$dateDigits[$di];
            $dateRoot = AppNumeroEngine::reduceChaldean($dateDigitSum, false);
            $inIdeal = in_array($dateRoot, $ideal, true); $inFriendly = in_array($dateRoot, $friendlyRoots, true);
            if (!in_array($dateRoot, $avoid, true) && !in_array((int)date('w', $dt), $avoidDow, true) && ($inIdeal || $inFriendly || $dateRoot === $driver)) {
                $score = ($dateRoot === $driver ? 30 : 0) + ($inIdeal ? 20 : 0) + ($inFriendly ? 15 : 0);
                $results[] = ['date' => date('Y-m-d', $dt), 'display' => date('d M Y', $dt), 'day' => date('l', $dt), 'date_root' => $dateRoot, 'score' => $score, 'grade' => $score >= 50 ? 'Excellent' : ($score >= 35 ? 'Good' : 'Favourable')];
            }
            $dt = strtotime('+1 day', $dt);
        }
        usort($results, fn($a, $b) => $b['score'] - $a['score']);
        return array_slice($results, 0, $limit);
    }
}

class VastuNameEngine {
    public static function score(string $name, int $ownerDriver): array {
        $data = AppNumeroEngine::getData(); $cmap = AppNumeroEngine::CHALDEAN_MAP; $vd = $data['vastu_name'] ?? [];
        $em = $vd['element_map'] ?? []; $gr = $vd['grade_thresholds'] ?? ['Excellent'=>80, 'Good'=>65, 'Neutral'=>50, 'Caution'=>0];
        $clean = strtoupper(preg_replace('/[^A-Za-z]/', '', $name)); if (empty($clean)) return ['error' => 'No alphabetic characters'];
        $total = 0; $letters = [];
        for ($i = 0; $i < strlen($clean); $i++) { $ch = $clean[$i]; $val = (int)($cmap[$ch] ?? 0); $letters[] = ['letter' => $ch, 'value' => $val]; $total += $val; }
        $root = AppNumeroEngine::reduceChaldean($total, false); // reduceChaldean guarantees ≤9; second loop removed
        
        $compound = $total; // compound = raw digit sum; root = reduceChaldean($total)
        $ideal = $vd['business_ideal_roots'] ?? [1, 3, 5, 6, 8, 9]; $avoidR = $vd['avoid_roots'] ?? []; $friends = $data['planets'][(string)$ownerDriver]['friends'] ?? [];
        $score = min(100, max(0, 50 + (in_array($root, $ideal, true) ? 25 : 0) + ($root === $ownerDriver ? 15 : 0) + (in_array($root, $friends, true) ? 10 : 0) - (in_array($root, $avoidR, true) ? 20 : 0)));
        $grade = 'Caution'; foreach ($gr as $g => $t) { if ($score >= $t) { $grade = $g; break; } }
        $elem = $em[(string)$root] ?? []; $ci = $data['compounds'][(string)$compound] ?? [];
        return ['name' => $name, 'chaldean_total' => $total, 'root' => $root, 'compound' => $compound, 'compound_name' => ($ci['name'] ?? ''), 'compound_verdict' => ($ci['verdict'] ?? 'Neutral'), 'element' => ($elem['element'] ?? ''), 'hi_element' => ($elem['hi'] ?? ''), 'vastu_direction' => ($elem['vastu_dir'] ?? ''), 'quality' => ($elem['quality'] ?? ''), 'score' => $score, 'grade' => $grade, 'owner_alignment' => ($root === $ownerDriver) ? 'Perfect' : (in_array($root, $friends, true) ? 'Friendly' : 'Neutral'), 'letters' => $letters];
    }
}

class CompositeEngine {
    public static function build(array $rA, array $rB): array {
        $cD = AppNumeroEngine::reduceChaldean((int)$rA['core']['driver'] + (int)$rB['core']['driver'], false);
        $cC = AppNumeroEngine::reduceChaldean((int)$rA['core']['conductor'] + (int)$rB['core']['conductor'], false);
        $cE = AppNumeroEngine::reduceChaldean((int)($rA['name_matrix']['full']['root'] ?? 1) + (int)($rB['name_matrix']['full']['root'] ?? 1), false);
        $cPY = AppNumeroEngine::reduceChaldean((int)($rA['personal_year']['number'] ?? 1) + (int)($rB['personal_year']['number'] ?? 1), false);
        $combined = []; for ($n = 1; $n <= 9; $n++) $combined[$n] = ((int)($rA['grid']['counts'][$n] ?? 0)) + ((int)($rB['grid']['counts'][$n] ?? 0));
        $missing = []; $strong = []; 
        for ($n = 1; $n <= 9; $n++) { if ($combined[$n] === 0) $missing[] = $n; if ($combined[$n] >= 3) $strong[] = $n; }
        $cDigit = $cD; while ($cDigit > 9) $cDigit = array_sum(str_split((string)$cDigit));
        $ins = AppNumeroEngine::getData()['composite_insights'][(string)$cDigit] ?? []; $pl = AppNumeroEngine::getData()['planets'][(string)$cDigit] ?? [];
        return ['composite_driver' => $cD, 'composite_conductor' => $cC, 'composite_expression' => $cE, 'composite_py' => $cPY, 'combined_grid' => $combined, 'combined_missing' => $missing, 'combined_strong' => $strong, 'entity_insight' => ($ins['en'] ?? ''), 'entity_insight_hi' => ($ins['hi'] ?? ''), 'entity_planet' => ($pl['name'] ?? ''), 'entity_gem' => ($pl['gem'] ?? ''), 'person_a' => ['name' => $rA['profile']['name'], 'driver' => $rA['core']['driver']], 'person_b' => ['name' => $rB['profile']['name'], 'driver' => $rB['core']['driver']]];
    }
}
