# Changelog

## 20261003.13 — Super Admin matrix + monitor cleanup

- Restored **Module matrix** on Tenant Setup (enable/disable modules per tenant)
- Monitor: single System Optimizer entry (link to `?tab=opt`) — removed duplicate full panel
- Permissions probe uses **tenant DATA_PATH** only (no legacy `/images`, `/docs`, root `/data`)
- Fixed `$_lib` undefined variable + stripos(null) warnings in library audit

