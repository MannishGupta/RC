# Launch operations

## Rotate access keys (required per live tenant)

1. On the **server only**, set unique keys for Super Admin and Company Admin (tenant `auth_config.php` or app auth UI).
2. Never commit `auth_config.php`. Never reuse demo keys on production.
3. After rotation, sign in with the new keys and confirm old keys fail.

## Deploy rule

Upload **code only**. Never replace `tenants/`. Use `.github/workflows/deploy.yml` excludes or selective FTP (`app/`, `assets/`, `cards/`, root PHP).

## After every deploy

1. Hard-refresh all tenant hosts.
2. `GET /health.php` → `status:ok` and expected version.
3. Super Admin → Optimise once (sessions + all tenants).
4. Company Admin → Organisation Setup visible and saves.
5. Visiting card renders (no raw PHP text).
6. Privacy / DPDP footer links open.
