<?php  
declare(strict_types=1);

if (!defined('BASE_PATH')) exit('No direct script access');

final class VehicleDecoder {

     
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

     
    private const SPECIAL = [
        'BH' => ['label' => 'Bharat Series',
                 'note'  => 'Nationwide registration — no re-registration needed when relocating between states.'],
        'CD' => ['label' => 'Diplomatic',
                 'note'  => 'Corps Diplomatique. Registered to a mission, not an individual.'],
    ];

    

    public static function decode(string $raw): array {
        $out = [
            'valid' => false, 'format' => 'unknown', 'normalised' => '', 'display' => '',
            'state_code' => null, 'state' => null, 'rto_code' => null,
             
            'registering_state' => null, 'registering_authority' => null,
            'series' => null, 'number' => null, 'badges' => [], 'notes' => [],
        ];

        $s = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($s === '') return $out;
        $out['normalised'] = $s;

         
        if (preg_match('/^(\d{2})BH(\d{4})([A-Z]{1,2})$/', $s, $m)) {
            $out['valid']   = true;
            $out['format']  = 'bharat';
            $out['display'] = $m[1] . ' BH ' . $m[2] . ' ' . $m[3];
            $out['series']  = $m[3];
            $out['number']  = $m[2];
            $yr = (int)$m[1];
             
            $out['notes'][]  = 'Registered ' . (2000 + $yr) . ' under the Bharat (BH) series.';
            $out['notes'][]  = self::SPECIAL['BH']['note'];
            $out['badges'][] = ['label' => 'Bharat Series', 'tone' => 'indigo'];
            return $out;
        }

         
        if (preg_match('/^(\d{1,3})CD(\d{1,4})$/', $s, $m)) {
            $out['valid']    = true;
            $out['format']   = 'diplomatic';
            $out['display']  = $m[1] . ' CD ' . $m[2];
            $out['number']   = $m[2];
            $out['notes'][]  = self::SPECIAL['CD']['note'];
            $out['badges'][] = ['label' => 'Diplomatic', 'tone' => 'violet'];
            return $out;
        }

         
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
                $out['registering_authority'] = $st . $out['rto_code'];    
                 
                $out['notes'][] = 'RTO ' . $out['rto_code'] . ' — district not decoded offline.';
            } else {
                $out['notes'][] = 'Unrecognised state code "' . $st . '". Check the number.';
                $out['badges'][] = ['label' => 'Unrecognised state', 'tone' => 'amber'];
            }

             
             
            if ($series === '' && strlen($num) <= 4) {
                $out['notes'][] = 'No letter series — typically an older registration.';
            }
            return $out;
        }

        $out['notes'][] = 'Does not match a known Indian registration format.';
        $out['badges'][] = ['label' => 'Invalid format', 'tone' => 'rose'];
        return $out;
    }

     
    public static function format(string $raw): string {
        $d = self::decode($raw);
        return $d['display'] !== '' ? $d['display'] : strtoupper(trim($raw));
    }

    

    public static function enrich(array $tag, array $scanLogs = []): array {
         
         
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
