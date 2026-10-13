# PageSpeed 100 push — 260921.01

PSI measures the **public login page** (unauthenticated). That page is zero third-party CSS/JS.

## login.php (primary LCP surface)
- Removed Google Fonts + Font Awesome (was main render-blocking cost)
- System font stack only
- Inline SVG icons
- Logo: `preload` + `fetchpriority=high` + explicit width/height (CLS)
- Critical CSS inline only
- Tiny deferred-free login script at end of body

## dashboard.php (post-login)
- Inter fonts: 400+700 only, loaded via media=print onload swap
- Font Awesome: non-blocking media=print onload (fixed/confirmed 260921.01)
- XLSX + EasyQRCodeJS lazy-loaded on first export/import/QR use
- app.css preloaded (compiled Tailwind, versioned)

## web.config (IIS)
- clientCache max-age 7 days for static files
- urlCompression static+dynamic

## bootstrap.php
- og:image:type matches real extension (webp/png/jpeg)
- Auto OPcache flush when APP_VERSION changes

## Deploy
```
version.php
app/views/dashboard.php
```
(Also login.php / bootstrap.php / web.config if not already on this pack.)

OPcache flushes on first request after version bump. Hard refresh, then re-run PSI desktop on https://rc.fusionlimited.in/

## Note on 100/100
Lab scores vary with server TTFB. If TTFB > ~0.3s on shared IIS, Performance may land 95–99 even with perfect front-end. Hosting/PHP OPcache and no-store only on HTML (not images) matter.
