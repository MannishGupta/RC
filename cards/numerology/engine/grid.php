<?php
// grid.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/grid.php — Lo Shu Grid, Yoga, Arrow, Cell Meta

class GridEngine {
    public const LAYOUT = [4, 9, 2, 3, 5, 7, 8, 1, 6];
    public static function build(string $dobDigits): array {
        $counts = array_fill(1, 9, 0);
        for ($i = 0; $i < strlen($dobDigits); $i++) { 
            $n = (int)$dobDigits[$i]; 
            if ($n >= 1 && $n <= 9) $counts[$n]++; 
        }
        $missing = []; $strong = [];
        foreach ($counts as $num => $count) { 
            if ($count === 0) $missing[] = $num; 
            elseif ($count >= 2) $strong[] = $num; 
        }
        return ['counts' => $counts, 'missing' => $missing, 'strong' => $strong];
    }
}

class YogaEngine {
    public static function detect(array $core, array $grid): array {
        $yogas = []; $defs = AppNumeroEngine::getData()['yogas'] ?? [];
        foreach ($defs as $y) {
            switch ($y['condition']) {
                case 'driver_in_1_3_6_AND_conductor_in_3_6_9':
                    if (in_array($core['driver'], [1, 3, 6], true) && in_array($core['conductor'], [3, 6, 9], true)) $yogas[] = $y; break;
                case 'grid_strong_has_4_or_8':
                    if (!empty(array_intersect([4, 8], $grid['strong']))) $yogas[] = $y; break;
                case 'grid_strong_has_1_5_9':
                    if (!empty(array_intersect([1, 5, 9], $grid['strong']))) $yogas[] = $y; break;
                case 'grid_strong_has_2_6_9':
                    if (!empty(array_intersect([2, 6, 9], $grid['strong']))) $yogas[] = $y; break;
                case 'grid_strong_has_3_5_7':
                    if (!empty(array_intersect([3, 5, 7], $grid['strong']))) $yogas[] = $y; break;
            }
        }
        return $yogas;
    }
}

class ArrowEngine {
    public static function detect(array $gridCounts, array $arrowConfig): array {
        if (empty($gridCounts) || empty($arrowConfig)) return [];
        $detected = [];
        foreach ($arrowConfig as $pattern => $data) {
            $numbers = explode('-', $pattern); $present = true; $strength = 0;
            foreach ($numbers as $n) { 
                $count = (int)($gridCounts[(int)$n] ?? 0); 
                if ($count === 0) { $present = false; break; } 
                $strength += $count; 
            }
            if ($present) {
                $rating = $strength > count($numbers) ? 'Dominant' : 'Moderate';
                $detected[] = ['name' => ($data['name'] ?? 'Arrow') . " ($rating)", 'desc' => $data['desc'] ?? '', 'type' => $data['type'] ?? 'general'];
            }
        }
        return $detected;
    }
}

class LoShuCellMeta {
    public static function get(int $n): array { return AppNumeroEngine::getData()['loshu_cells'][(string)$n] ?? []; }
}

