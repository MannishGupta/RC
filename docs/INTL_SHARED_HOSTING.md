# Enabling `intl` on Plesk shared hosting

Resource Centre uses the **intl** extension for ideal INR formatting (`₹` + Indian
digit grouping). Without it, the app **still runs** and uses a built-in fallback.

## Why Monitor may say `intl (INR) = MISSING`

The **web** PHP process (not only CLI) must load `intl`. Shared hosting often
disables it until the host or a PHP selector enables it.

## Method 1 — Plesk “Additional configuration directives” (try first)

1. Log in to **Plesk**.
2. Open the domain that serves Resource Centre (e.g. `rc.arthsathi.com`).
3. Go to **PHP Settings** (sometimes under **Hosting & DNS** → PHP).
4. Scroll to **Additional configuration directives** (or “Additional directives”).
5. Add exactly:

```ini
extension=intl
```

6. Click **OK / Apply**.
7. Wait 1–2 minutes (or restart PHP if Plesk offers “Reload”).
8. Hard-refresh Resource Centre → **Monitor** → check `intl (INR)`.

If the field rejects `extension=intl`, the plan does not allow customer-side
extension loading — use Method 3.

## Method 2 — Upload `.user.ini` (included in this package)

1. Upload **`.user.ini`** to the **same folder as `index.php`** (RC document root), e.g.:

   `D:\INETPUB\VHOSTS\...\rc\.user.ini`  
   or `/httpdocs/rc/.user.ini`

2. Do **not** put it only inside `tenants/` or `data/`.
3. Wait up to **5 minutes** (PHP caches `.user.ini`; default `user_ini.cache_ttl` is often 300 seconds).
4. Hard-refresh Monitor.

**Limitation:** On many shared servers, `extension=intl` inside `.user.ini` is
**ignored**. Other lines (timezone, upload limits) still apply.

Optional: also upload **`php.ini`** next to `index.php` if your host documents
support for a custom domain `php.ini`.

## Method 3 — Ask the host (most reliable on shared plans)

Send this short request:

> Please enable the PHP **intl** extension for domain `rc.example.com`
> (PHP 8.2 / 8.3 / 8.4). We need `extension=intl` so `NumberFormatter` and
> `IntlDateFormatter` are available to the web SAPI. Thank you.

After they confirm, recheck Monitor → `intl (INR)` should show **available**.

## Verify without Monitor

Create a temporary file `intl_check.php` in the document root:

```php
<?php
header('Content-Type: text/plain; charset=UTF-8');
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'extension_loaded intl: ' . (extension_loaded('intl') ? 'YES' : 'NO') . PHP_EOL;
echo 'NumberFormatter: ' . (class_exists('NumberFormatter') ? 'YES' : 'NO') . PHP_EOL;
```

Open `https://your-rc-domain/intl_check.php`. Delete the file afterward.

## If intl cannot be enabled

No further code change is required. `AppLocale::money()` already formats amounts
with Indian-style grouping when intl is absent. Only advanced locale APIs are limited.
