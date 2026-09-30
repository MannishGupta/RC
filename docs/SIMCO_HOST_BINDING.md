# rc.simcoauto.com — Host Binding Checklist

Goal: `https://rc.simcoauto.com/` stays in the address bar and serves tenant **simcoauto**  
(`tenants/simcoauto/data/`). **No redirect** to arthsathi.com or any other host.

---

## A. DNS (GoDaddy — domain simcoauto.com)

| Type | Name | Value | TTL |
|------|------|--------|-----|
| A | `rc` | `103.195.185.254` (RC / Plesk Windows IP) | 600 or 1 hour |

- Do **not** enable GoDaddy **Forwarding** / **Masking** to arthsathi.com.
- Optional: A record for bare `simcoauto.com` only if you want apex → same RC (map already lists apex → simcoauto).

Verify: `nslookup rc.simcoauto.com` → `103.195.185.254`.

---

## B. Plesk (BigRock Windows — shared with other RC hosts)

1. **Add Domain** or **Subdomain**: `rc.simcoauto.com`  
   (If only **Domain Alias** is available, alias of the **RC** vhost whose document root is the RC folder — **not** the main arthsathi.com marketing site.)

2. **Document root** (must match other RC hosts), example:  
   `D:\INETPUB\VHOSTS\fusionlimited.in\rc`  
   Folder must contain `index.php`, `app/`, `tenants/`.

3. **Turn OFF** any of:
   - Redirect / Forwarding to `www.arthsathi.com` or `arthsathi.com`
   - “Redirect with HTTP 301” on domain alias
   - Frame forwarding / masking

4. **SSL/TLS**: issue Let’s Encrypt (or upload cert) that includes **`rc.simcoauto.com`**.  
   Certificate for `arthsathi.com` alone is **not** valid for Simco.

5. Restart / apply web server if Plesk prompts.

---

## C. RC application (already in package)

| Item | Value |
|------|--------|
| `tenants/map.json` exact | `"rc.simcoauto.com": "simcoauto"` |
| Data path | `tenants/simcoauto/data/` |
| App redirects | None between tenants |

After DNS + Plesk: open Super Admin → Tenant Setup → **Modify** simcoauto if hosts need updating (save works on build **260926.30+**).

---

## D. Verify

```bash
curl -sI http://rc.simcoauto.com/
# Expect: 200 or redirect ONLY to https://rc.simcoauto.com/...
# NOT: Location: https://www.arthsathi.com/
```

Browser: `https://rc.simcoauto.com/` — bar stays on **simcoauto**.

Optional: `https://rc.simcoauto.com/rc_diag.php` → `TENANT_ID: simcoauto`, `HTTP_HOST: rc.simcoauto.com`.

---

## E. If still redirected

1. Plesk → **Hosting Settings** for `rc.simcoauto.com` → Preferred domain / redirect  
2. IIS site bindings: host header `rc.simcoauto.com` → RC site  
3. Clear CDN / Cloudflare proxy if any  
4. `curl -sI` again after each change
