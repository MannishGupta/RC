# Multi-tenant data roots

Each domain resolves to `tenants/{id}/data/` via `app/tenant_bootstrap.php`.

| Host | Tenant folder |
|------|----------------|
| rc.fusionlimited.in | `tenants/fusionlimited/data/` |
| rc.arthsathi.com | `tenants/arthsathi/data/` |
| rc.digisofts.com | `tenants/digisofts/data/` |

## Deploy

1. Point **all three domains** at the **same** DocumentRoot (this codebase).
2. Upload **code** once — never overwrite `tenants/*/data/` with empty folders.
3. Migrate each site’s old `data/` into the matching `tenants/{id}/data/`.
4. Edit `tenants/map.json` for extra hosts.

## Wildcard

Any host matching `rc.{name}.*` maps to tenant `{name}` automatically
(unless overridden in `exact`).

## Single-site

If `tenants/` is absent, the app uses classic `data/` at the project root.
