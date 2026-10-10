# Access key rotation (no code change required)

Factory installs may share known default keys. **Rotate before production traffic.**

## Generate a bcrypt hash

```bash
php -r "echo password_hash('ChooseALongSecret', PASSWORD_DEFAULT), PHP_EOL;"
```

## Apply per tenant

1. Edit `tenants/{id}/data/auth_config.php` (preferred) **or** `data/auth_config.php`.
2. Replace the four role hashes: `super_admin`, `admin`, `public`, `crm`.
3. Save; no deploy of PHP app files is required.
4. Sign out all sessions (or wait for Sunday 00:00 IST weekly force-logout).
5. Verify login with the new keys only.

## Checklist

- [ ] New unique keys per tenant (or shared deliberately with written policy)
- [ ] `auth_config.php` is **not** in git (see `.gitignore`)
- [ ] Example file `data/auth_config.example.php` remains the only template in the repo
- [ ] Operators store keys in a password manager — not chat or tickets
- [ ] PSI preview: delete `data/psi_token.txt` when finished

Do **not** put plaintext passwords in PHP comments or VERSION notes.
