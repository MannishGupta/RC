<?php
// core.php — Version: 260916.14
declare(strict_types=1);
if (!defined('BASE_PATH')) exit;

// engine/core.php — AppNumeroEngine (core: reduce, analyze, generateReport, etc.)

date_default_timezone_set('Asia/Kolkata');
class AppNumeroEngine {
    /**
     * Cache-busting token for the APCu copy of meanings.json.
     *
     * BUG THIS FIXES: this was a hardcoded '2027.400' that nobody remembered
     * to bump. The APCu key is derived from it, so after editing
     * meanings.json the key stayed identical and APCu kept serving the OLD
     * data for up to 24h (the store TTL). Deriving it from APP_VERSION means
     * every deploy gets a fresh key automatically, matching the OPcache
     * auto-flush in app/bootstrap.php.
     */
    private static function engineVersion(): string {
        return defined('APP_VERSION') ? (string)APP_VERSION : 'dev';
    }
    private const MASTER_NUMBERS = [11, 22];
    public const CHALDEAN_MAP = ['A'=>1, 'I'=>1, 'J'=>1, 'Q'=>1, 'Y'=>1, 'B'=>2, 'K'=>2, 'R'=>2, 'C'=>3, 'G'=>3, 'L'=>3, 'S'=>3, 'D'=>4, 'M'=>4, 'T'=>4, 'E'=>5, 'H'=>5, 'N'=>5, 'X'=>5, 'U'=>6, 'V'=>6, 'W'=>6, 'O'=>7, 'Z'=>7, 'F'=>8, 'P'=>8];
    // Y is treated as a consonant when it is the FIRST letter of a name/word (e.g. 'Yash', 'Yogesh')
    // and as a vowel when it appears mid-word with no adjacent vowel (e.g. 'Lynn', 'Gypsy').
    // For simplicity and broad correctness, Y is excluded from VOWELS (consonant by default);
    // this matches the most common Chaldean practice for Indian names.
    private const VOWELS = ['A', 'E', 'I', 'O', 'U'];
    private static array $data = [];
    private static bool $initialized = false;
    private static string $langCtx = 'en';

    public static function setLang(string $l): void { self::$langCtx = ($l === 'hi') ? 'hi' : 'en'; }
    public static function getLang(): string { return self::$langCtx; }

    public static function init(): void {
        if (self::$initialized) return;
        $ck = 'numero_data_v' . str_replace('.', '_', self::engineVersion());
        if (function_exists('apcu_fetch') && ($c = apcu_fetch($ck, $ok)) && $ok && is_array($c)) { self::$data = $c; self::$initialized = true; return; }
        // numero_meanings.json is always in cards/ (one level above engine/).
        // dirname(__DIR__) = cards/. Do NOT use BASE_PATH — it is the site root, not cards/.
        $jf = str_replace('\\', '/', dirname(__DIR__)) . '/data/meanings.json';

        // PERFORMANCE FIX: on hosts without APCu (common on shared Windows/
        // Plesk plans), the block above never hits, and this 3,000+ line
        // JSON file would otherwise be re-parsed with json_decode() on
        // EVERY single numero page view. As a fallback, cache the decoded
        // array as a plain PHP file (var_export'd) next to the JSON source.
        // Requiring a PHP array literal is far cheaper than json_decode()
        // on repeat requests, and it benefits from PHP's own OPcache
        // bytecode caching the same way any other .php file does. The
        // cache is invalidated automatically whenever numero_meanings.json
        // is modified (mtime comparison), so editing the JSON always wins.
        // Cache lives in the site-wide DATA_PATH (already created and
        // writable by index.php's bootstrap) rather than assuming a
        // cards/data subfolder exists.
        $cacheDir  = defined('DATA_PATH') ? DATA_PATH : (str_replace('\\', '/', dirname(__DIR__)) . '/data');
        $cacheFile = rtrim($cacheDir, '/\\') . '/.numero_meanings_cache.php';
        if (is_readable($jf)) {
            $jfMtime = @filemtime($jf) ?: 0;
            if (is_readable($cacheFile) && (@filemtime($cacheFile) >= $jfMtime)) {
                $cached = @include $cacheFile;
                if (is_array($cached)) {
                    self::$data = $cached;
                    self::$initialized = true;
                    if (function_exists('apcu_store')) apcu_store($ck, self::$data, 86400);
                    return;
                }
            }

            try {
                self::$data = json_decode(file_get_contents($jf), true, 512, JSON_THROW_ON_ERROR);
                if (function_exists('apcu_store')) apcu_store($ck, self::$data, 86400);

                // Write the PHP-array cache for next time. Best-effort: if the
                // data/ directory isn't writable, this silently falls back to
                // re-parsing JSON every request (same as before this fix) —
                // never a hard failure.
                if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
                $php = "<?php\n// Auto-generated cache of numero_meanings.json — do not edit by hand.\n// Regenerated automatically whenever the source JSON file changes.\nreturn " . var_export(self::$data, true) . ";\n";
                @file_put_contents($cacheFile, $php, LOCK_EX);
            } catch (\Throwable $e) {}
        }
        self::$initialized = true;
    }

    public static function getData(): array { if (!self::$initialized) self::init(); return self::$data; }
    public static function getMap(): array { return self::CHALDEAN_MAP; }
    public static function baseRoot(int $n): int { return self::reduceChaldean($n, false); }
    public static function reduce(int $n, bool $pm = false): int { return self::reduceChaldean($n, $pm); }

    public static function reduceChaldean(int $num, bool $keepMasters = false): int {
        if ($num === 0) return 0;
        if ($num < 0) $num = abs($num); // guard: negative input normalised to positive
        while ($num > 9) {
            if ($keepMasters && in_array($num, self::MASTER_NUMBERS, true)) break;
            $num = array_sum(str_split((string)$num));
        }
        if ($num < 0 || ($num > 9 && !$keepMasters)) throw new RuntimeException("Invalid Chaldean root calculation constraint violation: {$num}");
        return $num;
    }

    /**
     * Returns true when arr forms a 3-number consecutive run, linear or circular.
     * Mirrors ChaldeaMobileEngine::checkSequence() majority-drops logic for name-root triples.
     * Linear: [3,4,5]. Circular wrap: [8,9,1] or [9,1,2].
     */
    public static function isSequential(array $arr): bool {
        if (count($arr) < 3) return false;
        $chk = $arr; sort($chk);
        // Linear
        if ($chk[1] === $chk[0] + 1 && $chk[2] === $chk[1] + 1) return true;
        // Circular wrap-around at 9→1
        if ($chk === [1, 2, 9]) return true;
        if ($chk === [1, 8, 9]) return true;
        return false;
    }

    public static function analyzeName(string $name): array {
        $letters = str_split(strtoupper(preg_replace('/[^A-Z]/', '', $name)));
        $full = 0; $soul = 0; $personality = 0;
        foreach ($letters as $ch) {
            if (!isset(self::CHALDEAN_MAP[$ch])) continue;
            $val = self::CHALDEAN_MAP[$ch]; $full += $val;
            if (in_array($ch, self::VOWELS, true)) $soul += $val; else $personality += $val;
        }
        if ($soul === 0 && count($letters) > 0 && isset(self::CHALDEAN_MAP[$letters[0]])) {
            $soul += self::CHALDEAN_MAP[$letters[0]]; $personality -= self::CHALDEAN_MAP[$letters[0]];
        }
        return [
            'full' => ['compound' => $full, 'root' => self::reduceChaldean($full, false)],
            'soul_urge' => ['compound' => $soul, 'root' => self::reduceChaldean($soul, false)],
            'personality' => ['compound' => $personality, 'root' => self::reduceChaldean($personality, false)]
        ];
    }

    public static function evaluateHarmony(int $nameRoot, int $driver, array $friends): string {
        return ($nameRoot === $driver) ? 'Aligned' : (in_array($nameRoot, $friends, true) ? 'Supportive' : 'Challenging');
    }

    public static function calcDetails(string $str): array {
        $res = self::analyzeName($str);
        return ['display' => $str, 'compound' => $res['full']['compound'], 'root' => $res['full']['root']];
    }
    public static function calcSoulUrge(string $name): array {
        $res = self::analyzeName($name); return ['compound' => $res['soul_urge']['compound'], 'root' => $res['soul_urge']['root']];
    }
    public static function calcPersonality(string $name): array {
        $res = self::analyzeName($name); return ['compound' => $res['personality']['compound'], 'root' => $res['personality']['root']];
    }

    public static function calcHiddenPassion(string $name): array {
        $clean = strtoupper(preg_replace('/[^A-Z]/', '', $name)); $freq = [];
        for ($i = 0; $i < strlen($clean); $i++) if (($v = self::CHALDEAN_MAP[$clean[$i]] ?? 0) > 0) $freq[$v] = ($freq[$v] ?? 0) + 1;
        if (empty($freq)) return ['number' => 0, 'count' => 0, 'all' => []];
        $maxCount = max($freq);
        $nums = array_keys(array_filter($freq, fn($cnt) => $cnt === $maxCount)); sort($nums);
        return ['number' => (int)$nums[0], 'count' => $maxCount, 'all' => $nums];
    }

    public static function calcBalance(string $name): array {
        $parts = array_filter(explode(' ', trim($name))); $sum = 0;
        foreach ($parts as $part) $sum += self::CHALDEAN_MAP[strtoupper(substr($part, 0, 1))] ?? 0;
        return ['compound' => $sum, 'root' => self::reduceChaldean($sum, false)];
    }

    public static function calcSubconsciousSelf(array $missingNums): int { return max(1, 9 - count($missingNums)); }

    public static function calcBridge(int $expressionRoot, int $conductorRoot): int {
        $cDigit = $conductorRoot; while ($cDigit > 9) $cDigit = array_sum(str_split((string)$cDigit));
        $eDigit = $expressionRoot; while ($eDigit > 9) $eDigit = array_sum(str_split((string)$eDigit));
        return ($diff = abs($eDigit - $cDigit)) === 0 ? 0 : self::reduceChaldean($diff, false);
    }

    public static function detectKarmicDebt(array $rawCompounds): array {
        $kc = self::getData()['karmic_debt'] ?? []; $found = [];
        foreach ($rawCompounds as $label => $raw) {
            if (in_array($raw, [13, 14, 16, 19], true)) {
                $found[] = array_merge(['number' => $raw, 'source' => $label], $kc[(string)$raw] ?? ['name' => "Karmic Debt $raw", 'lesson' => '', 'challenge' => '', 'prescription' => '']);
            }
        }
        return $found;
    }

    public static function generateMobilePatterns(array $friendlyRoots, string $currentMobile = '', int $driver = 0, int $seed = 0): array {
        $friendlyRoots = array_values(array_filter($friendlyRoots, fn($r) => is_int($r) && $r >= 1 && $r <= 9));
        $TAIL_ENDINGS = self::getData()['mobile_tail_endings'] ?? [];
        $AVOID_MIDDLE = self::getData()['mobile_karmic_clashes'] ?? ['48', '84', '44', '88'];
        
        $selectedRoots = class_exists('MobileRecommendationEngine') ? MobileRecommendationEngine::suggestRoots($driver, $friendlyRoots, self::getData()['planets'][(string)$driver]['enemies'] ?? [], $seed) : array_slice(array_merge([$driver], $friendlyRoots), 0, 3);
        
        $patterns = [];
        foreach ($selectedRoots as $root) {
            $compat_tails = [];
            foreach ($TAIL_ENDINGS as $tail => $info) {
                if (self::reduceChaldean((int)array_sum(str_split((string)$tail)), false) === $root) {
                    $compat_tails[] = ['ending' => $tail, 'combo' => $info['combo'], 'benefit' => $info['benefit']];
                }
            }
            if (empty($compat_tails)) {
                for ($i=1; $i<=9; $i++) {
                    for ($j=0; $j<=9; $j++) {
                        if (self::reduceChaldean($i+$j, false) === $root && !in_array((string)$i.(string)$j, $AVOID_MIDDLE)) {
                            $compat_tails[] = ['ending' => (string)$i.(string)$j, 'combo' => 'Balanced Frequency', 'benefit' => 'Harmonious alignment with core.'];
                            break 2;
                        }
                    }
                }
            }
            usort($compat_tails, fn($a, $b) => (preg_match('/(\d)\1/', (string)$b['ending']) ? 1 : 0) - (preg_match('/(\d)\1/', (string)$a['ending']) ? 1 : 0) ?: strcmp((string)$a['ending'], (string)$b['ending']));
            $patterns[] = ['target_root' => $root, 'suggested_endings' => array_slice($compat_tails, 0, 2), 'avoid_patterns' => $AVOID_MIDDLE];
        }
        return ['patterns' => $patterns];
    }

    public static function calcHolisticCorrections(string $currentName, int $driver, array $friendlyRoots): array {
        return self::generateNameCorrections($currentName, $driver, $friendlyRoots, '');
    }

    public static function generateNameCorrections(string $currentName, int $driver, array $friendlyRoots, string $relationshipType = ''): array {
        if ($currentName === '' || ctype_digit($currentName)) return [];
        $cd = self::getData()['compounds'] ?? [];
        $LORDS = self::getData()['jyotish_lords'] ?? [];
        $priorityRoots = self::getData()['relation_priority_roots'][strtolower(str_replace(' ', '', trim($relationshipType)))] ?? $friendlyRoots;

        $scoreIt = function(string $name) use ($driver, $priorityRoots, $cd) {
            $m = AppNumeroEngine::analyzeName($name); $fm = AppNumeroEngine::analyzeName(explode(' ', trim($name))[0]);
            $score = ($m['full']['root'] === $driver ? 10 : (in_array($m['full']['root'], $priorityRoots, true) ? 5 : 0)) * 8
                   + ($m['soul_urge']['root'] === $driver ? 10 : (in_array($m['soul_urge']['root'], $priorityRoots, true) ? 5 : 0)) * 4
                   + ($m['personality']['root'] === $driver ? 10 : (in_array($m['personality']['root'], $priorityRoots, true) ? 5 : 0)) * 3
                   + ($fm['full']['root'] === $driver ? 10 : (in_array($fm['full']['root'], $priorityRoots, true) ? 5 : 0)) * 2;
            $cv = $cd[(string)$m['full']['compound']]['verdict'] ?? 'Neutral';
            $score += match($cv) { 'Excellent' => 12, 'Fortunate' => 8, 'Challenging' => -6, 'Warning' => -3, default => 0 };
            return ['name'=>$name, 'score'=>$score, 'full_compound'=>$m['full']['compound'], 'full_root'=>$m['full']['root'], 'su_compound'=>$m['soul_urge']['compound'], 'su_root'=>$m['soul_urge']['root'], 'p_compound'=>$m['personality']['compound'], 'p_root'=>$m['personality']['root'], 'first_compound'=>$fm['full']['compound'], 'first_root'=>$fm['full']['root'], 'compound_verdict'=>$cv];
        };

        $parts = explode(' ', trim($currentName)); $firstName = $parts[0]; $midName = count($parts) >= 3 ? $parts[1] : ''; $lastName = count($parts) >= 2 ? $parts[count($parts) - 1] : '';
        $orig = $scoreIt($currentName); $origScore = $orig['score'];
        $strategies = []; $seen = [];

        $isPronounceable = fn(string $str) => !(preg_match('/[BCDFGHJKLMNPQRSTVWXYZ]{4,}/i', $str) || preg_match('/[AEIOU]{3,}/i', $str));
        $addCand = function(string $name, string $key) use (&$strategies, &$seen, $currentName, $isPronounceable) {
            if (strtolower($name) !== strtolower($currentName) && !isset($seen[$name]) && $isPronounceable($name)) { $seen[$name] = 1; $strategies[$key] = $name; }
        };

        foreach (['a', 'e', 'i', 'u', 'o'] as $v) { $p = $parts; $p[0] = $firstName . $v; $addCand(implode(' ', $p), 'first_append_vowel_' . $v); }
        foreach (['n', 's', 'h', 'r', 'l', 'k', 'm', 't'] as $c) { $p = $parts; $p[0] = $firstName . $c; $addCand(implode(' ', $p), 'first_append_cons_' . $c); }
        if (strlen($firstName) >= 2) { $p = $parts; $p[0] = $firstName . strtolower(substr($firstName, -1)); $addCand(implode(' ', $p), 'first_double_last'); }
        for ($vi = 1; $vi < strlen($firstName) - 1; $vi++) { if (strpos('bcdfgklmnprstvy', strtolower($firstName[$vi])) !== false) { $p = $parts; $p[0] = substr($firstName, 0, $vi + 1) . 'h' . substr($firstName, $vi + 1); $addCand(implode(' ', $p), 'first_silent_h_' . $vi); break; } }
        for ($vi = 1; $vi < strlen($firstName); $vi++) { if (strpos('aeiou', strtolower($firstName[$vi - 1])) !== false) { foreach (['a', 'e'] as $sv) { $p = $parts; $p[0] = substr($firstName, 0, $vi) . $sv . substr($firstName, $vi); $addCand(implode(' ', $p), 'first_vowel_insert_' . $vi . '_' . $sv); break 2; } } }
        if ($lastName && $lastName !== $firstName) { foreach (['a', 'e', 'n', 's', 'h'] as $ch) { $p = $parts; $p[count($p) - 1] = $lastName . $ch; $addCand(implode(' ', $p), 'last_append_' . $ch); } $p = $parts; $p[count($p) - 1] = $lastName . strtolower(substr($lastName, -1)); $addCand(implode(' ', $p), 'last_double'); }
        if ($midName) { foreach (['a', 'e', 'n', 'h'] as $ch) { $p = $parts; $p[1] = $midName . $ch; $addCand(implode(' ', $p), 'mid_append_' . $ch); } }
        if (count($parts) <= 2) { foreach (['Kumar', 'Singh', 'Raj', 'Dev', 'Lal', 'Das', 'Nath'] as $mid) { $addCand($firstName . ' ' . $mid . (count($parts) > 1 ? ' ' . $lastName : ''), 'middle_' . $mid); } }
        if (strlen($firstName) > 4 && in_array(strtolower(substr($firstName, -1)), ['a', 'h', 'e'])) { $p = $parts; $p[0] = substr($firstName, 0, -1); $addCand(implode(' ', $p), 'first_trim'); }

        $scored = [];
        foreach ($strategies as $stratKey => $cand) {
            $m = $scoreIt($cand);
            if ($m['score'] <= $origScore || self::isSequential([$m['full_root'], $m['su_root'], $m['p_root']])) continue;
            if (!($m['full_root'] === $driver || in_array($m['full_root'], $priorityRoots, true)) && !($m['su_root'] === $driver || in_array($m['su_root'], $priorityRoots, true))) continue;
            $m['strategy_key'] = $stratKey; $scored[] = $m;
        }
        usort($scored, fn($a, $b) => $b['score'] - $a['score']);

        $top = []; $usedTypes = []; $seenNames = [];
        foreach ($scored as $c) {
            if (in_array($c['name'], $seenNames, true)) continue;
            $sk = $c['strategy_key'] ?? ''; $partType = strpos($sk, 'last') !== false ? 'L' : (strpos($sk, 'mid') !== false ? 'M' : 'F'); $changeType = $partType . '_' . substr($sk, -2);
            if (in_array($changeType, $usedTypes, true)) continue;
            $usedTypes[] = $changeType; $seenNames[] = $c['name'];
            $newFirst  = explode(' ', $c['name'])[0];
            
            $c['original_name'] = $currentName; $c['orig_full_root'] = $orig['full_root']; $c['orig_full_compound'] = $orig['full_compound']; $c['orig_first_root'] = $orig['first_root']; $c['orig_first_compound'] = $orig['first_compound']; $c['orig_su_root'] = $orig['su_root']; $c['orig_su_compound'] = $orig['su_compound']; $c['orig_p_root'] = $orig['p_root']; $c['orig_p_compound'] = $orig['p_compound']; $c['orig_score'] = $origScore; $c['score_improvement'] = $c['score'] - $origScore;
            $c['compound_name'] = self::$data['compounds'][(string)$c['full_compound']]['name'] ?? '';
            $c['added_letters'] = strlen($newFirst) > strlen($firstName) ? strtoupper(substr($newFirst, strlen($firstName))) : (strlen($newFirst) < strlen($firstName) ? '-' . strtoupper(substr($firstName, strlen($newFirst))) : '~');
            $c['change_location'] = $partType === 'L' ? 'Last Name' : ($partType === 'M' ? 'Middle Name' : 'First Name');
            $c['strategy_label'] = in_array(strtoupper(ltrim($c['added_letters'], '-~')), self::VOWELS) ? 'Vowel addition → boosts Soul Urge' : 'Consonant addition → boosts Personality';
            $c['planet_sig'] = $LORDS[$c['full_root']] ?? ['planet' => '', 'areas' => '']; $c['su_planet'] = $LORDS[$c['su_root']] ?? ['planet' => '', 'areas' => '']; $c['p_planet'] = $LORDS[$c['p_root']] ?? ['planet' => '', 'areas' => ''];
            
            $top[] = $c; if (count($top) >= 3) break;
        }
        return $top;
    }

    public static function buildCompatibilityMetrics(array $reportA, array $reportB): array {
        $lang = self::getLang();
        $score = function(array $r): array {
            $gridScore = max(0, 100 - count($r['missing'] ?? []) * 11);
            $yogaScore = min(count($r['yogas'] ?? []) * 20, 100);
            $nameScore  = ['Aligned'=>100,'Supportive'=>80,'Neutral'=>60,'Challenging'=>20][$r['name_matrix']['harmony']['status'] ?? 'Neutral'] ?? 60;
            $mobileScore = ['Highly Supportive'=>100,'Friendly'=>80,'Not Provided'=>50,'Neutral'=>50,'Challenging'=>20][$r['mobile']['compatibility'] ?? 'Not Provided'] ?? 50;
            $karmicLoad = max(0, 100 - count($r['karmic_debt'] ?? []) * 25);
            return [$gridScore, $yogaScore, $nameScore, $mobileScore, $karmicLoad, (int)($r['summary']['score'] ?? 50)];
        };
        
        $driverCompat = function(int $dA, int $dB, array $planetsA) use ($lang): string {
            if ($dA === $dB) return $lang === 'hi' ? 'दर्पण (समान चालक)' : 'Mirror (Same Driver)';
            if (in_array($dB, $planetsA['data']['friends'] ?? [], true)) return $lang === 'hi' ? 'सामंजस्यपूर्ण' : 'Harmonious';
            if (in_array($dB, $planetsA['data']['enemies'] ?? [], true)) return 'Challenging';
            return 'Neutral';
        };
        
        $mA = $score($reportA); $mB = $score($reportB);
        $avgCompat = (int)round(array_sum(array_map(fn($a, $b) => 100 - abs($a - $b), $mA, $mB)) / count($mA));
        
        return [
            'metrics_a' => $mA, 'metrics_b' => $mB,
            'driver_compat' => $driverCompat((int)$reportA['core']['driver'], (int)$reportB['core']['driver'], $reportA['lucky_driver'] ?? []),
            'overall_score' => $avgCompat,
            'labels' => (self::getData()['scoring']['compat_axis_labels'][$lang] ?? ['Grid Strength', 'Yoga Power', 'Name Harmony', 'Mobile Freq', 'Karmic Balance', 'Alignment'])
        ];
    }

    public static function generateReport(array $p, string $tier = 'elite'): array {
        $tier = in_array($tier, ['free', 'pro', 'elite'], true) ? $tier : 'elite';
        if (isset($p['lang'])) self::setLang((string)$p['lang']);
        self::init();
        
        $rawDOB = (string)($p['dob'] ?? $p['date_of_birth'] ?? '');
        $ts = is_numeric($rawDOB) ? (int)$rawDOB : strtotime(str_replace('/', '-', $rawDOB));
        if (!$ts) return ['status' => 'error', 'message' => 'Invalid DOB'];

        $dobDigits = str_pad(date('d', $ts), 2, '0', STR_PAD_LEFT) . str_pad(date('m', $ts), 2, '0', STR_PAD_LEFT) . date('Y', $ts);
        $rawDay = (int)date('d', $ts); $rawMonth = (int)date('m', $ts); $rawYear = (int)date('Y', $ts);
        
        $driver = self::reduceChaldean($rawDay, true);
        $conductorSum = 0; foreach (str_split(date('dmY', $ts)) as $ch) $conductorSum += (int)$ch;
        $conductor = self::reduceChaldean($conductorSum, true);
        
        $baseDriverDigit = $driver; while ($baseDriverDigit > 9) $baseDriverDigit = array_sum(str_split((string)$baseDriverDigit));
        $baseDriver = (int)(in_array($driver, self::MASTER_NUMBERS, true) ? $driver : $baseDriverDigit);
        
        $cleanName = trim((string)($p['name'] ?? 'Unknown'));
        if (mb_strlen($cleanName) < 2) $cleanName = 'Unknown'; // guard: 1-char names give nonsensical analysis
        $deterministicSeed = (int)(hexdec(substr(hash('sha256', $cleanName . $dobDigits), 0, 7)) & 0x7FFFFFFF); // safe on 32-bit

        $grid = GridEngine::build($dobDigits);
        $yogas = [];
        foreach (YogaEngine::detect(['driver' => $baseDriverDigit, 'conductor' => $conductor], $grid) as $y) $yogas[$y['name']] = $y;
        foreach (ArrowEngine::detect($grid['counts'], self::$data['arrows'] ?? []) as $y) $yogas[$y['name']] = $y;
        $yogas = array_values($yogas); usort($yogas, fn($a, $b) => strcmp($a['name'], $b['name']));

        $planet = self::$data['planets'][(string)$baseDriverDigit] ?? ['name' => 'Master Vibration', 'friends' => [1, 5, 6]];
        $planetFriends = array_values(array_filter($planet['friends'] ?? [1, 5, 6], fn($f) => is_int($f) && $f >= 1 && $f <= 9));
        $planet['friends'] = $planetFriends;

        $mobile = MobileEngine::analyze((string)($p['phone'] ?? $p['mobile'] ?? ''), $baseDriverDigit, $planetFriends);
        $py = class_exists('PersonalYearEngine') ? PersonalYearEngine::calculate($rawDay, $rawMonth) : ['year' => (int)date('Y'), 'number' => 1];
        $diagnostics = DiagnosticsEngine::analyzeMissing($grid['missing'], self::$data['missing'] ?? []);

        $firstNameStr = (explode(' ', trim($cleanName))[0]) ?: $cleanName; // explode() is stateless; strtok() has shared global state
        $nameMatrixCore = self::analyzeName($cleanName);
        $firstNameCore = self::analyzeName($firstNameStr);

        $firstNameData = ['display' => $firstNameStr, 'compound' => $firstNameCore['full']['compound'], 'root' => $firstNameCore['full']['root'], 'compound_info' => CompoundEngine::analyze($firstNameCore['full']['compound'], self::$data['compounds'] ?? [])];
        $fullNameData = ['display' => $cleanName, 'compound' => $nameMatrixCore['full']['compound'], 'root' => $nameMatrixCore['full']['root'], 'compound_info' => CompoundEngine::analyze($nameMatrixCore['full']['compound'], self::$data['compounds'] ?? [])];
        $soulUrge = ['compound' => $nameMatrixCore['soul_urge']['compound'], 'root' => $nameMatrixCore['soul_urge']['root'], 'compound_info' => CompoundEngine::analyze($nameMatrixCore['soul_urge']['compound'], self::$data['compounds'] ?? [])];
        $personality = ['compound' => $nameMatrixCore['personality']['compound'], 'root' => $nameMatrixCore['personality']['root'], 'compound_info' => CompoundEngine::analyze($nameMatrixCore['personality']['compound'], self::$data['compounds'] ?? [])];

        $hiddenPassion = self::calcHiddenPassion($cleanName);
        $balance = self::calcBalance($cleanName);
        $balance['compound_info'] = CompoundEngine::analyze($balance['compound'], self::$data['compounds'] ?? []);
        $subconsciousSelf = self::calcSubconsciousSelf($grid['missing']);
        $bridge = self::calcBridge($fullNameData['root'], $conductor);
        $harmonyStatus = self::evaluateHarmony($fullNameData['root'], $baseDriverDigit, $planetFriends);

        $nameIsOptimal = (in_array($fullNameData['root'], $planetFriends, true) || $fullNameData['root'] === $baseDriverDigit) && in_array($fullNameData['compound_info']['verdict'] ?? '', ['Fortunate', 'Excellent'], true);
        
        $holisticCorrections = [];
        if (!$nameIsOptimal) {
            try {
                $holisticCorrections = self::generateNameCorrections($cleanName, $baseDriverDigit, $planetFriends, (string)($p['relationship_type'] ?? ''));
            } catch (Exception $e) {
                error_log("[NumeroEngine] Name correction calculation failed: " . $e->getMessage());
            }
        }

        $karmicDebt = self::detectKarmicDebt(['Birth Day (Driver)' => $rawDay, 'Life Path (Conductor)' => $conductorSum, 'Soul Urge Compound' => $soulUrge['compound'], 'Expression Compound' => $fullNameData['compound']]);
        
        $nameConflict = []; $nameGrid = null;
        if ($tier === 'elite') {
            $nameDigitsOnly = ''; $_ngMap = self::getMap();
            for ($i = 0; $i < strlen($cleanName); $i++) if (isset($_ngMap[strtoupper($cleanName[$i])])) $nameDigitsOnly .= self::baseRoot((int)$_ngMap[strtoupper($cleanName[$i])]);
            if (!empty($nameDigitsOnly)) {
                $nameGrid = GridEngine::build($nameDigitsOnly);
                $healed = []; $excess = [];
                for ($n = 1; $n <= 9; $n++) {
                    if ($grid['counts'][$n] === 0 && $nameGrid['counts'][$n] > 0) $healed[] = $n;
                    if ($grid['counts'][$n] >= 2 && $nameGrid['counts'][$n] > 0) $excess[] = $n;
                }
                $weights = [1=>2, 2=>1, 3=>1, 4=>3, 5=>3, 6=>1, 7=>0, 8=>3, 9=>1]; $conflictScore = 0;
                foreach ($healed as $n) $conflictScore -= ($weights[$n] ?? 1);
                foreach ($excess as $n) $conflictScore += ($weights[$n] ?? 1);
                $conflictScore = max(-6, min($conflictScore, 6));
                $conflictLevel = match(true) { $conflictScore >= 6 => 'High', $conflictScore >= 2 => 'Moderate', $conflictScore > 0 => 'Low', default => 'None' };
                $desc = $conflictScore > 0 ? "Name overloads {$conflictScore} dominant birth energies." : ($conflictScore < 0 ? "Name compensates for missing birth energies." : "Name is neutral relative to birth grid.");
                $nameConflict = ['level' => $conflictLevel, 'score' => $conflictScore, 'healed' => $healed, 'excess' => $excess, 'desc' => $desc];
            }
        }

        $scoreData = ScoringEngine::derive(['grid' => $grid, 'mobile' => $mobile, 'yogas' => $yogas, 'name_matrix' => ['harmony' => ['status' => $harmonyStatus]]], $nameConflict);

        $pinnacleData = class_exists('PinnacleEngine') ? PinnacleEngine::calculate($rawDay, $rawMonth, $rawYear, $conductorSum) : ['pinnacles' => [['number'=>1, 'phase'=>1, 'age_range'=>"0 – 36"],['number'=>1, 'phase'=>2, 'age_range'=>"37 – 45"],['number'=>1, 'phase'=>3, 'age_range'=>"46 – 54"],['number'=>1, 'phase'=>4, 'age_range'=>"55 – Life"]], 'challenges' => [['number'=>0, 'phase'=>1, 'age_range'=>"0 – 36"],['number'=>0, 'phase'=>2, 'age_range'=>"37 – 45"],['number'=>0, 'phase'=>3, 'age_range'=>"46 – 54"],['number'=>0, 'phase'=>4, 'age_range'=>"55 – Life"]]];
        $pCycle = class_exists('PersonalCycleEngine') ? PersonalCycleEngine::calculate((string)date('Y-m-d', $ts)) : ['personal_year' => 1, 'personal_month' => 1, 'personal_day' => 1, 'today' => date('d M Y'), 'month_name' => date('F')];
        // Maturity = Expression (fullName root) + LifePath — NOT driver + conductor
        $maturity = class_exists('MaturityEngine') ? MaturityEngine::calculate($fullNameData['root'], $conductor) : ['number' => 1, 'activates_at' => 35, 'note' => ''];
        $timeline = class_exists('TimelineEngine') ? TimelineEngine::build((string)date('Y-m-d', $ts)) : [];

        foreach ($pinnacleData['pinnacles'] as &$pin) $pin['data'] = self::$data['pinnacles'][(string)$pin['number']] ?? [];
        foreach ($pinnacleData['challenges'] as &$cha) $cha['data'] = self::$data['challenges'][(string)$cha['number']] ?? [];
        unset($pin, $cha);

        $report = [
            'status' => 'success', 'tier' => $tier,
            'profile' => ['name' => $cleanName, 'dob' => date('d M Y', $ts), 'photo' => (string)($p['photo'] ?? ''), 'gender' => (string)($p['gender'] ?? 'Unknown'), 'dob_raw' => date('Y-m-d', $ts)],
            'core' => ['driver' => $baseDriverDigit, 'raw_driver' => $driver, 'conductor' => $conductor, 'raw_conductor' => $conductorSum],
            'grid' => $grid, 'yogas' => $yogas, 'missing' => $diagnostics['details'],
            'mobile' => $mobile,
            'lucky_driver' => ['name' => $planet['name'], 'hi_name' => $planet['hi_name'] ?? $planet['name'], 'friends' => $planetFriends, 'data' => $planet],
            'personal_year' => ['year' => $py['year'], 'number' => $py['number'], 'title' => self::$data['personal_years'][(string)$py['number']]['title'] ?? ('Personal Year ' . $py['number']), 'action_timing' => self::$data['personal_years'][(string)$py['number']]['action_timing'] ?? 'Observe and align.', 'theme' => self::$data['personal_years'][(string)$py['number']]['theme'] ?? 'Transition phase.', 'avoid' => self::$data['personal_years'][(string)$py['number']]['avoid'] ?? 'Impulsive actions.'],
            'summary' => ['score' => $scoreData['total'], 'breakdown' => $scoreData['breakdown'], 'narrative' => $scoreData['narrative'], 'missing_numbers' => $grid['missing'], 'risk_index' => $diagnostics['risk_index']],
            'name_matrix' => [
                'first' => $firstNameData, 'full' => $fullNameData, 'soul_urge' => $soulUrge, 'personality' => $personality, 'hidden_passion' => $hiddenPassion, 'balance' => $balance, 'subconscious_self' => $subconsciousSelf, 'bridge' => $bridge,
                'harmony' => ['status' => $harmonyStatus, 'desc' => 'Relational vibrational alignment vs planetary driver.'],
                'corrections' => $holisticCorrections, 'name_is_optimal' => $nameIsOptimal
            ],
            'bridge' => $bridge, 'karmic_debt' => $karmicDebt,
            'interpretation' => InterpretationEngine::build(['driver' => $baseDriverDigit], $planet, $diagnostics['details']),
            'planetInfo' => self::$data['planets'] ?? [],
            'pinnacles' => $pinnacleData,
            'personal_cycle' => array_merge($pCycle, ['month_data' => self::$data['personal_years'][(string)$pCycle['personal_month']] ?? [], 'day_data' => self::$data['personal_years'][(string)$pCycle['personal_day']] ?? []]),
            'maturity' => $maturity, 'timeline' => $timeline,
        ];

        if ($nameGrid) {
            $report['name_grid'] = $nameGrid;
            $report['name_matrix']['conflict'] = $nameConflict;
        }

        $report['mobile']['patterns'] = [];
        $report['mobile']['alignment_score'] = $mobile['score'] ?? 0;

        if (!empty($mobile['display']) && $mobile['display'] !== 'N/A') {
            $mobData = self::generateMobilePatterns($planetFriends, (string)($p['phone'] ?? ''), $baseDriverDigit, $deterministicSeed);
            $report['mobile']['patterns'] = $mobData['patterns'] ?? [];
        }

        if ($tier === 'free') {
            $report['missing'] = []; $report['name_matrix']['corrections'] = []; $report['mobile']['patterns'] = []; $report['interpretation'] = null; $report['summary']['risk_index'] = null; $report['karmic_debt'] = [];
        } elseif ($tier === 'pro') {
            $report['interpretation'] = null;
        }
        
        $harmonized = FinalDecisionEngine::harmonize($report);

        // NumerologyValidator.php is in cards/ root, one level above engine/.
        $validatorPath = str_replace('\\', '/', dirname(__DIR__)) . '/NumerologyValidator.php';
        if (file_exists($validatorPath)) {
            require_once $validatorPath;
            if (class_exists('NumerologyValidator')) {
                $validation = NumerologyValidator::validate($harmonized);
                $harmonized['validation'] = $validation;
                if ($validation['score'] < 50) {
                    $harmonized['status'] = 'validation_failed';
                    error_log("[NumerologyValidator] CRITICAL FAILURE. Score {$validation['score']}. Payload flagged.");
                } elseif ($validation['score'] < 70) {
                    error_log("[NumerologyValidator] Score {$validation['score']} [Status: {$validation['status']}]: " . implode(" | ", array_merge($validation['errors'], $validation['warnings'])));
                }
            }
        }

        return $harmonized;
    }
}
