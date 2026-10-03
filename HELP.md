# Fusion Resource Centre — Operator Guide

**Build:** see `VERSION` (first stamped line) · **Developer:** Arthsathi Limited

## What it is
Multi-tenant directory and operations hub: people, treasury, documents, premises, field tracking, astrology modules, and Super Admin control plane.

## Roles
| Role | Typical password key | Can do |
|------|----------------------|--------|
| **Super Admin** | `blsbls` | Tenants, global optimise/backup, changelog, switch view role |
| **Company Admin** | `lifeisgood` | Full CRUD on this tenant’s data |
| **Visitor / HR** | `alliswell` | View, share, print — no edit/delete/setup |

Super Admin may use the left **role rail** to view as Company Admin or General HR without re-entering a password.

## Tenants & data layout
All live data for a host lives under:

```
tenants/{tenantId}/data/
  team.json, company.json, banking.json, documents.json
  locations.json, designations.json, departments.json, …
  media/images/, media/documents/
  sessions/, logs/, janam/, runners/, …
```

Host → tenant mapping: `tenants/map.json`.  
Do **not** use retired root folders (`/images`, `/docs`, root `bank.json`).

## Day-to-day (Company Admin)
1. **Human Capital Index (Team)** — cards, search, add/edit, import XLSX on Team tab only  
2. **Treasury (Bank)** — accounts & UPI; import XLSX/CSV **only on Bank tab**  
3. **Documents / Locations / Events** — vault and premises (logos via media gateway)  
4. **Share / Print / VCF** — share links, directory print, vCard export (Team)  
5. **Janam Patri / Numerology** — astrology tools; import birth data from team when fields exist  

## Super Admin
1. **Tenants** — provision / edit map entries  
2. **Monitor / Optimise** — repair JSON, media, sessions across **all** tenants  
3. **Backup** — full multi-tenant ZIP (excludes sessions/logs)  
4. **Access / Changelog** — role context and VERSION history  

## Import rules
- Open the **correct tab** first (Bank → bank data, Team → people).  
- Bank sheets cannot write `team.json` (and the reverse is blocked).  
- Formats: `.xlsx`, `.xls`, `.csv`.

## Media
Public URLs: `/images/{file}` → `media_serve.php` (tenant `data/media/images`).  
Upload photos/logos in the record editor; do not place files only under a root `/images` folder.

## Theme
Defaults to OS light/dark (`prefers-color-scheme`). Manual toggle is remembered in the browser.

## Deploy checklist
1. Upload code ZIP over document root (keep each `tenants/*/data` intact).  
2. Confirm `tenants/map.json` and writable `data` folders (`0775` / IIS write ACL).  
3. Hard-refresh browser; run **Optimise** once as Super Admin after major upgrades.  

## Support files
| File | Purpose |
|------|---------|
| `VERSION` | Changelog (UI + operators) |
| `version.php` | `APP_VERSION` constant |
| `HELP.md` | This guide |
| `PACKAGE.md` | Package layout |
| `DEPLOY.md` | Hosting notes |

## Front-end assets (cacheable)
- `/assets/dashboard.css`, `/assets/dashboard-app.js` — main shell
- `/assets/numerology.css` — numerology report theme
- `/assets/contrast-lock.css` — accessibility contrast lock


## Commercial launch
See **LAUNCH.md** for the full go-live checklist.  
**Change default role passwords before public use.** Factory keys are documented for install only.

## Protect tenant data
Never overwrite `tenants/*/data/` from a code ZIP. See DEPLOY.md.


## Engineering
- Refactor roadmap (ROI path): [docs/REFACTOR_ROADMAP.md](docs/REFACTOR_ROADMAP.md)

