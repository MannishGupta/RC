# Resource Centre — commercial launch record

**Release:** 20261006.0 (06 Oct 2026)

## Canonical entry points
| Surface | Path |
|---------|------|
| App | `index.php` → `app/views/dashboard.php` |
| Login | `app/views/login.php` |
| Health | `health.php` |
| Print directory | `print_directory.php` → `tools/print_directory.php` |
| Signatures | `cards/signature.php` + `cards/sig_img.php` |
| Theme CSS | `assets/contrast-lock.css` (single controller) |

## Design rules (maintainers)
- Prefer tokens/surfaces in `contrast-lock.css`; avoid new colour `!important` wars in modules.
- Never display unresolved master codes (`desig_*`, `dept_*`, `loc_*`) as labels.
- Tenant data stays under `tenants/{id}/data/` and is never committed.
- Email clients need raster logos; SVG uploads should produce a PNG sidecar where possible.

## GitHub
- Workflows: safety check, optional Composer, Gitleaks, Dependabot.
- See `.github/` after push; protect `main` with required **RC Safety Check**.

## Related docs
- `docs/PRODUCTION_LAUNCH_CHECKLIST.md`
- `docs/AUTH_ROTATION.md`
- `docs/TENANT_QUICKSTART.md`
