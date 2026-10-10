# Resource Centre — production go-live checklist

## Before deploy
- [ ] Backup each tenant `tenants/*/data/` (never overwrite with empty seed)
- [ ] Confirm `version.php` matches intended build
- [ ] PHP: `upload_max_filesize` ≥ 12M, `post_max_size` ≥ 40M, `memory_limit` ≥ 256M
- [ ] `IMG_PATH` / `DATA_PATH` writable by web user

## Deploy
- [ ] Upload changed files only (binary FTP for images)
- [ ] Do **not** replace `tenants/*/data/`
- [ ] Hard-refresh (Ctrl+F5) on each host

## Brand images (highest residual risk)
On **one** admin account → Organisation Setup:
- [ ] Primary logo (PNG or SVG) → Save images → hard-refresh → sidebar shows new logo
- [ ] Site icon / favicon → Save → tab icon updates (may need close/reopen tab)
- [ ] Social preview / cover → Save → cover persists
- [ ] If failure: note server message `Received:` line and PHP upload limits

## Auth & session
- [ ] Rotate default role passwords (do not leave factory hashes in production)
- [ ] Delete or expire `data/psi_token.txt` if present
- [ ] Login + logout (desktop + mobile power icon)

## Functional smoke
- [ ] Team directory + print directory (names/designations match screen)
- [ ] Blood report while signed in
- [ ] Numero + Janam in light / dark / reserve (readable)
- [ ] Media Kit list (no duplicates) + share
- [ ] Footer DPDP / Privacy link opens entity-specific page

## Optional polish
- [ ] Lighthouse accessibility on dashboard (reserve theme)
- [ ] `tools/backup.php` once; restore dry-run on empty tenant
- [ ] Upload official bank/OEM marks via Brand Logos if customer-facing
- [ ] Confirm Install App only where desired

## After go-live
- [ ] Watch error log 24h
- [ ] One real logo re-save per major tenant


## Secrets (required for production)
- [ ] Follow `docs/AUTH_ROTATION.md` — unique bcrypt hashes per role
- [ ] Confirm `.gitignore` excludes `auth_config.php` and `psi_token.txt`
- [ ] No plaintext passwords in source comments

## Accessibility (optional polish)
- [ ] Tab through sidebar + header with keyboard only (`:focus-visible` rings)
- [ ] Reserve theme: sample forms + directory cards for contrast
- [ ] Mobile landscape: theme chips + power sign-out reachable
