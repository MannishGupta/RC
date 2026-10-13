# Deploy safety — tenant data never overwritten

## Guarantee

A **complete code** ZIP or GitHub FTP deploy **must not** replace:

| Path | Why |
|------|-----|
| `tenants/**` | All live tenant JSON, media, status data, sessions |
| `data/**` | Shared/default runtime (if any) |
| `status/data/**` | Legacy status JSON (if still present on host) |
| `status/uploads/**` | Legacy status media |
| `**/auth_config.php` | Passwords |
| `**/psi_token.txt` | Preview tokens |

`.github/workflows/deploy.yml` sets `dangerous-clean-slate: false` and excludes the paths above.

**Manual FTP:** upload only application code. Never drag a full folder over `tenants/`.

**Tenant backup:** archive `tenants/{id}/` (or whole `tenants/`) separately before any risky ops.

## What *is* replaced on code deploy

PHP, JS, CSS, `assets/vendor/*`, `assets/brand/*` (product), `status/*.php`, `status/assets/*.css`, root favicons, workflows.

## Libraries — single source

| Library | One location |
|---------|----------------|
| Alpine 3.17.4 | `assets/vendor/alpine.min.js` |
| Font Awesome 7.3.1 | `assets/vendor/fontawesome.min.css` + `webfonts/` |
| Chart.js 4.5.1 | `assets/vendor/chart.umd.min.js` |
| Leaflet 1.9.4 | `assets/vendor/leaflet/` |
| Bootstrap 5.3.8 | CDN **only** under Status module (layout dependency) |
| Fonts (UI) | Google: **Inter** + **Noto Sans Devanagari** (main + Status) |

Status no longer loads Playfair / Plus Jakarta separately.
