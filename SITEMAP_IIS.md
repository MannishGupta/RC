# Linking sitemap.php for organic search (IIS / Plesk / BigRock)

## 1. Files at site root
Upload these next to `index.php`:
- `sitemap.php`
- `robots.txt`  (contains: `Sitemap: /sitemap.php`)

## 2. Public URL
After deploy, open:
```
https://rc.fusionlimited.in/sitemap.php
```
You should see valid XML with `/?tab=team`, `/?tab=bank`, etc.

## 3. robots.txt
```
https://rc.fusionlimited.in/robots.txt
```
Must list: `Sitemap: /sitemap.php`

If Plesk serves a different robots.txt, edit it in:
**Plesk → Domains → rc.fusionlimited.in → Files → robots.txt**
or **Hosting & DNS → SEO Toolkit** (disable conflicting robots if any).

## 4. IIS optional rewrite (pretty URL)
In `web.config` (site root), you may add:
```xml
<rule name="SitemapXML" stopProcessing="true">
  <match url="^sitemap\.xml$" />
  <action type="Rewrite" url="sitemap.php" />
</rule>
```
Then both `/sitemap.php` and `/sitemap.xml` work.

## 5. Google Search Console
1. Verify property `https://rc.fusionlimited.in`
2. Sitemaps → Add: `https://rc.fusionlimited.in/sitemap.php`
3. URL Inspection → test `/?tab=bank` and request indexing

## 6. Social share preview
WhatsApp/Facebook fetch pages **without login**.  
`login.php` emits per-tab OG tags from `?tab=` so previews work while logged out.
