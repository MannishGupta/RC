Resource Centre — how to update (simple steps)
==============================================
Package version: 20260929.23

1. Download this ZIP on your computer.
2. Open FileZilla / FTP (or Plesk File Manager).
3. Go to the folder of your website (Document Root), e.g.  .../rc/
4. Upload ALL files from this ZIP, and choose OVERWRITE when asked.
5. In your browser, press Ctrl + F5 (hard refresh) so old icons/CSS clear.
6. Sign in again:
   - Super Admin: blsbls
   - Company Admin: lifeisgood
   - General HR: alliswell

What this update fixes
----------------------
- Left menu Insights: Numero, Janam Patri, Blood Stats in ONE row of icons
  (Company Admin and General HR)
- Team page: four card buttons stay on one row
- Top “X entries” count readable (not dark-on-dark)
- Security: backup download and cache flush only for admins
- Public locations API no longer shows private addresses by mistake
- Numerology: only one language button at the top

Speed check (optional)
----------------------
After login as admin, open:
  https://YOUR-SITE/tools/opcache_status.php
If it says GOOD, do nothing more about speed.
If it says NEEDS HOSTING HELP, message support: “Please enable PHP OPcache.”

Do not delete the “tenants” folder — that holds each company’s data.
