# Brand assets & icons audit — Resource Centre

Verified: **09 Oct 2026** · Build **20261009.11**

## 1. UI icons (Font Awesome Free **7.3.1**)

| Item | Status |
|------|--------|
| Source | Official `@fortawesome/fontawesome-free` npm package |
| Design | **Identical** to Font Awesome’s current Free glyph set |
| Files | `assets/vendor/fontawesome.min.css` + `assets/vendor/webfonts/*.woff2` |

These are not third-party clones; they are FA’s own Free release.

## 2. Product / developer brand (Arthsathi)

| File | Role | Status |
|------|------|--------|
| `assets/brand/arthsathi.svg` | Icon mark | **Owner asset** (supplied by Arthsathi; matches product favicon) |
| `assets/brand/arthsathi-icon.svg` | Icon | Same mark |
| `assets/brand/arthsathi-wordmark.svg` | Wordmark | Owner asset |
| `favicon.svg` | Browser icon | Same mark |

These cannot be “updated from the internet” — the brand owner is the source of truth. If Arthsathi publishes a new lockup, replace these four files.

## 3. Microsoft Outlook (email signature guide)

| File | Before | After (20261009.11) |
|------|--------|---------------------|
| `assets/brand/microsoft-outlook.svg` | SVG Repo monochrome (legacy path icon, 570 B) | **Official 2024–2025 Outlook app mark** from Wikimedia Commons (Microsoft source, ~10 KB) |
| `assets/brand/microsoft-outlook.jpg` | Raster companion for clients that block SVG | Kept for Outlook/desktop paste |

**Note:** Microsoft app icons are trademarks. Use is limited to identifying the product in help/signature guides (nominative fair use). Do not recolour or distort.

## 4. Bank logos (`assets/logos/banks/*.svg`)

| Status | Detail |
|--------|--------|
| **Not official brand packs** | Simplified 128×128 geometric marks for registry UI |
| **Not identical** to current ICICI / HDFC / Axis / etc. brand guidelines |
| **Correct approach** | Super-admin **Brand Logos** tool → upload official SVG/PNG into `data/masters/logos/banks/{slug}.*` (survives deploy; served via `media_serve.php`) |

Replace any bank mark used in customer-facing material with assets from that bank’s brand portal or press kit.

## 5. Vehicle OEM logos (`assets/logos/oems/`)

Same pattern as banks: optional drop-in pack; prefer official OEM brand assets via master logo upload.

## 6. Tenant company logos

Uploaded per tenant (SVG sanitised / raster optimised). Fidelity depends on the file the tenant supplies — RC does not invent brand marks.

## Summary

| Category | Latest official design? |
|----------|-------------------------|
| Font Awesome icons | **Yes** (7.3.1) |
| Arthsathi brand | **Yes** (owner-supplied) |
| Outlook mark | **Yes** (updated to 2024–2025 official) |
| Bank / OEM ship pack | **No** — placeholders; use master upload for production |
| Tenant logos | **As uploaded** by each tenant |
