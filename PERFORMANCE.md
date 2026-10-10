# Resource Centre — Performance & CWV notes (20260929.21)

## What this package configures in code
- `.user.ini` / `php.ini` templates for timezone, limits, optional OPcache/JIT comments
- Apache `.htaccess`: gzip/deflate, optional Brotli, long-cache for CSS/JS/fonts/images
- `media_serve.php` already sends `Cache-Control: public, max-age=604800` for tenant images
- Dashboard: preconnect to Google Fonts; fonts load with `display=swap` pattern
- Team cards: `loading="lazy"` + width/height on photos
- Business cards: `fetchpriority="high"` on primary photo
- `tools/opcache_status.php` — admin-only OPcache/JIT verification

## Shared hosting (Plesk / BigRock) — do this in the panel
1. **PHP → OPcache**: enable Zend OPcache if listed.
2. **Additional configuration directives** (paste):
```
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.validate_timestamps=1
opcache.jit=tracing
opcache.jit_buffer_size=64M
```
3. Keep `validate_timestamps=1` unless you can reload PHP-FPM after every FTP deploy.
4. **HTTP/2**: enable in Plesk SSL/TLS or at Cloudflare; not controlled by PHP files.
5. **Brotli**: Cloudflare “Brotli” toggle, or Apache `mod_brotli` if the host provides it.

## After deploy
1. Sign in as admin → open `/tools/opcache_status.php` (or Monitor if linked).
2. Confirm `opcache_enabled` true.
3. Run [PageSpeed Insights](https://pagespeed.web.dev/) on a public card URL and the team tab.
4. Hard-refresh after asset deploys (`?v=APP_VERSION` is already used on many CSS links).

## Not done in app code (host/CDN)
- Redis/Memcached (no server module assumed on shared plans)
- Full-page HTML cache for authenticated app shell (session-specific)
- HTTP/3 (QUIC) — CDN/panel only
- Mass AVIF conversion of existing tenant uploads (run offline; serve via `<picture>` when files exist)
