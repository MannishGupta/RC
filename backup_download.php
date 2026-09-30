<?php
declare(strict_types=1);
/**
 * Root backup entry — use when /tools/ is blocked by IIS hiddenSegments.
 * Same auth as tools/backup.php (super_admin / admin).
 */
require __DIR__ . '/tools/backup.php';
