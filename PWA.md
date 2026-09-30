# PWA — installable app without an app store

Goal: a home-screen app on iOS and Android with **no Play Store or App Store
involvement** — no developer accounts, no review queues, no 30% cut, and
updates that ship the moment you upload a file.

---

## Status

| Capability | State |
|---|---|
| Installable ("Add to Home Screen") | ✅ **Works now** (260906.18) |
| Home-screen icon from company logo | ✅ Works now |
| Standalone window, no browser chrome | ✅ Works now |
| Status bar matched to page colour | ✅ Works now |
| Long-press shortcuts on the icon | ✅ Works now |
| Offline access | ✅ **Works now** (260906.20) |
| Push notifications | ◻ Not built — see roadmap |
| Background sync | ◻ Not built |

**Try it now:** open the dashboard on a phone → browser menu → *Add to Home
Screen*. It launches like an app. It just needs a connection.

---

## Blocker — RESOLVED

Static `.js` serving was verified on 6 Sep 2026 (`test.js` rendered as text in
the browser, and both `pwa_icon.php` sizes returned images). The service
worker was therefore built and shipped in 260906.20.

Original note retained below for reference.

## The original blocker

Offline access and push both require a **service worker**, and a service
worker will only register if the `.js` file is served as
`application/javascript` over HTTPS.

You reported earlier that *"static css file doesn't work correctly on our
shared hosting"*. If `.css` has a MIME problem then `.js` almost certainly
does too — and a service worker whose MIME type is wrong fails **silently**.
No error, no console warning in some browsers, just an app that never works
offline and nobody can explain why.

**This must be confirmed before writing the service worker**, not after.

### How to check — 30 seconds

1. Open `https://rc.arthsathi.com/` on desktop
2. DevTools → **Network** tab → reload
3. Click any `.css` or `.js` request
4. Read the **Content-Type** response header

| You see | Meaning |
|---|---|
| `text/css` / `application/javascript` | Fine — the service worker can be built |
| `application/octet-stream` or missing | This is the blocker. Fixable in `web.config` |

If it is the second, the fix is a MIME mapping in `staticContent`. Note that
`web.config` has broken static serving on this host twice, so that change
should be made and verified on its own, not bundled with anything else.

---

## Why the manifest is PHP, not JSON

`web.config` denies the `.json` extension outright to keep `data/` unreachable:

```xml
<add fileExtension=".json" allowed="false" />
```

A static `manifest.json` would therefore 404. Rather than relax that rule —
and risk the static-serving breakage again — `manifest.php` emits the same
document with `Content-Type: application/manifest+json`.

A side benefit: the manifest reads live company data, so renaming the
organisation or changing its logo updates the installed app's name and icon
with no file to regenerate.

Icons work the same way: `tools/pwa_icon.php?size=192|512[&maskable=1]`
renders from the logo in Company Setup, so nobody has to produce and maintain
three correctly-padded PNGs.

---

## Scope — what should and should not be in the app

Not everything belongs on a phone.

| Feature | In app | Reasoning |
|---|---|---|
| Team directory | ✅ | Offline access, tap-to-call — the strongest case |
| Vehicle tags admin | ✅ | Camera QR scan, compliance alerts |
| Documents / events | ✅ | Reference material, read on the move |
| Digital cards | ➖ Already right | Shared as a WhatsApp link. Requiring an install would *reduce* reach |
| **Public vehicle scan** | ❌ **Must stay web** | A stranger scanning a windscreen will never install an app first. This one must remain a plain URL |
| Numerology report | ➖ Marginal | Long-form reading; the browser is fine |
| Print directory / VCF export | ❌ | Desktop tasks |
| Optimiser / Monitor / Cleanup | ❌ | Admin maintenance, done at a desk |

The public scan page is the important line. Its entire value is that it works
for someone who has never heard of your organisation.

---

## Roadmap once the MIME issue is resolved

**Phase 1 — offline shell**
`sw.js` caching the app shell and the last-loaded directory. Team contacts
readable with no signal, which is when a directory is most needed.

**Phase 2 — install prompt**
Capture `beforeinstallprompt` and offer a proper "Install app" button instead
of relying on people finding the browser menu. Android only; iOS has no
programmatic install.

**Phase 3 — push notifications**
VAPID keys, a push endpoint, subscription storage. Works on Android and iOS
16.4+ **when installed to the home screen** — iOS does not support web push
from a browser tab.

> Worth weighing first: compliance alerts could go out over **WhatsApp**,
> which this system already has plumbing for. People read WhatsApp. Push
> notifications on a rarely-opened internal app are widely ignored, and
> WhatsApp needs no VAPID keys, no subscription management, and no iOS
> version floor.

**Phase 4 — camera QR scanning**
`BarcodeDetector` where available, `jsQR` as fallback, so an admin can scan a
printed tag to open it directly.

---

## What a PWA cannot do

Be honest about this before promising it internally:

- **No store listing.** If clients expect to find you on the Play Store for
  credibility, a PWA will not satisfy that. It is a business decision, not a
  technical one.
- **iOS restrictions.** Web push needs iOS 16.4+ *and* home-screen
  installation. Background sync is unsupported. Storage can be evicted after
  a few weeks of non-use.
- **Discovery.** Nobody browses for a PWA. Distribution is your link, your QR
  code, your onboarding email.

For an internal tool distributed to your own staff, none of these matter much.
For a consumer product, they would.
