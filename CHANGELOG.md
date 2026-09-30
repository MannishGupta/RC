# Fusion Resource Centre — Changelog

## 20260929.16 — 30 Sep 2026 · UX & dasha math
- **Dasha Now card:** progress % and remaining days were stuck at 0 — `$current` was copied before progress fields were written; fixed assignment order
- **Header:** count badge + IST clock locked high-contrast in light and dark (fixes unreadable "1 entry" / date)
- **Public widgets:** wall clock, weather, AQI forced to a single 3-column row
- **Janam Patri:** removed duplicate Print bar (chrome toolbar only)
- **New team member:** default place of birth `Delhi, IN` and coordinates `28.610000, 77.230000` (28°36′36″N 77°13′48″E)

## 20260929.13 — 29 Sep 2026 · Blood Report team integration
- **Blood Report** (`blood_report.php`): ABO± traits, witty workplace lore, fun facts (pop-culture framed, not medical)
- Team compatibility matrix (simplified textbook “can donate to” model) with green highlights
- Human Capital: droplet action button, clickable Blood · pill on cards, command palette entry
- Hindi / English toggle + Print/PDF; strong non-clinical disclaimer
- Site developer branding: Arthsathi Limited

## 20260929.12 — 29 Sep 2026 · Blood Report module
- Standalone blood intelligence page: focus member by slug/group, team map, donor/recipient lists
- Educational transfusion model only — never for clinical decisions

## 20260929.11 — 29 Sep 2026 · Gochar + house strength
- Janam Patri tabs: **Transits (गोचर)** — natal + today’s transit overlay, notable transit blurbs
- **Strength (बल)** — indicative Sarvashtakavarga-style house bindu bars (simplified, educational)
- Honest disclaimers: not full classical recon / not permanent reading

## 20260929.10 — 29 Sep 2026 · Sade Sati + Vargas
- **Sade Sati** tab: 3-phase strip, Moon vs transit Saturn, next-window estimate
- **Vargas** tab: D-1 / D-3 / D-7 / D-9 / D-10 / D-12 switcher on one diamond chart
- Captions for career, children, parents, siblings themes

## 20260929.09 — 29 Sep 2026 · Report family chrome + Dasha Now
- Unified language + Print controls across Janam Patri, Milan, Numerology action bar
- Patri sub-tabs: Kundli · Dasha · (later Sade / Varga / Gochar / Strength / Doshas)
- **Vimshottari Now card**: current Mahadasha + Antardasha, progress %, plain-language countdown
- Improved dasha balance from Moon nakshatra/pada (not fixed year offset)

## 20260929.08 — 29 Sep 2026 · Numerology locale fix
- **Critical:** removed conflicting `app/Locale.php` (redeclared bootstrap `AppLocale` → fatal on numero)
- Numerology default **hi_IN**; cookie `rc_lang`; `nr_clip` without mbstring
- Lo Shu planet/element Devanagari labels; language toggle on report header

## 20260929.07 — 29 Sep 2026 · Janam Devanagari data labels
- Render-time maps: graha, rashi, nakshatra, bhava in Devanagari (hi) / en-IN (en)
- Tables, diamond chart, Manglik/Ayanamsa labels localized

## 20260929.06 — 29 Sep 2026 · A4 print Janam Patri
- `@page { size: A4 portrait; margin: 15mm; }` dedicated print CSS
- Print masthead/footer, Print Janam Patri action bar

## 20260929.05 and earlier (29 Sep day) — Vedic UI / contrast / signature
- Hindi-default Janam Patri UI, Vedic palette, tab contrast fixes
- Email signature: full address under company name (not location slug only)
- Light-mode data-count contrast locks

## 20260929.01 — CODE baseline
- Multi-tenant RC code tree (CODE-ONLY); commercial launch baseline from 20260928.x

---

## 20260928.17 — 28 Sep 2026
- Numerology: MobileEvaluationEngine optional if missing; clearer missing-file message
- Ensure cards/numerology/engine/* ships complete (including MobileEvaluationEngine.php)

## 20260928.16 — 28 Sep 2026 · Security hardening (pre-launch)
- Auth: removed plaintext master passwords from code; bcrypt-only via auth_config.php
- Endpoints: team_public_json + share_link require session (admin for share); print_directory gated
- Uploads: team photo filename path-safe; SVG/HTML/XML blocked; media_serve forces attachment for residual SVG
- PublicTeam strip no longer includes phone/email/blood_group
- Tenant boot: strict_hosts 403; no auto-create for unknown Host headers
- IIS web.config: hide tenants/tools/app; removed obsolete root bootstrap.php

## 20260928.15 — 28 Sep 2026 · Commercial launch candidate
- LAUNCH.md, diag removed, robots/tools hardened

## 20260928.14 — Asset extraction (dashboard / numerology)

## 20260928.13–.10 — Docs cleanup, legacy paths, bank isolation, contrast

## Earlier 20260925–20260928
- Multi-tenant optimizer, Super Admin dashboard, role tiers, field ops, accessibility passes
- See git history / prior RC-*.zip packages for granular entries

---

### Deploy notes (20260929.13)
1. Upload this package over DocumentRoot (preserve `tenants/*/data/` on server — do not wipe live JSON).
2. **Delete** `app/Locale.php` if an older patch left it (conflicts with `AppLocale` in bootstrap).
3. Ensure writable: `tenants/{id}/data/` (0775).
4. Smoke: `/?tab=team`, `/?card=numero&slug=…`, `/janam_patri.php?view=patri&id=…`, `/blood_report.php`.
