# Changelog

## 20261003.11 — Real bank & OEM logos

- Local monogram SVGs (letter badges) demoted to last fallback
- Bank logos resolve from masters domain → Clearbit → Google favicon
- `bankLogoFor()` + improved name matching (ICICI Bank → icici)
- Vehicle cards link `oem_logo` to resolved manufacturer (make/model parse)
- VehicleCatalog prefers Clearbit domain marks over simple-icons glyphs
- Client `rcLogoCascade` walks candidate list on image error

