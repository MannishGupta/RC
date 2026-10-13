# System Optimizer & weekly backups

## All tenants (domain-independent)

`SystemDataOptimizer::runAllTenants(true)` (Super Admin → Optimise) walks every
`tenants/{id}/data` root from `TenantPaths` / `map.json`, not only the host you are on.

## Rubbish cleanup

Auto during optimise (`phaseRubbishSweep`):

- `*.bak`, `*~`, `*.orig`, `*.tmp`
- `Thumbs.db`, `.DS_Store`, `desktop.ini`
- `*-copy.*`, `*_old.*`

Also listed under Cleanup scanner group **Accidental / unused files** for review.

## Weekly Sunday backup (IST)

- Folder: `data/backups/weekly/RC-weekly-YYYYMMDD.zip`
- Triggered after full optimise when the day is Sunday Asia/Kolkata
- Or CLI: `php tools/weekly_backup.php` / `php tools/weekly_backup.php --force`
- Keeps last 8 weekly zips

Manual full backup still via `TenantBackup::backupAllTenants()` → `data/backups/RC-data-*.zip`.
