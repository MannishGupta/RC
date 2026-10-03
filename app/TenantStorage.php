<?php
declare(strict_types=1);
final class TenantStorage
{
    public static function tableFiles(): array
    {
        return ['team.json','bank.json','docs.json','events.json','locations.json','departments.json','designations.json','cartags.json','cartag_logs.json','cctv.json','leads.json','statutory.json'];
    }
    public static function ensureBaseline(string $dataPath): void
    {
        if (!is_dir($dataPath)) @mkdir($dataPath, 0775, true);
        foreach (self::tableFiles() as $fn) {
            $path = rtrim($dataPath, '/\\') . '/' . $fn;
            if (!is_file($path)) {
                @file_put_contents($path, "[]\n", LOCK_EX);
            }
        }
        $company = rtrim($dataPath, '/\\') . '/company.json';
        if (!is_file($company)) @file_put_contents($company, "{}\n", LOCK_EX);
        if (class_exists('MasterDirectory') && method_exists('MasterDirectory', 'ensureFiles')) {
            try { MasterDirectory::ensureFiles(); } catch (\Throwable $e) { /* non-fatal */ }
        }
    }
    public static function purgeTrial(string $dataPath): array
    {
        $written = [];
        foreach (array_merge(self::tableFiles(), ['company.json']) as $fn) {
            $path = rtrim($dataPath, '/\\') . '/' . $fn;
            $body = $fn === 'company.json' ? "{}\n" : "[]\n";
            @file_put_contents($path, $body, LOCK_EX);
            $written[] = $fn;
        }
        return $written;
    }
}
