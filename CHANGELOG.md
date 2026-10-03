# Changelog

## 20261003.12 — Logo proxy + location media

- New `logo_proxy.php`: server fetches Google/DuckDuckGo/Clearbit, caches 30 days under `data/cache/logos/`
- Bank + OEM logo URLs use same-origin proxy (no browser Clearbit block)
- `media_serve` searches more folders (media/locations, uploads, assets/locations)
- Location images: multi-step fallback (media_serve → /images → webp → jpg)
- Vehicle header logo shell + cascade; hide broken-image glyphs

