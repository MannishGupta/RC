# Production launch checklist — Resource Centre

**Build:** 20261006.0

## A. Deploy
- [ ] Backup every `tenants/*/data/` (and shared `data/` if used)
- [ ] Upload release files in **binary** mode; do **not** overwrite `tenants/*/data/`
- [ ] Deploy the **same** build to every tenant host
- [ ] Hard-refresh (Ctrl+F5); confirm footer version **20261006.0**
- [ ] `GET /health.php` → JSON `status: ok` and matching version

## B. Security
- [ ] HTTPS only
- [ ] Complete `docs/AUTH_ROTATION.md` on each host
- [ ] No public listing of `/data` or `/tenants`
- [ ] Delete any `psi_token.txt` left from demos
- [ ] GitHub Actions **RC Safety Check** green on the release commit

## C. Product smoke (per host)
- [ ] Login (new portal) works
- [ ] Theme: Light / Dark / Reserve / System readable
- [ ] Sign out visible (sidebar bottom + mobile **Out**)
- [ ] Organisation logo saves (PNG and/or SVG)
- [ ] Team directory labels show names (not `desig_2`)
- [ ] Directory print matches on-screen roles/locations
- [ ] Email signature shows logo (not letter plate) after logo re-save if needed
- [ ] Blood / numerology / janam open when signed in

## D. Legal / DPDP
- [ ] Privacy / DPDP page names real data types (DOB, blood, phone, etc.)
- [ ] Named contact for access / erasure
- [ ] Client sign-off on terms if required

## E. Handover
- [ ] Share `docs/TENANT_QUICKSTART.md` with tenant admins
- [ ] Support owner named
- [ ] Demo/sample data removed from production tenants

When A–E are done, the host is **commercially live**.
