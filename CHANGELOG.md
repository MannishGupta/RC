# Fusion Resource Centre — Changelog

## 20260929.21 — 30 Sep 2026 · Vehicle Inventory: real OEM logos, removed the white-hole artifact

Reported from a live screenshot: a visible white circle in every vehicle
card's dark header, plus a request to show actual vehicle-brand logos
instead of a generic car icon.

### Found — the logo-resolution engine existed but was never loaded anywhere

`app/VehicleCatalog.php` is a complete, well-built class: fuzzy make-name
resolution, then a 3-tier logo fallback chain (a local override folder,
simple-icons via jsDelivr, Clearbit's domain-based logo API, generic
silhouette as the last resort). It was never `require_once`'d from any
file in the app — the only reference to its own path was inside its own
header comment demonstrating how to require it, not a real call anywhere.
Every vehicle card has shown a generic icon since this class was written,
simply because nothing ever loaded it.

### Fixed — wired it in, resolved server-side per record

The card list renders through `x-for="t in tags"`, a client-side loop over
a JSON array — so `VehicleCatalog::getVehicleLogo()` can't be called
per-card inline in the template; it only runs once, server-side, when the
page is built. Added the require, and resolved each record's logo in the
existing per-record enrichment loop in `tab_cartags.php` — specifically the
one that runs *after* legacy-fallback records get appended, so it covers
every record (original and legacy) in a single pass rather than missing
the ones appended afterward. `resolveMake()` does fuzzy partial matching,
so the full combined `make_model` string resolves correctly without
parsing out just the brand name first.

### Fixed — the white-hole artifact

Traced to a purely decorative element: `absolute -right-6 -top-6 w-20 h-20
rounded-full bg-white/5`, a subtle highlight bubble intended to sit mostly
clipped in the header's top-right corner. What actually renders in the
live screenshot doesn't match that intended position or opacity — full
browser access wasn't available to pin down the exact rendering mechanism
with certainty. Rather than chase an elusive CSS mystery, removed the
element outright: it was never functional, only decorative, so removing it
resolves the reported symptom regardless of the precise cause, and clears
space for the new logo tile that replaces it. The small icon box that used
to hold a static car icon now holds the resolved logo (white tile so any
brand's own colours stay legible against the dark header), with the car
icon kept only as the genuine fallback — shown when no logo resolves at
all, and swapped in reactively via `@error` if a resolved CDN logo URL
404s, which is an expected outcome for the two best-effort CDN tiers, not
an exceptional one.

Not independently verified in a live browser this session — flagged plainly
rather than claimed as visually confirmed.

---

## 20260929.20 — 30 Sep 2026 · Orphan cleanup: JSON reference verification gap

Checked the Cleanup Scanner against three stated requirements rather than
assuming compliance: legacy root folders must never be targeted, unreferenced
media may only be removed once JSON references are actually verified, and a
backup must be encouraged before any deletion.

**Two of three were already correctly implemented** — confirmed by tracing
the actual code, not just reading comments. Legacy `BASE_PATH/images` and
`BASE_PATH/docs` are genuinely excluded from the scan loop (an earlier
in-session fix had added them; a later one correctly removed them again).
The backup requirement is covered twice over: a "Download a backup first"
panel with a direct link sits directly above the delete button, and the
button's own confirm() dialog repeats the warning — both tied specifically
to the selective cleanup_apply action, not just the general optimizer run.

### Fixed — collectReferencedFiles() didn't check every namespace that
actually stores a filename under images/

Found while verifying the "JSON references are verified" requirement
properly rather than taking it at face value: the function checked
`company`, `team`, `docs`, `cartags` — but not `bank` or `locations`, both
of which hold real files in the same `images/` directory the scanner
watches. `bank.qr_image` is the pre-generated static UPI QR code (written
there specifically so sharing a bank record never depends on a live,
external QR-generator link); `locations.logo` and `locations.photo` are
listed right in `locationsSchema()` two namespaces below where they were
missing from the reference check. Both were genuinely at risk of being
flagged as unreferenced and deleted by this same scanner, despite being
actively linked from and used by the exact record that owns them. Added
both namespaces and the `qr_image` field to the check.

Also synced `Optimizer.php`'s own `bankSchema()` — missing `qr_image`
entirely, out of sync with the live schema in `app/bootstrap.php`. Checked
whether this was actually stripping the field during normalisation before
treating it as a bug: `phaseNormalizeList`'s `array_merge($schema, $row)`
keeps any key already present in the real record regardless of whether the
schema lists it, so the field was never actually being erased. Fixed the
schema anyway so it accurately documents what bank records hold, rather
than silently depending on merge behaviour a future reader wouldn't know
to check.

---

## 20260929.19 — 30 Sep 2026 · Live-deployment debugging session

Debugged two issues reported directly from the live site
(rc.fusionlimited.in), working from what the browser actually showed since
automated browser/fetch access wasn't available this session — verified
everything against the actual pasted file content and direct code tracing
rather than guessing.

### Investigated — "left pane blank, header row not visible"

Traced the sidebar and header to `x-data="dashboardApp"` and its
`navGroups`/`primaryNav` getters in `assets/dashboard-app.js`. The user
pasted the live file's actual content for inspection: `node --check`
confirmed it's syntactically valid, and diffing it word-for-word against
the known-good source found exactly 4 differences, all cosmetic —
`"Starting…"` and `"⚠️"` had been mangled into `"Startingâ€¦"` and
`"âš ï¸ "`, the textbook signature of a file transferred via FTP in text/ASCII
mode instead of binary, corrupting multi-byte UTF-8 sequences. Confirmed
against the source repo that this corruption was introduced during that
specific upload, not present in the code handed over. Fixed the 4 strings.
This file was not the cause of the blank sidebar; the user's follow-up
re-upload (with the FTP client corrected) appears to have resolved that
half on its own.

### Fixed — Optimizer HTTP 500 on multi-tenant sweep (likely root cause: memory exhaustion)

The resulting error, `⚠️ Non-JSON response (HTTP 500)`, traced to
`SystemDataOptimizer::runAllTenants()` — a multi-tenant sweep feature built
by someone else after this session's earlier explicit conclusion that
implementing one safely was a large refactor with real regression risk,
given `DATA_PATH` is an immutable PHP constant read directly in 40+ places
in this file. That conclusion turned out correct: the attempt built the
right mechanism (a `$GLOBALS['RC_OPT_DATA_PATH']` override checked by a new
`dataRoot()` helper) but left 26 direct `DATA_PATH`-family constant
references unconverted across the file, including two in functions that
are genuinely still called during the sweep:

- **`phaseEnsureDirs()`** and **`phaseTrimSessions()`** were still reading
  `DISPATCH_DATA_PATH`, `SESSION_PATH`, and siblings — constants fixed once
  per request by `tenant_bootstrap.php` for whichever tenant the current
  host resolves to, never changing across the sweep's per-tenant loop.
  Correct for exactly one tenant in the loop, silently wrong for every
  other one. Fixed both to always build from `$data` (i.e.
  `self::dataRoot()`, which does respect the per-tenant override), never
  from the request-fixed constants. (`applyCleanup()`,
  `inventoryWatched()`, and `phaseMigrateDataLayout()` also still reference
  `DATA_PATH` directly, but checked each one's call sites and confirmed
  none are part of the sweep's actual phase sequence — not touched, since
  they're unrelated to this crash and changing them without the same
  scrutiny would be an unverified guess.)
- **The likely actual crash**: `phaseConvertImagesWebp()` is still called,
  once per tenant, inside the sweep's full phase sequence. This phase
  already carries careful per-run limits (25 images per pass, 6MB/25MP
  ceilings per image) and its own comment notes shared hosting often caps
  memory at 128MB — caps clearly sized for a single tenant's single bounded
  run, not for that same bounded work multiplied across however many
  tenants exist, sequentially, inside one PHP process's memory budget.
  PHP's memory-exhaustion fatal is not catchable by the `\Throwable` wrapper
  this method is already wrapped in, so it would surface exactly as an
  uncaught 500 with no JSON body — bypassing the existing graceful-error
  handling entirely rather than tripping it.
  Added a `$lightweight` flag to `runForDataPath()`; `runAllTenants()` now
  always passes it, skipping this one phase during a multi-tenant sweep
  regardless of today's tenant count, so behaviour doesn't silently change
  as tenants are added later. The single-tenant `run()` method (the normal
  per-tab optimise button) is a separate code path, calls this phase
  unconditionally exactly as before, and is untouched by this fix.

Not independently verified against server logs — browser and fetch access
weren't available this session. Flagged as the likely cause with the
specific reasoning laid out above, not claimed as a confirmed diagnosis.

---

## 20260929.17 — 30 Sep 2026 · Audit pass (github.com/MannishGupta/RC)

Audited the repository as cloned, not assumed clean because the changelog
said so. Re-ran the full verification toolkit from earlier sessions
(structural brace balance, cross-class static-method calls checked against
every method actually defined, the nested-quote/corrupted-comment
signature, every icon used checked against the real Font Awesome package)
— all came back clean or matched already-known, already-safe items.

**Verified, not just trusted:** the Dasha Now card progress fix the
changelog claims for 20260929.16 — traced the actual assignment order in
`janam_patri.php` and confirmed the reference write (`&$p`) populates the
progress fields before the copy (`$current = $p`) happens. Genuinely fixed,
not just claimed.

### Fixed — blood_report.php: the tenant-isolation bug, recurring in new code

A new standalone entry point built after the multi-file tenant-isolation
fix (that audit, this session) reproduced the identical mistake in a new
file: checked for `tenant_bootstrap.php` at `BASE_PATH` root, which never
exists there — the real file lives at `app/tenant_bootstrap.php`, exactly
where every other correctly-wired entry point requires it from. The
`is_file()` check on the wrong path always returned false, silently
falling through to `app/bootstrap.php` alone, which never resolves
`DATA_PATH` per tenant — it fell straight to the hardcoded legacy default.
On a multi-tenant deployment, this file was reading the wrong tenant's team
data on every request. Fixed to the correct path. Swept the rest of the
codebase for the same wrong pattern and for any other standalone entry
point with zero tenant resolution at all — this was the only instance of
either.

### Verified — blood compatibility logic is medically correct, cleaned up dead code

The ABO+Rh donor-compatibility table was being assigned twice in
`bg_can_donate_to()` — a first block immediately overwritten by a second
before anything ever read it. Checked precisely rather than assumed: both
blocks contained the exact same sets, just reordered (irrelevant to
`in_array()`), so this was dead code, not a logic bug — the table actually
in use was and is correct, checked directly against the standard ABO+Rh
donor chart for all 8 blood types. Removed the dead first block so a
future edit to one copy can't silently drift from the other while both
still look live.

---

## 20260929.16 — 30 Sep 2026 · UX & dasha math
- **Dasha Now card:** progress % and remaining days were stuck at 0 — `$current` was copied before progress fields were written; fixed assignment order
- **Header:** count badge + IST clock locked high-contrast in light and dark (fixes unreadable "1 entry" / date)
- **Public widgets:** wall clock, weather, AQI forced to a single 3-column row
- **Janam Patri:** removed duplicate Print bar (chrome toolbar only)
- **New team member:** default place of birth `Delhi, IN` and coordinates `28.610000, 77.230000` (28°36′36″N 77°13′48″E)

## 20260929.13 — 29 Sep 2026 · Blood Report team integration
- **Blood Report** (`blood_report.php`): ABO± traits, witty workplace lore, fun facts (pop-culture framed, not medical)
- Team compatibility matrix (simplified textbook “can donate to” model) with green highlights
- Human Capital: droplet action button, clickable Blood · pill on cards, command palette entry
- Hindi / English toggle + Print/PDF; strong non-clinical disclaimer
- Site developer branding: Arthsathi Limited

## 20260929.12 — 29 Sep 2026 · Blood Report module
- Standalone blood intelligence page: focus member by slug/group, team map, donor/recipient lists
- Educational transfusion model only — never for clinical decisions

## 20260929.11 — 29 Sep 2026 · Gochar + house strength
- Janam Patri tabs: **Transits (गोचर)** — natal + today’s transit overlay, notable transit blurbs
- **Strength (बल)** — indicative Sarvashtakavarga-style house bindu bars (simplified, educational)
- Honest disclaimers: not full classical recon / not permanent reading

## 20260929.10 — 29 Sep 2026 · Sade Sati + Vargas
- **Sade Sati** tab: 3-phase strip, Moon vs transit Saturn, next-window estimate
- **Vargas** tab: D-1 / D-3 / D-7 / D-9 / D-10 / D-12 switcher on one diamond chart
- Captions for career, children, parents, siblings themes

## 20260929.09 — 29 Sep 2026 · Report family chrome + Dasha Now
- Unified language + Print controls across Janam Patri, Milan, Numerology action bar
- Patri sub-tabs: Kundli · Dasha · (later Sade / Varga / Gochar / Strength / Doshas)
- **Vimshottari Now card**: current Mahadasha + Antardasha, progress %, plain-language countdown
- Improved dasha balance from Moon nakshatra/pada (not fixed year offset)

## 20260929.08 — 29 Sep 2026 · Numerology locale fix
- **Critical:** removed conflicting `app/Locale.php` (redeclared bootstrap `AppLocale` → fatal on numero)
- Numerology default **hi_IN**; cookie `rc_lang`; `nr_clip` without mbstring
- Lo Shu planet/element Devanagari labels; language toggle on report header

## 20260929.07 — 29 Sep 2026 · Janam Devanagari data labels
- Render-time maps: graha, rashi, nakshatra, bhava in Devanagari (hi) / en-IN (en)
- Tables, diamond chart, Manglik/Ayanamsa labels localized

## 20260929.06 — 29 Sep 2026 · A4 print Janam Patri
- `@page { size: A4 portrait; margin: 15mm; }` dedicated print CSS
- Print masthead/footer, Print Janam Patri action bar

## 20260929.05 and earlier (29 Sep day) — Vedic UI / contrast / signature
- Hindi-default Janam Patri UI, Vedic palette, tab contrast fixes
- Email signature: full address under company name (not location slug only)
- Light-mode data-count contrast locks

## 20260929.01 — CODE baseline
- Multi-tenant RC code tree (CODE-ONLY); commercial launch baseline from 20260928.x

---

## 20260928.17 — 28 Sep 2026
- Numerology: MobileEvaluationEngine optional if missing; clearer missing-file message
- Ensure cards/numerology/engine/* ships complete (including MobileEvaluationEngine.php)

## 20260928.16 — 28 Sep 2026 · Security hardening (pre-launch)
- Auth: removed plaintext master passwords from code; bcrypt-only via auth_config.php
- Endpoints: team_public_json + share_link require session (admin for share); print_directory gated
- Uploads: team photo filename path-safe; SVG/HTML/XML blocked; media_serve forces attachment for residual SVG
- PublicTeam strip no longer includes phone/email/blood_group
- Tenant boot: strict_hosts 403; no auto-create for unknown Host headers
- IIS web.config: hide tenants/tools/app; removed obsolete root bootstrap.php

## 20260928.15 — 28 Sep 2026 · Commercial launch candidate
- LAUNCH.md, diag removed, robots/tools hardened

## 20260928.14 — Asset extraction (dashboard / numerology)

## 20260928.13–.10 — Docs cleanup, legacy paths, bank isolation, contrast

## Earlier 20260925–20260928
- Multi-tenant optimizer, Super Admin dashboard, role tiers, field ops, accessibility passes
- See git history / prior RC-*.zip packages for granular entries

---

### Deploy notes (20260929.13)
1. Upload this package over DocumentRoot (preserve `tenants/*/data/` on server — do not wipe live JSON).
2. **Delete** `app/Locale.php` if an older patch left it (conflicts with `AppLocale` in bootstrap).
3. Ensure writable: `tenants/{id}/data/` (0775).
4. Smoke: `/?tab=team`, `/?card=numero&slug=…`, `/janam_patri.php?view=patri&id=…`, `/blood_report.php`.
