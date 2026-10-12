# Safe deploy (Resource Centre)

## Never overwrite live tenant data

When uploading a build ZIP or FTP sync:

**DO upload**
- `app/`, `assets/`, `status/*.php`, `status/assets/`, `cards/`, `tools/`, root `*.php`, `web.config`, `.htaccess`

**DO NOT upload / overwrite**
- `tenants/*/data/` (team.json, company.json, mediakit, status, sessions, logs, media)
- `data/` on a live host (if used)
- `status/uploads/` and `status/data/` if they already hold live construction files

If a full-tree replace emptied Team Directory:
1. Restore `tenants/{id}/data/` from the last backup (`tools/backup.php` or host backup).
2. Hard-refresh; counts should return.

## Status module data
Per-tenant path: `tenants/{id}/data/status/`.  
On first load, if that store is empty `[]` and `status/data/` has projects, they are copied once into the tenant folder.
