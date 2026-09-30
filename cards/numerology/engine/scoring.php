<?php
// scoring.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/scoring.php — Alignment Scoring & Final Decision Harmoniser

class ScoringEngine {
    public static function derive(array $data, array $nameConflict = []): array {
        $weights = [1=>8, 2=>6, 3=>5, 4=>9, 5=>10, 6=>6, 7=>4, 8=>9, 9=>7];
        $penalty = array_sum(array_map(fn($m) => $weights[$m] ?? 5, $data['grid']['missing'] ?? []));
        $gridStrength = max(0, 100 - $penalty);
        // evaluateHarmony() emits: Aligned | Supportive | Challenging. 'Neutral' is never emitted.
        // Default branch now maps to Challenging (40) instead of silently scoring 60.
        $nameAlignment = match($data['name_matrix']['harmony']['status'] ?? 'Challenging') {
            'Aligned'    => 100,
            'Supportive' => 80,
            'Challenging'=> 40,
            default      => 40  // safeguard for any future status value
        };
        $mobileAlignment = match($data['mobile']['compatibility'] ?? 'Not Provided') { 'Highly Supportive' => 100, 'Friendly' => 80, 'Not Provided' => 50, 'Neutral' => 50, 'Challenging' => 20, default => 50 };
        $yogaStrength = min(100, count($data['yogas'] ?? []) * 25);
        $conflictPenalty = (!empty($nameConflict) && ($nameConflict['score'] ?? 0) > 0) ? min((int)$nameConflict['score'] * 2, 10) : 0;
        $finalScore = max(0, min(100, (int)round(($gridStrength * 0.25) + ($nameAlignment * 0.30) + ($mobileAlignment * 0.20) + ($yogaStrength * 0.25)) - $conflictPenalty));
        $narrative = "Your deterministic alignment score of {$finalScore}/100 is based on a structured rule-set mapping four core domains. " . ($finalScore >= 80 ? "Your vibrational matrix is exceptionally well-aligned." : ($finalScore >= 60 ? "Your matrix is balanced but holds specific reservoirs for optimization." : "Your matrix contains notable philosophical friction requiring targeted realignment."));
        return ['total' => $finalScore, 'breakdown' => ['grid_strength' => $gridStrength, 'name_alignment' => $nameAlignment, 'mobile_alignment' => $mobileAlignment, 'yoga_strength' => $yogaStrength, 'name_conflict' => -$conflictPenalty], 'narrative' => $narrative];
    }
}

class FinalDecisionEngine {
    public static function harmonize(array $report): array {
        $enemies = $report['lucky_driver']['data']['enemies'] ?? [];
        if (empty($enemies)) return $report;
        if (!empty($report['name_matrix']['corrections'])) {
            $report['name_matrix']['corrections'] = array_values(array_filter($report['name_matrix']['corrections'], fn($c) => !in_array($c['full_root'], $enemies, true) && !in_array($c['su_root'], $enemies, true)));
        }
        if (!empty($report['mobile']['patterns'])) {
            $filtered = array_values(array_filter($report['mobile']['patterns'], fn($p) => !in_array($p['target_root'], $enemies, true)));
            // Keep filtered result only if at least one pattern survived; otherwise keep originals
            $report['mobile']['patterns'] = !empty($filtered) ? $filtered : $report['mobile']['patterns'];
        }
        return $report;
    }
}



