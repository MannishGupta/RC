# Module matrix — write access

The matrix is saved **per tenant** to:

```text
{DATA_PATH}/config/modules.json
```

Examples (Windows / Plesk):

```text
D:\INETPUB\VHOSTS\fusionlimited.in\rc\tenants\arthsathi\data\config\modules.json
D:\INETPUB\VHOSTS\fusionlimited.in\rc\tenants\fusionlimited\data\config\modules.json
```

## What needs write permission

| Path | Purpose |
|------|---------|
| `tenants/{id}/data/` | Tenant root (already needed for team, bank, etc.) |
| `tenants/{id}/data/config/` | Created automatically if missing |
| `tenants/{id}/data/config/modules.json` | Module on/off flags |

## Shared hosting

**Linux:** `chmod 775` on `data` and `data/config` (or ownership = PHP user).  
**Windows / IIS:** grant the app-pool identity **Modify** on `tenants\*\data` (and subfolders).

You do **not** need write access to PHP code files — only tenant **data**.

After upload, Super Admin → Monitor → Module matrix → Save.  
On failure the UI shows the exact path and a write-access hint.
