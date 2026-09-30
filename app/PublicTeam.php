<?php
declare(strict_types=1);
if (!defined('BASE_PATH')) { exit; }
class PublicTeam {
    public static function dataRoot(): string {
        if (!empty($GLOBALS['RC_OPT_DATA_PATH']) && is_string($GLOBALS['RC_OPT_DATA_PATH'])) {
            return rtrim(str_replace('\\', '/', $GLOBALS['RC_OPT_DATA_PATH']), '/');
        }
        if (defined('DATA_PATH')) {
            return rtrim(str_replace('\\', '/', (string)DATA_PATH), '/');
        }
        return rtrim(str_replace('\\', '/', BASE_PATH . '/data'), '/');
    }
    public static function strip(array $team): array {
        $out = [];
        $allow = ['id','name','slug','photo','designation','designation_name','department','department_name','location_name'];
        foreach ($team as $m) {
            if (!is_array($m)) continue;
            $row = [];
            foreach ($allow as $k) {
                if (isset($m[$k]) && $m[$k] !== '' && $m[$k] !== null) $row[$k] = $m[$k];
            }
            if (!empty($row['name'])) $out[] = $row;
        }
        return $out;
    }
    public static function writeFromTeam(?array $team = null): bool {
        if ($team === null) {
            if (!class_exists('AppDB')) return false;
            $team = AppDB::read('team');
        }
        if (!is_array($team)) $team = [];
        $path = self::dataRoot() . '/team_public.json';
        $json = json_encode(self::strip($team), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false && @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}
