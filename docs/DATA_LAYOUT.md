# Data layout — single writable root

**Rule:** Only `data/` must be writable and preserved across upgrades.  
Everything outside `data/` is application code and may be replaced on FTP deploy.

## Tree

```
data/
  config/              Integration secrets (auth, SMTP, maps, WhatsApp, wallets)
  media/
    images/            Uploaded photos, QR assets
    documents/         Uploaded document files
  sessions/            PHP session files
  logs/                Application logs
  backups/             Zip snapshots from Health tab
  runners/             Live tracking state & policy
  value/               Value Pack (attendance, assets, audit, roles…)
  dispatch/            Routes, dispatches, assignments
  janam/               Janam Patri profiles & match logs
  *.json               Core entities (team, bank, docs, company, …)
```

## Path constants (`index.php`)

| Constant | Path |
|----------|------|
| `DATA_PATH` | `/data` |
| `IMG_PATH` | `/data/media/images` |
| `DOC_PATH` | `/data/media/documents` |
| `SESSION_PATH` | `/data/sessions` |
| `LOG_PATH` | `/data/logs` |
| `BACKUP_PATH` | `/data/backups` |
| `DISPATCH_DATA_PATH` | `/data/dispatch` |
| `JANAM_DATA_PATH` | `/data/janam` |
| `VALUE_DATA_PATH` | `/data/value` |
| `RUNNERS_DATA_PATH` | `/data/runners` |
| `CONFIG_DATA_PATH` | `/data/config` |

## Deploy

1. Backup entire `data/` folder.
2. Replace application files (everything **except** `data/`).
3. Restore `data/` if the zip overwrote it.
4. Ensure `data/` and all subfolders are writable (775 / IIS Modify).

## Legacy paths

Older installs may still have `images/`, `docs/`, `dispatch/data/`, `storage/janam/`.  
New code prefers `data/…` and migrates JSON once when possible.


## Automatic migration (Optimizer)

`SystemDataOptimizer::run()` calls `phaseMigrateDataLayout(false)`:

1. Ensures all `data/**` folders exist
2. **Copies** files from legacy paths into `data/…` when the destination file is missing
3. Does **not** delete non-empty legacy folders

Monitor → **Data layout & watched stores**:

- Lists JSON namespaces Optimise normalises
- Lists writable folders under `data/`
- Shows legacy paths still holding files
- **Migrate into data/** — same copy step on demand
- **Migrate + remove empty legacy** — only `rmdir` if the legacy folder is empty after copy

Legacy sources: `images/`, `docs/` (binary uploads only), `dispatch/data/`, `storage/janam/`, config PHP at `data/*.php` → `data/config/`.


## Multi-tenant (260921.57+)

When `tenants/` is present, durable data lives at:

```
tenants/{tenantId}/data/
```

Host mapping: `tenants/map.json` + wildcards `rc.{tenant}.*`.
Code remains shared at the DocumentRoot; only `tenants/*/data` differs per domain.
