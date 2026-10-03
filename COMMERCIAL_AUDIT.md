# Commercial pre-launch audit — 20261001.15

## Verified in this package

| Area | Status |
|------|--------|
| PHP syntax (sample entry points) | Pass (`index`, `janam_patri`, `blood_report`, `signature`, `media_serve`) |
| `backup_download.php` auth | Pass — session role gate + path param reject |
| `tools/backup.php` auth | Pass — admin/super_admin only |
| `public_locations` | Pass — only explicit public flags; no “return all” fallback |
| Multi-tenant paths | Present (`tenant_bootstrap`, `TenantStorage`, `tenants/map.json`) |
| Theme contrast Light/Dark/Reserve | Present (`contrast-lock.css`, `tab_team` locks) |
| Email signature Outlook | Present — embed images, PNG social, ≤10px |
| Bank/OEM logos | MasterDirectory + resolvers |
| `.htaccess` + `web.config` | Present |

## Residual risks (operator action)

1. **Change default passwords** immediately after first Super Admin login.
2. **Enable GD** on PHP for signature social letter badges (solid colour PNG works without GD).
3. **Enable intl** for perfect INR grouping (fallback number format exists).
4. **OPcache** `validate_timestamps=0` only after stable deploy + PHP reload process.
5. **IIS hiddenSegments** may block `/tools/` — use `backup_download.php` at root.
6. **Service workers** — hard-refresh or unregister if offline shell caches an old build.
7. Human **WCAG** keyboard/screen-reader pass recommended on production URL.

## Intentionally code-only ZIP

Tenant operational data (team photos, live JSON) is **not** bundled. Deploy code over existing `tenants/*/data/` without wiping data.

