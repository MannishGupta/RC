<?php
// compat.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/compat.php — Compatibility Remedies & Pair Insights

class CompatibilityRemediesEngine {
    public static function getDriverPairInsight(int $dA, int $dB): array {
        $lang = AppNumeroEngine::getLang(); $key = min($dA, $dB) . '-' . max($dA, $dB);
        $pairs = AppNumeroEngine::getData()['compat_pairs'] ?? [];
        if (isset($pairs[$key])) return ['verdict' => $pairs[$key]['verdict'], 'description' => $pairs[$key]['desc'], 'joint_remedy' => $pairs[$key]['remedy']];
        return ['verdict' => $lang === 'hi' ? 'तटस्थ' : 'Neutral', 'description' => 'An uncommon pairing. Growth through mutual respect.', 'joint_remedy' => 'Focus on complementary strengths.'];
    }
    public static function getAxisRemedies(array $compat): array {
        $labels = $compat['labels'] ?? []; $mA = $compat['metrics_a'] ?? []; $mB = $compat['metrics_b'] ?? [];
        $remedies = ['Identify shared strengths and build a joint ritual around them.', 'Channel yoga power into collaborative creative or business projects.', 'Align name vibrations through a joint venture name.', 'Both adopt compatible mobile numbers ending in friendly roots.', 'Clear karmic debt together through charity, fasting, or service.', 'Use the overall alignment score to time major decisions together.'];
        $result = [];
        foreach ($labels as $i => $label) {
            $a = (int)($mA[$i] ?? 50); $b = (int)($mB[$i] ?? 50);
            $result[] = ['axis' => $label, 'a' => $a, 'b' => $b, 'status' => abs($a - $b) < 30 ? 'Aligned' : 'Tension', 'advice' => $remedies[$i] ?? 'Focus on mutual respect.'];
        }
        return $result;
    }
    public static function getRelationshipTimingAdvice(array $rA, array $rB): array {
        $pyA = (int)($rA['personal_year']['number'] ?? 1); $pyB = (int)($rB['personal_year']['number'] ?? 1);
        $joint = AppNumeroEngine::reduceChaldean($pyA + $pyB, false);
        $raw = AppNumeroEngine::getData()['joint_py_advice'][(string)$joint] ?? null;
        $advice = is_array($raw) ? ($raw['advice'] ?? '') : ((string)($raw ?: 'Joint Personal Year ' . $joint . ' — opportunity for growth and alignment.'));
        return ['joint_py' => $joint, 'py_a' => $pyA, 'py_b' => $pyB, 'advice' => $advice, 'hi_advice' => is_array($raw) ? ($raw['hi_advice'] ?? $advice) : $advice];
    }
}

