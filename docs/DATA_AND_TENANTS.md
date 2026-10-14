# Data layout & tenants

- Host → tenant via `tenants/map.json` and `app/tenant_bootstrap.php`.
- Per-tenant store: `tenants/{id}/data/*.json`, media under `…/data/media/`.
- Product name: **Resource Centre (RC)**; developer branding: **Arthsathi Limited** (hardcoded marks only).

## Quick start (operators)

1. Sign in → **Organisation** (name, website, logo PNG/SVG, favicon).
2. **Team** → people; print directory from team tools.
3. **Email signature** → copy for Gmail/Outlook/Apple Mail.
4. Theme: Light / Dark / Reserve (left menu).
5. Footer: Governance / Privacy / DPDP for data requests.

## Optimizer & weekly backup

- Super Admin **Optimise** runs on **every** `tenants/{id}/data` (not only current host).
- Rubbish sweep: `.bak`, `*~`, OS junk; orphan media via cleanup scanner.
- Sunday IST: compressed zip under `data/backups/weekly/` (or `php tools/weekly_backup.php`).

## Logos

- Tenant logos: company upload (SVG sanitised + hardened serve).
- Master OEM/bank logos: `data/masters/logos/{banks|oems}/` then `assets/logos/`.
- See short notes in `assets/logos/README.md`.
