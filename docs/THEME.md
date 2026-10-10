# Resource Centre — theme system

## Global controller

**File:** `assets/contrast-lock.css`  
**Role:** Single global CSS controller for colour and contrast (Fluent design tokens).

Load order (dashboard):

1. `app.css` (Tailwind utilities — may be purged)
2. Feature CSS (`dashboard.css`, etc.)
3. **`contrast-lock.css` (last)** — wins for theme/contrast

Login and numerology/Janam shells also link this file last (or via `rc_theme_head.php`).

## Themes

Set only on `<html>`:

| `data-theme` | Intent |
|--------------|--------|
| `light` | Default Microsoft Fluent greys + `#0078D4` accent |
| `dark` | Fluent dark greys + brighter accent |
| `reserve` | Warm executive cream + blue accent |

JS should only call:

```js
document.documentElement.setAttribute('data-theme', 'light'|'dark'|'reserve');
```

## Tokens (do not hardcode hex in new UI)

| Token | Meaning |
|-------|---------|
| `--rc-ink` / `--rc-color-text` | Primary text |
| `--rc-ink-muted` / `--rc-color-text-muted` | Secondary text |
| `--rc-paper` / `--rc-color-bg` | Page background |
| `--rc-card` / `--rc-color-surface` | Cards / panels |
| `--rc-border` / `--rc-color-border` | Borders |
| `--rc-accent` / `--rc-color-accent` | Links, active, primary actions |
| `--rc-side-*` | Sidebar only |

## Surface roles (readable pairings)

Prefer classes over inventing new `text-white` + `from-slate-900` combos:

| Class | Pairing |
|-------|---------|
| `.rc-surface-default` | Card + dark/light ink |
| `.rc-surface-muted` | Page paper + ink |
| `.rc-surface-inverse` / `.rc-hero-dark` | Dark gradient + **white** text |
| `.rc-surface-accent` | Accent fill + white text |

**Rule:** White text only on inverse or accent surfaces. Never force white on the whole app.

## Scoped exceptions

| Scope | Purpose |
|-------|---------|
| `body:not(.nr-sacred):not(.jp-vedic)` | Dashboard-only “white on dark Tailwind bg” guard |
| `body.nr-sacred` / `body.jp-vedic` | Report buttons/pills readable in all themes |

## How to fix unreadable text without regressions

1. Identify surface: default card vs dark hero vs report chrome.
2. If dark hero → add `rc-hero-dark` or `rc-surface-inverse` (do not add a new global force-white rule).
3. If report control → extend the report-controls block in this file only.
4. If normal form/card → use tokens / surface-default; check light, dark, and reserve.
5. Spot-check those three themes before shipping.

## Anti-patterns

- Unscoped `color:#fff !important` or `color:#000 !important`
- Readability patches inside `numerology.css` that fight this file
- Relying on purged Tailwind gradient classes without `rc-hero-dark`
