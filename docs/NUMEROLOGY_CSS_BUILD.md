# Numerology compiled CSS — build plan

**Status:** design system (`nr-*`) ships inline in `views/head.php` (260921.03).  
Play CDN remains required until a full Tailwind compile covers ~170 colour utilities used across all tabs.

## Why Play CDN is still present

The report templates use many non-dashboard colour tokens (amber/emerald/rose/fuchsia/indigo shades with opacity variants). `assets/app.css` was compiled for the portal dashboard and does **not** include that set. Removing the CDN without a dedicated build breaks colours.

## Recommended path (when markup stabilises)

### 1. Inventory classes
```bash
# From repo root (or cards/numerology)
grep -rohE 'class="[^"]+"' cards/numerology/views/ \
  | tr ' ' '\n' | sed 's/"//g' | sort -u > /tmp/nr-classes.txt
```

### 2. Local Tailwind project (Node once)
```bash
mkdir -p tools/nr-tailwind && cd tools/nr-tailwind
npm init -y
npm i -D tailwindcss@3.4 postcss autoprefixer
npx tailwindcss init
```

`tailwind.config.js`:
```js
module.exports = {
  content: ['../../cards/numerology/views/**/*.php'],
  darkMode: 'class',
  theme: { extend: {} },
  plugins: [],
}
```

`src/input.css`:
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
/* Optional: @import the nr-* block from head.php as components */
```

### 3. Build
```bash
npx tailwindcss -i ./src/input.css -o ../../assets/numerology.css --minify
```

### 4. Wire in
In `views/head.php`, replace:
```html
<script src="https://cdn.tailwindcss.com"></script>
```
with:
```html
<link rel="stylesheet" href="/assets/numerology.css?v=<?= rawurlencode(APP_VERSION) ?>">
```

Keep the `nr-*` design-system block (or move it into `input.css` under `@layer components`).

### 5. Guardrails
- Rebuild after any new Tailwind class in numerology views.
- CI check: fail PR if `cdn.tailwindcss.com` still referenced once compiled path is default.
- Gzip size target: **< 40 KB** for `numerology.css` (full report subset).

## Team list pagination (portal dashboard) — deferred

**Why not in this release:** requires Alpine list rewrite + optional `?ajax=team_page` endpoint so first paint does not embed the full team array in `__DASHBOARD_STATE__`.

**Sketch:**
1. Server: `AppDB::read('team')` returns page slice (`limit`/`offset`) + `total`.
2. Client: virtual scroll or “Load more” in `tab_team.php`; keep search client-side on current page or server search.
3. JSON: omit non-visible tab payloads from initial `__DASHBOARD_STATE__` until tab first open.

Priority: only if team roster regularly exceeds ~80 rows (HTML/JSON weight becomes noticeable on mobile).
