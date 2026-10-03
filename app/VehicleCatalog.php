<?php
/**
 * VehicleCatalog.php — Indian Automotive Vehicle Tagging Catalog
 * Version: 260919.01
 *
 * Production module for Indian market vehicle make/model resolution,
 * logo CDN fallback chain, and search for vehicle tags / asset labels.
 *
 * Usage:
 *   require_once BASE_PATH . '/app/VehicleCatalog.php';
 *   $logo  = VehicleCatalog::getVehicleLogo('Maruti Suzuki');
 *   $photo = VehicleCatalog::getCarModelPhoto('Tata', 'Nexon', 2024);
 *   $hits  = VehicleCatalog::searchVehicleTags('swift cng');
 */
declare(strict_types=1);

if (!class_exists('VehicleCatalog')) {

final class VehicleCatalog
{
    /** Local override directory relative to BASE_PATH (optional). */
    public const LOCAL_ASSETS = 'assets/cars';

    /**
     * Hardcoded Indian market catalog.
     * Structure: make => [ 'slug', 'segment', 'models' => [ model => [body, fuels[], years?] ] ]
     */
    private static array $catalog = [
        // ── Mass market ──────────────────────────────────────────
        'Maruti Suzuki' => [
            'slug' => 'maruti-suzuki',
            'segment' => 'mass',
            'models' => [
                'Alto K10'   => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'S-Presso'   => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'Wagon R'    => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'Celerio'    => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'Swift'      => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'Dzire'      => ['body' => 'Sedan',     'fuels' => ['Petrol', 'CNG']],
                'Baleno'     => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'Brezza'     => ['body' => 'SUV',       'fuels' => ['Petrol', 'CNG']],
                'Ertiga'     => ['body' => 'MPV',       'fuels' => ['Petrol', 'CNG']],
                'XL6'        => ['body' => 'MPV',       'fuels' => ['Petrol', 'CNG']],
                'Grand Vitara'=> ['body' => 'SUV',      'fuels' => ['Petrol', 'CNG', 'Hybrid']],
                'Jimny'      => ['body' => 'SUV',       'fuels' => ['Petrol']],
                'Invicto'    => ['body' => 'MPV',       'fuels' => ['Hybrid']],
                'Fronx'      => ['body' => 'SUV',       'fuels' => ['Petrol', 'CNG']],
            ],
        ],
        'Hyundai' => [
            'slug' => 'hyundai',
            'segment' => 'mass',
            'models' => [
                'Grand i10 Nios' => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'i20'            => ['body' => 'Hatchback', 'fuels' => ['Petrol']],
                'Aura'           => ['body' => 'Sedan',     'fuels' => ['Petrol', 'CNG']],
                'Venue'          => ['body' => 'SUV',       'fuels' => ['Petrol', 'Diesel']],
                'Venue N Line'   => ['body' => 'SUV',       'fuels' => ['Petrol']],
                'Creta'          => ['body' => 'SUV',       'fuels' => ['Petrol', 'Diesel']],
                'Alcazar'        => ['body' => 'SUV',       'fuels' => ['Petrol', 'Diesel']],
                'Tucson'         => ['body' => 'SUV',       'fuels' => ['Petrol', 'Diesel']],
                'Exter'          => ['body' => 'SUV',       'fuels' => ['Petrol', 'CNG']],
                'Ioniq 5'        => ['body' => 'SUV',       'fuels' => ['EV']],
            ],
        ],
        'Tata' => [
            'slug' => 'tata',
            'segment' => 'mass',
            'models' => [
                'Tiago'    => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'Tigor'    => ['body' => 'Sedan',     'fuels' => ['Petrol', 'CNG', 'EV']],
                'Altroz'   => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'Diesel', 'CNG']],
                'Punch'    => ['body' => 'SUV',       'fuels' => ['Petrol', 'CNG', 'EV']],
                'Nexon'    => ['body' => 'SUV',       'fuels' => ['Petrol', 'Diesel', 'EV']],
                'Harrier'  => ['body' => 'SUV',       'fuels' => ['Diesel']],
                'Safari'   => ['body' => 'SUV',       'fuels' => ['Diesel']],
                'Curvv'    => ['body' => 'SUV',       'fuels' => ['Petrol', 'Diesel', 'EV']],
            ],
        ],
        'Mahindra' => [
            'slug' => 'mahindra',
            'segment' => 'mass',
            'models' => [
                'Bolero'        => ['body' => 'SUV', 'fuels' => ['Diesel']],
                'Bolero Neo'    => ['body' => 'SUV', 'fuels' => ['Diesel']],
                'Scorpio-N'     => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'Scorpio Classic'=> ['body' => 'SUV','fuels' => ['Diesel']],
                'XUV300'        => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'XUV700'        => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'XUV 3XO'       => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'Thar'          => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'Thar Roxx'     => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'BE 6e'         => ['body' => 'SUV', 'fuels' => ['EV']],
                'XUV.e9'        => ['body' => 'SUV', 'fuels' => ['EV']],
            ],
        ],
        'Kia' => [
            'slug' => 'kia',
            'segment' => 'mass',
            'models' => [
                'Sonet'   => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'Seltos'  => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'Carens'  => ['body' => 'MPV', 'fuels' => ['Petrol', 'Diesel']],
                'Carnival'=> ['body' => 'MPV', 'fuels' => ['Diesel']],
                'EV6'     => ['body' => 'SUV', 'fuels' => ['EV']],
                'Syros'   => ['body' => 'SUV', 'fuels' => ['Petrol']],
            ],
        ],
        'Toyota' => [
            'slug' => 'toyota',
            'segment' => 'mass',
            'models' => [
                'Glanza'       => ['body' => 'Hatchback', 'fuels' => ['Petrol', 'CNG']],
                'Urban Cruiser Taisor' => ['body' => 'SUV', 'fuels' => ['Petrol', 'CNG']],
                'Rumion'       => ['body' => 'MPV', 'fuels' => ['Petrol', 'CNG']],
                'Innova Crysta'=> ['body' => 'MPV', 'fuels' => ['Diesel']],
                'Innova Hycross'=> ['body' => 'MPV','fuels' => ['Hybrid']],
                'Fortuner'     => ['body' => 'SUV', 'fuels' => ['Diesel']],
                'Legender'     => ['body' => 'SUV', 'fuels' => ['Diesel']],
                'Camry'        => ['body' => 'Sedan','fuels' => ['Hybrid']],
                'Hilux'        => ['body' => 'Pickup','fuels' => ['Diesel']],
            ],
        ],
        'Honda' => [
            'slug' => 'honda',
            'segment' => 'mass',
            'models' => [
                'Amaze'  => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'City'   => ['body' => 'Sedan', 'fuels' => ['Petrol', 'Hybrid']],
                'Elevate'=> ['body' => 'SUV',   'fuels' => ['Petrol']],
            ],
        ],
        'Skoda' => [
            'slug' => 'skoda',
            'segment' => 'mass',
            'models' => [
                'Kylaq'   => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'Kushaq'  => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'Slavia'  => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'Kodiaq'  => ['body' => 'SUV',   'fuels' => ['Petrol']],
            ],
        ],
        'Volkswagen' => [
            'slug' => 'volkswagen',
            'segment' => 'mass',
            'models' => [
                'Taigun'  => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'Virtus'  => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'Tiguan'  => ['body' => 'SUV',   'fuels' => ['Petrol']],
            ],
        ],
        'Renault' => [
            'slug' => 'renault',
            'segment' => 'mass',
            'models' => [
                'Kwid'    => ['body' => 'Hatchback', 'fuels' => ['Petrol']],
                'Triber'  => ['body' => 'MPV',       'fuels' => ['Petrol']],
                'Kiger'   => ['body' => 'SUV',       'fuels' => ['Petrol']],
            ],
        ],
        'Nissan' => [
            'slug' => 'nissan',
            'segment' => 'mass',
            'models' => [
                'Magnite' => ['body' => 'SUV', 'fuels' => ['Petrol']],
                'X-Trail' => ['body' => 'SUV', 'fuels' => ['Petrol', 'Hybrid']],
            ],
        ],
        'MG' => [
            'slug' => 'mg',
            'segment' => 'mass',
            'models' => [
                'Comet EV'   => ['body' => 'Hatchback', 'fuels' => ['EV']],
                'Windsor EV' => ['body' => 'SUV',       'fuels' => ['EV']],
                'Astor'      => ['body' => 'SUV',       'fuels' => ['Petrol']],
                'Hector'     => ['body' => 'SUV',       'fuels' => ['Petrol', 'Diesel']],
                'Gloster'    => ['body' => 'SUV',       'fuels' => ['Diesel']],
                'ZS EV'      => ['body' => 'SUV',       'fuels' => ['EV']],
            ],
        ],
        'Citroen' => [
            'slug' => 'citroen',
            'segment' => 'mass',
            'models' => [
                'C3'       => ['body' => 'Hatchback', 'fuels' => ['Petrol']],
                'Basalt'   => ['body' => 'SUV',       'fuels' => ['Petrol']],
                'Aircross' => ['body' => 'SUV',       'fuels' => ['Petrol']],
                'C5 Aircross' => ['body' => 'SUV',    'fuels' => ['Diesel']],
            ],
        ],
        // ── Luxury ───────────────────────────────────────────────
        'Mercedes-Benz' => [
            'slug' => 'mercedes-benz',
            'segment' => 'luxury',
            'models' => [
                'A-Class'  => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'C-Class'  => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'E-Class'  => ['body' => 'Sedan', 'fuels' => ['Petrol', 'Diesel']],
                'S-Class'  => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'GLA'      => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'GLC'      => ['body' => 'SUV',   'fuels' => ['Petrol', 'Diesel']],
                'GLE'      => ['body' => 'SUV',   'fuels' => ['Petrol', 'Diesel']],
                'GLS'      => ['body' => 'SUV',   'fuels' => ['Petrol', 'Diesel']],
                'EQB'      => ['body' => 'SUV',   'fuels' => ['EV']],
                'EQS'      => ['body' => 'Sedan', 'fuels' => ['EV']],
            ],
        ],
        'BMW' => [
            'slug' => 'bmw',
            'segment' => 'luxury',
            'models' => [
                '2 Series' => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                '3 Series' => ['body' => 'Sedan', 'fuels' => ['Petrol', 'Diesel']],
                '5 Series' => ['body' => 'Sedan', 'fuels' => ['Petrol', 'Diesel']],
                '7 Series' => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'X1'       => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'X3'       => ['body' => 'SUV',   'fuels' => ['Petrol', 'Diesel']],
                'X5'       => ['body' => 'SUV',   'fuels' => ['Petrol', 'Diesel']],
                'X7'       => ['body' => 'SUV',   'fuels' => ['Petrol', 'Diesel']],
                'iX1'      => ['body' => 'SUV',   'fuels' => ['EV']],
                'i4'       => ['body' => 'Sedan', 'fuels' => ['EV']],
                'i7'       => ['body' => 'Sedan', 'fuels' => ['EV']],
            ],
        ],
        'Audi' => [
            'slug' => 'audi',
            'segment' => 'luxury',
            'models' => [
                'A4'   => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'A6'   => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'A8 L' => ['body' => 'Sedan', 'fuels' => ['Petrol']],
                'Q3'   => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'Q5'   => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'Q7'   => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'Q8'   => ['body' => 'SUV',   'fuels' => ['Petrol']],
                'e-tron'=> ['body' => 'SUV',  'fuels' => ['EV']],
                'Q8 e-tron' => ['body' => 'SUV', 'fuels' => ['EV']],
            ],
        ],
        'Jaguar' => [
            'slug' => 'jaguar',
            'segment' => 'luxury',
            'models' => [
                'F-Pace' => ['body' => 'SUV', 'fuels' => ['Petrol']],
                'I-Pace' => ['body' => 'SUV', 'fuels' => ['EV']],
            ],
        ],
        'Land Rover' => [
            'slug' => 'land-rover',
            'segment' => 'luxury',
            'models' => [
                'Discovery Sport' => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'Defender'        => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'Range Rover Evoque' => ['body' => 'SUV', 'fuels' => ['Petrol']],
                'Range Rover Sport'  => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
                'Range Rover'        => ['body' => 'SUV', 'fuels' => ['Petrol', 'Diesel']],
            ],
        ],
        'Volvo' => [
            'slug' => 'volvo',
            'segment' => 'luxury',
            'models' => [
                'XC40'    => ['body' => 'SUV', 'fuels' => ['Petrol', 'EV']],
                'XC60'    => ['body' => 'SUV', 'fuels' => ['Petrol', 'Hybrid']],
                'XC90'    => ['body' => 'SUV', 'fuels' => ['Hybrid']],
                'C40 Recharge' => ['body' => 'SUV', 'fuels' => ['EV']],
            ],
        ],
        'Lexus' => [
            'slug' => 'lexus',
            'segment' => 'luxury',
            'models' => [
                'ES'  => ['body' => 'Sedan', 'fuels' => ['Hybrid']],
                'NX'  => ['body' => 'SUV',   'fuels' => ['Hybrid']],
                'RX'  => ['body' => 'SUV',   'fuels' => ['Hybrid']],
                'LM'  => ['body' => 'MPV',   'fuels' => ['Hybrid']],
            ],
        ],
        // ── EV specialists ───────────────────────────────────────
        'Tata EV' => [ // alias helper — maps to Tata EV models
            'slug' => 'tata',
            'segment' => 'ev',
            'models' => [
                'Tiago EV' => ['body' => 'Hatchback', 'fuels' => ['EV']],
                'Tigor EV' => ['body' => 'Sedan',     'fuels' => ['EV']],
                'Punch EV' => ['body' => 'SUV',       'fuels' => ['EV']],
                'Nexon EV' => ['body' => 'SUV',       'fuels' => ['EV']],
                'Curvv EV' => ['body' => 'SUV',       'fuels' => ['EV']],
            ],
        ],
        'BYD' => [
            'slug' => 'byd',
            'segment' => 'ev',
            'models' => [
                'Atto 3'   => ['body' => 'SUV',   'fuels' => ['EV']],
                'Seal'     => ['body' => 'Sedan', 'fuels' => ['EV']],
                'eMAX 7'   => ['body' => 'MPV',   'fuels' => ['EV']],
            ],
        ],
        'Citroen EV' => [
            'slug' => 'citroen',
            'segment' => 'ev',
            'models' => [
                'e-C3' => ['body' => 'Hatchback', 'fuels' => ['EV']],
            ],
        ],
    ];

    /** Simple SVG silhouette data-URI used as last-resort fallback. */
    private static function genericSilhouette(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 64" fill="none">'
             . '<rect width="120" height="64" rx="8" fill="#e2e8f0"/>'
             . '<path d="M18 42h84c2 0 4-2 4-4v-6c0-3-2-6-5-7l-12-4-8-10H39l-8 10-12 4c-3 1-5 4-5 7v6c0 2 2 4 4 4z" fill="#94a3b8"/>'
             . '<circle cx="34" cy="42" r="7" fill="#64748b"/><circle cx="86" cy="42" r="7" fill="#64748b"/>'
             . '<circle cx="34" cy="42" r="3" fill="#e2e8f0"/><circle cx="86" cy="42" r="3" fill="#e2e8f0"/>'
             . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;
        return trim($s, '-');
    }

    /** Resolve catalog key by fuzzy make name. */
    public static function resolveMake(string $make): ?string
    {
        $make = trim($make);
        if ($make === '') return null;
        if (isset(self::$catalog[$make])) return $make;
        $needle = strtolower($make);
        foreach (self::$catalog as $name => $meta) {
            if (strtolower($name) === $needle) return $name;
            if (($meta['slug'] ?? '') === self::slugify($make)) return $name;
            // partial: "Maruti" → "Maruti Suzuki"
            if (str_contains(strtolower($name), $needle) || str_contains($needle, strtolower(explode(' ', $name)[0]))) {
                return $name;
            }
        }
        return null;
    }

    public static function getCatalog(): array
    {
        return self::$catalog;
    }

    public static function listMakes(?string $segment = null): array
    {
        $out = [];
        foreach (self::$catalog as $name => $meta) {
            if ($segment !== null && ($meta['segment'] ?? '') !== $segment) continue;
            $out[] = ['name' => $name, 'slug' => $meta['slug'], 'segment' => $meta['segment'] ?? 'mass'];
        }
        return $out;
    }

    public static function listModels(string $make): array
    {
        $key = self::resolveMake($make);
        if ($key === null) return [];
        $models = [];
        foreach (self::$catalog[$key]['models'] as $model => $info) {
            $models[] = [
                'make'  => $key,
                'model' => $model,
                'body'  => $info['body'] ?? '',
                'fuels' => $info['fuels'] ?? [],
            ];
        }
        return $models;
    }

    /**
     * Logo URL resolver with fallback chain:
     *   1) Local: assets/cars/{slug}/logo.png|svg
     *   2) jsDelivr simple-icons / clearbit-style public logo endpoints
     *   3) Generic silhouette
     */
    public static function getVehicleLogo(string $make): string
    {
        $key = self::resolveMake($make);
        $slug = $key ? (self::$catalog[$key]['slug'] ?? self::slugify($make)) : self::slugify($make);

        // 1) Local override
        $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/\\') : '';
        if ($base !== '') {
            foreach (['png', 'svg', 'webp', 'jpg'] as $ext) {
                $rel = self::LOCAL_ASSETS . '/' . $slug . '/logo.' . $ext;
                $abs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_file($abs)) {
                    return $rel . '?v=' . (string)@filemtime($abs);
                }
            }
        }

        // 2) Domain → real brand mark (Clearbit then Google). Avoid simple-icons
        // (monochrome glyphs often look "unlinked" / wrong brand on dark UI).
        $domains = [
            'maruti-suzuki' => 'marutisuzuki.com',
            'hyundai' => 'hyundai.com',
            'tata' => 'tatamotors.com',
            'mahindra' => 'mahindra.com',
            'toyota' => 'toyota.co.in',
            'honda' => 'honda.co.in',
            'bmw' => 'bmw.in',
            'mercedes-benz' => 'mercedes-benz.co.in',
            'audi' => 'audi.co.in',
            'volkswagen' => 'volkswagen.co.in',
            'kia' => 'kia.com',
            'nissan' => 'nissan.co.in',
            'volvo' => 'volvocars.com',
            'jaguar' => 'jaguar.in',
            'land-rover' => 'landrover.in',
            'lexus' => 'lexusindia.co.in',
            'skoda' => 'skoda-auto.co.in',
            'renault' => 'renault.co.in',
            'mg' => 'mgmotor.co.in',
            'byd' => 'byd.com',
            'citroen' => 'citroen.in',
            'force' => 'forcemotors.com',
            'isuzu' => 'isuzu.in',
            'jeep' => 'jeep-india.com',
            'porsche' => 'porsche.com',
            'mini' => 'mini.in',
            'ashok-leyland' => 'ashokleyland.com',
            'eicher' => 'eicher.in',
        ];
        if (!empty($domains[$slug])) {
            $d = $domains[$slug];
            // Prefer Clearbit full-color mark; UI onerror can fall back to Google
            return 'https://logo.clearbit.com/' . rawurlencode($d);
        }

        // 3) Generic
        return self::genericSilhouette();
    }

    /**
     * Model photo resolver:
     *   1) Local assets/cars/{makeSlug}/{modelSlug}.png|.webp|.jpg
     *   2) Local with year suffix modelSlug-2024.webp
     *   3) Generic silhouette
     *
     * Note: No paid stock CDN is assumed. Place files under assets/cars/ for production art.
     */
    public static function getCarModelPhoto(string $make, string $model, int|string|null $year = null): string
    {
        $key = self::resolveMake($make);
        $makeSlug = $key ? (self::$catalog[$key]['slug'] ?? self::slugify($make)) : self::slugify($make);
        $modelSlug = self::slugify($model);
        $year = $year !== null && $year !== '' ? (int)$year : null;

        $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/\\') : '';
        $candidates = [];
        if ($year) {
            $candidates[] = self::LOCAL_ASSETS . "/{$makeSlug}/{$modelSlug}-{$year}";
        }
        $candidates[] = self::LOCAL_ASSETS . "/{$makeSlug}/{$modelSlug}";
        $candidates[] = self::LOCAL_ASSETS . "/{$makeSlug}/{$modelSlug}-default";

        if ($base !== '') {
            foreach ($candidates as $stem) {
                foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
                    $rel = $stem . '.' . $ext;
                    $abs = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                    if (is_file($abs)) {
                        return $rel . '?v=' . (string)@filemtime($abs);
                    }
                }
            }
        }

        return self::genericSilhouette();
    }

    /**
     * Free-text search across makes, models, body styles, fuels.
     * Returns list of tag-friendly rows for autocomplete / vehicle tag UI.
     *
     * @return list<array{make:string,model:string,body:string,fuels:array,label:string,logo:string}>
     */
    public static function searchVehicleTags(string $query, int $limit = 25): array
    {
        $q = strtolower(trim($query));
        $hits = [];
        foreach (self::$catalog as $make => $meta) {
            foreach ($meta['models'] as $model => $info) {
                $body = (string)($info['body'] ?? '');
                $fuels = $info['fuels'] ?? [];
                $hay = strtolower(implode(' ', [$make, $model, $body, implode(' ', $fuels), $meta['slug'] ?? '']));
                $score = 0;
                if ($q === '') {
                    $score = 1;
                } else {
                    if (str_contains($hay, $q)) $score += 10;
                    foreach (preg_split('/\s+/', $q) as $tok) {
                        if ($tok !== '' && str_contains($hay, $tok)) $score += 3;
                    }
                }
                if ($score > 0) {
                    $hits[] = [
                        'make'  => $make,
                        'model' => $model,
                        'body'  => $body,
                        'fuels' => $fuels,
                        'label' => trim($make . ' ' . $model . ($body ? " ({$body})" : '')),
                        'logo'  => self::getVehicleLogo($make),
                        '_score' => $score,
                    ];
                }
            }
        }
        usort($hits, fn($a, $b) => $b['_score'] <=> $a['_score'] ?: strcasecmp($a['label'], $b['label']));
        $hits = array_slice($hits, 0, max(1, $limit));
        foreach ($hits as &$h) { unset($h['_score']); }
        return $hits;
    }
}

} // class_exists
