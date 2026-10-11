# v14.1.0 — Resource Centre bridge, canonical public install, SEO and OG

Everything you asked for in one release. Nothing in `data/` or `uploads/`
changes; this is a code drop on top of v14.0.0.

---

## 1 · Architectural compatibility with the Resource Centre

I read the actual repo (`github.com/MannishGupta/RC`, `main`) rather than guess,
and built the bridge against what is there: a PHP front controller that resolves
`?tab=` through `$viewMap` and includes views with `require $viewFile` inside an
output buffer, an `AppAuth`/`ModuleRegistry` pair with per-tenant `modules.json`,
and a root `web.config` with its own filtering rules.

**`status-tab.php`** is the module. One file, three lines to mount:

```php
if ($tab === 'status') { require __DIR__ . '/status/status-tab.php'; }
```

It emits no `<html>`/`<head>`/`<body>`, sends no headers, starts no session,
prefixes all its variables `$rc*`, honours `ModuleRegistry::isEnabled('status')`
when that class is loaded, and is **read-only** — no POST handler, no CSRF
surface, no admin action. Registering it in `catalogue()` is optional, because
`isEnabled()` returns true for ids it does not know, so the tab works before
anyone edits the registry; registering is what gives a tenant the off switch.

**`assets/app-embed.css`** is a generated build of `app.css` in which every one
of **287 selectors** is prefixed `.rc-status` (verified: 0 unscoped) and the
print/A4 layer is stripped. The embed cannot reach the Resource Centre's own
styles and cannot affect its print output.

Both modes read the same `data/`, so an update published in the console appears
in the tab on the next request. No sync, no second copy.

Full contract, the RC config constraints the portal has to live with, and the
hook for per-tenant data: **`INTEGRATION.md`**.

> **Verified** against a simulated host shell that defines `APP_VERSION`,
> declares a `ModuleRegistry`, opens a session and includes the fragment inside
> `ob_start()` — exactly RC's include position: 3,705 bytes emitted, no document
> tags, one `.rc-status` wrapper, no PHP notices, and **0 bytes** when
> `modules.json` disables the module.

## 2 · Synchronised versioning

**`version.php`** mirrors the RC's convention exactly — `YYYYMMDD.build` guarded
with `!defined()`, plus `APP_VERSION_DATE`:

| | Shown in the footer |
|---|---|
| Standalone at `/status/` | `v20261011.1 · 11 Oct 2026` (its own build) |
| Embedded in the RC | the **host's** `APP_VERSION`, marked *(build from host application)* |

`STATUS_RELEASE = '14.1.0'` is the portal's own release line either way — that is
what to quote in a bug report. Requested directly, `version.php` returns a JSON
report (both versions, inherited flag, PHP version, canonical host).

It is `version.php` and not `version.json` **because the RC root `web.config`
disallows `.json`** — a `version.json` would 404 under the RC and work
standalone, which is the worst of both. Same reason for `sitemap.php`,
`feed.php` and `manifest.php`.

## 3 · `rc.arthsathi.com/status` as the canonical public install

`seo.canonical_base` in `config.php` names it. Every other host serving the same
files — a staging copy, `rc.fusionlimited.in` — is detected automatically and
serves `noindex, follow` **with the canonical still pointing at
`rc.arthsathi.com`**, so a mirror can never duplicate or outrank the real site.

> **Verified on a live server.** `Host: rc.arthsathi.com` →
> `index, follow, max-image-preview:large…` + canonical + `og:url` on the
> canonical host. `Host: rc.fusionlimited.in` → `noindex, follow`, canonical
> unchanged. `preflight.php` reports which mode the host it runs on is in.

## 4 · Developer footer

A standard footer on every page, from `config.php` → `footer`: developer name
and link, version label (inherited-aware, per §2), support email, and the
privacy-notice link the DPDP notice needs anyway. `footer.show_version` turns
the build string off if you would rather not advertise it.

## 5 · Meta, Open Graph and organic SEO

`seoHead()` in `lib.php` emits, on every public page:

- title, description, keywords, author, canonical, robots (per §3), theme-colour
- **Open Graph** — `og:type`, `title`, `description`, `url`, `site_name`,
  `locale` (`en_IN`), `image` + `image:width`/`height`/`alt`
- **Twitter** — `summary_large_image` card, `twitter:site` from config
- hreflang `en-IN` + `x-default`, RSS `alternate`, sitemap, manifest, preconnect
- **JSON-LD `@graph`** — `Organization`, `WebSite` (with `SearchAction`),
  `BreadcrumbList`, **`ApartmentComplex`** (the development itself, with the RERA
  number as an `identifier`), and an `ItemList` of the latest updates

> **Verified:** the JSON-LD parses, the graph contains exactly those five types,
> and `UPRERAPRJ228846` is present as an identifier.

New endpoints, all serving correct content types:

| | |
|---|---|
| `sitemap.php` | XML sitemap with `image:image` entries (capped at 1,000) |
| `feed.php` | RSS 2.0, latest 50 updates |
| `manifest.php` | installable web-app manifest |

> **Verified:** both XML documents parse; sitemap 3 `<url>` + 1 `<image:image>`,
> feed 1 item, correct `Content-Type` on all three.

## 6 · AI-search-optimised OG images

`ogBuildImages()` renders a **1200×630 PNG** share card with GD: Fluent
communication-blue field, project name, tower count and update count, the RERA
number, and the canonical host — all text baked into the pixels, which is what
an AI crawler or a social unfurl can actually read. It runs as part of
**Optimise**, so the card refreshes when the content does, and falls back to the
static image if GD has no FreeType.

> **Verified by generating it:** `assets/og/og-home.png`, 1200×630, 30 KB, and
> `seoHead()` then prefers it over the static image automatically.

Plus `llms.txt` and `ROOT-robots-additions.txt` for AI crawlers. Per your
choice, **GPTBot, ClaudeBot, PerplexityBot, Google-Extended and friends are
allowed** (`seo.allow_ai_crawlers`).

> **Important, and easy to miss:** `robots.txt` and `llms.txt` are only read from
> the **domain root** — never from `/status/`. The RC already serves `robots.txt`
> via a `robots.php` rewrite, so merge `ROOT-robots-additions.txt` into that
> script rather than dropping a file. `preflight.php` now warns when the domain
> root has neither.

---

## Upload

Everything here replaces its counterpart in `\status\`. New files are marked.

```
config.php           ← REVIEW: new 'seo', 'integration' and 'footer' sections
lib.php
version.php          ← new
status-tab.php       ← new  (the RC bridge; harmless standalone)
sitemap.php          ← new
feed.php             ← new
manifest.php         ← new
index.php  admin.php  admin_backend.php  report.php  actions.php
analytics.php  backup.php  reminder.php  error.php
consent.php  privacy.php
preflight.php        ← updated; delete after sign-off
assets\app.css
assets\app-embed.css ← new  (scoped build for the RC embed)
uploads\web.config
web.config           ← unchanged from v13.0.1; keeps the <clear />
INTEGRATION.md       ← new  (documentation; do not upload)
VERSION
llms.txt                  ← belongs at the DOMAIN ROOT, not in /status/
ROOT-robots-additions.txt ← merge into the root robots, do not upload as-is
```

Then:

1. Edit `config.php` → `seo.canonical_base` (already
   `https://rc.arthsathi.com/status/`), `seo.twitter_site`, and
   `footer.support_email`.
2. Merge `ROOT-robots-additions.txt` into the domain root's robots output and
   copy `llms.txt` to the domain root.
3. Run `preflight.php?k=preflight`. The new **4b** section reports canonical
   status, version mode, the OG card and the domain-root robots situation. Fix
   every `[FAIL]`.
4. Log in and run **Optimise** once — it generates the OG card.
5. Delete `preflight.php`.
6. Submit `https://rc.arthsathi.com/status/sitemap.php` in Search Console.

To mount the RC tab, follow `INTEGRATION.md` §1 — three lines, nothing else in
the Resource Centre changes.

Rollback is restoring the v14.0.0 files; the new files are additive and inert if
nothing references them.

## Verified before shipping

- Canonical and mirror behaviour, on a live server, both hosts.
- JSON-LD graph parsed and type-checked; RERA identifier present.
- `sitemap.php` / `feed.php` well-formed XML; `manifest.php` valid JSON; all
  three with correct `Content-Type`.
- OG card actually generated: 1200×630 PNG, and picked up by `seoHead()`.
- The fragment embedded in a simulated RC shell, enabled and disabled.
- `app-embed.css`: 287 selectors, 0 unscoped.
- `preflight.php` run through a real server on both hosts: 0 failures.
- `php -l` clean on all 19 PHP files.

## Still outstanding

- **Per-tower deep links** (`?tower=<id>`) — the largest remaining SEO win. Each
  tower gets its own indexable, shareable URL and its own sitemap entry and OG
  card, instead of one page for the whole development. It is a real change to
  `index.php`'s filtering, so I have kept it out of this drop deliberately; say
  the word and it is the next release.
- **Rotate** the SQL password and ASP.NET `machineKey` from the root `web.config`
  shared earlier in this thread. Unchanged from my last four reports, and the
  one item on this list that is a live exposure rather than an improvement.
- **Image `srcset`** — thumbnails still load the full 1600px image into a ~350px
  box. Biggest remaining page-weight win.
- **Icon migration** to Fluent UI System Icons — ~55 swaps, ~400 KB saved.
- The privacy notice still needs **counsel review**; it is not legal advice.
- Delete `preflight.php` from the server after sign-off.
