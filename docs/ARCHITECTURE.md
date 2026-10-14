# RC Architecture 3.1.0

## Core vs tenant data
- **Deployable code:** `app/`, `assets/`, `cards/`, `status/` (code), root PHP, `tools/`, `docs/`.
- **Never overwrite:** `tenants/{id}/data/**`, `**/auth_config.php`, sessions, logs, media, backups.

## Request path
Browser → `index.php` / surface scripts → `tenant_bootstrap` → `bootstrap` → views or JSON actions.

## Compliance
- Notices: `tenants/{id}/data/compliance/notices/`
- Consent log: `tenants/{id}/data/compliance/consents.jsonl`

## Maintenance
`php tools/maintenance_worker.php` — sessions & cache purge (safe for cron).

## Core path (3.1.1+)

- Canonical engine modules live under **`core/`** (Compliance, I18n, WhatsNew).
- **`app/Compliance`** and **`app/WhatsNew`** remain thin shims for backward-compatible requires.
- Do not store tenant JSON under `core/`.

## Quality tooling

- Dev-only: `composer.json` → PHPStan level 5 on `core/`, PHPCS PSR-12 on `core/`.
- Production hosts do not need Composer; CI runs tools on GitHub Actions.
