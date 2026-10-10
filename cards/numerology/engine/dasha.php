<?php
/**
 * Numerology Dasha Calculator — life-cycle periods (convention-based guidance).
 * Version: 20261003.29
 *
 * Pure, unit-testable helpers. Not scientific — interpretations are labelled as guidance.
 */
declare(strict_types=1);

/**
 * Repeatedly sum digits until single digit; optionally preserve 11 / 22 / 33.
 * Formula: while n > 9 (and not a kept master), n = sum of decimal digits of n.
 */
function numero_reduce(int $n, bool $keepMaster = true): int
{
    $n = abs($n);
    while ($n > 9) {
        if ($keepMaster && in_array($n, [11, 22, 33], true)) {
            return $n;
        }
        $s = 0;
        while ($n > 0) {
            $s += $n % 10;
            $n = intdiv($n, 10);
        }
        $n = $s;
    }
    return $n === 0 ? 0 : $n;
}

/**
 * Always reduce fully to 1–9 (masters not preserved). Used ONLY for age arithmetic.
 * Formula: same digit-sum loop with keepMaster=false; 0 stays 0.
 */
function numero_reduce_to_single(int $n): int
{
    $n = numero_reduce(abs($n), false);
    return $n === 0 ? 9 : $n; // conventional: avoid 0 for life-path age math
}

/** Pythagorean letter values A=1 … I=9, J=1 … R=9, S=1 … Z=8 */
function numero_pythagorean_map(): array
{
    $map = [];
    $letters = range('A', 'Z');
    foreach ($letters as $i => $ch) {
        $map[$ch] = ($i % 9) + 1;
    }
    return $map;
}

/** Chaldean letter values (matches AppNumeroEngine::CHALDEAN_MAP where available). */
function numero_chaldean_map(): array
{
    if (class_exists('AppNumeroEngine')) {
        try {
            $ref = new ReflectionClass('AppNumeroEngine');
            if ($ref->hasConstant('CHALDEAN_MAP')) {
                $m = $ref->getConstant('CHALDEAN_MAP');
                if (is_array($m) && $m !== []) {
                    return $m;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
    }
    return [
        'A' => 1, 'I' => 1, 'J' => 1, 'Q' => 1, 'Y' => 1,
        'B' => 2, 'K' => 2, 'R' => 2,
        'C' => 3, 'G' => 3, 'L' => 3, 'S' => 3,
        'D' => 4, 'M' => 4, 'T' => 4,
        'E' => 5, 'H' => 5, 'N' => 5, 'X' => 5,
        'U' => 6, 'V' => 6, 'W' => 6,
        'O' => 7, 'Z' => 7,
        'F' => 8, 'P' => 8,
    ];
}

/**
 * Validate DOB and return [day, month, year] or null.
 * Accepts DD/MM/YYYY, YYYY-MM-DD, or DateTimeInterface.
 */
function numero_parse_dob(string|DateTimeInterface $dob): ?array
{
    if ($dob instanceof DateTimeInterface) {
        return [(int)$dob->format('d'), (int)$dob->format('m'), (int)$dob->format('Y')];
    }
    $dob = trim($dob);
    $d = $m = $y = 0;
    if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $dob, $mm)) {
        $d = (int)$mm[1];
        $m = (int)$mm[2];
        $y = (int)$mm[3];
    } elseif (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $dob, $mm)) {
        $y = (int)$mm[1];
        $m = (int)$mm[2];
        $d = (int)$mm[3];
    } else {
        return null;
    }
    if (!checkdate($m, $d, $y) || $y < 1800 || $y > 2100) {
        return null;
    }
    return [$d, $m, $y];
}

/**
 * Life Path: sum ALL digits of DOB, reduce(keepMaster=true).
 * Example: 11/09/1985 → 1+1+0+9+1+9+8+5 = 34 → 7 (or masters when present).
 */
function numero_life_path(int $day, int $month, int $year): int
{
    $digits = sprintf('%02d%02d%04d', $day, $month, $year);
    $sum = 0;
    for ($i = 0, $L = strlen($digits); $i < $L; $i++) {
        $sum += (int)$digits[$i];
    }
    return numero_reduce($sum, true);
}

/**
 * Expression (Destiny) from full name.
 * system: 'pythagorean' (default) or 'chaldean'.
 */
function numero_expression(string $fullName, string $system = 'pythagorean'): int
{
    $map = ($system === 'chaldean') ? numero_chaldean_map() : numero_pythagorean_map();
    $sum = 0;
    $name = strtoupper(preg_replace('/[^A-Za-z]/', '', $fullName) ?? '');
    for ($i = 0, $L = strlen($name); $i < $L; $i++) {
        $ch = $name[$i];
        $sum += (int)($map[$ch] ?? 0);
    }
    return numero_reduce($sum, true);
}

/** Birth Day number: reduce(day, keepMaster=true). */
function numero_birth_day(int $day): int
{
    return numero_reduce($day, true);
}

/**
 * Guidance blurbs for pinnacle / challenge / personal periods (editable lookup).
 * @return array<int|string, string>
 */
function numero_dasha_interpretations(): array
{
    return [
        1 => 'Independence, initiative, and new beginnings. Guidance only.',
        2 => 'Cooperation, patience, and partnership themes. Guidance only.',
        3 => 'Expression, creativity, and social energy. Guidance only.',
        4 => 'Structure, work, and building foundations. Guidance only.',
        5 => 'Change, freedom, and adaptability. Guidance only.',
        6 => 'Responsibility, home, and service. Guidance only.',
        7 => 'Analysis, study, and inner focus. Guidance only.',
        8 => 'Ambition, authority, and material results. Guidance only.',
        9 => 'Completion, compassion, and wider outlook. Guidance only.',
        11 => 'Inspiration and intuition (master). Guidance only.',
        22 => 'Large-scale building (master). Guidance only.',
        33 => 'Compassionate teaching (master). Guidance only.',
        'py_1' => 'Personal Year 1: plant seeds; start projects. Guidance only.',
        'py_2' => 'Personal Year 2: cooperate and refine. Guidance only.',
        'py_3' => 'Personal Year 3: communicate and create. Guidance only.',
        'py_4' => 'Personal Year 4: organise and stabilise. Guidance only.',
        'py_5' => 'Personal Year 5: change and travel themes. Guidance only.',
        'py_6' => 'Personal Year 6: family and duty. Guidance only.',
        'py_7' => 'Personal Year 7: reflection and skill. Guidance only.',
        'py_8' => 'Personal Year 8: harvest and recognition. Guidance only.',
        'py_9' => 'Personal Year 9: release and complete. Guidance only.',
    ];
}

function numero_theme_for(int $n, string $prefix = ''): string
{
    $table = numero_dasha_interpretations();
    if ($prefix !== '' && isset($table[$prefix . $n])) {
        return $table[$prefix . $n];
    }
    return $table[$n] ?? ('Period number ' . $n . '. Guidance only.');
}

/**
 * Macro-dasha (Pinnacles + Challenges).
 * P1=reduce(m+d), P2=reduce(d+y), P3=reduce(P1+P2), P4=reduce(m+y) — masters kept.
 * Challenges always single digit: C1=|m−d|, C2=|d−y|, C3=|C1−C2|, C4=|m−y|.
 * Ages use lpr = reduceToSingle(LP): e1=36−lpr; P1: 0..e1; P2: e1+1..e1+9; …
 *
 * @return list<array{index:int,pinnacle_number:int,challenge_number:int,start_age:int,end_age:int,thematic_focus:string}>
 */
function numero_pinnacles(int $day, int $month, int $year, int $lifePath): array
{
    $m = numero_reduce_to_single($month);
    $d = numero_reduce_to_single($day);
    $y = numero_reduce_to_single($year);

    $p1 = numero_reduce($m + $d, true);
    $p2 = numero_reduce($d + $y, true);
    $p3 = numero_reduce($p1 + $p2, true);
    $p4 = numero_reduce($m + $y, true);

    $c1 = numero_reduce(abs($m - $d), false);
    $c2 = numero_reduce(abs($d - $y), false);
    $c3 = numero_reduce(abs($c1 - $c2), false);
    $c4 = numero_reduce(abs($m - $y), false);

    $lpr = numero_reduce_to_single($lifePath);
    $e1 = 36 - $lpr;

    $ranges = [
        1 => [0, $e1],
        2 => [$e1 + 1, $e1 + 9],
        3 => [$e1 + 10, $e1 + 18],
        4 => [$e1 + 19, 100],
    ];
    $ps = [1 => $p1, 2 => $p2, 3 => $p3, 4 => $p4];
    $cs = [1 => $c1, 2 => $c2, 3 => $c3, 4 => $c4];

    $out = [];
    foreach ([1, 2, 3, 4] as $i) {
        $out[] = [
            'index' => $i,
            'pinnacle_number' => $ps[$i],
            'challenge_number' => $cs[$i],
            'start_age' => $ranges[$i][0],
            'end_age' => $ranges[$i][1],
            'thematic_focus' => numero_theme_for($ps[$i]),
        ];
    }
    return $out;
}

/**
 * Personal Year: reduce(reduceToSingle(birthMonth)+reduceToSingle(birthDay)+reduceToSingle(targetYear)).
 */
function numero_personal_year(int $birthDay, int $birthMonth, int $targetYear): int
{
    return numero_reduce(
        numero_reduce_to_single($birthMonth)
        + numero_reduce_to_single($birthDay)
        + numero_reduce_to_single($targetYear),
        true
    );
}

/** Personal Month: reduce(PY + calendarMonth). */
function numero_personal_month(int $personalYear, int $calendarMonth): int
{
    return numero_reduce($personalYear + $calendarMonth, true);
}

/** Personal Day: reduce(PM + calendarDay). */
function numero_personal_day(int $personalMonth, int $calendarDay): int
{
    return numero_reduce($personalMonth + $calendarDay, true);
}

/**
 * Full dasha report as structured array (JSON-serialisable).
 *
 * @param array{system?:string,target?:string|DateTimeInterface} $opts
 * @return array{ok:bool,error?:string,core_profile?:array,macro_dasha_pinnacles?:array,micro_dasha_current?:array}
 */
function numero_dasha_report(string $fullName, string|DateTimeInterface $dob, array $opts = []): array
{
    $parsed = numero_parse_dob($dob);
    if ($parsed === null) {
        return ['ok' => false, 'error' => 'Invalid date of birth. Use DD/MM/YYYY or YYYY-MM-DD.'];
    }
    [$day, $month, $year] = $parsed;
    $system = (($opts['system'] ?? 'pythagorean') === 'chaldean') ? 'chaldean' : 'pythagorean';

    $lp = numero_life_path($day, $month, $year);
    $lpr = numero_reduce_to_single($lp);
    $expr = numero_expression($fullName, $system);
    $bd = numero_birth_day($day);

    $target = $opts['target'] ?? 'now';
    if ($target instanceof DateTimeInterface) {
        $ty = (int)$target->format('Y');
        $tm = (int)$target->format('n');
        $td = (int)$target->format('j');
    } elseif (is_string($target) && $target !== '' && strtolower($target) !== 'now') {
        $tp = numero_parse_dob($target);
        if ($tp === null) {
            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
            $ty = (int)$now->format('Y');
            $tm = (int)$now->format('n');
            $td = (int)$now->format('j');
        } else {
            [$td, $tm, $ty] = $tp;
        }
    } else {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
        $ty = (int)$now->format('Y');
        $tm = (int)$now->format('n');
        $td = (int)$now->format('j');
    }

    $py = numero_personal_year($day, $month, $ty);
    $pm = numero_personal_month($py, $tm);
    $pd = numero_personal_day($pm, $td);

    return [
        'ok' => true,
        'core_profile' => [
            'life_path' => $lp,
            'life_path_reduced' => $lpr,
            'expression' => $expr,
            'birth_day' => $bd,
            'system' => $system,
            'name' => trim($fullName),
            'dob' => sprintf('%02d/%02d/%04d', $day, $month, $year),
        ],
        'macro_dasha_pinnacles' => numero_pinnacles($day, $month, $year, $lp),
        'micro_dasha_current' => [
            'target_year' => $ty,
            'target_month' => $tm,
            'target_day' => $td,
            'personal_year' => $py,
            'personal_month' => $pm,
            'personal_day' => $pd,
            'phase_interpretation' => numero_theme_for($py, 'py_'),
        ],
    ];
}
