<?php
declare(strict_types=1);
namespace App\Rc\Support;

/**
 * Slug sanitize + duplicate detection for collection saves.
 * Version: 20261002.18
 */
final class SlugGuard
{
    public static function sanitize(string $slug): string
    {
        if (class_exists(SafeName::class, false)) {
            return SafeName::slug($slug);
        }
        $slug = strtolower(trim(basename(str_replace('\\', '/', $slug))));
        return (string) preg_replace('/[^a-z0-9\-]+/', '', $slug);
    }

    /**
     * @param array<int|string,mixed> $existingRows
     * @return string|null Error message if duplicate, null if ok
     */
    public static function duplicateMessage(string $ns, string $slug, string $currentId, array $existingRows): ?string
    {
        if ($slug === '' || $ns === 'company') {
            return null;
        }
        foreach ($existingRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['slug'] ?? '') === $slug && (string) ($row['id'] ?? '') !== $currentId) {
                return 'Duplicate ID/Slug detected.';
            }
        }
        return null;
    }
}
