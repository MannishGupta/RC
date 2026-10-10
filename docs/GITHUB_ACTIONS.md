# GitHub Actions

| Workflow | File | PHP versions | Purpose |
|----------|------|--------------|---------|
| **RC Safety Check** | `.github/workflows/rc-safety-check.yml` | **8.2 · 8.3 · 8.4** | On every push: `php -l` all PHP files; validate all JSON |
| **PHP Composer** | `.github/workflows/php-composer.yml` | **8.2 · 8.3 · 8.4** | On main push/PR: Composer validate/install only if `composer.json` exists |

Both jobs use a matrix (`fail-fast: false`) so each PHP version is reported independently.

## Activate

1. Push this tree to GitHub (`main` or any branch).
2. Open the repo → **Actions** tab → enable workflows if prompted.
3. Green checks on **all three** PHP jobs = safe to deploy; red X lists the failing file and version.

No secrets are required for these jobs.
