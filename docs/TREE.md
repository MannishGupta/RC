# Application tree (logical)

```
/
  index.php, media_serve.php, manifest.php, health.php, …
  app/                 PHP core (Auth, DB, Optimizer, views)
  assets/
    vendor/            Alpine, FA, Chart, Leaflet (only copy)
    brand/             Arthsathi product marks (developer)
    contrast-lock.css, app.css, …
  status/              Construction Status module (code + CSS)
    assets/            status-only styles (app.css)
    # data/ uploads/   NOT in code deploy — live on server under tenants/…/status/
  tenants/             LIVE ONLY — never ship in code ZIP
  cards/, tools/, docs/
  .github/workflows/   safety + deploy
```

Empty dirs kept with `.gitkeep`: `assets/cars`, `assets/logos/oems`, `data/masters/logos/{banks,oems}` — drop-in logo homes.
