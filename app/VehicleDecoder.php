<?php // Version: 260916.14
declare(strict_types=1);

/**
 * app/VehicleDecoder.php — Offline Indian registration-number decoder.
 *
 * WHY OFFLINE
 * There is no free Parivahan/VAHAN API. MoRTH publishes no public endpoint,
 * and every aggregator (Surepass, Signzy, Karza, APIClub) bills roughly
 * ₹2–5 per lookup. Anything marketed as a "free VAHAN API" is scraping the
 * public site — it breaks without warning and moves the legal exposure onto
 * whoever calls it.
 *
 * Everything in this class is derived from the registration number itself
 * plus static tables bundled in this file. That means:
 *   - zero recurring cost
 *   - zero network dependency (works when the internet is down)
 *   - zero personal data: a plate prefix identifies a REGION, never a person
 *
 * WHAT THIS DELIBERATELY DOES NOT DO
 * No owner name, no address, no chassis/engine number, no insurance or PUC
 * status. Those exist only behind a paid, authenticated registry call and
 * carry DPDP Act obligations. This class is the free tier; it is honest
 * about its own limits rather than guessing.
 *
 * DISTRICT CODES
 * The state prefix (first two letters) is authoritative and complete below.
 * District-level RTO codes number well over a thousand, are revised without
 * notice, and are frequently wrong in the copies floating around online —
 * e.g. UP14 is Ghaziabad while UP16 is Gautam Buddh Nagar, a pair that is
 * mixed up constantly. Rather than print a plausible-looking wrong district,
 * this returns the verified state and the raw RTO number, and says the
 * district is not decoded. A curated district table can be added later; the
 * API shape below already accommodates it.
 */

if (!defined('BASE_PATH')) exit('No direct script access');

final class VehicleDecoder {

    /** State / UT codes. Current series plus pre-reorganisation codes still on the road. */
    private const STATES = [
        'AP' => 'Andhra Pradesh',           'AR' => 'Arunachal Pradesh',
        'AS' => 'Assam',                    'BR' => 'Bihar',
        'CG' => 'Chhattisgarh',             'CH' => 'Chandigarh',
        'DD' => 'Daman & Diu',              'DL' => 'Delhi',
        'DN' => 'Dadra & Nagar Haveli',     'GA' => 'Goa',
        'GJ' => 'Gujarat',                  'HP' => 'Himachal Pradesh',
        'HR' => 'Haryana',                  'JH' => 'Jharkhand',
        'JK' => 'Jammu & Kashmir',          'KA' => 'Karnataka',
        'KL' => 'Kerala',                   'LA' => 'Ladakh',
        'LD' => 'Lakshadweep',              'MH' => 'Maharashtra',
        'ML' => 'Meghalaya',                'MN' => 'Manipur',
        'MP' => 'Madhya Pradesh',           'MZ' => 'Mizoram',
        'NL' => 'Nagaland',                 'OD' => 'Odisha',
        'OR' => 'Odisha (old series)',      'PB' => 'Punjab',
        'PY' => 'Puducherry',               'RJ' => 'Rajasthan',
        'SK' => 'Sikkim',                   'TN' => 'Tamil Nadu',
        'TR' => 'Tripura',                  'TS' => 'Telangana',
        'UK' => 'Uttarakhand',              'UA' => 'Uttarakhand (old series)',
        'UP' => 'Uttar Pradesh',            'WB' => 'West Bengal',
        'AN' => 'Andaman & Nicobar',
    ];

    /** Non-standard series that do not follow the STATE-RTO pattern. */
    private const SPECIAL = [
        'BH' => ['label' => 'Bharat Series',
                 'note'  => 'Nationwide registration — no re-registration needed when relocating between states.'],
        'CD' => ['label' => 'Diplomatic',
                 'note'  => 'Corps Diplomatique. Registered to a mission, not an individual.'],
    ];

    /**
     * Decode a registration number.
     *
     * @return array{
     *   valid:bool, format:string, normalised:string, display:string,
     *   state_code:?string, state:?string, rto_code:?string,
     *   series:?string, number:?string, badges:array<int,array{label:string,tone:string}>,
     *   notes:array<int,string>
     * }
     */
    public static function decode(string $raw): array {
        $out = [
            'valid' => false, 'format' => 'unknown', 'normalised' => '', 'display' => '',
            'state_code' => null, 'state' => null, 'rto_code' => null,
            // VAHAN-parity aliases so callers can use registry vocabulary directly.
            'registering_state' => null, 'registering_authority' => null,
            'series' => null, 'number' => null, 'badges' => [], 'notes' => [],
        ];

        $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($s === '') return $out;
        $out['normalised'] = $s;

        // ── Bharat series: 22BH1234AA (2-digit year + BH + 4 digits + 1–2 letters)
        if (preg_match('/^(\d{2})BH(\d{4})([A-Z]{1,2})$/', $s, $m)) {
            $out['valid']   = true;
            $out['format']  = 'bharat';
            $out['display'] = $m[1] . ' BH ' . $m[2] . ' ' . $m[3];
            $out['series']  = $m[3];
            $out['number']  = $m[2];
            $yr = (int)$m[1];
            // BH series began 2021; a 2-digit year is unambiguous for decades.
            $out['notes'][]  = 'Registered ' . (2000 + $yr) . ' under the Bharat (BH) series.';
            $out['notes'][]  = self::SPECIAL['BH']['note'];
            $out['badges'][] = ['label' => 'Bharat Series', 'tone' => 'indigo'];
            return $out;
        }

        // ── Diplomatic: 11CD1234
        if (preg_match('/^(\d{1,3})CD(\d{1,4})$/', $s, $m)) {
            $out['valid']    = true;
            $out['format']   = 'diplomatic';
            $out['display']  = $m[1] . ' CD ' . $m[2];
            $out['number']   = $m[2];
            $out['notes'][]  = self::SPECIAL['CD']['note'];
            $out['badges'][] = ['label' => 'Diplomatic', 'tone' => 'violet'];
            return $out;
        }

        // ── Standard: XX 00 XX 0000  (state, RTO, series 0–3 letters, 1–4 digits)
        if (preg_match('/^([A-Z]{2})(\d{1,2})([A-Z]{0,3})(\d{1,4})$/', $s, $m)) {
            [$all, $st, $rto, $series, $num] = $m;

            $out['format']     = 'standard';
            $out['state_code'] = $st;
            $out['rto_code']   = str_pad($rto, 2, '0', STR_PAD_LEFT);
            $out['series']     = $series !== '' ? $series : null;
            $out['number']     = $num;
            $out['display']    = trim($st . ' ' . $out['rto_code'] . ' ' . $series . ' ' . $num);

            if (isset(self::STATES[$st])) {
                $out['valid'] = true;
                $out['state'] = self::STATES[$st];
                $out['registering_state']     = $out['state'];
                $out['registering_authority'] = $st . $out['rto_code'];   // e.g. UP16
                // District is intentionally NOT guessed — see the class docblock.
                $out['notes'][] = 'RTO ' . $out['rto_code'] . ' — district not decoded offline.';
            } else {
                $out['notes'][] = 'Unrecognised state code "' . $st . '". Check the number.';
                $out['badges'][] = ['label' => 'Unrecognised state', 'tone' => 'amber'];
            }

            // A short number with no letter series usually means an older
            // registration — worth surfacing, but stated as a hint, not a fact.
            if ($series === '' && strlen($num) <= 4) {
                $out['notes'][] = 'No letter series — typically an older registration.';
            }
            return $out;
        }

        $out['notes'][] = 'Does not match a known Indian registration format.';
        $out['badges'][] = ['label' => 'Invalid format', 'tone' => 'rose'];
        return $out;
    }

    /** Pretty-print for display, falling back to the raw input when undecodable. */
    public static function format(string $raw): string {
        $d = self::decode($raw);
        return $d['display'] !== '' ? $d['display'] : strtoupper(trim($raw));
    }

    /**
     * Free enrichment derived from data already in the record — no API.
     * Kept separate from decode() because it needs the tag row, not just
     * the plate.
     */
    public static function enrich(array $tag, array $scanLogs = []): array {
        // Accept the new key, falling back to the legacy one so a record that
        // has not yet been through the optimizer's alias migration still works.
        $plate = (string)($tag['registration_number'] ?? $tag['plate'] ?? '');
        $d     = self::decode($plate);

        $tagKey = (string)($tag['tag_id'] ?? $tag['id'] ?? '');
        $scans  = array_values(array_filter(
            $scanLogs,
            fn($l) => (string)($l['tag_id'] ?? '') === $tagKey
        ));

        $last = null; $emergencies = 0;
        foreach ($scans as $l) {
            if (!empty($l['emergency'])) $emergencies++;
            $ts = strtotime((string)($l['ts'] ?? ''));
            if ($ts && (!$last || $ts > $last)) $last = $ts;
        }

        return [
            'decode'      => $d,
            'scan_count'  => count($scans),
            'last_scan'   => $last ? date('d M Y, H:i', $last) : null,
            'emergencies' => $emergencies,
        ];
    }
}
