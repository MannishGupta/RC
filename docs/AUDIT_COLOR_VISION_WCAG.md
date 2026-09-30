# Color vision & WCAG contrast audit — Resource Centre

**Build:** 260926.13  
**Standards:** WCAG 2.2 — 1.4.1 Use of Color, 1.4.3 Contrast (Minimum), 1.4.11 Non-text Contrast  
**Scope:** Dashboard shell, design tokens, forms, numerology report, charts, status badges

---

## 1. Audit findings

### 1.1 Color as sole indicator (1.4.1) — **FAIL → remediated**

| Component | Issue | Severity |
|-----------|--------|----------|
| Numerology `getStatusColor` | Supportive = green, Challenging = rose only | High |
| Numerology `getVerdictColor` | Excellent green vs Challenging rose | High |
| Numerology `getHeatmapClass` | Void cells red-only vs filled blue | Medium |
| Timeline Chart.js bars | Current year only thicker *amber* border; series used pure red/green PY palette | High |
| Compatibility radar | Person A indigo / Person B pink — hue-only for two series | Medium |
| Form validation (generic) | Many forms relied on red border alone | Medium |
| Progress / runner status (ops) | Green moving / amber idle / gray offline historically hue-led | Medium |
| Links in body copy | Blue without underline in places | Low |

### 1.2 Contrast (1.4.3 / 1.4.11) — sample ratios

| Pairing | Approx ratio | AA normal text | Notes |
|---------|--------------|----------------|-------|
| `#0f172a` on `#ffffff` | ~16:1 | Pass | Body text |
| `#475569` on `#ffffff` | ~4.6:1 | Pass | Muted floor after fix (was slate-400 ~2.5–3:1) |
| `#94a3b8` on `#ffffff` | ~2.9:1 | **Fail** | Do not use for essential UI text |
| `#d6d3d1` on `#0f172a` | ~10:1 | Pass | Cmd bar (when applied) |
| `#0f172a` forced on dark pills | ~1.2:1 | **Fail** | Fixed 260926.13 cmd-bar lock |
| `#059669` on white | ~4.5:1 | Borderline | Token moved to `#0f766e` |
| `#DC2626` on white | ~4.5:1 | Borderline | Token moved to `#9f1239` |
| Gold `#fde68a` on `#0f172a` | ~10:1 | Pass | Metric numbers |

### 1.3 Problematic palette pairings

| Pairing | Where | CVD risk |
|---------|--------|----------|
| Red / green | Status, heatmap voids, PY 5 vs 9 | Deuteranopia / protanopia |
| Green / brown | Limited | Moderate |
| Blue / purple | Compatibility A/B (indigo/pink) | Tritanopia weaker; still dual-code |

---

## 2. Remediation plan (implemented)

1. **Status helpers** (`cards/numerology/helpers.php`): teal/sky/violet instead of pure green/red; **solid / dashed / dotted** borders; font-weight dual-coding.
2. **Personal Year colors**: Wong-safe palette (orange, sky, yellow, blue, bluish-green, purple, gray, vermillion, black).
3. **Timeline chart**: current year = **4px dark border**; non-current thinner; tooltip still names year + “Current”.
4. **Compatibility radar**: blue vs vermillion, **circle vs triangle** points, **dashed** border on series B.
5. **Global `assets/a11y.css`**: form error left-bar + ⚠ prefix; success ✓; progress **striped** fill; links underlined; `prefers-contrast: more`; muted text floor.
6. **Design tokens**: darker success/danger/warning for AA on white.
7. **Command bar** (prior 260926.13): light text on dark chrome; FA load fix.

### Recommended hex (ongoing)

| Role | Hex | Use |
|------|-----|-----|
| Text primary | `#0f172a` | Body |
| Text muted | `#334155` or `#475569` | Secondary |
| Info / link | `#1d4ed8` | Links + underline |
| Success | `#0f766e` | + ✓ or solid border |
| Warning | `#b45309` | + ▲ or dashed border |
| Error | `#9f1239` | + ■ or dotted/dashed |
| Chart series A | `#0072B2` | Solid |
| Chart series B | `#D55E00` | Dashed / different pointStyle |

---

## 3. Human verification checklist

- [ ] Mobile numerology: tab labels readable; icons visible
- [ ] Light + dark theme: no white-on-white / black-on-navy body
- [ ] Form save error: message text present, not only red border
- [ ] Timeline chart: current year identifiable with grayscale simulation
- [ ] Compatibility radar: two people distinguishable in grayscale
- [ ] Keyboard: `:focus-visible` ring on tabs, inputs, buttons
- [ ] Optional: axe DevTools / Lighthouse accessibility on `?tab=team` and numero report

**Do not claim full WCAG 2.2 AA certification** without independent tool + assistive-tech pass. This release implements evidence-based remediations for color and contrast defects identified in source.


## 4. Remaining checks completed (260926.15)

| Check | Result | Fix |
|-------|--------|-----|
| Runner status strip (green/amber/gray only) | Fail → Fixed | Sky/amber/slate + solid/dashed/dotted borders + ●▲■ labels |
| Runner list badges | Fail → Fixed | `stateLabel()` text + dual border styles |
| Map markers | Fail → Fixed | Wong blues/amber/gray; legend under map |
| Record editor errors | Strengthened | Dashed border + ⚠ + role=alert |
| Profile meta on dark hero | Strengthened | Forced light slate + gold icons |
| Ops/tracking/dispatch emerald→sky | Softened | Reduce pure-green status reliance |
| Live region / print / disabled | Added | Visible toast style; print black; disabled dashed outline |
| Human: keyboard focus | Prior a11y.css | `:focus-visible` 3px blue ring retained |
| Human: grayscale charts | Prior 14 | Timeline border weight; radar dash+shape |

Still recommended offline: axe DevTools + VoiceOver/TalkBack pass on login, team edit modal, and numero tabs.
