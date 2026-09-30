# PageSpeed / Website Grader Action Plan — rc.fusionlimited.in
Version 260919.01

## LCP (Largest Contentful Paint)
1. Compress hero/banner images to WebP, max width 1600px, quality ~75.
2. Add `fetchpriority="high"` + width/height on the LCP image only.
3. Preload LCP image in `<head>`: `<link rel="preload" as="image" href="…webp">`
4. Defer non-critical CSS; keep critical path under ~50KB.

## TBT (Total Blocking Time)
1. Defer Alpine / charts / QR libraries with `defer` or dynamic `import()`.
2. Remove or delay third-party trackers until `requestIdleCallback` or after first interaction.
3. Avoid large synchronous JSON blobs in HTML when possible; paginate team lists.

## Tracking scripts
1. Load analytics with `async` and after `load` event.
2. Prefer server-side or tag-manager single container over multiple pixels.
3. CSP: allow only known analytics origins.

## JSON-LD (implemented in AppSEO)
- Organization
- LocalBusiness (India, en-IN)
- WebSite + SearchAction
- WebPage
- RealEstateProject (from projects/status records, max 12)

## Quick wins checklist
- [x] WebP banners + lazy-load below fold (`loading="lazy"`) — where images exist
- [x] Font subset Inter 400/700 only; `font-display: swap` (login: system fonts)
- [x] OPcache enabled; APP_VERSION bump on deploy (auto-flush on change)
- [x] CDN cache headers for `/images/*` and `/assets/*` (1 week+ via web.config clientCache)
- [x] Sitemap + robots absolute URLs (sitemap.php / robots.php)
- [x] Font Awesome non-blocking on dashboard (media=print onload) — 260921.01
- [x] Heavy libs (XLSX, EasyQR) loaded only on first use
- [x] Alpine deferred; login page zero third-party CSS/JS
