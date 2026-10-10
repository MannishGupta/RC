# Resource Centre — Commercial Launch Checklist

**Build:** 20261001.15 · **Developer:** Arthsathi Limited

## Before go-live

1. **PHP** 8.2+ (8.4.x tested). Enable `opcache`, `intl` (INR), `gd` (signature icons), `zip`.
2. **Document root** points at this package (all tenant domains share one codebase).
3. **Writable:** `tenants/*/data/` (sessions, media, JSON). Mode `0775` or IIS write ACL.
4. **Map tenants** in `tenants/map.json` (host → tenant id). Never commit production passwords in repo.
5. **Auth defaults** (change in Super Admin / auth config after first login):
   - Super Admin: `blsbls`
   - Company Admin: `lifeisgood`
   - Visitor: `alliswell`
6. **Google Maps key** (if tracking/dispatch used): store in tenant policy / company settings — not in public JS.
7. **SSL** on every host; HSTS optional via host panel.
8. **Delete** `rc_diag.php`, any `tools/diagnostics` public links after use.
9. **Backup** via Super Admin / `backup_download.php` (admin session required).
10. **Smoke test:** login all 3 roles · team list · bank logos · vehicle OEM · signature copy to Outlook · numero/janam/blood · theme Light/Dark/Reserve · print directory.

## Multi-tenant

- Host header → `tenants/map.json` → `tenants/{id}/data/`
- Code is shared; **data is never shared** across tenants.
- Optimizer (Super Admin only) sweeps all tenants.

## Not included in this ZIP

- Production tenant JSON / photos / sessions (keep on server under `tenants/`)
- Replace only **code** files on deploy; preserve `tenants/*/data/`

## Support notes

- IIS: use `web.config` (tools/ may be hidden — use root `backup_download.php`)
- Apache/Linux: use `.htaccess`
- Email signatures: re-copy after each signature.php update; prefer HTML paste in Outlook

