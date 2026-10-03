<?php
declare(strict_types=1);
namespace App\Rc\Domain\Bank;

/**
 * Bank / treasury field normalization.
 * Version: 20261002.17
 */
final class RecordNormalize
{
    /**
     * @param array<string,mixed> $newData
     * @return array<string,mixed>
     */
    public static function apply(array $newData): array
    {
        if (!empty($newData['ifsc'])) {
            $newData['ifsc'] = strtoupper(preg_replace('/\s+/', '', (string) $newData['ifsc']) ?? '');
        }
        if (!empty($newData['upi_id'])) {
            $newData['upi_id'] = strtolower(trim((string) $newData['upi_id']));
        } elseif (!empty($newData['upi'])) {
            $newData['upi'] = strtolower(trim((string) $newData['upi']));
            $newData['upi_id'] = $newData['upi'];
        }
        if (!empty($newData['acc_no'])) {
            $newData['acc_no'] = preg_replace('/\s+/', '', (string) $newData['acc_no']);
        }
        return $newData;
    }
}
