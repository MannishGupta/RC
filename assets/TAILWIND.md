# Compiled Tailwind — optional, NOT enabled by default

`assets/tailwind.min.css` is a pre-compiled stylesheet that can replace the
Tailwind Play CDN. It is **shipped but not wired in**, deliberately. Read this
before switching.

---

## The numbers

| | Play CDN (current) | Compiled |
|---|---|---|
| Transferred | ~380 KB JS | 21 KB gzipped (171 KB raw) |
| Work per page load | Compiles CSS in the browser | None |
| Works offline | No | Yes |
| Production-supported | **No** — Tailwind's own docs say so | Yes |
| Rebuild needed after markup edits | No | **Yes** |

## Why it is not switched on

That last row is the whole argument.

The Play CDN generates styles at runtime, so any class you type works
immediately. A compiled stylesheet contains only the classes that existed
**when it was built**. Add `mt-7` to a tab tomorrow and it silently does
nothing — no error, no console warning, just an element that ignores its
margin. That is a genuinely unpleasant class of bug to chase.

This project has ~60 tab and card templates and saw ten releases in a single
day. Markup changes often, and there is no Node on the shared host to rebuild
with. So switching means: every future markup change requires someone to
regenerate this file, and forgetting produces silent visual breakage.

That is a real cost, paid repeatedly, against a one-time performance win. On a
small internal tool, the CDN's convenience may well be worth its weight.

**Switch if:** the markup has settled, offline mode matters, or the page-load
cost is being felt.
**Stay on the CDN if:** you expect to keep editing templates at the current
rate.

---

## How to switch

In each of the six files that load the CDN, replace:

```html
<script src="https://cdn.tailwindcss.com"></script>
```

with:

```html
<link rel="stylesheet" href="/assets/tailwind.min.css">
```

Files (verified in 260907.8):
`app/views/dashboard.php`, `cards/id.php`, `cards/qr.php`,
`cards/visiting.php`, `cards/numerology/views/head.php`,
`tools/diagnostics.php`.

Then hard-refresh and check: the dashboard at desktop width (multi-column
grids), the numerology report in dark mode, and a card on a phone.

## How to rebuild after editing markup

Needs Node on any machine — not on the server.

```bash
cd assets
npm i -D tailwindcss@4.3.3 @tailwindcss/cli@4.3.3
npx @tailwindcss/cli -i tailwind.in.css -o tailwind.min.css --minify
```

**v4 is CSS-first — there is no `tailwind.config.js`.** Sources, safelist and
theme overrides all live in `tailwind.in.css`.

### The safelist matters

Ten numerology templates build class names by interpolation:

```php
class="text-<?= $tone ?>-500 bg-<?= $c ?>-50 dark:bg-<?= $c ?>-900"
```

Static scanning cannot see those — `$tone` is only known at runtime. Without a
safelist the compatibility, corrections, muhurat, vastu, timeline, structural,
personality, forecast and remedies tabs lose most of their colour.

v4 expresses this with `@source inline(...)` and brace expansion, in
`tailwind.in.css`:

```css
@source inline("{,dark:}{text,bg,border}-{slate,gray,...,pink}-{50,...,900}");
```

1,200 utilities, about 28 KB of the total. That is the price of supporting
dynamic class names, and worth paying over hunting down which nine templates
lost their colour.

## v3 → v4 compatibility layer

`tailwind.in.css` restores two v3 behaviours that v4 changed, because the
markup relies on both:

- **Shadow scale.** v4 shifted it one step: v3's `shadow` became `shadow-sm`,
  and v3's `shadow-sm` became `shadow-xs`. This codebase uses `shadow-sm` in
  **117 places** as the intended subtle shadow, so v4 unchanged would make
  every card visibly heavier. `@theme` restores the v3 values, so the class
  names keep meaning what the markup intends — better than rewriting 117 call
  sites for no benefit.
- **`outline-none`.** v4 redefined it as `outline-style: none`, which removes
  the outline entirely, including the focus ring browsers draw in
  forced-colors/high-contrast mode. v3 kept a transparent 2px outline exactly
  so focus stayed visible. Used in **30 places**, all on inputs and buttons
  that style their own focus. `@utility` restores v3's behaviour; v4's
  `outline-hidden` remains available where the harsher version is wanted.

---

## If you switch and something looks unstyled

It is almost certainly a class missing from the build. Find it with DevTools
(inspect the element, look for a class with no matching rule), add it to the
safelist, rebuild. Or revert the `<link>` back to the `<script>` tag — the CDN
keeps working and nothing else depends on this file.
