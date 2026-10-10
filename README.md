# Resource Centre (RC)

Multi-tenant corporate directory and operations portal (PHP 8.x, flat-file JSON, no required Composer).

## Quick links
- **Version:** see `version.php` / `VERSION`
- **Health:** `/health.php`
- **Launch:** `docs/PRODUCTION_LAUNCH_CHECKLIST.md`
- **Auth rotation:** `docs/AUTH_ROTATION.md`
- **Tenant guide:** `docs/TENANT_QUICKSTART.md`

## Development
- Entry: `index.php`
- Tenancy: `app/tenant_bootstrap.php` → `tenants/{id}/data/`
- Theme: `assets/contrast-lock.css`

## CI
Push to GitHub to run **RC Safety Check** (PHP syntax, JSON, forbidden secret paths).

## License / product
Commercial product operated per tenant; “Fusion” and similar names are tenant data only, not product branding.
