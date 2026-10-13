# Deploy — protect tenant data

## Critical rule
**Never extract a code ZIP over `tenants/*/data/`.**

That folder holds live team, bank, documents, images, sessions.

### Safe FTP / extract
Upload/overwrite only:
- `index.php`, `app/`, `assets/`, `cards/`, `tools/`, `*.php` at root
- `tenants/map.json` (host → tenant map only)
- `tenants/{id}/` **folder names** if new tenant (empty `data/`)

**Do not upload:**
- `tenants/*/data/team.json`
- `tenants/*/data/*.json` from the package
- Sample T1–T5 or empty arrays from builds

### If team data was overwritten
1. Plesk → **Backup Manager** → restore `tenants/arthsathi/data/` from before the upload.
2. Or restore from `tenants/arthsathi/data/backups/` if Super Admin backup existed.
3. Or copy `team.json` from another machine/export if you have one.

### After code-only deploy
Hard-refresh. Team list should show server data unchanged.
