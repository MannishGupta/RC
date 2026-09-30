# Release checklist (mandatory on every version bump)

Before shipping any `APP_VERSION` / `VERSION` increment, verify **all four**:

## 1. Changelog (`VERSION` + `version.php`)
- [ ] `version.php` `APP_VERSION` and `APP_VERSION_DATE` match the new release
- [ ] `VERSION` has a new `## YYMMDD.N` section at the top describing user-visible and ops changes
- [ ] Sidebar changelog modal still reads `VERSION` (no separate copy to drift)

## 2. Backup
- [ ] Health tab `backup_create` (`api_value.php`) still covers full durable `data/**`
- [ ] Includes: entity JSON, module stores (value/dispatch/janam/runners), media, data config
- [ ] Excludes: sessions, logs, nested backups, tmp, cache stamps, **application PHP**
- [ ] `tools/backup.php` uses the same include/exclude policy and current path constants
- [ ] `VERSION-STAMP.txt` inside the zip still accurate

## 3. Optimization
- [ ] `SystemDataOptimizer::run()` still: ensure dirs → **layout migrate** → normalize watched JSON namespaces
- [ ] New durable folders or JSON namespaces added this release are in:
  - `phaseEnsureDirs()`
  - `phaseMigrateDataLayout()` pairs (if legacy path exists)
  - `phaseNormalizeList` / schemas (if optimisable entity)
- [ ] Migration comments match behaviour (no false claims)

## 4. Monitoring
- [ ] `inventoryWatched()` lists every watched folder and optimised JSON namespace
- [ ] Monitor **Data layout & watched stores** panel still calls `layout_inventory` / `layout_migrate`
- [ ] Health writable checks cover the same path set as inventory
- [ ] Admin gates remain embedded-safe (`return`, not `die`/`exit`)

## Path truth source
`docs/DATA_LAYOUT.md` and constants in `index.php` (`DATA_PATH`, `IMG_PATH`, `DOC_PATH`, module `*_DATA_PATH`).

If any path or store is added without updating backup + optimizer + monitor + changelog, the release is incomplete.
