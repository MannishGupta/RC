# Commercial launch checklist — Fusion Resource Centre

**Build:** 20260928.15 · **Developer:** Arthsathi Limited

## Before go-live (each host)

1. **DNS / Document root**  
   Point `rc.*` (or alias) at this package root. Multi-tenant: one codebase, isolated `tenants/{id}/data/`.

2. **Map hosts**  
   Edit `tenants/map.json` exact entries for every production hostname. Confirm no forced cross-domain redirects.

3. **Permissions**  
   Writable: `tenants/{id}/data/**` (0775 or IIS write ACL). Sessions, logs, media, backups under that tree.

4. **Change default access keys**  
   Defaults (`blsbls` / `lifeisgood` / `alliswell`) are for setup only.  
   Set production hashes via Organizational Config / auth config under the active tenant `data/` (or regenerate with `password_hash`).  
   Do not ship customer-facing portals on factory passwords.

5. **PHP**  
   8.2+ recommended (8.4 tested). Extensions: `json`, `mbstring`, `gd` (images), `zip` (backup). Optional: `intl` for INR grouping.

6. **HTTPS**  
   Enable TLS on the host. Uncomment HTTPS redirect in `.htaccess` when the site is HTTPS-only.

7. **Smoke test**  
   - Login each role  
   - Team list loads  
   - Bank import on Bank tab only  
   - Location logos  
   - Numerology + print  
   - Super Admin → Optimise (JSON response, no 500)  
   - Backup download  
   - `/?tab=terms` + Privacy  

8. **Remove diagnostics**  
   Do not leave `rc_diag.php` on production. `tools/diagnostics.php` is session-gated for admin roles only.

9. **Legal**  
   Review Governance / Privacy (DPDP-oriented) copy for your entity and jurisdictions.

10. **Hard-refresh** after deploy so browsers load `dashboard-app.js` / `numerology.css` / `contrast-lock.css`.

## Post-launch

- Super Admin → Optimise after major code upgrades  
- Schedule backups (tools/backup or Super Admin backup)  
- Monitor `tenants/*/data/logs` for ERROR/CRITICAL  

## Support docs

| File | Use |
|------|-----|
| HELP.md | Operators |
| VERSION | Changelog |
| DEPLOY.md | Hosting |
| PACKAGE.md | Tree layout |


## Security notes (20260928.16+)
- Access keys are **bcrypt hashes only** in `tenants/{id}/data/auth_config.php` (no plaintext masters in PHP).
- Rotate hashes after install: `php -r "echo password_hash('YourNewSecret', PASSWORD_DEFAULT), PHP_EOL;"`
- Team photo uploads cannot traverse paths; SVG/HTML uploads are rejected.
- `team_public_json`, `share_link`, and directory print require a signed-in session (admin for share links).
- With `"strict_hosts": true` in `tenants/map.json`, unmapped hosts receive 403 and no new tenant folders are created.
