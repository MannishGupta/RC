# Theme, accessibility, DPDP & UX (commercial)

## Singular design system — Microsoft Fluent

| Layer | File / rule |
|-------|-------------|
| **Global theme controller** | `assets/contrast-lock.css` (loaded last) |
| Themes | `light` · `dark` · `reserve` via `html[data-theme]` |
| Tokens | `--rc-ink`, `--rc-paper`, `--rc-card`, `--rc-accent` (#0078D4 light), side/nav |
| Type | Fluent stack: Segoe UI Variable → Segoe UI → Aptos → **Inter** → **Noto Sans Devanagari** |
| Hindi | `:lang(hi)` / `.rc-hi` uses Devanagari-first stack, 1.65 line-height |

Do **not** add competing colour systems in tab PHP. Extend tokens in `contrast-lock.css` only.

## Fonts (English + Hindi)

- **English UI:** Inter (400–800) + system Segoe for native Windows feel  
- **Hindi:** Noto Sans Devanagari (400–700) — high legibility for Devanagari  
- Status module uses the **same** Inter + Noto pair (no separate Playfair/Jakarta)

## WCAG / WCAP-style accessibility

| Practice | Where |
|----------|--------|
| Contrast tokens for 3 themes | `contrast-lock.css` |
| Reduced motion | `@media (prefers-reduced-motion: reduce)` |
| Focus / tooltips | `assets/a11y.css`, `a11y-focus-trap.js`, `a11y-tooltip.js` |
| ARIA on theme/nav | `aria-pressed`, `aria-current`, `role="tab"` |
| Governance accessibility note | `?tab=terms` Accessibility panel |

Target: **WCAG 2.2 AA** contrast on primary text/surfaces in all three themes.

## DPDP Act 2023

| Item | Location |
|------|----------|
| Privacy & DPDP notice | `?tab=terms` → Privacy & DPDP (tenant Data Fiduciary from company record) |
| Grievance officer fields | Company / `company.dpdp` |
| Status analytics consent | `status/consent.php` + DPDP block in `status/config.php` |
| Footer links | Governance · Privacy · DPDP |

Personal data (DOB, blood group, phone, photos) is **tenant-controlled**; platform documents purpose, rights (access, correction, erasure), and fiduciary identity per tenant.

## UI language (friendlier navigation)

Module labels use plain business English (e.g. Construction Status, Organisation Setup, Designations, Departments) instead of internal jargon. Keys/IDs unchanged so data and permissions stay stable.

Principles for copy:
1. Prefer everyday words over internal codes  
2. One clear action per button (Save, Upload, Sign out)  
3. Errors name the problem and next step  
4. Hindi surfaces use Noto Devanagari; keep bilingual reports readable in both themes  
