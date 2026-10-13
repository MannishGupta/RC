# Commercial launch audit — data & logos

Date: 12 Oct 2026 · Build 20261011.29

## Rule 1 — Variable data lives under the tenant folder

| Kind | Location |
|------|----------|
| Team, company, locations, mediakit, modules | `tenants/{id}/data/*.json` |
| Construction Status JSON + uploads | `tenants/{id}/data/status/` |
| Status site_config (brand, video, DPDP overrides) | `tenants/{id}/data/status/site_config.json` |
| Media images / documents | `tenants/{id}/data/media/` |
| Sessions, logs, backups | `tenants/{id}/data/sessions|logs|backups/` |

Shipped `status/config.php` holds **neutral defaults only**. Per-tenant brand/DPDP is resolved via `statusTenantOverlay()` from `site_config.json` + RC **Organisation Setup** (`company` record).

Legacy `status/data/` is copied **once** into the tenant store when empty, then ignored for writes.

## Rule 2 — Logos

| Logo | Source | Changeable by tenant? |
|------|--------|------------------------|
| Company / primary logo | Organisation Setup → `company.logo` | **Yes** |
| Favicon / cover / OG | Organisation Setup / status `site_config` / `status/assets/og.*` in tenant uploads | **Yes** |
| Project logos (Brook, etc.) | Status Project Master → `uploads/` under tenant status | **Yes** |
| **Developer (Arthsathi) icon / wordmark** | `assets/brand/arthsathi-*.svg|png` | **No** (product identity) |
| PWA / Windows installer icon | Same `assets/brand/` product set | **No** |

Status header prefers RC company logo, then project logos from tenant uploads — never a fixed Fusion mark.

## Rule 3 — Developer branding (hardcoded by design)

- Footer: `footer.developer` = Arthsathi Limited → https://arthsathi.com/
- `manifest.php`, `InstallerBuild.php`, login “Developed by” mark
- Product favicons under web root may remain Arthsathi product icons; **tenant favicon** still comes from company record when set

## Deploy checklist

1. Confirm every live host maps in `tenants/map.json` → `tenants/{id}/data/`.
2. Do **not** FTP-overwrite `tenants/*/data/`.
3. After deploy, open Status Management Console and set brand fields if `site_config` is empty (company name will still come from Organisation Setup).
4. Verify logos: white SVGs on sand plate; no `logo">` leak in admin.
