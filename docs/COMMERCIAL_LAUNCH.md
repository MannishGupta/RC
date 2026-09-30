# Commercial launch checklist — RC 260925.01

See also root **PACKAGE.md** for full package inventory.

## Version

- **Code:** `260925.01` (`yymmdd.xx`)
- **Archive:** `RC-260925.01.zip`
- **Developer:** Arthsathi Limited

## Before go-live

1. **FTP** upload full package; **keep** `tenants/*/data/` intact (never replace with empty tree).
2. **Permissions** `0775` (or IIS write ACL) on each tenant: `data/`, `sessions/`, `logs/`, `media/`, `runners/`.
3. **DNS / SSL** for every public host; DocumentRoot = shared `rc` folder.
4. **map.json** exact host → tenant id for fusionlimited, arthsathi, digisofts, etc.
5. **Passwords** — change Super Admin / Co. Admin / Visitor after first login.
6. **Google Maps API key** (if used) only in tenant-secure config — not in public JS repos.
7. **Delete** `rc_diag.php` and any temporary probes from production.

## Smoke tests

| Check | Action |
|-------|--------|
| Login tiers | `blsbls` / `lifeisgood` / `alliswell` |
| Team + print directory | `?tab=team` |
| Bank | `?tab=bank` (cards, not “Missing UI”) |
| Business card meta | View-source `?card=business&slug=…` → `og:title`, `og:image` |
| WhatsApp scrape | Paste card URL in WhatsApp |
| Janam import | Select All → Import Selected |
| robots / sitemap | `/robots.php`, `/sitemap.php` |
| Super Admin | Access Mode, Tenant Setup, Monitor only |
| Cross-tenant | Each host shows its own team data |

## SEO

- Dashboard: AppSEO (tab meta, OG, Twitter, JSON-LD).
- Public cards: SeoShare (`index,follow`).
- Janam: `noindex,follow`.
- track_share: full SSR meta.

## Performance

- Alpine deferred; SheetJS / QR on demand.
- Non-blocking Font Awesome pattern on cards.
- Prefer self-hosting FA/fonts later for CSP and offline.
- Run **Lighthouse** on production hosts after deploy.

## Branding

Site Developer: **Arthsathi Limited**
