# INTEGRATION.md — mounting the Construction Progress Portal in the Resource Centre

Written against `github.com/MannishGupta/RC` as it stands on `main`. If the front
controller changes shape, the only thing here that matters is the contract in
§2 — the portal does not care how the host reaches it.

The portal runs in two modes from one copy of the code:

| | Standalone | Embedded module |
|---|---|---|
| URL | `https://rc.arthsathi.com/status/` | `https://rc.arthsathi.com/?tab=status` |
| Entry point | `index.php` | `status-tab.php` |
| Owns the page shell | yes | **no** — the RC does |
| Session | its own (`fusion_public_session`) | the RC's, untouched |
| Stylesheet | `assets/app.css` | `assets/app-embed.css` (scoped) |
| Version shown | `STATUS_VERSION` | the host's `APP_VERSION` |
| Writes data | yes (admin console) | **no** — read-only |
| Indexed by search engines | yes, canonical | no (the RC page's own canonical applies) |

Both modes read the same `data/` directory, so an update published in the console
appears in the tab on the next request. There is no sync step and no second copy.

---

## 1 · The three-line mount

Drop the portal at `<RC root>/status/` — the same directory layout as the
standalone install, nothing moved. Then:

**1. Dispatch the tab.** Wherever the front controller resolves `$tab` to a view
(`$viewMap` in `index.php`):

```php
if ($tab === 'status') { require __DIR__ . '/status/status-tab.php'; }
```

If you would rather route it through `$viewMap` like every other view, that works
too — the fragment is safe inside the output buffer that `require $viewFile` runs
in, and it does not need `$cardCtx`:

```php
$viewMap['status'] = 'status/status-tab.php';
```

**2. Register the module** in `app/ModuleRegistry.php` → `catalogue()`:

```php
'status' => [
    'label'    => 'Construction Status',
    'group'    => 'Projects',
    'features' => ['status_feed' => 'Tower-wise progress and photographs'],
],
```

This step is **optional**. `ModuleRegistry::isEnabled()` returns `true` for ids it
does not know, so the tab works before anyone edits `catalogue()`. Registering it
is what lets a tenant switch it off in their `modules.json`.

**3. Add the nav item** however the shell builds navigation, pointing at
`?tab=status`.

Nothing else in the Resource Centre changes. No composer package, no autoloader
entry, no migration, no new config file on the RC side.

---

## 2 · The contract the fragment honours

These are the guarantees `status-tab.php` makes about itself. They are what make
it safe to `require` from inside an output buffer in someone else's page.

- **Emits no document structure.** No `<!doctype>`, `<html>`, `<head>` or
  `<body>`. It opens with a `<link rel="stylesheet">` and a single
  `<div class="rc-status">` and closes it. (`<header>` and `<section>` elements
  appear *inside* that div — those are content, not shell.)
- **Sends no headers.** No `header()`, no `http_response_code()`, no cookies. It
  is included after the RC has already started output, so sending any would be a
  fatal warning.
- **Starts no session.** The RC's session is already open; a second
  `session_start()` raises a notice. The fragment reads no session state either.
- **Defines nothing the host owns.** `version.php` guards `APP_VERSION` with
  `!defined()`, so the host's constant always wins. The fragment's own variables
  are all prefixed `$rc*` to avoid colliding with whatever is in scope at the
  include point.
- **Returns instead of exiting.** When the module is disabled it `return`s from
  the included file, emitting zero bytes. It never calls `exit` — that would kill
  the rest of the RC page.
- **Read-only.** No POST handler, no form that writes, no CSRF surface, no admin
  action. Editing stays in `status/admin.php`. An attacker who finds the tab
  cannot reach a write path through it.
- **Cannot leak CSS.** Every selector in `app-embed.css` is prefixed `.rc-status`
  — 287 of them, verified zero unscoped at build time. The print/A4 layer is
  stripped from the embed build, so the RC's own print output is unaffected.
- **Degrades to a link.** If the data store is empty or unreadable the fragment
  renders an empty-state panel and a link to the standalone dashboard, not an
  error.

**Verified end to end** against a simulated host shell that defines
`APP_VERSION`, declares a `ModuleRegistry`, opens a session and includes the
fragment inside `ob_start()`: 3,705 bytes emitted, no document tags, one
`.rc-status` wrapper, host version inherited and labelled as such, no PHP
notices — and 0 bytes when `modules.json` disables `status`.

---

## 3 · Versioning

`status/version.php` mirrors the RC's own convention exactly:

```php
const STATUS_VERSION      = '20261011.1';   // YYYYMMDD.build
const STATUS_VERSION_DATE = '11 Oct 2026';
const STATUS_RELEASE      = '14.1.0';       // the portal's own semver

if (!defined('APP_VERSION'))      { define('APP_VERSION', STATUS_VERSION); }
if (!defined('APP_VERSION_DATE')) { define('APP_VERSION_DATE', STATUS_VERSION_DATE); }
```

So: **standalone, the portal names its own build; embedded, the host's build wins
and the footer says so.** `statusVersionIsInherited()` is how the footer knows.
`STATUS_RELEASE` is unaffected either way — it is the portal's release line, and
it is what you quote in a bug report.

`status/version.php` requested directly returns a JSON report (both versions,
the release, whether it is inherited, PHP version, canonical host). It is a
`.php` endpoint and not `version.json` **because the RC root `web.config`
disallows `.json` via requestFiltering** — a `version.json` would 404 under the
RC and work standalone, which is the worst of both.

---

## 4 · Things in the RC's configuration that the portal has to live with

Found by reading the actual repo, not assumed. Each of these cost a deployment
somewhere, so they are written down.

- **`.json` and `.sql` are blocked at the RC root.** Hence `sitemap.php`,
  `feed.php`, `manifest.php`, `version.php` — every endpoint that would
  conventionally be a static `.json`/`.xml` file is a PHP script. `manifest.php`
  sets `Content-Type: application/manifest+json` itself.
- **`hiddenSegments` blocks `data`, `tenants`, `app`, `storage`, `sql`, `tests`,
  `docs`.** The portal's own `data/` directory sits at `status/data/`, which the
  root rule covers as a path segment — so the JSON stores are unreachable over
  HTTP under the RC *for free*. Keep it that way; do not rename the directory.
- **The RC root rewrites are root-anchored** (`^sitemap.xml$` → `sitemap.php`,
  `^robots.txt$` → `robots.php`). They do not apply inside `/status/`, which is
  why the portal advertises `status/sitemap.php` directly in its `<head>` and in
  the robots additions rather than hoping for a pretty URL.
- **The RC sets no CSP.** The portal sets its own on its own pages. The embedded
  fragment sets none — it inherits whatever the RC page has, which is nothing, so
  there is no intersection to trip over. (This is the trap that killed all inline
  CSS on the previous host: under CSP Level 3 a hash or nonce in a directive makes
  `'unsafe-inline'` be ignored. If you ever add a CSP to the RC, test the status
  tab immediately after.)
- **`status/web.config` must keep its `<clear />`** in
  `<defaultDocument><files>`. `<files>` is an inherited, key-unique collection;
  adding `index.php` when a parent already lists it is a hard 500.19 that takes
  out the whole folder including static files. This is not theoretical — it
  happened on `rc.fusionlimited.in`.

---

## 5 · Per-tenant status data (default under Resource Centre)

When the portal runs under RC multi-tenant hosting, **storage is per tenant**:

```
tenants/{tenantId}/data/status/           ← projects.json, components.json, updates.json
tenants/{tenantId}/data/status/uploads/   ← site photographs
```

`lib.php` detects `DATA_PATH` (from `app/tenant_bootstrap.php`) and uses that tree.
Public image URLs go through `status/serve.php?f=uploads/…`.

Set `integration.tenant_data => false` in `status/config.php` to force the legacy
shared `status/data/` + `status/uploads/` layout (single-site only).

### Legacy migration
On first request for a tenant whose `…/data/status/projects.json` is empty, if
`status/data/projects.json` still has content, those JSON files are **copied once**
into the tenant folder. Move or copy `status/uploads/*` into
`tenants/{id}/data/status/uploads/` manually if photographs must follow.

---

## 5b · Per-tenant status data (manual STATUS_DATA_DIR — advanced)

The portal currently has **one** `data/` directory, so every tenant viewing the
tab sees the same construction updates. For a multi-project RC that is usually
what you want — the dashboard is about one development.

If you do need per-tenant data, the hook is deliberately small: `DATA_DIR` is
defined once in `lib.php`. Set it before the fragment is included:

```php
// In the RC, before requiring status-tab.php:
define('STATUS_DATA_DIR', __DIR__ . '/tenants/' . $tenantId . '/status-data');
```

…and in `lib.php`, change the one definition to honour it:

```php
define('DATA_DIR', defined('STATUS_DATA_DIR')
    ? STATUS_DATA_DIR
    : __DIR__ . DIRECTORY_SEPARATOR . 'data');
```

Each tenant directory then needs its own `projects.json`, `components.json`,
`updates.json` and `site_config.json`, and the admin console needs the same
constant set for whoever is editing. **This is a change to `lib.php`, so it will
be overwritten by the next portal release** — tell me if you want it, and I will
ship it as a supported config option instead of a local patch.

---

## 6 · Standalone deployment (unchanged by any of this)

`rc.arthsathi.com/status/` is the canonical public installation. It needs
nothing from the Resource Centre — `status-tab.php` simply never runs.

Any other host serving the same files (a staging copy, `rc.fusionlimited.in`)
is detected as non-canonical and serves `noindex, follow` with the canonical URL
still pointing at `rc.arthsathi.com`, so a mirror can never outrank or duplicate
the real site. `preflight.php` reports which mode the host it is running on is
in. Deployment steps are in `RELEASE-v14.1.md`.
