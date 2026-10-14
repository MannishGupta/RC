# Deploy & safety

## Never overwrite tenant data

Code ZIP or GitHub FTP **must not** replace:

| Path | Why |
|------|-----|
| `tenants/**` | Live tenant JSON, media, status, sessions |
| `data/**` (runtime) | Shared/default runtime if used |
| `status/data/**`, `status/uploads/**` | Legacy status stores (prefer tenant status data) |
| `**/auth_config.php` | Passwords |
| `**/psi_token.txt` | Preview tokens |

- `.github/workflows/deploy.yml` → `dangerous-clean-slate: false` + path excludes.
- **Manual FTP:** upload application code only; never drag a full tree over `tenants/`.
- **Backup:** archive `tenants/` (or `tenants/{id}/`) before risky ops.

## Go-live checklist (short)

1. Deploy code; hard-refresh dashboard + `/status/`.
2. Confirm `/health.php` returns JSON `status:ok`.
3. Super Admin → Optimise once (all tenants); optional `php tools/weekly_backup.php`.
4. Privacy / DPDP footer links work; logo & theme OK in light/dark/reserve.
5. Login, logout, one team save, one media serve of company logo.

## CI

PHP syntax + JSON validity: `.github/workflows/rc-safety-check.yml` (PHP 8.2–8.4).

## Status module

Public: `/status/`. Tenant data should live under `tenants/{id}/data/status/` when configured; do not rely on root `status/data/` for production tenants.

See also [LAUNCH_OPS.md](LAUNCH_OPS.md).
