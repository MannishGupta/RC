# Tenant data (do not overwrite on code deploy)

- **map.json** — host → tenant routing
- **deleted.json** — tombstones for removed tenants (stops resurrection after ZIP/FTP)
- **{tenantId}/** — live data; keep on server when uploading code

Upload application files only. Run Monitor → Optimise after deploy to scrub ghost folders for tombstoned IDs.
