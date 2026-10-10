# Resource Centre — What's New

**Product:** Resource Centre (RC)  
**Release:** **20261005.1** (major)  
**Date:** 05 October 2026  
**Previous line:** 20261003.x (build stream through .53)

This major release consolidates the 20261003.42–.53 stream into one deployable tree: logo/SVG reliability, brand asset terminology, pre-launch UX, and a **single global theme controller**.

---

## Version numbering

| Scheme | Meaning |
|--------|---------|
| `YYYYMMDD.N` | Calendar stream (legacy builds used `20261003.N`) |
| **`20261005.1`** | **Major** consolidation release for 05 Oct 2026 |
| `20261005.2+` | Future patches on this major line |

`version.php` is the only runtime source (`APP_VERSION`). `VERSION` and this file are the human changelog.

---

## 20261005.1 — Major highlights

### Security & media
- Company **Primary logo** / **Site icon** / **Social preview** uploads with singleton merge (partial image save no longer wipes company fields).
- Stable filenames (`company-logo.*`, `company-favicon.*`, `company-cover.*`) + cache revalidation for brand files.
- SVG logos: sanitiser keeps `<style>`, strips scripts/handlers/external refs; `open_basedir`-safe staging; optional `$allowSvg` on dangerous-upload check.
- `media_serve.php`: hardened SVG CSP; `no-cache` for company-* brand files.
- Master brand logos path (`data/masters/logos/`) with jail; SQL folder not web-reachable (`web.config` + `.htaccess`).

### Organisation setup
- **Brand assets** named by use (standard terminology):
  - **Primary logo** — sidebar, login, cards, print, headers  
  - **Site icon** — browser tab / favicon  
  - **Social preview image** — Open Graph / WhatsApp / LinkedIn + org banner  
- Size/format hints + **Used for:** line under each control (`AppMedia::imageSpec`).

### Theme (global CSS controller)
- `assets/contrast-lock.css` is the **single global theme controller** (Fluent tokens, load last).
- Themes: `light` | `dark` | `reserve` via `html[data-theme]`.
- Surface roles: `.rc-surface-default` | `.rc-surface-muted` | `.rc-surface-inverse` / `.rc-hero-dark` | `.rc-surface-accent`.
- Report controls scoped to `body.nr-sacred` / `body.jp-vedic` so dashboard fixes do not break numerology/Janam.
- Tenant Overview uses inverse surface so metrics stay readable.
- See `docs/THEME.md`.

### UX / pre-launch
- Team directory Rank/Name/Loc/Dept + utilities bar **sticky** in scroll area.
- Mobile: hamburger nav drawer + logout in header (sidebar off-canvas &lt;768px).
- Numerology stylesheet link fixed (`<?=` cache-bust); readable report pills/buttons.
- PSI / footer / login shell hardening from prior 20261003 stream retained.

### Hygiene
- `.gitignore` patterns for tenant data, sessions, auth_config (do not commit secrets).
- `docs/AUTH_ROTATION.md`, `docs/PRODUCTION_LAUNCH_CHECKLIST.md`, `docs/THEME.md`.
- CI safety check workflow (PHP lint + JSON) when present under `.github/workflows`.

---

## Build stream changelog (20261003 → this major)

| Version | Summary |
|---------|---------|
| **20261005.1** | **Major** — full tree + theme controller + brand terminology + consolidated fixes |
| 20261003.53 | Global theme controller, surface roles, `docs/THEME.md` |
| 20261003.52 | Super Admin Tenant Overview `rc-hero-dark` contrast |
| 20261003.51 | Brand asset names by use (Primary logo / Site icon / Social preview) |
| 20261003.50 | Brand usage hints under upload controls |
| 20261003.49 | Sticky team chrome; report control CSS; mobile logout/drawer; SQL locked; deploy checklist |
| 20261003.48 | SVG allow/sanitise harden; WP1 gitignore + auth rotation doc |
| 20261003.47 | SVG open_basedir staging; company-* Cache-Control no-cache |
| 20261003.46 | SVG style preserve; modal company file upload; card cache bust |
| 20261003.45–.44 | Logo persist, company_id merge, stable company-* names |
| 20261003.42–.43 | Baseline FULL stream (optimizer, media gateway, Fluent tokens, cards) |

For granular notes see historical `VERSION` entries in git history where retained.

---

## Deploy notes

1. FTP **binary**; upload this tree; **do not** overwrite `tenants/*/data/` with empty sample data.
2. Hard-refresh browsers (Ctrl+F5) so `contrast-lock.css` and JS cache-bust.
3. Confirm `/health.php` (if present) and login on each tenant host.
4. Rotate factory auth hashes per `docs/AUTH_ROTATION.md` before production go-live.
5. Spot-check themes light / dark / reserve: dashboard, Tenant Overview, Organisation brand assets, numerology, Janam, login.

---

## Support

Developer: Arthsathi Limited · Product name: **Resource Centre** (tenant “Fusion” is data only, not the product name).
