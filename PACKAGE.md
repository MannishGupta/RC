# Package layout

```
index.php                 Front controller
app/                      PHP application (bootstrap, Optimizer, views, …)
tenants/
  map.json                Host → tenant id
  {tenantId}/data/        Isolated JSON + media + sessions
assets/                   CSS/JS (incl. contrast-lock.css)
cards/                    Business / numerology / signature shells
tools/                    Backup, diagnostics, exports
media_serve.php           Tenant media gateway
VERSION                   Changelog (single file)
HELP.md                   Operator guide
version.php               APP_VERSION
```

Canonical JSON under each tenant `data/`: `team.json`, `company.json`, `banking.json`, `documents.json`, `locations.json`, `designations.json`, `departments.json`, …
