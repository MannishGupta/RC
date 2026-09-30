# Fusion Resource Centre — WCAG 2.2 Level AA  
## Accessibility Audit, Remediation & Implementation Status

**Application:** Fusion Resource Centre (RC)  
**Standard:** [WCAG 2.2](https://www.w3.org/TR/WCAG22/) Level A + AA  
**Build:** 260921.29  
**Date:** 22 September 2026  
**Scope:** Authenticated dashboard, login, Human Capital, cards, dispatch, tracking, forms, modals  

**Important:** This document states **implementation status**, not a legal claim of full WCAG 2.2 AA certification. Automated tooling and code review cannot replace full manual AT testing on production data.

---

## 1. Executive summary

| Metric | Count (approx.) |
|--------|-----------------|
| PHP / view surfaces inventoried | 100+ files |
| Central a11y layer added | `assets/a11y.css` |
| Primary shells remediated | Dashboard, Login, Team tab |
| Criteria reviewed (A+AA applicable) | 50+ |
| Code-level fixes applied this release | Critical/High structural |
| Remaining human verification | Required (see §8) |

**Overall status:** **Partial WCAG 2.2 AA implementation** — foundational defects remediated; residual risk remains on custom Alpine components, numerology theme contrast, generated PDFs, and full NVDA/VoiceOver passes.

---

## 2. Application inventory (summary)

| Category | Examples |
|----------|----------|
| Shell | `index.php`, `app/views/dashboard.php`, `app/views/login.php`, `offline.php` |
| Tabs | `tab_team`, `tab_bank`, `tab_docs`, `tab_events`, `tab_locations`, `tab_tracking`, `tab_dispatch`, `tab_cartags`, `tab_monitor`, … |
| Cards | `cards/business.php`, `id.php`, `visiting.php`, `qr.php`, `numero.php`, numerology views |
| Field ops | `dispatch/`, `runners.php`, `track_share.php`, `vehicle-tags/` |
| Data | JSON under `data/` (not user-facing HTML) |
| Assets | `assets/app.css`, `assets/a11y.css` (new) |
| Forms/modals | `record_editor.php`, Alpine modals in dashboard |

---

## 3. Defect register (selected — code-fixed this release)

| ID | WCAG SC | Level | Severity | Component | Issue | Fix | Status |
|----|---------|-------|----------|-----------|-------|-----|--------|
| A01 | 2.4.1 | A | High | Dashboard | No skip link | `.rc-skip-link` → `#rc-main-content` | Fixed |
| A02 | 2.4.7 / 2.4.13 | AA | High | Global | Weak/removed focus | `:focus-visible` 3px blue ring in `a11y.css` | Fixed |
| A03 | 2.4.11 | AA | Medium | Sticky header | Focus can sit under chrome | `scroll-margin-top` on focused controls | Fixed |
| A04 | 2.5.8 | AA | High | Team icon actions | Targets ~28px | `rc-hit-lg` min 44×44 + labels | Fixed |
| A05 | 4.1.2 | A | High | Icon-only buttons | Name = icon only | `aria-label` + `aria-hidden` on icons | Fixed |
| A06 | 1.3.1 / 2.4.1 | A | Medium | Main region | No main target id | `id="rc-main-content"` on `<main>` | Fixed |
| A07 | 2.2.2 / 2.3.3 | AA | Medium | Motion | No reduced-motion | `prefers-reduced-motion` rules | Fixed |
| A08 | 4.1.3 | AA | Medium | Dynamic status | No polite live region | `#rc-status-live` | Fixed |
| A09 | 3.3.2 | A | Low | Login | Password already labelled | Confirmed `label[for=password]` | Pass |
| A10 | 2.4.2 | A | Medium | Titles | Generic fallback | Fallback title includes “Fusion Resource Centre” | Fixed |

### Known residual defects (not fully closed)

| ID | WCAG SC | Severity | Area | Notes |
|----|---------|----------|------|-------|
| R01 | 1.4.3 | High | Numerology sacred theme | Dark theme historically failed contrast; 260921.24–25 improved; **manual re-check required** on mobile |
| R02 | 4.1.2 | Medium | Alpine modals | Focus trap added for editor, doc preview, mobile more — AT re-test |
| R03 | 2.1.1 | High | Custom card grids | Some `@click` on non-button containers (team card row) |
| R04 | 1.1.1 | Medium | QR / generated images | Need consistent text alternative beside QR |
| R05 | 1.4.10 | Medium | Wide tables | Bank/docs tables may 2D-scroll on 320px |
| R06 | PDF | High | Generated PDFs | Library may not emit tagged PDF — **do not claim PDF AA** |
| R07 | 3.3.8 | AA | Auth | Password-only portal; password managers supported; cognitive test not used — OK pattern; verify OTP flows if added later |

---

## 4. Compliance matrix (applicable WCAG 2.2 A + AA)

Statuses: **PASS** (evidence in code) · **PARTIAL** · **FAIL** · **N/A** · **HUMAN** (needs AT/manual)

| SC | Name | Level | Status | Evidence / notes |
|----|------|-------|--------|------------------|
| 1.1.1 | Non-text Content | A | PARTIAL | Photos use `alt`/empty in places; QR still needs paired text |
| 1.2.1–1.2.5 | Time-based Media | A/AA | N/A | No core video/audio product feature |
| 1.3.1 | Info and Relationships | A | PARTIAL | Landmarks present; tables vary by tab |
| 1.3.2 | Meaningful Sequence | A | PARTIAL | CSS order mostly matches DOM |
| 1.3.3 | Sensory Characteristics | A | PARTIAL | Status often colour + text; monitor needs review |
| 1.3.4 | Orientation | AA | PASS | No orientation lock found |
| 1.3.5 | Identify Input Purpose | AA | PARTIAL | Login uses autocomplete; other forms incomplete |
| 1.4.1 | Use of Color | A | PARTIAL | Not colour-only for all states |
| 1.4.2 | Audio Control | A | N/A | |
| 1.4.3 | Contrast (Minimum) | AA | PARTIAL | Dashboard light UI generally OK; numerology dark needs human check |
| 1.4.4 | Resize Text | AA | HUMAN | Test 200% |
| 1.4.5 | Images of Text | AA | PARTIAL | Prefer real text; cards may use graphics |
| 1.4.10 | Reflow | AA | HUMAN | Test 320px width |
| 1.4.11 | Non-text Contrast | AA | PARTIAL | Focus ring 3:1+ intended |
| 1.4.12 | Text Spacing | AA | HUMAN | |
| 1.4.13 | Content on Hover or Focus | AA | PARTIAL | Tooltips not sole name after icon fixes |
| 2.1.1 | Keyboard | A | PARTIAL | Core nav OK; modal trap residual |
| 2.1.2 | No Keyboard Trap | A | PARTIAL | Modals need full trap audit |
| 2.1.4 | Character Key Shortcuts | A | PASS | No single-key shortcuts found |
| 2.2.1 | Timing Adjustable | A | N/A / PASS | No aggressive timeouts found in UI |
| 2.2.2 | Pause, Stop, Hide | A | PARTIAL | Reduced motion added |
| 2.3.1 | Three Flashes | A | PASS | No flashing content |
| 2.4.1 | Bypass Blocks | A | PASS | Skip link |
| 2.4.2 | Page Titled | A | PASS | AppSEO + fallback |
| 2.4.3 | Focus Order | A | PARTIAL | |
| 2.4.4 | Link Purpose | A | PARTIAL | |
| 2.4.5 | Multiple Ways | AA | PARTIAL | Nav + search |
| 2.4.6 | Headings and Labels | AA | PARTIAL | H1 from context title |
| 2.4.7 | Focus Visible | AA | PASS | `a11y.css` focus-visible |
| 2.4.11 | Focus Not Obscured (Min) | AA | PARTIAL | scroll-margin; sticky bars need device test |
| 2.4.12 | Focus Not Obscured (Enh) | AAA | HUMAN | Out of AA scope; optional |
| 2.4.13 | Focus Appearance | AAA | PARTIAL | Strong indicator provided |
| 2.5.1 | Pointer Gestures | A | PASS | No path-based only gestures found |
| 2.5.2 | Pointer Cancellation | A | PASS | Click on up typical |
| 2.5.3 | Label in Name | A | PARTIAL | |
| 2.5.4 | Motion Actuation | A | N/A | |
| 2.5.7 | Dragging Movements | AA | N/A | No required drag UX found |
| 2.5.8 | Target Size (Minimum) | AA | PARTIAL | Team actions enlarged; audit all tabs |
| 3.1.1 | Language of Page | A | PASS | `lang="en-IN"` |
| 3.1.2 | Language of Parts | AA | PARTIAL | Hindi numerology segments may need `lang` |
| 3.2.1 | On Focus | A | PASS | |
| 3.2.2 | On Input | A | PARTIAL | |
| 3.2.3 | Consistent Navigation | AA | PASS | Sidebar groups stable |
| 3.2.4 | Consistent Identification | AA | PARTIAL | |
| 3.2.6 | Consistent Help | A | N/A / PARTIAL | |
| 3.3.1 | Error Identification | A | PARTIAL | Login status region |
| 3.3.2 | Labels or Instructions | A | PARTIAL | Login OK; editor mixed |
| 3.3.3 | Error Suggestion | AA | PARTIAL | |
| 3.3.4 | Error Prevention | AA | PARTIAL | Delete confirms exist in places |
| 3.3.7 | Redundant Entry | A | PARTIAL | |
| 3.3.8 | Accessible Authentication | AA | PARTIAL | Password + autocomplete; no CAPTCHA cognitive test |
| 4.1.1 | Parsing | A | N/A | Removed in WCAG 2.2 as SC; still validate HTML |
| 4.1.2 | Name, Role, Value | A | PARTIAL | Icon buttons improved |
| 4.1.3 | Status Messages | AA | PARTIAL | Live region present; wire to search count next |

---

## 5. File change log (this remediation)

| File | Change | WCAG | Regression risk |
|------|--------|------|-----------------|
| `assets/a11y.css` | **New** focus, skip, targets, reduced-motion, errors | Multiple | Low |
| `app/views/dashboard.php` | Skip link, main id, a11y CSS, logout/theme names, live region | 2.4.1, 2.4.7, 4.1.2, 4.1.3 | Low |
| `app/views/login.php` | a11y CSS + skip to form | 2.4.1 | Low |
| `app/views/tabs/tab_team.php` | Icon control names + 44px targets | 2.5.8, 4.1.2 | Low |

---

## 6. Regression checklist (every release)

- [ ] Login / logout keyboard only  
- [ ] Skip link lands on main  
- [ ] Sidebar + bottom nav operable with Tab / Enter  
- [ ] Team: open card actions by keyboard  
- [ ] Search + filter announce or visible count  
- [ ] Record editor open/close focus returns to trigger  
- [ ] Delete confirmation keyboard accessible  
- [ ] WhatsApp / VCF / QR still function  
- [ ] Numerology report readable at 200% zoom  
- [ ] Dispatch / tracking maps alternative text or instructions  
- [ ] Print directory readable without colour-only meaning  
- [ ] No new keyboard traps  

---

## 7. Automated vs human verification

| Method | Role |
|--------|------|
| Code review + inventory | Structural SC coverage |
| axe / Lighthouse Accessibility | Heuristics only — **not** certification |
| Keyboard-only walkthrough | **Required** before AA claim |
| NVDA (Windows) + VoiceOver (iOS/macOS) | **Required** for 4.1.2 / 1.3.1 confidence |
| 320px reflow + 200%/400% zoom | **Required** for 1.4.10 / 1.4.4 |
| Contrast analyser on numerology + dark panes | **Required** for 1.4.3 |

---

## 8. Final QA statement

**WCAG 2.2 AA implementation status: PARTIAL.**

Safe, high-impact foundations are in place (skip link, focus visibility, reduced motion, target sizing on primary Human Capital actions, accessible names on key icon controls, language and main landmark).

**Do not state “WCAG 2.2 AA compliant”** until residual items R01–R07 are closed and recorded with manual AT evidence on the production hostname.

**Next remediation priorities**

1. Alpine modal focus trap + restore (`record_editor`, share sheets)  
2. Convert clickable `div` cards to `<button>` / accessible pattern  
3. Numerology contrast human re-test  
4. Wire `#rc-status-live` to search/filter result counts  
5. QR + PDF accessibility strategy document  

---

*Report generated with code remediation for Fusion RC build 260921.29.*


## 260921.29 — Tooltip + Dialog APG implementation

- `assets/a11y-tooltip.js`: role=tooltip, aria-describedby, focus/hover/Escape
- `assets/a11y-focus-trap.js`: dialog semantics + focus trap + return focus
- Record editor: aria-labelledby, aria-describedby, tabindex=-1
- Team icon actions: data-rc-tooltip descriptions (names remain aria-label)
