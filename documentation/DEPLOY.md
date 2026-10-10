# Deploy (FTP)

## Rules
- **Never** deploy `data/`, `tenants/`, or `storage/` over FTP from CI — live tenant data and auth stay on the server.
- Deploy is **manual only** (`workflow_dispatch`) or on version tags `v*`.
- Default input: **dry-run = true**. Review the log; paths must not start with `data/`, `tenants/`, `storage/`, `images/`, or `docs/`.

## First run
1. Actions → **Deploy (manual / tag only)** → Run workflow.
2. Choose environment (`fusionlimited` / `arthsathi` / `digisofts` / `simcoauto`).
3. Leave **dry_run** checked.
4. Confirm the log lists only PHP/JS/CSS/assets under the app root.
5. Re-run with dry_run unchecked only after the log looks correct.

## Secrets (per environment)
Set repository or environment secrets: `FTP_SERVER_<ENV>`, `FTP_USERNAME_<ENV>`, `FTP_PASSWORD_<ENV>`, optional `FTP_SERVER_DIR_<ENV>`.

## Sync state
FTP-Deploy-Action may write `.ftp-deploy-sync-state.json` in the web root. Ensure `.json` under web root is blocked by `.htaccess` / `web.config` on each host.
