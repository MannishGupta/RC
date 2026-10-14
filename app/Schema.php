<?php

if (!defined('BASE_PATH')) {
    exit('No direct script access');
}

final class AppSchema
{
     
    public static function defaults(string $ns): array
    {
        $map = [
            'team' => [
                'id' => '', 'name' => '', 'slug' => '', 'phone' => '', 'email' => '',
                'photo' => '', 'dob' => '', 'gender' => '', 'blood_group' => '',
                'designation_id' => '', 'department_id' => '', 'location_id' => '',
                'social' => [],
            ],
            'bank' => [
                'id' => '', 'slug' => '', 'bank_name' => '', 'holder_name' => '',
                'acc_no' => '', 'ifsc' => '', 'branch' => '', 'upi_id' => '',
            ],
            'docs' => [
                'id' => '', 'name' => '', 'slug' => '', 'doc_file' => '',
                'file_type' => '', 'size' => '', 'version' => '', 'external_url' => '',
                'updated_at' => '',
            ],
            'events' => [
                'id' => '', 'name' => '', 'date' => '', 'location' => '',
                'description' => '', 'url' => '', 'is_virtual' => false,
            ],
            'locations' => [
                'id' => '', 'slug' => '', 'name' => '', 'address' => '',
                'city' => '', 'state' => '', 'pincode' => '', 'map_url' => '',
            ],
            'departments' => ['id' => '', 'code' => '', 'name' => '', 'slug' => ''],
            'designations' => [
                'id' => '', 'code' => '', 'name' => '', 'rank' => 0, 'slug' => '',
                'mandatory_live_tracking' => false,
            ],
            'statutory' => [
                'id' => '', 'company_name' => '', 'cin' => '', 'pan' => '', 'tan' => '',
                'gst' => '', 'lei' => '', 'roc_code' => '', 'date_of_incorporation' => '',
                'msme' => '', 'dpiit_startup' => '', 'bank_account' => '',
                'esic' => '', 'pf_code' => '', 'isin' => '', 'demat_id' => '',
                'phone' => '', 'email' => '', 'address' => '',
                'development_office' => '', 'gst_sales_office' => '',
                'rera' => '', 'rera_project_name' => '',
                'rera_phase_1' => '', 'rera_phase_2' => '', 'rera_phase_3' => '',
            ],
            'cartags' => [
                'id' => '', 'tag_id' => '', 'registration_number' => '', 'plate' => '',
                'make_model' => '', 'colour' => '', 'vehicle_class' => '',
                'owner_name' => '', 'member_id' => '', 'photo' => '',
            ],
            'company' => [
                'name' => '', 'website' => '', 'phone' => '', 'email' => '',
                'logo' => '', 'favicon' => '', 'cover' => '', 'brand_color' => '#1e3a5f',
                'google_maps_api_key' => '', 'maps_api_key' => '',
                'social' => [],
            ],
        ];
        return $map[$ns] ?? [];
    }

    

    public static function exportColumns(string $ns): array
    {
        $map = [
            'team' => ['name', 'slug', 'phone', 'email', 'designation_name', 'department_name', 'location_name', 'dob', 'blood_group', 'gender'],
            'bank' => ['holder_name', 'bank_name', 'branch', 'acc_no', 'ifsc', 'upi_id', 'slug'],
            'cartags' => ['tag_id', 'registration_number', 'plate', 'make_model', 'colour', 'vehicle_class', 'owner_name', 'member_id'],
            'docs' => ['name', 'title', 'file_type', 'version', 'size', 'updated_at', 'slug'],
            'events' => ['name', 'date', 'location', 'description', 'is_virtual', 'photo', 'image', 'url'],
            'locations' => ['name', 'address', 'city', 'state', 'pincode', 'map_url'],
            'statutory' => ['company_name', 'cin', 'pan', 'tan', 'gst', 'lei', 'roc_code', 'msme', 'esic', 'pf_code', 'isin', 'demat_id', 'phone', 'email', 'address'],
        ];
        return $map[$ns] ?? [];
    }

    

    public static function identifierRules(): array
    {
        return [
            'PAN'   => '10 chars: 5 letters + 4 digits + 1 letter (e.g. AAXCA1651P)',
            'TAN'   => '10 chars: 4 letters + 5 digits + 1 letter (e.g. DELA67622C)',
            'CIN'   => '21 chars (MCA), e.g. U68100DL2022PLC400132',
            'GSTIN' => '15 chars: state(2) + PAN(10) + entity + Z + check digit',
            'LEI'   => '20 alphanumeric (ISO 17442)',
            'ISIN'  => '12 chars: 2 letters + 9 alnum + check digit',
            'UDYAM' => 'UDYAM-XX-XX-#######',
            'IFSC'  => '11 chars: 4 letters + 0 + 6 alnum',
            'UPI'   => 'user@handle (VPA)',
        ];
    }

    /**
     * Standard field order for entity records (commercial schema).
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function standardEnvelope(array $row, string $tenantId = ''): array
    {
        $id = (string)($row['id'] ?? $row['uuid'] ?? '');
        $out = [
            'id' => $id,
            'tenant_id' => $tenantId !== '' ? $tenantId : (string)($row['tenant_id'] ?? (defined('TENANT_ID') ? TENANT_ID : '')),
            'meta' => is_array($row['meta'] ?? null) ? $row['meta'] : [],
            'flags' => is_array($row['flags'] ?? null) ? $row['flags'] : [],
        ];
        foreach ($row as $k => $v) {
            if (in_array($k, ['id', 'uuid', 'tenant_id', 'meta', 'flags', 'created_at', 'updated_at', 'deleted_at', 'audit'], true)) {
                continue;
            }
            $out[$k] = $v;
        }
        $out['created_at'] = (string)($row['created_at'] ?? $row['ts'] ?? '');
        $out['updated_at'] = (string)($row['updated_at'] ?? '');
        $out['deleted_at'] = (string)($row['deleted_at'] ?? '');
        $out['audit'] = is_array($row['audit'] ?? null) ? $row['audit'] : [];
        return $out;
    }
}
