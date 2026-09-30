# Fusion RC — W3C / WCAG-oriented validation readiness

**Build:** 260921.35  
**Claim level:** Built toward W3C HTML5 + WAI-ARIA APG + WCAG 2.2 AA practices. **Not** a formal certification.

## Implemented in code

- HTML5 doctype, `lang`, charset, viewport on primary shells
- Skip link → `#rc-main-content`
- Landmarks: `complementary` sidebar, `banner` header, `main`, labelled `nav`
- Search control: visible association via `<label for>` (sr-only)
- Sort control: `<label for>` + `aria-label`
- Live status region for result counts (`role="status"`)
- Dialogs: `role="dialog"`, `aria-modal`, focus trap (`RcFocusTrap`)
- Tooltips: `role="tooltip"` + `aria-describedby` (`RcTooltip`)
- Icon-only controls: accessible names; decorative icons `aria-hidden`
- Team row cards: `role="button"`, keyboard Enter/Space
- Login: labelled password field, `main` landmark
- Numerology: `lang` normalized, `dir="ltr"`, solid report surface for contrast
- Print: single consolidated `@media print` (numero)
- `prefers-reduced-motion` in `assets/a11y.css`

## Operator checklist (run after each deploy)

1. [ ] https://validator.w3.org/nu/ — login, `?tab=team`, `?card=numero&slug=…`
2. [ ] axe DevTools or Lighthouse Accessibility — same URLs
3. [ ] Keyboard-only: skip link, sidebar, search, open/edit modal, Escape, restore focus
4. [ ] Zoom 200% and 320px width reflow on team + numero
5. [ ] Screen reader sample (NVDA or VoiceOver) on search + one modal

## Residual (human / tooling)

- Alpine-rendered options in `<datalist>` may still flag in strict checkers
- Map embeds and third-party scripts are outside full control
- Full WCAG 2.2 AA remains **partial** until checklist evidence is archived per host
