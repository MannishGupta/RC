# Third-party libraries & fonts — Resource Centre

Last verified: **09 Oct 2026** · App build **20261009.10**

All script/CSS libraries below are **self-hosted** under `assets/vendor/` (no runtime CDN dependency for core UI), except optional Google Fonts for Inter / Noto Sans Devanagari.

| Package | Path | Version | Status | License |
|---------|------|---------|--------|---------|
| **Alpine.js** | `assets/vendor/alpine.min.js` | **3.17.4** | Latest stable | MIT |
| **Chart.js** | `assets/vendor/chart.umd.min.js` | **4.5.1** | Latest stable | MIT |
| **chartjs-plugin-datalabels** | `assets/vendor/chartjs-plugin-datalabels.min.js` | **2.2.0** | Latest stable | MIT |
| **EasyQRCodeJS** | `assets/vendor/easy.qrcode.min.js` | **4.6.2** | Latest stable | MIT |
| **Font Awesome Free** | `assets/vendor/fontawesome.min.css` + `webfonts/` | **7.3.1** | Latest stable | Icons: CC BY 4.0 · Fonts: SIL OFL 1.1 · Code: MIT |

## Fonts

| Font | Source | Usage |
|------|--------|--------|
| **Segoe UI Variable / Segoe UI / Aptos** | System (Windows) | Primary UI (Fluent / WinUI 3) via `--rc-font` |
| **Inter** | Google Fonts (optional CDN) | Fallback UI weight 400/700 |
| **Noto Sans Devanagari** | Google Fonts (optional CDN) | Hindi / numerology / janam |
| **Instrument Serif / DM Sans** | Google Fonts | Public digital cards only |
| **Poppins / Yatra One** | Google Fonts | Janam Patri decorative |

System fonts do not require a download. Google Fonts load with `display=swap` and are allowed in CSP (`fonts.googleapis.com` / `fonts.gstatic.com`).

## How versions were checked

- npm / jsDelivr: alpinejs@3.17.4, chart.js@4.5.1, chartjs-plugin-datalabels@2.2.0, easyqrcodejs@4.6.2
- npm: `@fortawesome/fontawesome-free@7.3.1` (0 known CVEs on Snyk as of check date)

## Update procedure

```bash
# Example: Alpine
curl -fsSL -o assets/vendor/alpine.min.js \
  https://cdn.jsdelivr.net/npm/alpinejs@3.17.4/dist/cdn.min.js
```

After updating Font Awesome, keep `url(webfonts/…)` paths relative to `assets/vendor/fontawesome.min.css`.
