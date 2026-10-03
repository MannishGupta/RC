# Fusion Resource Centre — Pre-Launch QA Audit

**Build audited:** 20261002.23  
**Date:** 02 Oct 2026  
**Scope:** Multi-tenant RC (PHP flat-file JSON, shared hosting IIS/Apache)  
**Method:** Static code review + configuration inspection (not live Lighthouse/crawl of production hosts)

**Status language:** Implementation status — not a claim of full WCAG/legal certification without human verification.

---

## 1. UI/UX

| Check | Status | Evidence / notes |
|-------|--------|------------------|
| Light / dark / reserve / system themes | **Partial pass** | `theme-global.css`, `contrast-lock.css`, `prefers-color-scheme` + localStorage override |
| Text contrast (main) | **Mostly pass** | Contrast-lock forces ink tokens; residual risk on third-party / Tailwind one-offs |
| Text contrast (sidebar, light) | **Fixed in 20261002.23** | Light sidebar was dark navy; now elevated white rail with dark ink |
| Navigation | **Pass with polish** | Left nav + mobile bottom nav; module matrix can hide items |
| Responsiveness | **Needs human test** | Tailwind breakpoints + bottom nav; verify tablet and 320px width |
| Report readability (numero / janam / blood) | **Improved historically** | Print CSS present; human check PDF/print still required |
| Color-blind dual coding | **Partial** | Status pills often text+icon; charts may still rely on color |

### Actions in this release
- Light theme left pane: white elevated background, clear active/hover states, readable count badges.

### Human verification required
- Walk all tabs in Light, Dark, Reserve on Chrome, Edge, Safari, Firefox.
- Keyboard-only nav + one screen-reader sample (NVDA/VoiceOver).

---

## 2. SEO & social meta

| Check | Status | Notes |
|-------|--------|-------|
| Dashboard titles | **Pass** | `AppSEO::generateTags` on dashboard |
| Card / share pages | **Pass structure** | `track_share.php`, `SeoShare`, `og_card.php` |
| Blood / Janam OG | **Pass structure** | Dedicated meta blocks + `tools/og_card.php` |
| Canonical URLs | **Pass on report pages** | Present on blood/janam/share paths |
| `robots.txt` / `robots.php` | **Pass** | Disallows admin tabs, `/data/`, `/app/`, `/tools/`; Sitemap line |
| `sitemap.php` | **Pass** | Public tabs listed; host-absolute URLs |
| JSON-LD | **Partial** | AppSEO emits schema.org blocks for some contexts — verify per card type |
| Twitter cards | **Pass structure** | summary_large_image on key reports |

### Residual risks
- Social crawlers must hit **server-rendered** share URLs (not JS-only).
- Private/tenant data should stay `noindex` where appropriate (admin tabs already Disallow).

### Human verification
- Facebook Sharing Debugger / LinkedIn inspector / WhatsApp preview on one team card, one numero, one janam, one blood report per tenant.

---

## 3. Performance & infrastructure

| Check | Status | Notes |
|-------|--------|-------|
| Compiled CSS vs Play CDN | **Mostly pass** | `app.css` primary; fringe Play CDN risk if any page left |
| Masters JS | **Pass** | Lazy on bank/cartags only (20261002.22) |
| Media cache | **Pass** | `media_serve` Cache-Control + ETag |
| Static expires | **Pass** | `.htaccess` expires/headers for assets |
| OPcache | **Host-dependent** | Shared hosting may show enabled but `validate_timestamps` on |
| intl extension | **Often missing** | INR fallbacks present; not a blocker |
| GD | **Required for signatures** | Confirmed on sample host earlier |
| Core Web Vitals | **Not measured here** | Run PageSpeed Insights on production URL |

### Security / permissions

| Check | Status | Notes |
|-------|--------|-------|
| `/data` HTTP deny | **Pass on Apache** | rewrite rules + tenant `.htaccess` |
| IIS `web.config` | **Verify** | Ensure `hiddenSegments` covers `data`, `tenants`, `tools` on every host |
| Tenant isolation | **Pass by design** | `DATA_PATH` under `/tenants/{id}/data/` |
| Uploads | **Hardened** | dangerous MIME checks; basename slug for photos |
| Public janam | **Pass** | No import/directory without admin (20261002.19) |
| Backup download | **Verify auth** | Must remain admin-only on production |

**Write paths needing 0775 (or IIS write ACL):**  
`tenants/*/data/`, `sessions/`, `media/images`, `media/docs`, `logs/`, `config/`.

---

## 4. Code hygiene & release docs

| Check | Status | Notes |
|-------|--------|-------|
| Version headers | **Present** | Many files carry build stamps (useful for support) |
| Debug noise | **Partial** | Large changelog comments remain in `dashboard.php` — low risk, not stripped wholesale (could hide audit trail) |
| Empty stubs | **Monitor** | Historical empty `CardCache`/`PublicTeam` patterns — loaders treat optional |
| Consolidated What’s New | **This pack** | `docs/WHATS_NEW.md` |

**Not done automatically:** mass deletion of all inline comments (high regression risk). Prefer gradual cleanup on files touched for features.

---

## 5. Compliance, legal & branding

| Check | Status | Notes |
|-------|--------|-------|
| Terms of use | **Present** | `?tab=terms` governance hub |
| Privacy / DPDP-oriented | **Present** | Same hub; Client as fiduciary language |
| Location notice | **Present** | In terms module |
| Accessibility statement | **Present** | In terms module |
| Advisory disclaimers (astro/numero/blood) | **Present** | Module-level disclaimer text |
| “Developed by Arthsathi Limited” | **Strengthened** | Sidebar + content footer + terms |
| License / IP | **Documented in Terms §5** | Software remains Arthsathi; client content remains client |

### Legal residual (counsel, not code)
- Client-specific privacy contacts and retention schedules.
- Employment/location-tracking consent records for runners.
- Cookie/session notice if marketing cookies added later (currently session + preference cookies).

---

## 6. Go-live checklist (operations)

1. DNS + HTTPS valid for every tenant host; document root → single RC code root.  
2. Upload build; confirm `tenants/{id}/data` writable.  
3. Super Admin: module matrix per tenant; passwords rotated from defaults.  
4. Smoke: login three roles, team CRUD, bank QR, cartags logo, location image, event photo, janam share (logged-out), backup.  
5. PageSpeed + mobile screenshot pass.  
6. Delete `rc_diag.php` / `info.php` if present on production.  
7. Confirm `robots.php` and `sitemap.php` return 200 on each host.

---

## 7. Verdict

**Ready for commercial pilot** after human verification of themes, social previews, and IIS hidden segments on each host.

**Not claimed:** formal WCAG 2.2 AA certification, penetration-test clearance, or legal sign-off.
