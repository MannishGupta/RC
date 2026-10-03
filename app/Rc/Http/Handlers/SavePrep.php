<?php
declare(strict_types=1);
namespace App\Rc\Http\Handlers;

use App\Rc\Domain\Bank\RecordNormalize as BankNormalize;
use App\Rc\Domain\Team\RecordNormalize as TeamNormalize;
use App\Rc\Support\FieldNormalize;
use App\Rc\Support\SlugGuard;

/**
 * Prepare payload before persist (ns-aware). Does not write files or DB.
 * Version: 20261002.18
 */
final class SavePrep
{
    /**
     * @param array<string,mixed> $newData
     * @return array{data: array<string,mixed>, error: ?string}
     */
    public static function prepare(string $ns, array $newData, string $id, bool $isEdit): array
    {
        $newData = FieldNormalize::apply($newData);

        if ($ns === 'team' && class_exists(TeamNormalize::class, false)) {
            $newData = TeamNormalize::apply($newData);
        }
        if ($ns === 'bank' && class_exists(BankNormalize::class, false)) {
            $newData = BankNormalize::apply($newData);
        }

        if ($ns === 'company' && (empty($newData['name']) || strlen(trim((string) $newData['name'])) < 2)) {
            return ['data' => $newData, 'error' => 'Organization Name is required.'];
        }

        if (isset($newData['slug'])) {
            $newData['slug'] = SlugGuard::sanitize((string) $newData['slug']);
        }

        if (!empty($newData['slug']) && class_exists('AppDB', false)) {
            $existing = \AppDB::read($ns);
            if (is_array($existing)) {
                $dup = SlugGuard::duplicateMessage($ns, (string) $newData['slug'], $id, $existing);
                if ($dup !== null) {
                    return ['data' => $newData, 'error' => $dup];
                }
            }
        }

        if ($ns === 'team' && !$isEdit && class_exists('AppSlug', false)) {
            if (!empty($newData['phone'])) {
                $newData['phone'] = \AppSlug::normalizePhone((string) $newData['phone']);
            }
            if (empty($newData['slug'])) {
                $newData['slug'] = \AppSlug::generate(
                    (string) ($newData['name'] ?? ''),
                    (string) ($newData['dob'] ?? ''),
                    (string) ($newData['phone'] ?? '')
                );
            }
            $newData['slug'] = SlugGuard::sanitize((string) ($newData['slug'] ?? ''));
        }

        return ['data' => $newData, 'error' => null];
    }
}
