<?php

if (!defined('BASE_PATH')) {
    exit('No direct script access');
}

class SystemDataOptimizer
{
    /** Must stay in sync with AppSlug::PROTECTED_SLUGS */
    public const PROTECTED_SLUGS = ['mg', 'ag', 'ap', 'rj', 'akg', 'mk'];

    private const THROTTLE_SECONDS = 21600; // 6 hours
    private const THROTTLE_FILE = 'optimizer_last_run.json';

    /** @var list<string> */
    private static array $logs = [];

    private static function dataRoot(): string
    {
        if (!empty($GLOBALS['RC_OPT_DATA_PATH']) && is_string($GLOBALS['RC_OPT_DATA_PATH'])) {
            return rtrim($GLOBALS['RC_OPT_DATA_PATH'], '/\\');
        }
        if (defined('DATA_PATH')) {
            return rtrim((string)DATA_PATH, '/\\');
        }
        return rtrim(BASE_PATH . '/data', '/\\');
    }

    private static function mediaImagesDir(): string
    {
        return self::dataRoot() . '/media/images';
    }

    private static function mediaDocsDir(): string
    {
        return self::dataRoot() . '/media/documents';
    }


    /**
     * @return array{status:string,logs:list<string>,skipped?:bool,message?:string}
     */
    public static function run(bool $force = false, bool $lightweight = false): array
    {
        self::$logs = [];

        if (!$force && self::isThrottled()) {
            self::log('Skipped — last run within 6 hours (pass force=true to override).');
            return [
                'status'  => 'success',
                'skipped' => true,
                'logs'    => self::$logs,
                'message' => 'Throttled',
            ];
        }

        try {
            self::log('Optimizer started' . ($force ? ' (forced)' : '') . ($lightweight ? ' [lightweight]' : '') . '.');

            self::phaseEnsureDirs();
            self::phaseGenerateTemplates(); // create missing namespace JSON files, never touch existing ones
            // Legacy root migration retired (images/, docs/, storage/janam, dispatch/data no longer used)
            self::phaseNormalizeCompany();
            // Dynamic: every *.json entity under data/ (plus known schemas), not a hardcoded list
            foreach (self::discoverNormalizeTargets() as $ns => $schema) {
                if ($ns === 'company') {
                    continue; // handled above
                }
                self::phaseNormalizeList($ns, $schema);
            }

            self::phaseTeamPhonesAndNames();
            self::phaseMigrateTeamSlugs();
            self::phaseRebuildTeamIndex();
            self::phaseTrimSessions();
            self::phaseTrimLogs();
            self::phaseOrphanMedia();
            // BUG FIX: the likely actual cause of the HTTP 500 this was
            if (!$lightweight) {
                self::phaseConvertImagesWebp();
            } else {
                self::log('WebP convert skipped (multi-tenant sweep — run per-tenant optimise to convert images for a specific tenant).');
            }
            self::phaseRebuildDataCache();
            self::phasePublicTeamAndHealth();
            self::phaseLogRotateAndBackupHint();

            self::markRan();

            // Permanent: clear OPcache so FTP-uploaded PHP is live immediately.
            if (class_exists('AppCacheFlush')) {
                $flush = AppCacheFlush::all('optimise');
                self::log(
                    'OPcache ' . (!empty($flush['opcache']) ? 'cleared' : 'not available')
                    . ' (v' . ($flush['version'] ?? '?') . ')'
                );
            } elseif (function_exists('opcache_reset')) {
                @opcache_reset();
                self::log('OPcache cleared (fallback).');
            }

            self::log('Optimizer finished.');
            return ['status' => 'success', 'logs' => self::$logs];
        } catch (\Throwable $e) {
            self::log('FATAL: ' . $e->getMessage());
            if (class_exists('AppLog')) {
                AppLog::error('Optimizer failed: ' . $e->getMessage(), [
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ]);
            }
            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
                'logs'    => self::$logs,
            ];
        }
    }

    /**
     * Report-only cleanup scan (no deletes).
     * @return array{status:string,candidates:list<array>,bytes:int}
     */
    public static function scanCleanup(): array
    {
        // Shape expected by app/views/partials/optimizer_panel.php:
        // { status, groups:[{key,label,safety,warning,items:[{path,name,note,bytes}]}], total_files, total_bytes, total_human }
        $groups = [
            'images' => [
                'key' => 'images', 'label' => 'Unreferenced images',
                'safety' => 'review', 'warning' => 'Not linked from team/company/docs records. Confirm before delete.',
                'items' => [],
            ],
            'docs' => [
                'key' => 'docs', 'label' => 'Unreferenced documents',
                'safety' => 'review', 'warning' => 'Not linked from document records. Confirm before delete.',
                'items' => [],
            ],
            'sessions' => [
                'key' => 'sessions', 'label' => 'Expired session files',
                'safety' => 'safe', 'warning' => '',
                'items' => [],
            ],
            'logs' => [
                'key' => 'logs', 'label' => 'Oversized log artefacts',
                'safety' => 'safe', 'warning' => 'Only flags logs over 2 MB; deletion is optional.',
                'items' => [],
            ],
            'temp' => [
                'key' => 'temp', 'label' => 'Temp / cache artefacts',
                'safety' => 'safe', 'warning' => '',
                'items' => [],
            ],
        ];

        $referenced = self::collectReferencedFiles();
        $imgDir = self::mediaImagesDir();
        $docDir = self::mediaDocsDir();
        $dataDir = self::dataRoot();
        // Also watch legacy roots so pre-migration files are visible in cleanup
        // Legacy BASE_PATH/images and BASE_PATH/docs are no longer scanned

        // label ("images/" . filename, "docs/" . filename) with no record
        // of which ROOT directory the file was actually scanned from.
        // applyCleanup() then had to reconstruct a "full" path later by
        // guessing BASE_PATH . '/' . that label -- which always resolves to
        // one (tenants/{id}/data/media/images), even when the scan
        // correctly found the file under the tenant path. On a
        // single-tenant install these two happen to be the same path, so
        // the bug was invisible; on any multi-tenant install, deleting a
        // nonexistent path returns false -> refused, not deleted) and the
        // file would correctly reappear on every subsequent scan, because
        // "deleted file records reappear on rescan" symptom.
        // Fixed by capturing the real, already-known full path ($full, at
        // every call site below) at scan time and carrying it straight
        // through to applyCleanup() instead of re-deriving it unreliably.
        $add = static function (string $gkey, string $rel, string $name, int $bytes, string $note, string $fullPath) use (&$groups): void {
            if (!isset($groups[$gkey])) return;
            $groups[$gkey]['items'][] = [
                'path'  => $rel,
                'name'  => $name,
                'note'  => $note,
                'bytes' => $bytes,
                'full'  => $fullPath,
            ];
        };

        foreach ([$imgDir => 'images', $docDir => 'docs'] as $dir => $label) {
            if (!is_dir($dir)) continue;
            foreach (@scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $full = $dir . DIRECTORY_SEPARATOR . $f;
                if (!is_file($full)) continue;
                if (isset($referenced[strtolower($f)])) continue;
                if (preg_match('/^og-/i', $f) || in_array(strtolower($f), ['favicon.ico', 'favicon.png', 'cover.jpg', '.htaccess'], true)) {
                    continue;
                }
                $size = (int) @filesize($full);
                $add($label, $label . '/' . $f, $f, $size, 'Unreferenced ' . $label, $full);
            }
        }

        $sessDir = $dataDir . '/sessions';
        if (is_dir($sessDir)) {
            $now = time();
            foreach (@scandir($sessDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $full = $sessDir . DIRECTORY_SEPARATOR . $f;
                if (!is_file($full)) continue;
                $mtime = (int) @filemtime($full);
                if ($mtime > 0 && ($now - $mtime) > 86400 * 14) {
                    $size = (int) @filesize($full);
                    $add('sessions', 'data/sessions/' . $f, $f, $size, 'Idle > 14 days', $full);
                }
            }
        }

        $logFile = $dataDir . '/logs/app.log';
        if (is_file($logFile) && filesize($logFile) > 2_000_000) {
            $add('logs', 'data/logs/app.log', 'app.log', (int) filesize($logFile), 'Over 2 MB — consider trim via Optimise', $logFile);
        }

        $tmpDir = $dataDir . '/tmp';
        if (is_dir($tmpDir)) {
            foreach (@scandir($tmpDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $full = $tmpDir . DIRECTORY_SEPARATOR . $f;
                if (!is_file($full)) continue;
                $add('temp', 'data/tmp/' . $f, $f, (int) @filesize($full), 'Temp file', $full);
            }
        }

        // Drop empty groups; reindex as list
        $outGroups = [];
        $totalFiles = 0;
        $totalBytes = 0;
        foreach ($groups as $g) {
            if (empty($g['items'])) continue;
            $outGroups[] = $g;
            $totalFiles += count($g['items']);
            foreach ($g['items'] as $it) {
                $totalBytes += (int) ($it['bytes'] ?? 0);
            }
        }

        $human = static function (int $b): string {
            $u = ['B', 'KB', 'MB', 'GB'];
            $i = 0;
            $x = (float) $b;
            while ($x >= 1024 && $i < count($u) - 1) { $x /= 1024; $i++; }
            return (string) (round($x * 10) / 10) . ' ' . $u[$i];
        };

        // Flat candidates still used by applyCleanup.
        // ($it['full'], set by $add() above) instead of reconstructing it
        // from BASE_PATH + the generic relative label -- see the long
        // comment on the $add closure for why that reconstruction was
        // wrong on any multi-tenant install. A defensive fallback to the
        // old reconstruction is kept only for any legacy-shaped item that
        // somehow lacks 'full' (there should be none, since every call
        // site now supplies it), so this never regresses to a hard error.
        $candidates = [];
        foreach ($outGroups as $g) {
            foreach ($g['items'] as $it) {
                $candidates[] = [
                    'path' => $it['path'],
                    'size' => $it['bytes'],
                    'full' => $it['full'] ?? (BASE_PATH . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $it['path'])),
                ];
            }
        }

        return [
            'status'      => 'success',
            'groups'      => $outGroups,
            'total_files' => $totalFiles,
            'total_bytes' => $totalBytes,
            'total_human' => $human($totalBytes),
            'candidates'  => $candidates,
            'bytes'       => $totalBytes,
            'count'       => $totalFiles,
        ];
    }

    /**
     * Delete only paths the admin confirmed; re-validate against a fresh scan.
     * @param list<string> $paths  Relative paths like "images/foo.jpg"
     * @return array{status:string,deleted:int,freed:int,refused:int,failed:int}
     */
    public static function applyCleanup(array $paths): array
    {
        $scan = self::scanCleanup();
        $allowed = [];
        foreach ($scan['candidates'] as $c) {
            $allowed[$c['path']] = $c;
        }

        $deleted = 0;
        $freed = 0;
        $refused = 0;
        $failed = 0;

        foreach ($paths as $rel) {
            $rel = str_replace('\\', '/', (string) $rel);
            $rel = ltrim($rel, '/');
            if (!isset($allowed[$rel])) {
                $refused++;
                continue;
            }
            $full = $allowed[$rel]['full'];
            $size = (int) ($allowed[$rel]['size'] ?? $allowed[$rel]['bytes'] ?? 0);
            // Safety: only under images, docs, or data/{sessions,logs,tmp}
            $roots = [];
            foreach ([
                defined('IMG_PATH') ? IMG_PATH : (self::dataRoot() . '/media/images'),
                defined('DOC_PATH') ? DOC_PATH : (self::dataRoot() . '/media/documents'),
                (defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data')) . '/sessions',
                (defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data')) . '/logs',
                (defined('DATA_PATH') ? DATA_PATH : (BASE_PATH . '/data')) . '/tmp',
                // pre-migration files are visible in cleanup"), but this
                // list never included them -- any file correctly flagged
                // from a legacy location would always fail this safety
                // check and be refused, the second half of why a flagged
                // file could never actually be deleted and would reappear
                // on every rescan.
                // retired root media folders omitted
            ] as $r) {
                $rp = realpath($r);
                if ($rp !== false) $roots[] = $rp;
            }
            $real = realpath($full);
            if ($real === false) {
                // file may already be gone
                $refused++;
                continue;
            }
            // e.g. "images-archive" sitting next to the real "images" root
            // would false-positive match strpos($real, $root) === 0 --
            // same class of prefix-collision risk already tested for and
            // avoided in AppMedia::getFont()'s open_basedir check.
            // Delete is a destructive action, so this boundary is worth
            // the same care.
            $okRoot = false;
            foreach ($roots as $root) {
                if ($real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
                    $okRoot = true;
                    break;
                }
            }
            if (!$okRoot) {
                $refused++;
                continue;
            }
            if (@unlink($real)) {
                $deleted++;
                $freed += $size;
            } else {
                $failed++;
            }
        }

        if (class_exists('AppLog')) {
            AppLog::info('Cleanup applied', compact('deleted', 'freed', 'refused', 'failed'));
        }

        $logs = [
            "Deleted {$deleted} file(s).",
            'Freed ~' . $freed . ' bytes.',
            "Refused {$refused}, failed {$failed}.",
        ];

        return [
            'status'  => 'success',
            'deleted' => $deleted,
            'freed'   => $freed,
            'refused' => $refused,
            'failed'  => $failed,
            'logs'    => $logs,
        ];
    }

    // ── Phases ────────────────────────────────────────────────────────────

    private static function phaseEnsureDirs(): void
    {
        $data = self::dataRoot();
        // BUG FIX: IMG_PATH/DOC_PATH/SESSION_PATH/etc. are constants fixed once
        $dirs = [
            $data,
            $data . '/media/images',
            $data . '/media/documents',
            $data . '/sessions',
            $data . '/logs',
            $data . '/backups',
            $data . '/dispatch',
            $data . '/janam',
            $data . '/value',
            $data . '/runners',
            $data . '/config',
            $data . '/media',
            $data . '/tmp',
        ];
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                if (@mkdir($d, 0775, true)) {
                    self::log('Created directory ' . self::relPath($d));
                }
            }
        }
    }

    
    private static function phaseGenerateTemplates(): void
    {
        $data = self::dataRoot();
        $inv = self::inventoryWatched();
        $created = 0;
        foreach ($inv['namespaces'] as $ns) {
            $path = $data . '/' . $ns['file'];
            if (is_file($path)) continue; // never touch an existing file, empty or not
            $key = (string) ($ns['key'] ?? '');
            // $schemaType is a PRIVATE static property on AppDB (a
            // different class, in app/bootstrap.php), not on this class
            // (SystemDataOptimizer). That would have been a fatal
            // "undeclared static property" error the instant this ran,
            // caught by re-checking rather than trusting the first draft --
            // other code) several times already this session. AppDB has no
            // public accessor for this, so the one known object-type
            // namespace is named directly here instead of reaching into a
            // private property that was never reachable in the first place.
            $isObject = ($key === 'company');
            $template = $isObject ? '{}' : '[]';
            if (@file_put_contents($path, $template, LOCK_EX) !== false) {
                $created++;
                self::log('Created template ' . self::relPath($path) . ' (' . $template . ')');
            }
        }
        if ($created > 0) {
            self::log("Template generation: {$created} missing JSON file(s) created.");
        }
    }

    /**
     * Inventory of JSON namespaces + writable folders the platform watches.
     * Used by Monitor / Health UIs.
     *
     * @return array{status:string,namespaces:list<array>,folders:list<array>,legacy:list<array>,layout_version:string}
     */
    public static function inventoryWatched(): array
    {
        $data = self::dataRoot();
        $namespaces = [
            ['key' => 'team', 'file' => 'team.json', 'optimized' => true, 'label' => 'Human Capital Index'],
            ['key' => 'company', 'file' => 'company.json', 'optimized' => true, 'label' => 'Organizational Config'],
            ['key' => 'bank', 'file' => 'banking.json', 'optimized' => true, 'label' => 'Treasury Ledger'],
            ['key' => 'docs', 'file' => 'documents.json', 'optimized' => true, 'label' => 'Document Vault'],
            ['key' => 'events', 'file' => 'events.json', 'optimized' => true, 'label' => 'Corporate Calendar'],
            ['key' => 'locations', 'file' => 'locations.json', 'optimized' => true, 'label' => 'Premises Registry'],
            ['key' => 'departments', 'file' => 'departments.json', 'optimized' => true, 'label' => 'Organizational Units'],
            ['key' => 'designations', 'file' => 'designations.json', 'optimized' => true, 'label' => 'Role Taxonomy'],
            ['key' => 'statutory', 'file' => 'statutory.json', 'optimized' => true, 'label' => 'Compliance Register'],
            ['key' => 'cartags', 'file' => 'cartags.json', 'optimized' => true, 'label' => 'Fleet Asset Registry'],
            ['key' => 'settings', 'file' => 'settings.json', 'optimized' => false, 'label' => 'Platform settings'],
        ];
        foreach ($namespaces as &$ns) {
            $path = $data . '/' . $ns['file'];
            $ns['exists'] = is_file($path);
            $ns['bytes'] = $ns['exists'] ? (int) @filesize($path) : 0;
            $ns['writable'] = $ns['exists'] ? is_writable($path) : is_writable($data);
            $ns['path'] = 'data/' . $ns['file'];
        }
        unset($ns);

        $folderDefs = [
            ['id' => 'data', 'path' => $data, 'label' => 'Data root', 'required' => true],
            ['id' => 'images', 'path' => $data . '/media/images', 'label' => 'Media · images', 'required' => true],
            ['id' => 'documents', 'path' => $data . '/media/documents', 'label' => 'Media · documents', 'required' => true],
            ['id' => 'sessions', 'path' => defined('SESSION_PATH') ? SESSION_PATH : ($data . '/sessions'), 'label' => 'Sessions', 'required' => true],
            ['id' => 'logs', 'path' => defined('LOG_PATH') ? LOG_PATH : ($data . '/logs'), 'label' => 'Logs', 'required' => true],
            ['id' => 'backups', 'path' => defined('BACKUP_PATH') ? BACKUP_PATH : ($data . '/backups'), 'label' => 'Backups', 'required' => false],
            ['id' => 'dispatch', 'path' => defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : ($data . '/dispatch'), 'label' => 'Dispatch store', 'required' => false],
            ['id' => 'janam', 'path' => defined('JANAM_DATA_PATH') ? JANAM_DATA_PATH : ($data . '/janam'), 'label' => 'Janam Patri store', 'required' => false],
            ['id' => 'value', 'path' => defined('VALUE_DATA_PATH') ? VALUE_DATA_PATH : ($data . '/value'), 'label' => 'Value Pack store', 'required' => false],
            ['id' => 'runners', 'path' => defined('RUNNERS_DATA_PATH') ? RUNNERS_DATA_PATH : ($data . '/runners'), 'label' => 'Live tracking', 'required' => false],
            ['id' => 'config', 'path' => defined('CONFIG_DATA_PATH') ? CONFIG_DATA_PATH : ($data . '/config'), 'label' => 'Config secrets', 'required' => false],
        ];
        $folders = [];
        foreach ($folderDefs as $fd) {
            $path = $fd['path'];
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $fileCount = 0;
            $bytes = 0;
            if ($exists) {
                foreach (scandir($path) ?: [] as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    $fp = $path . '/' . $f;
                    if (is_file($fp)) {
                        $fileCount++;
                        $bytes += (int) @filesize($fp);
                    }
                }
            }
            $folders[] = [
                'id' => $fd['id'],
                'label' => $fd['label'],
                'path' => self::relPath($path),
                'required' => $fd['required'],
                'exists' => $exists,
                'writable' => $writable,
                'files' => $fileCount,
                'bytes' => $bytes,
                'ok' => $exists && $writable,
            ];
        }

        $legacyDefs = []; // obsolete root paths retired — do not scan or migrate

        $legacy = [];
        foreach ($legacyDefs as $ld) {
            $exists = is_dir($ld['path']);
            $count = 0;
            if ($exists) {
                foreach (scandir($ld['path']) ?: [] as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    if (is_file($ld['path'] . '/' . $f)) {
                        $count++;
                    }
                }
            }
            $legacy[] = [
                'label' => $ld['label'],
                'path' => self::relPath($ld['path']),
                'target' => self::relPath($ld['target']),
                'exists' => $exists,
                'files' => $count,
                'needs_migration' => $exists && $count > 0,
            ];
        }

        return [
            'status' => 'success',
            'layout_version' => 'data-v1',
            'namespaces' => $namespaces,
            'folders' => $folders,
            'legacy' => $legacy,
            'backup_policy' => [
                'includes' => [
                    'data/** entity JSON and module stores',
                    'data/media (images + documents)',
                    'data/config and data/*_config.php',
                    '(legacy root paths retired — only tenants/*/data and unified data/media)',
                ],
                'excludes' => [
                    'data/sessions',
                    'data/logs',
                    'data/backups',
                    'data/tmp',
                    '*.tmp / cache stamps',
                    'application PHP (app/, cards/, index.php)',
                ],
            ],
            'optimize_namespaces' => array_values(array_unique(array_merge(
                ['company'],
                array_keys(self::discoverNormalizeTargets())
            ))),
        ];
    }

    /**
     * Copy durable files from legacy locations into unified data/ tree.
     * When $removeEmptyLegacy is true, removes legacy directories that are empty after migration.
     *
     * @return array{copied:int,skipped:int,removed_dirs:list<string>,errors:list<string>}
     */
    public static function migrateDataLayout(bool $removeEmptyLegacy = false): array
    {
        return ['copied' => 0, 'removed' => [], 'skipped' => true, 'message' => 'Legacy migration retired'];
    }

    /**
     * @return array{copied:int,skipped:int,removed_dirs:list<string>,errors:list<string>}
     */
    private static function phaseMigrateDataLayout(bool $removeEmptyLegacy = false, bool $asPublic = false): array
    {
        self::log('Legacy layout migration skipped (obsolete paths retired).');
        return ['copied' => 0, 'removed' => [], 'skipped' => true];

        $data = self::dataRoot();
        $img = $data . '/media/images';
        $doc = $data . '/media/documents';
        $dispatch = defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : ($data . '/dispatch');
        $janam = defined('JANAM_DATA_PATH') ? JANAM_DATA_PATH : ($data . '/janam');
        $config = defined('CONFIG_DATA_PATH') ? CONFIG_DATA_PATH : ($data . '/config');

        foreach ([$img, $doc, $dispatch, $janam, $config] as $d) {
            if (!is_dir($d)) {
                @mkdir($d, 0775, true);
            }
        }

        $copied = 0;
        $skipped = 0;
        $errors = [];
        $removed = [];

        $pairs = [
            // [sourceDir, destDir, fileFilter callable|null]
            [BASE_PATH . '/images', $img, null],
            [BASE_PATH . '/dispatch/data', $dispatch, null],
            [BASE_PATH . '/storage/janam', $janam, null],
        ];

        // Root docs/: only migrate common binary upload extensions (leave markdown docs alone)
        $docFilter = static function (string $name): bool {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            return in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip', 'txt'], true);
        };
        $pairs[] = [BASE_PATH . '/docs', $doc, $docFilter];

        // Config PHP files sitting in data/ root → data/config/
        if (is_dir($data)) {
            foreach (scandir($data) ?: [] as $f) {
                if (!preg_match('/^(auth_config|smtp_config|registry_config|whatsapp_config|google_wallet_config|apple_wallet_config)\.php$/', $f)) {
                    continue;
                }
                $src = $data . '/' . $f;
                $dst = $config . '/' . $f;
                if (!is_file($src)) {
                    continue;
                }
                if (is_file($dst)) {
                    $skipped++;
                    continue;
                }
                if (@copy($src, $dst)) {
                    $copied++;
                    self::log('Migrated config ' . $f . ' → data/config/');
                } else {
                    $errors[] = 'Failed to copy ' . $f;
                }
            }
        }

        foreach ($pairs as [$srcDir, $dstDir, $filter]) {
            if (!is_dir($srcDir)) {
                continue;
            }
            if (!is_dir($dstDir)) {
                @mkdir($dstDir, 0775, true);
            }
            foreach (scandir($srcDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $src = $srcDir . '/' . $f;
                if (!is_file($src)) {
                    continue;
                }
                if ($filter !== null && !$filter($f)) {
                    $skipped++;
                    continue;
                }
                $dst = $dstDir . '/' . $f;
                if (is_file($dst)) {
                    // Destination already exists → skip unconditionally (never overwrite).
                    // Safer for production data; re-copy requires deleting the dest file first.
                    $skipped++;
                    continue;
                }
                if (@copy($src, $dst)) {
                    $copied++;
                    self::log('Migrated ' . self::relPath($src) . ' → ' . self::relPath($dst));
                } else {
                    $errors[] = 'Failed ' . self::relPath($src);
                    self::log('ERROR migrating ' . self::relPath($src));
                }
            }
        }

        if ($removeEmptyLegacy) {
            foreach ([BASE_PATH . '/images', BASE_PATH . '/dispatch/data', BASE_PATH . '/storage/janam', BASE_PATH . '/storage'] as $legacyDir) {
                if (!is_dir($legacyDir)) {
                    continue;
                }
                $left = array_values(array_filter(scandir($legacyDir) ?: [], static fn($x) => $x !== '.' && $x !== '..'));
                if ($left === []) {
                    if (@rmdir($legacyDir)) {
                        $removed[] = self::relPath($legacyDir);
                        self::log('Removed empty legacy folder ' . self::relPath($legacyDir));
                    }
                } else {
                    self::log('Legacy folder not empty, kept: ' . self::relPath($legacyDir) . ' (' . count($left) . ' entries)');
                }
            }
        }

        self::log("Layout migration: copied={$copied}, skipped={$skipped}, removed_dirs=" . count($removed));

        $result = [
            'copied' => $copied,
            'skipped' => $skipped,
            'removed_dirs' => $removed,
            'errors' => $errors,
        ];
        if ($asPublic) {
            $result['status'] = empty($errors) ? 'success' : 'partial';
            $result['logs'] = self::$logs;
        }
        return $result;
    }

    private static function relPath(string $abs): string
    {
        $base = str_replace('\\', '/', BASE_PATH);
        $abs = str_replace('\\', '/', $abs);
        if (str_starts_with($abs, $base . '/')) {
            return substr($abs, strlen($base) + 1);
        }
        return $abs;
    }


    private static function phaseNormalizeCompany(): void
    {
        $c = AppDB::read('company');
        if (!is_array($c)) {
            $c = [];
        }
        $schema = [
            'name' => '', 'website' => '', 'phone' => '', 'email' => '',
            'logo' => '', 'favicon' => '', 'cover' => '', 'brand_color' => '#1e3a5f',
            'social' => [],
        ];
        $out = array_merge($schema, $c);
        if (isset($out['social']) && is_string($out['social'])) {
            $decoded = json_decode($out['social'], true);
            $out['social'] = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($out['social'])) {
            $out['social'] = [];
        }
        AppDB::save('company', $out);
        self::log('Company singleton normalised.');
    }

    /**
     * @param array<string,mixed> $schema
     */
    private static function phaseNormalizeList(string $ns, array $schema): void
    {
        $rows = AppDB::read($ns);
        if (!is_array($rows)) {
            $rows = [];
        }
        // company is object; lists must be list
        if ($ns !== 'company' && self::isAssoc($rows)) {
            $rows = array_values($rows);
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $merged = array_merge($schema, $row);
            // Preserve slug_locked on team
            if ($ns === 'team') {
                if (!empty($row['slug_locked'])) {
                    $merged['slug_locked'] = true;
                } else {
                    unset($merged['slug_locked']);
                }
            }
            if (empty($merged['id'])) {
                $merged['id'] = uniqid('', true);
            }
            $out[] = $merged;
        }
        AppDB::save($ns, $out);
        self::log(ucfirst($ns) . ': ' . count($out) . ' record(s) normalised.');
    }

    private static function phaseTeamPhonesAndNames(): void
    {
        $rows = AppDB::read('team');
        if (!is_array($rows)) {
            return;
        }
        $changed = 0;
        foreach ($rows as &$m) {
            if (!is_array($m)) {
                continue;
            }
            if (!empty($m['name']) && class_exists('mb_convert_case')) {
                $n = mb_convert_case(mb_strtolower(trim((string) $m['name']), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                if ($n !== $m['name']) {
                    $m['name'] = $n;
                    $changed++;
                }
            }
            if (!empty($m['email'])) {
                $m['email'] = strtolower(trim((string) $m['email']));
            }
            if (!empty($m['phone']) && class_exists('AppSlug')) {
                $p = AppSlug::normalizePhone((string) $m['phone']);
                if ($p !== $m['phone']) {
                    $m['phone'] = $p;
                    $changed++;
                }
            }
        }
        unset($m);
        AppDB::save('team', array_values($rows));
        self::log("Team phones/names adjusted ({$changed} field change(s)).");
    }

    private static function phaseMigrateTeamSlugs(): void
    {
        $rows = AppDB::read('team');
        if (!is_array($rows) || !class_exists('AppSlug')) {
            return;
        }
        $used = [];
        foreach ($rows as $m) {
            if (is_array($m) && !empty($m['slug'])) {
                $used[strtolower((string) $m['slug'])] = true;
            }
        }
        $migrated = 0;
        $skipped = 0;
        foreach ($rows as &$m) {
            if (!is_array($m)) {
                continue;
            }
            $slug = strtolower(trim((string) ($m['slug'] ?? '')));
            if (!empty($m['slug_locked'])) {
                $skipped++;
                continue;
            }
            if ($slug !== '' && AppSlug::isProtected($slug)) {
                $skipped++;
                continue;
            }
            if ($slug !== '' && in_array($slug, self::PROTECTED_SLUGS, true)) {
                $skipped++;
                continue;
            }
            // Only auto-generate when empty
            if ($slug !== '') {
                continue;
            }
            $new = AppSlug::generate(
                (string) ($m['name'] ?? ''),
                (string) ($m['dob'] ?? ''),
                (string) ($m['phone'] ?? $m['mobile'] ?? '')
            );
            $base = $new;
            $i = 2;
            while (isset($used[$new])) {
                $new = $base . $i;
                $i++;
            }
            $m['slug'] = $new;
            $used[$new] = true;
            $migrated++;
        }
        unset($m);
        AppDB::save('team', array_values($rows));
        self::log("Slug migration: {$migrated} generated, {$skipped} skipped (locked/protected).");
    }


    private static function phaseRebuildTeamIndex(): void
    {
        self::phaseRebuildIndexes();
    }

    /**
     * Fast-load indexes + catalog so runtime can trust order and find rows by key.
     */
    private static function phaseRebuildIndexes(): void
    {
        $root = self::dataRoot();
        $idxDir = $root . '/indexes';
        if (!is_dir($idxDir)) {
            @mkdir($idxDir, 0775, true);
        }

        $catalog = [
            'schema_version' => 1,
            'generated_at' => date('c'),
            'timezone' => 'Asia/Kolkata',
            'namespaces' => [],
        ];

        $namespaces = [
            'team' => 'rank_asc_name_asc',
            'events' => 'date_asc',
            'docs' => 'updated_desc',
            'bank' => 'name_asc',
            'locations' => 'name_asc',
            'departments' => 'name_asc',
            'designations' => 'rank_asc_name_asc',
            'cartags' => 'name_asc',
            'leads' => 'updated_desc',
            'cctv' => 'name_asc',
            'statutory' => 'name_asc',
        ];

        foreach ($namespaces as $ns => $order) {
            $rows = AppDB::read($ns);
            if (!is_array($rows)) {
                $rows = [];
            }
            // Re-save applies canonicalize + sort + compact JSON
            if ($ns !== 'company') {
                AppDB::save($ns, $rows);
                $rows = AppDB::read($ns);
            }
            $count = is_array($rows) ? count(array_filter($rows, 'is_array')) : 0;
            $byKey = [];
            if (is_array($rows)) {
                foreach ($rows as $i => $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    foreach (['slug', 'id', 'code', 'tag_id', 'plate', 'phone', 'mobile'] as $k) {
                        $v = strtolower(trim((string)($m[$k] ?? '')));
                        if ($v !== '') {
                            $byKey[$k . ':' . $v] = (int)$i;
                        }
                    }
                }
            }
            $idxFile = $idxDir . '/' . $ns . '.json';
            @file_put_contents(
                $idxFile,
                json_encode(['order' => $order, 'count' => $count, 'by' => $byKey], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
            $catalog['namespaces'][$ns] = [
                'count' => $count,
                'order' => $order,
                'index' => 'indexes/' . $ns . '.json',
            ];
            self::log("Index {$ns}: {$count} row(s), order={$order}");
        }

        // Legacy team_index.json (slug/id → offset) for older readers
        $team = AppDB::read('team');
        $legacy = [];
        if (is_array($team)) {
            foreach ($team as $i => $m) {
                if (!is_array($m)) {
                    continue;
                }
                $slug = strtolower(trim((string)($m['slug'] ?? '')));
                if ($slug !== '') {
                    $legacy[$slug] = $i;
                }
                $id = strtolower(trim((string)($m['id'] ?? '')));
                if ($id !== '') {
                    $legacy[$id] = $i;
                }
            }
        }
        @file_put_contents($root . '/team_index.json', json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

        @file_put_contents(
            $root . '/_catalog.json',
            json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        self::log('_catalog.json written (' . count($catalog['namespaces']) . ' namespaces).');
    }


    private static function phaseTrimSessions(): void
    {
        // BUG FIX: same issue as phaseEnsureDirs above -- SESSION_PATH is
        $dir = self::dataRoot() . '/sessions';
        if (!is_dir($dir)) {
            return;
        }
        $maxAge = 31536000; // 1 year cookie lifetime ceiling
        $now = time();
        $n = 0;
        foreach (@scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($p)) {
                continue;
            }
            $mtime = @filemtime($p) ?: 0;
            if ($mtime > 0 && ($now - $mtime) > $maxAge) {
                if (@unlink($p)) {
                    $n++;
                }
            }
        }
        self::log("Expired sessions removed: {$n}.");
    }

    private static function phaseTrimLogs(): void
    {
        $log = self::dataRoot() . '/logs/app.log';
        if (!is_file($log)) {
            return;
        }
        $size = (int) @filesize($log);
        // Cap ~2 MB
        if ($size > 2_000_000) {
            $data = @file($log);
            if (is_array($data) && count($data) > 5000) {
                $data = array_slice($data, -5000);
                @file_put_contents($log, implode('', $data), LOCK_EX);
                self::log('app.log trimmed to last 5000 lines.');
                return;
            }
        }
        self::log('app.log size OK (' . $size . ' bytes).');
    }


    /**
     * Optimise all still images under media/images (JPEG/PNG/GIF/WebP) — downscale + WebP, never skip large files.
     * Updates team/company references when the filename changes.
     * Requires GD imagewebp. Safe no-op when GD/WebP missing.
     */
    private static function phaseConvertImagesWebp(): void
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromjpeg')) {
            self::log('WebP convert skipped (GD imagewebp unavailable).');
            return;
        }

        // Shared hosting often caps at 128MB — raise for this phase only, then restore.
        $prevMem = ini_get('memory_limit');
        @ini_set('memory_limit', '256M');

        $imgDir = self::mediaImagesDir();
        if (!is_dir($imgDir)) {
            self::log('WebP convert: no media/images directory.');
            if ($prevMem !== false && $prevMem !== '') {
                @ini_set('memory_limit', (string)$prevMem);
            }
            return;
        }

        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $map = [];
        // Cap work per pass; never skip permanently — next run continues
        $maxPerRun = 20;
        $targetBytes = 450000;
        $maxEdge = 1600;

        $files = @scandir($imgDir) ?: [];
        foreach ($files as $f) {
            if ($converted >= $maxPerRun) {
                $skipped++;
                continue;
            }
            if ($f === '.' || $f === '..' || str_contains($f, '.opt-tmp.')) {
                continue;
            }
            $full = $imgDir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($full)) {
                continue;
            }
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $skipped++;
                continue;
            }
            if (preg_match('/^(bank-qr-|og-)/i', $f)) {
                $skipped++;
                continue;
            }

            $sz = (int) @filesize($full);
            $info = @getimagesize($full);
            $edge = 0;
            if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
                $edge = max((int) $info[0], (int) $info[1]);
                // Extreme rasters: still process but with a tighter edge to avoid OOM
                if (((int)$info[0] * (int)$info[1]) > 40000000) {
                    $edgeCap = 1200;
                } else {
                    $edgeCap = $maxEdge;
                }
            } else {
                $edgeCap = $maxEdge;
            }
            if ($ext === 'webp' && $sz > 0 && $sz <= $targetBytes && $edge > 0 && $edge <= $maxEdge) {
                $skipped++;
                continue;
            }
            if ($ext === 'webp' && $sz > 0 && $sz <= $targetBytes && $edge === 0) {
                $skipped++;
                continue;
            }

            // Abort image phase early if free memory is critically low
            $memLimit = self::parseIniBytes((string)ini_get('memory_limit'));
            $memUsed = memory_get_usage(true);
            if ($memLimit > 0 && ($memLimit - $memUsed) < 16 * 1048576) {
                self::log('Image optimise paused — low memory (' . round($memUsed/1048576,1) . 'M used). Re-run to continue.');
                break;
            }

            try {
                $result = self::optimizeImageFile($full, [
                    'max_edge' => $edgeCap,
                    'quality' => 80,
                    'prefer_webp' => true,
                    'max_bytes' => $targetBytes,
                ]);
            } catch (\Throwable $ie) {
                $failed++;
                self::log('Image optimise exception on ' . $f . ': ' . $ie->getMessage());
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                continue;
            }
            if (!empty($result['ok'])) {
                $converted++;
                if (($result['name'] ?? '') !== '' && ($result['name'] ?? '') !== $f) {
                    $map[$f] = $result['name'];
                }
                self::log(sprintf(
                    'Image optimised: %s → %s (%d → %d bytes)',
                    $f,
                    $result['name'] ?? $f,
                    (int) ($result['bytes_before'] ?? 0),
                    (int) ($result['bytes_after'] ?? 0)
                ));
            } else {
                $failed++;
                self::log('Image optimise failed (' . ($result['message'] ?? '?') . '): ' . $f);
            }
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Rewrite photo references in team + company
        $rewritten = 0;
        if ($map && class_exists('AppDB')) {
            $team = AppDB::read('team');
            if (is_array($team)) {
                $changed = false;
                foreach ($team as &$m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    foreach (['photo', 'image', 'avatar'] as $fk) {
                        $v = (string) ($m[$fk] ?? '');
                        $bn = basename($v);
                        if ($bn !== '' && isset($map[$bn])) {
                            $m[$fk] = $map[$bn];
                            $changed = true;
                            $rewritten++;
                        }
                    }
                }
                unset($m);
                if ($changed) {
                    AppDB::save('team', $team);
                }
            }
            $co = AppDB::read('company');
            if (is_array($co)) {
                $changed = false;
                foreach (['logo', 'favicon', 'cover'] as $fk) {
                    $v = (string) ($co[$fk] ?? '');
                    $bn = basename($v);
                    if ($bn !== '' && isset($map[$bn])) {
                        $co[$fk] = $map[$bn];
                        $changed = true;
                        $rewritten++;
                    }
                }
                if ($changed) {
                    AppDB::save('company', $co);
                }
            }
        }

        self::log("Image optimise: converted={$converted}, skipped={$skipped}, failed={$failed}, refs_updated={$rewritten} (max {$maxPerRun}/run, includes large WebP)");
        if ($prevMem !== false && $prevMem !== '') {
            @ini_set('memory_limit', (string)$prevMem);
        }
    }

    private static function phaseRebuildDataCache(): void
    {
        if (!class_exists('AppDataCache')) {
            $f = BASE_PATH . '/app/DataCache.php';
            if (is_file($f)) {
                require_once $f;
            }
        }
        if (!class_exists('AppDataCache')) {
            self::log('Data cache rebuild skipped (AppDataCache missing).');
            return;
        }
        AppDataCache::flushAll();
        $done = AppDataCache::rebuildFromAppDb();
        $n = count(array_filter($done, static fn($v) => is_int($v)));
        self::log("SQLite/file data cache rebuilt ({$n} namespaces). sqlite=" . (AppDataCache::sqliteAvailable() ? 'yes' : 'no'));
    }


    private static function phasePublicTeamAndHealth(): void
    {
        if (!class_exists('PublicTeam') && is_file(BASE_PATH . '/app/PublicTeam.php')) {
            require_once BASE_PATH . '/app/PublicTeam.php';
        }
        if (class_exists('PublicTeam')) {
            $ok = PublicTeam::writeFromTeam();
            self::log($ok ? 'team_public.json refreshed.' : 'team_public.json write failed.');
        }
        $root = self::dataRoot();
        $checks = [
            'data_writable' => is_dir($root) && is_writable($root),
            'images_writable' => is_dir($root . '/media/images') && is_writable($root . '/media/images'),
            'team_exists' => is_file($root . '/team.json'),
            'map_readable' => is_readable(BASE_PATH . '/tenants/map.json'),
        ];
        $checks['ok'] = !in_array(false, $checks, true);
        $checks['checked_at'] = date('c');
        @file_put_contents($root . '/_health.json', json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        self::log('Health: ' . ($checks['ok'] ? 'OK' : 'ISSUES'));
    }

    private static function phaseLogRotateAndBackupHint(): void
    {
        if (!class_exists('TenantBackup') && is_file(BASE_PATH . '/app/TenantBackup.php')) {
            require_once BASE_PATH . '/app/TenantBackup.php';
        }
        if (class_exists('TenantBackup')) {
            if (TenantBackup::rotateLogs(self::dataRoot())) {
                self::log('Log rotated for tenant data root.');
            }
        }
    }

    private static function phaseOrphanMedia(): void
    {
        // Conservative: only log orphans; destructive delete stays in applyCleanup.
        $scan = self::scanCleanup();
        $n = (int) ($scan['count'] ?? 0);
        $bytes = (int) ($scan['bytes'] ?? 0);
        self::log("Orphan media scan: {$n} file(s), ~{$bytes} bytes (delete via Cleanup UI).");
    }

    // ── Schemas ───────────────────────────────────────────────────────────

    /** @return array<string,mixed> */

    /**
     * Discover JSON entity files under data/ and pair with known schemas.
     * Unknown list-shaped files get a minimal id/name schema so they are still normalised.
     * @return array<string, array<string,mixed>>
     */

    private static function parseIniBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '' || $val === '-1') {
            return 0; // unlimited
        }
        if (!preg_match('/^(\d+)\s*([KMG])?$/i', $val, $m)) {
            return (int) $val;
        }
        $n = (int) $m[1];
        $u = strtoupper($m[2] ?? '');
        if ($u === 'G') {
            return $n * 1073741824;
        }
        if ($u === 'M') {
            return $n * 1048576;
        }
        if ($u === 'K') {
            return $n * 1024;
        }
        return $n;
    }

    private static function discoverNormalizeTargets(): array
    {
        $known = [
            'team' => self::teamSchema(),
            'bank' => self::bankSchema(),
            'docs' => self::docsSchema(),
            'events' => self::eventsSchema(),
            'locations' => self::locationsSchema(),
            'departments' => self::departmentsSchema(),
            'designations' => self::designationsSchema(),
            'statutory' => self::statutorySchema(),
            'cartags' => self::cartagsSchema(),
            'cctv' => method_exists(__CLASS__, 'cctvSchema')
                ? self::cctvSchema()
                : ['id' => '', 'name' => '', 'url' => '', 'location' => ''],
            'leads' => ['id' => '', 'name' => '', 'phone' => '', 'email' => '', 'company' => '', 'status' => ''],
        ];
        // Skip internal / non-entity stores
        $skip = [
            'map', 'analytics', 'team_index', 'team_public', 'optimizer_last_run',
            'card_analytics', 'audit_log', 'attendance', 'assets', 'visits',
            'roles', 'geofences', 'runners_state', 'policy_settings', 'settings',
        ];
        $root = self::dataRoot();
        $found = $known;
        if (is_dir($root)) {
            foreach (@scandir($root) ?: [] as $f) {
                if ($f === '.' || $f === '..' || !str_ends_with(strtolower($f), '.json')) {
                    continue;
                }
                $ns = substr($f, 0, -5);
                if ($ns === '' || isset($found[$ns]) || in_array($ns, $skip, true)) {
                    continue;
                }
                if (str_starts_with($ns, '_') || str_starts_with($ns, '.')) {
                    continue;
                }
                $path = $root . '/' . $f;
                $raw = @file_get_contents($path);
                $data = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($data)) {
                    continue;
                }
                // List of records → minimal schema from first row keys
                $sample = null;
                if (array_is_list($data) && isset($data[0]) && is_array($data[0])) {
                    $sample = $data[0];
                } elseif (!array_is_list($data) && isset($data['id'])) {
                    $sample = $data;
                }
                if ($sample === null) {
                    continue;
                }
                $schema = ['id' => ''];
                foreach (array_keys($sample) as $k) {
                    if (!is_string($k) || $k === '') {
                        continue;
                    }
                    $schema[$k] = is_bool($sample[$k] ?? null) ? false : (is_int($sample[$k] ?? null) ? 0 : '');
                }
                $found[$ns] = $schema;
            }
        }
        return $found;
    }

    /**
     * Optimise a single image on disk (upload / save path).
     * Always processes JPEG/PNG/GIF/WebP — never skips by file size.
     * Downscales long edge, re-encodes WebP (or JPEG fallback), replaces source when smaller/equal.
     *
     * @param array{max_edge?:int,quality?:int,prefer_webp?:bool,max_bytes?:int} $opts
     * @return array{ok:bool,path:string,name:string,bytes_before:int,bytes_after:int,message?:string}
     */
    public static function optimizeImageFile(string $path, array $opts = []): array
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $before = is_file($path) ? (int) @filesize($path) : 0;
        $name = basename($path);
        $empty = ['ok' => false, 'path' => $path, 'name' => $name, 'bytes_before' => $before, 'bytes_after' => $before];

        if (!is_file($path) || !is_readable($path)) {
            return $empty + ['message' => 'not_found'];
        }
        if (!function_exists('imagecreatetruecolor')) {
            return $empty + ['message' => 'gd_missing'];
        }

        $maxEdge = (int) ($opts['max_edge'] ?? 1600);
        $quality = (int) ($opts['quality'] ?? 80);
        $preferWebp = (bool) ($opts['prefer_webp'] ?? true);
        $maxBytes = (int) ($opts['max_bytes'] ?? 450000); // target ~450 KB when possible
        if ($maxEdge < 320) {
            $maxEdge = 320;
        }
        if ($quality < 40) {
            $quality = 40;
        }
        if ($quality > 95) {
            $quality = 95;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return $empty + ['message' => 'unsupported_ext'];
        }

        $info = @getimagesize($path);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return $empty + ['message' => 'bad_image'];
        }

        $src = null;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $src = @imagecreatefromjpeg($path);
            if ($src && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                if (!empty($exif['Orientation'])) {
                    switch ((int) $exif['Orientation']) {
                        case 3: $src = imagerotate($src, 180, 0); break;
                        case 6: $src = imagerotate($src, -90, 0); break;
                        case 8: $src = imagerotate($src, 90, 0); break;
                    }
                }
            }
        } elseif ($ext === 'png') {
            $src = @imagecreatefrompng($path);
            if ($src) {
                @imagepalettetotruecolor($src);
                @imagealphablending($src, true);
                @imagesavealpha($src, true);
            }
        } elseif ($ext === 'gif') {
            $src = @imagecreatefromgif($path);
        } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($path);
        }
        if (!$src) {
            return $empty + ['message' => 'decode_failed'];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);
            return $empty + ['message' => 'zero_dim'];
        }

        // Downscale if over max edge OR file already over target bytes
        $needScale = max($w, $h) > $maxEdge || $before > $maxBytes;
        if ($needScale && max($w, $h) > $maxEdge) {
            $scale = $maxEdge / max($w, $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
                imagealphablending($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($src);
                $src = $dst;
                $w = $nw;
                $h = $nh;
            }
        } elseif ($needScale && $before > $maxBytes && max($w, $h) > 800) {
            // Still large on disk but under maxEdge — mild shrink to cut bytes
            $scale = 0.85;
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($src);
                $src = $dst;
            }
        }

        $dir = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $useWebp = $preferWebp && function_exists('imagewebp');
        $outExt = $useWebp ? 'webp' : (($ext === 'png') ? 'png' : 'jpg');
        $tmpOut = $dir . DIRECTORY_SEPARATOR . $base . '.opt-tmp.' . $outExt;

        $ok = false;
        if ($outExt === 'webp') {
            $ok = @imagewebp($src, $tmpOut, $quality);
        } elseif ($outExt === 'png') {
            $ok = @imagepng($src, $tmpOut, 6);
        } else {
            $ok = @imagejpeg($src, $tmpOut, $quality);
        }
        imagedestroy($src);

        if (!$ok || !is_file($tmpOut)) {
            @unlink($tmpOut);
            return $empty + ['message' => 'encode_failed'];
        }

        $after = (int) @filesize($tmpOut);
        // If still above target, re-encode once at lower quality
        if ($after > $maxBytes && $outExt === 'webp' && $quality > 55) {
            $src2 = @imagecreatefromwebp($tmpOut);
            if ($src2) {
                $q2 = max(50, $quality - 15);
                @imagewebp($src2, $tmpOut, $q2);
                imagedestroy($src2);
                $after = (int) @filesize($tmpOut);
            }
        }

        $finalName = $base . '.' . $outExt;
        $finalPath = $dir . DIRECTORY_SEPARATOR . $finalName;

        // Replace: write optimised; remove original if different name
        if (!@rename($tmpOut, $finalPath)) {
            @copy($tmpOut, $finalPath);
            @unlink($tmpOut);
        }
        if (is_file($finalPath) && realpath($path) && realpath($path) !== realpath($finalPath)) {
            @unlink($path);
        }
        @chmod($finalPath, 0664);

        return [
            'ok' => true,
            'path' => $finalPath,
            'name' => $finalName,
            'bytes_before' => $before,
            'bytes_after' => is_file($finalPath) ? (int) filesize($finalPath) : $after,
            'message' => 'optimised',
        ];
    }

    private static function teamSchema(): array
    {
        return [
            'id' => '', 'name' => '', 'slug' => '', 'phone' => '', 'email' => '',
            'photo' => '', 'dob' => '', 'time_of_birth' => '', 'place_of_birth' => '',
            'gender' => '', 'blood_group' => '', 'gotra' => '',
            'designation_id' => '', 'department_id' => '', 'location_id' => '',
            'social' => [],
        ];
    }

    /** @return array<string,mixed> */
    private static function bankSchema(): array
    {
        // 'qr_image' added to match the live schema in app/bootstrap.php,
        // which already includes it -- this copy had drifted out of sync.
        // Not a functional bug on its own: phaseNormalizeList's
        // array_merge($schema, $row) keeps any key already present in the
        // real record regardless of whether it's listed here, so qr_image
        // was never actually stripped by normalisation. Fixed anyway so
        // this schema accurately documents every field bank records hold,
        // rather than silently relying on merge behaviour a future reader
        // wouldn't know to check.
        return [
            'id' => '', 'slug' => '', 'bank_name' => '', 'holder_name' => '',
            'acc_no' => '', 'ifsc' => '', 'branch' => '', 'upi_id' => '', 'qr_image' => '',
        ];
    }

    /** @return array<string,mixed> */
    private static function docsSchema(): array
    {
        return [
            'id' => '', 'name' => '', 'slug' => '', 'doc_file' => '',
            'file_type' => '', 'size' => '', 'version' => '', 'external_url' => '',
            'updated_at' => '',
        ];
    }

    /** @return array<string,mixed> */
    private static function eventsSchema(): array
    {
        return [
            'id' => '', 'name' => '', 'date' => '', 'location' => '',
            'description' => '', 'url' => '', 'is_virtual' => false,
        ];
    }

    /** @return array<string,mixed> */
    private static function locationsSchema(): array
    {
        return [
            'id' => '', 'slug' => '', 'name' => '', 'address' => '',
            'city' => '', 'state' => '', 'pincode' => '', 'map_url' => '',
            'logo' => '', 'photo' => '',
        ];
    }

    /** @return array<string,mixed> */
    private static function departmentsSchema(): array
    {
        return ['id' => '', 'code' => '', 'name' => '', 'slug' => ''];
    }

    /** @return array<string,mixed> */
    private static function designationsSchema(): array
    {
        return ['id' => '', 'code' => '', 'name' => '', 'rank' => 0, 'slug' => '', 'mandatory_live_tracking' => false];
    }

    /** @return array<string,mixed> */
    private static function statutorySchema(): array
    {
        return [
            'id' => '', 'company_name' => '', 'cin' => '', 'pan' => '', 'tan' => '',
            'gst' => '', 'lei' => '', 'roc_code' => '', 'date_of_incorporation' => '',
            'msme' => '', 'dpiit_startup' => '', 'bank_account' => '',
            'esic' => '', 'pf_code' => '', 'isin' => '', 'demat_id' => '',
            'phone' => '', 'email' => '', 'address' => '',
            'development_office' => '', 'gst_sales_office' => '',
            'rera' => '', 'rera_project_name' => '',
            'rera_phase_1' => '', 'rera_phase_2' => '', 'rera_phase_3' => '',
        ];
    }

    /** @return array<string,mixed> */
    private static function cartagsSchema(): array
    {
        return [
            'id' => '', 'tag_id' => '', 'registration_number' => '', 'plate' => '',
            'make_model' => '', 'colour' => '', 'vehicle_class' => '',
            'owner_name' => '', 'member_id' => '', 'photo' => '',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** @return array<string,true> basename => true */
    private static function collectReferencedFiles(): array
    {
        $ref = [];
        $add = static function ($v) use (&$ref): void {
            if (!$v || !is_string($v)) {
                return;
            }
            // media_serve.php?f=name.jpg (how the app itself addresses
            // stored media) -- the filename is the f= parameter, not the
            // path segment, so basename() of the raw string would register
            // "media_serve.php?f=name.jpg" and miss the real file.
            if (preg_match('~[?&]f=([^&#]+)~', $v, $mm)) {
                $v = rawurldecode($mm[1]);
            } elseif (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')) {
                return; // genuinely external: nothing on disk to protect
            }
            // Drop any cache-buster / fragment before taking the filename.
            $v = (string) strtok($v, '?#');
            $base = strtolower(basename(str_replace('\\', '/', $v)));
            if ($base !== '') {
                $ref[$base] = true;
            }
        };

        $company = AppDB::read('company');
        if (is_array($company)) {
            foreach (['logo', 'favicon', 'cover'] as $k) {
                $add($company[$k] ?? '');
            }
        }
        // BUG FIX: 'bank' and 'locations' both store a filename directly
        $nsList = ['team', 'docs', 'cartags', 'bank', 'locations', 'events',
                   'leads', 'statutory', 'departments', 'designations', 'cctv'];
        $keys = ['photo', 'image', 'doc_file', 'logo', 'cover', 'favicon',
                 'qr_image', 'banner', 'avatar'];
        foreach ($nsList as $ns) {
            $rows = AppDB::read($ns);
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($keys as $k) {
                    $add($row[$k] ?? '');
                }
            }
        }
        // Optional Delivery Status module keeps its own projects.json
        // outside AppDB, and its cards read 'image'/'photo' from
        // /images/ (see app/bootstrap.php, project JSON-LD). If it's
        // installed, its references must count too.
        foreach ([BASE_PATH . '/status/data/projects.json', BASE_PATH . '/status/projects.json'] as $pf) {
            if (!is_file($pf)) {
                continue;
            }
            $pj = json_decode((string) @file_get_contents($pf), true);
            if (!is_array($pj)) {
                continue;
            }
            foreach ($pj as $proj) {
                if (!is_array($proj)) {
                    continue;
                }
                foreach ($keys as $k) {
                    $add($proj[$k] ?? '');
                }
            }
        }
        return $ref;
    }

    private static function isThrottled(): bool
    {
        $file = self::dataRoot() . '/' . self::THROTTLE_FILE;
        if (!is_file($file)) {
            return false;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        $ts = (int) ($data['ts'] ?? 0);
        return $ts > 0 && (time() - $ts) < self::THROTTLE_SECONDS;
    }

    private static function markRan(): void
    {
        $file = self::dataRoot() . '/' . self::THROTTLE_FILE;
        @file_put_contents($file, json_encode(['ts' => time(), 'version' => defined('APP_VERSION') ? APP_VERSION : '']), LOCK_EX);
    }

    private static function log(string $msg): void
    {
        self::$logs[] = $msg;
        if (class_exists('AppLog')) {
            AppLog::info('Optimizer: ' . $msg);
        }
    }

    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Super Admin: optimize every tenants/{id}/data root (+ legacy data/).
     *
     * @return array{status:string,logs:list<string>,tenants:list<array>,message?:string}
     */
    public static function runAllTenants(bool $force = true): array
    {
        $allLogs = [];
        $results = [];
        $allLogs[] = 'Global multi-tenant optimizer started (IST ' . date('d-m-Y H:i:s') . ').';

        $roots = [];
        $tenantsDir = BASE_PATH . '/tenants';
        if (is_dir($tenantsDir)) {
            if (!class_exists('TenantPaths') && is_file(BASE_PATH . '/app/TenantPaths.php')) {
                require_once BASE_PATH . '/app/TenantPaths.php';
            }
            $ids = class_exists('TenantPaths') ? TenantPaths::listIds() : [];
            if ($ids === []) {
                foreach (scandir($tenantsDir) ?: [] as $d) {
                    if ($d === '.' || $d === '..' || str_starts_with((string)$d, '.') || $d === 'map.json') continue;
                    if (preg_match('/\.(md|txt|json|htaccess)$/i', (string)$d)) continue;
                    $folder = $tenantsDir . '/' . $d;
                    if (!@is_dir($folder)) continue;
                    $ids[] = $d;
                }
            }
            foreach ($ids as $d) {
                $folder = $tenantsDir . '/' . $d;
                if (!@is_dir($folder)) continue;
                $data = $folder . '/data';
                if (!is_dir($data)) {
                    @mkdir($data, 0775, true);
                }
                $roots[$d] = $data;
            }
        }
        $legacy = BASE_PATH . '/data';
        if (is_dir($legacy) && !isset($roots['default'])) {
            $roots['_legacy'] = $legacy;
        }
        if ($roots === []) {
            $cur = defined('DATA_PATH') ? DATA_PATH : $legacy;
            $roots[defined('TENANT_ID') ? (string) TENANT_ID : 'current'] = $cur;
        }

        foreach ($roots as $tid => $path) {
            $allLogs[] = '--- Tenant: ' . $tid . ' ---';
            $allLogs[] = 'Data path: ' . $path;
            $teamFile = '';
            foreach (['team.json'] as $tf) {
                if (is_file($path . '/' . $tf)) {
                    $teamFile = $path . '/' . $tf;
                    break;
                }
            }
            if ($teamFile !== '') {
                $pre = json_decode((string)@file_get_contents($teamFile), true);
                $preN = is_array($pre) ? count(array_filter($pre, 'is_array')) : 0;
                $allLogs[] = 'Pre-scan team.json records: ' . $preN;
            } else {
                $allLogs[] = 'Pre-scan team.json records: 0 (file missing — will seed empty template if needed)';
            }
            if (function_exists('rc_storage_self_heal')) {
                rc_storage_self_heal($path);
            }
            // Full image optimise only for the active tenant data path; other
            // tenants stay lightweight to avoid multi-tenant memory exhaustion.
            $activeData = defined('DATA_PATH') ? rtrim(str_replace(["\\", "/"], DIRECTORY_SEPARATOR, (string)DATA_PATH), DIRECTORY_SEPARATOR) : '';
            $thisData = rtrim(str_replace(["\\", "/"], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
            $doImages = ($activeData !== '' && strcasecmp($activeData, $thisData) === 0);
            $r = self::runForDataPath($path, $force, !$doImages);
            $results[] = [
                'tenant' => $tid,
                'path'   => $path,
                'status' => $r['status'] ?? 'unknown',
            ];
            foreach ($r['logs'] ?? [] as $line) {
                $allLogs[] = '[' . $tid . '] ' . $line;
            }
        }

        if (!class_exists('TenantBackup') && is_file(BASE_PATH . '/app/TenantBackup.php')) {
            require_once BASE_PATH . '/app/TenantBackup.php';
        }
        if (class_exists('TenantBackup')) {
            try {
                $bak = TenantBackup::backupAllTenants();
                $allLogs[] = !empty($bak['ok']) ? ('Backup: ' . ($bak['message'] ?? 'ok')) : ('Backup: ' . ($bak['message'] ?? 'failed'));
            } catch (\Throwable $be) {
                $allLogs[] = 'Backup skipped: ' . $be->getMessage();
            }
        }
        $allLogs[] = 'Global multi-tenant optimizer finished. Tenants processed: ' . count($results);
        self::$logs = $allLogs;
        return [
            'status'  => 'success',
            'logs'    => $allLogs,
            'tenants' => $results,
            'message' => 'Optimized ' . count($results) . ' tenant data root(s)',
        ];
    }

    /**
     * @return array{status:string,logs:list<string>,message?:string}
     */
    private static function runForDataPath(string $dataPath, bool $force = true, bool $lightweight = false): array
    {
        self::$logs = [];
        $prev = $GLOBALS['RC_OPT_DATA_PATH'] ?? null;
        $GLOBALS['RC_OPT_DATA_PATH'] = $dataPath;
        try {
            if (class_exists('AppDB') && method_exists('AppDB', 'clearCache')) {
                AppDB::clearCache();
            }
            self::log('Optimizer started for ' . $dataPath);
            self::phaseEnsureDirs();
            // phaseMigrateDataLayout retired

            self::phaseNormalizeCompany();
            // Dynamic: every *.json entity under data/ (plus known schemas), not a hardcoded list
            foreach (self::discoverNormalizeTargets() as $ns => $schema) {
                if ($ns === 'company') {
                    continue; // handled above
                }
                self::phaseNormalizeList($ns, $schema);
            }
            self::phaseTeamPhonesAndNames();
            self::phaseMigrateTeamSlugs();
            self::phaseRebuildTeamIndex();
            self::phaseTrimSessions();
            self::phaseTrimLogs();
            self::phaseOrphanMedia();
            if (!$lightweight) {
                self::phaseConvertImagesWebp();
            } else {
                self::log('WebP convert skipped (multi-tenant lightweight pass).');
            }
            self::phaseRebuildDataCache();
            self::phasePublicTeamAndHealth();
            self::phaseLogRotateAndBackupHint();
            self::markRan();
            return ['status' => 'success', 'logs' => self::$logs];
        } catch (\Throwable $e) {
            self::log('ERROR: ' . $e->getMessage());
            return ['status' => 'error', 'logs' => self::$logs, 'message' => $e->getMessage()];
        } finally {
            if ($prev === null) {
                unset($GLOBALS['RC_OPT_DATA_PATH']);
            } else {
                $GLOBALS['RC_OPT_DATA_PATH'] = $prev;
            }
            if (class_exists('AppDB') && method_exists('AppDB', 'clearCache')) {
                AppDB::clearCache();
            }
        }
    }

}
