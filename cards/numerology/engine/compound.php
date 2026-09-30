<?php
// compound.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/compound.php — Compound Analysis, Diagnostics, Interpretation

class CompoundEngine {
    public static function analyze(int $compound, array $config): array {
        return $config[(string)$compound] ?? ['name' => 'Standard Vibration', 'verdict' => 'Neutral', 'meaning' => 'A stable, balanced vibrational energy.'];
    }
}

class DiagnosticsEngine {
    public static function analyzeMissing(array $missing, array $missingConfig): array {
        if (empty($missing)) return ['details' => [], 'risk_index' => 0, 'has_critical' => false];
        $details = []; $riskIndex = 0; $hasCritical = false;
        $_scoring = AppNumeroEngine::getData()['scoring'] ?? []; $numWeights = [];
        foreach (($_scoring['num_weights'] ?? ['1'=>2,'2'=>1,'3'=>1,'4'=>3,'5'=>3,'6'=>1,'7'=>0,'8'=>3,'9'=>1]) as $_k => $_v) $numWeights[(int)$_k] = (int)$_v;
        foreach ($missing as $m) {
            $conf = $missingConfig[(string)$m] ?? []; $sev = $conf['severity'] ?? 'Neutral';
            $sevMult = match($sev) { 'Critical' => 3, 'High' => 2, 'Moderate' => 1, default => 0 };
            if ($sev === 'Critical') $hasCritical = true;
            $riskIndex += (($numWeights[$m] ?? 1) * $sevMult);
            $details[$m] = ['trait' => $conf['trait'] ?? 'N/A', 'remedy' => $conf['remedy'] ?? 'Consult expert.', 'severity' => $sev, 'area' => $conf['area'] ?? 'General'];
        }
        foreach ([[4,9,2], [3,5,7], [8,1,6], [4,3,8], [9,5,1], [2,7,6], [4,5,6], [2,5,8], [1,5,9]] as $axis) {
            if (count(array_intersect($axis, $missing)) === 3) $riskIndex += 5;
        }
        return ['details' => $details, 'risk_index' => $riskIndex, 'has_critical' => $hasCritical];
    }
}

class InterpretationEngine {
    public static function build(array $core, array $planet, array $missingDetails): array {
        $openers = AppNumeroEngine::getData()['strength_openers'] ?? [];
        // Guard: if strength_openers is missing/empty in JSON, fall back to default
        // phrase to prevent DivisionByZeroError from modulo on count() === 0.
        $openerCount = count($openers);
        $opener = $openerCount > 0
            ? ($openers[$core['driver'] % $openerCount] ?? "You carry a rare planetary blueprint. ")
            : "You carry a rare planetary blueprint. ";
        return [
            'driver_summary'  => $opener . ($planet['attributes']['career'] ?? ''),
            'missing_summary' => empty($missingDetails)
                ? "Your elemental grid is perfectly balanced — a rare and powerful foundation."
                : "Your growth reservoirs — numbers " . implode(", ", array_keys($missingDetails)) . " — represent untapped potential waiting to be activated.",
        ];
    }
}

