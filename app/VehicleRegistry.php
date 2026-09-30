<?php // Version: 260916.14
declare(strict_types=1);

/**
 * app/VehicleRegistry.php — RC compliance lookup (PUC, insurance, fitness).
 *
 * COMPLEMENTS, DOES NOT REPLACE, app/VehicleDecoder.php:
 *   VehicleDecoder  free, offline, always available — state, RTO, series
 *   VehicleRegistry paid, networked, cached — PUC, insurance, RC status
 * If this class is unconfigured or the network is down, the decoder still
 * works and the tag remains fully usable. Compliance data is additive.
 *
 * ── PROVIDER MODEL ────────────────────────────────────────────────────────
 * Deliberately provider-agnostic. Direct scraping of parivahan.gov.in is not
 * viable: NIC enforces session tokens, OTP logins and CAPTCHAs, and blocks
 * high-frequency subnets. Two supported routes:
 *
 *   'commercial' — Surepass / Attestr / Signzy / Cashfree / Sandbox etc.
 *                  Bearer-token REST. ₹0.50–₹2.00 per call. Works today.
 *   'apisetu'    — apisetu.gov.in, MoRTH Registration of Vehicles.
 *                  OAuth2 client credentials. Free, but access must be
 *                  granted to a registered entity.
 *
 * Response shapes differ per provider, so normalise() maps a wide set of
 * known key spellings onto one internal schema. Adding a provider usually
 * means adding key names to those lists, not writing new code.
 *
 * ── WHAT IS STORED ────────────────────────────────────────────────────────
 * Only the whitelisted compliance fields below. The raw provider response is
 * never persisted: it can contain owner name, father's name and address,
 * which this system has no purpose for and which would make an unmanaged
 * liability out of cartags data under the DPDP Act 2023. Chassis and engine
 * numbers are masked to their last four characters — enough to match against
 * a physical plate during an audit, useless for anything else.
 *
 * ── COST CONTROL ──────────────────────────────────────────────────────────
 * Fetches happen on explicit admin action or a scheduled refresh, and are
 * cached for CACHE_DAYS. Nothing on the PUBLIC scan path may ever trigger a
 * lookup — a paid API call reachable by an anonymous QR scan is a trivially
 * abusable cost-amplification vector.
 */

if (!defined('BASE_PATH')) exit('No direct script access');

final class VehicleRegistry {

    /** Re-fetch after this many days. Insurance/PUC change slowly. */
    private const CACHE_DAYS = 30;

    /** Only these fields are ever written to disk. */
    private const KEEP = [
        'registration_number', 'maker_model', 'vehicle_class', 'fuel_type', 'colour',
        'registration_date', 'rc_status', 'registering_authority',
        'insurance_upto', 'insurance_company', 'pucc_upto',
        'fitness_upto', 'tax_upto', 'emission_norms',
        'financier', 'chassis_last4', 'engine_last4', 'owner_serial',
    ];

    private static function store(): string { return DATA_PATH . '/vehicle_registry.json'; }

    /** Provider config lives with the other secrets, never in source. */
    private static function config(): array {
        $f = DATA_PATH . '/registry_config.php';
        if (!is_readable($f)) return [];
        $c = @include $f;
        return is_array($c) ? $c : [];
    }

    public static function isConfigured(): bool {
        $c = self::config();
        return !empty($c['enabled']) && !empty($c['endpoint']) && !empty($c['api_key']);
    }

    // ─── Cache ────────────────────────────────────────────────────────────

    public static function all(): array {
        $f = self::store();
        if (!is_readable($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    /** Cached record for a registration number, or null. */
    public static function get(string $regNo): ?array {
        $k = self::key($regNo);
        $all = self::all();
        return $all[$k] ?? null;
    }

    public static function isStale(?array $rec): bool {
        if (!$rec || empty($rec['fetched_at'])) return true;
        return (time() - (int)$rec['fetched_at']) > (self::CACHE_DAYS * 86400);
    }

    private static function key(string $regNo): string {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $regNo) ?? '');
    }

    private static function put(string $regNo, array $rec): void {
        $all = self::all();
        $all[self::key($regNo)] = $rec;
        @file_put_contents(self::store(), json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /** Drop a cached record — call when a tag is deleted. */
    public static function forget(string $regNo): void {
        $all = self::all();
        unset($all[self::key($regNo)]);
        @file_put_contents(self::store(), json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    // ─── Fetch ────────────────────────────────────────────────────────────

    /**
     * @param bool $force ignore the cache TTL
     * @return array{ok:bool, cached:bool, data:?array, error:?string}
     */
    public static function fetch(string $regNo, bool $force = false): array {
        $regNo = self::key($regNo);
        if ($regNo === '') return ['ok' => false, 'cached' => false, 'data' => null, 'error' => 'Empty registration number'];

        $cached = self::get($regNo);
        if (!$force && $cached && !self::isStale($cached)) {
            return ['ok' => true, 'cached' => true, 'data' => $cached, 'error' => null];
        }

        if (!self::isConfigured()) {
            return ['ok' => false, 'cached' => (bool)$cached, 'data' => $cached,
                    'error' => 'Registry lookup is not configured. See data/registry_config.php.'];
        }

        $c = self::config();
        try {
            $raw = ($c['provider'] ?? 'commercial') === 'apisetu'
                 ? self::callApiSetu($regNo, $c)
                 : self::callCommercial($regNo, $c);
        } catch (Throwable $e) {
            if (class_exists('AppLog')) AppLog::error('Registry fetch failed: ' . $e->getMessage(), ['reg' => $regNo]);
            // Serve stale data rather than nothing — an old PUC date is more
            // useful than a blank panel, as long as the age is shown.
            return ['ok' => false, 'cached' => (bool)$cached, 'data' => $cached, 'error' => $e->getMessage()];
        }

        $rec = self::normalise($raw);
        $rec['registration_number'] = $regNo;
        $rec['fetched_at'] = time();
        self::put($regNo, $rec);

        if (class_exists('AppLog')) AppLog::info('Registry fetched', ['reg' => $regNo]);
        return ['ok' => true, 'cached' => false, 'data' => $rec, 'error' => null];
    }

    private static function callCommercial(string $regNo, array $c): array {
        $ch = curl_init((string)$c['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                ($c['request_field'] ?? 'vehicle_number') => $regNo,
            ], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $c['api_key'],
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') throw new RuntimeException('Network error: ' . $err);
        if ($code === 404) throw new RuntimeException('Vehicle not found in registry.');
        if ($code === 429) throw new RuntimeException('Provider rate limit reached. Try again shortly.');
        if ($code !== 200)  throw new RuntimeException("Provider returned HTTP {$code}.");

        $d = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
        return is_array($d) ? ($d['data'] ?? $d['result'] ?? $d) : [];
    }

    private static function callApiSetu(string $regNo, array $c): array {
        // OAuth2 client-credentials, then the MoRTH resource.
        $tok = curl_init((string)($c['token_url'] ?? ''));
        curl_setopt_array($tok, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $c['client_id'] ?? '',
                'client_secret' => $c['api_key'],
            ]),
            CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $tr = curl_exec($tok); curl_close($tok);
        $tj = json_decode((string)$tr, true);
        $access = $tj['access_token'] ?? '';
        if ($access === '') throw new RuntimeException('API Setu token request failed.');

        $ch = curl_init(rtrim((string)$c['endpoint'], '/') . '/' . rawurlencode($regNo));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $access],
            CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) throw new RuntimeException("API Setu returned HTTP {$code}.");
        $d = json_decode((string)$body, true, 512, JSON_THROW_ON_ERROR);
        return is_array($d) ? ($d['data'] ?? $d) : [];
    }

    // ─── Normalisation ────────────────────────────────────────────────────

    /** First non-empty value among several possible key spellings. */
    private static function pick(array $d, array $keys): string {
        foreach ($keys as $k) {
            if (isset($d[$k]) && trim((string)$d[$k]) !== '') return trim((string)$d[$k]);
        }
        return '';
    }

    private static function mask4(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        return strlen($v) <= 4 ? $v : str_repeat('•', 6) . substr($v, -4);
    }

    /** Map a provider payload onto the internal schema, keeping only KEEP. */
    private static function normalise(array $d): array {
        $out = [
            'maker_model'      => trim(self::pick($d, ['maker_description','maker_desc','maker']) . ' '
                                     . self::pick($d, ['maker_model','model','vehicle_model'])),
            'vehicle_class'    => self::pick($d, ['vehicle_class','class','vh_class_desc','vehicle_category']),
            'fuel_type'        => self::pick($d, ['fuel_type','fuel','fuel_desc']),
            'colour'           => self::pick($d, ['color','colour','vehicle_colour']),
            'registration_date'=> self::pick($d, ['registration_date','regn_dt','rc_regn_dt']),
            'rc_status'        => self::pick($d, ['rc_status','status','vehicle_status']),
            'registering_authority' => self::pick($d, ['registered_at','rto','registering_authority','rc_registered_at']),
            'insurance_upto'   => self::pick($d, ['insurance_upto','insurance_validity','insurance_valid_upto','rc_insurance_upto']),
            'insurance_company'=> self::pick($d, ['insurance_company','insurer','rc_insurance_comp']),
            'pucc_upto'        => self::pick($d, ['pucc_upto','pollution_upto','puc_valid_upto','pucc_validity']),
            'fitness_upto'     => self::pick($d, ['fitness_upto','fit_upto','rc_fit_upto']),
            'tax_upto'         => self::pick($d, ['tax_upto','rc_tax_upto']),
            'emission_norms'   => self::pick($d, ['norms_type','emission_norms','rc_norms_desc']),
            'financier'        => self::pick($d, ['financier','financer','rc_financer']),
            'chassis_last4'    => self::mask4(self::pick($d, ['chassis_number','chassis','rc_chasi_no'])),
            'engine_last4'     => self::mask4(self::pick($d, ['engine_number','engine','rc_eng_no'])),
            'owner_serial'     => self::pick($d, ['owner_serial_number','owner_count','rc_owner_sr']),
        ];
        // Whitelist enforcement: anything not in KEEP never reaches disk.
        return array_intersect_key($out, array_flip(self::KEEP));
    }

    // ─── Presentation helpers ─────────────────────────────────────────────

    /**
     * Traffic-light state for a validity date.
     * @return array{state:string, label:string, days:?int}
     */
    public static function validity(string $date): array {
        $date = trim($date);
        if ($date === '') return ['state' => 'unknown', 'label' => 'Not available', 'days' => null];
        $ts = strtotime(str_replace('/', '-', $date));
        if (!$ts) return ['state' => 'unknown', 'label' => $date, 'days' => null];

        $days = (int)floor(($ts - strtotime('today')) / 86400);
        if ($days < 0)  return ['state' => 'expired', 'label' => 'Expired ' . date('d M Y', $ts), 'days' => $days];
        if ($days <= 30) return ['state' => 'soon',   'label' => 'Expires in ' . $days . ' day' . ($days === 1 ? '' : 's'), 'days' => $days];
        return ['state' => 'ok', 'label' => 'Valid to ' . date('d M Y', $ts), 'days' => $days];
    }

    /** Compliance items needing attention, for the fleet alert view. */
    public static function alerts(array $rec): array {
        $out = [];
        foreach ([
            'insurance_upto' => 'Insurance',
            'pucc_upto'      => 'PUC',
            'fitness_upto'   => 'Fitness',
        ] as $field => $label) {
            $v = self::validity((string)($rec[$field] ?? ''));
            if (in_array($v['state'], ['expired', 'soon'], true)) {
                $out[] = ['field' => $label, 'state' => $v['state'], 'label' => $v['label']];
            }
        }
        return $out;
    }
}
