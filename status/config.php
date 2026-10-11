<?php
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | Release v14.1.0
   config.php — THE ONLY FILE YOU EDIT WHEN MOVING THE PORTAL.

   Everything that used to be hardcoded across index.php, admin_backend.php,
   report.php, actions.php and reminder.php now lives here. Moving hosts, or
   standing up a second deployment, means editing this file and nothing else.

   Target environment (verified 2026-09-18):
     PHP 8.4.20 · Microsoft-IIS/10.0 · Plesk shared hosting · BigRock India
     memory_limit 128M · upload_max_filesize 2M
     en-IN · Asia/Kolkata (IST, UTC+5:30) · INR (₹) · +91 · d M Y
   ───────────────────────────────────────────────────────────────────────── */
declare(strict_types=1);

return [

    /* ── Branding ─────────────────────────────────────────────────────────
       Used in page titles, meta tags, schema.org, the WhatsApp share text,
       the admin console header and the PDF report footer.                  */
    'brand' => [
        'company'      => 'Fusion Limited',
        'company_url'  => 'https://fusionlimited.in/',
        'project'      => 'Brook & Rivulet',
        'dashboard'    => 'Official Construction Progress Dashboard',
        'report_title' => 'Official Progress Report',
        'locality'     => 'Greater Noida West',
    ],

    /* ── Site ─────────────────────────────────────────────────────────────
       base_url: leave null to detect scheme + host + folder from the request.
       Detection is what makes the move to rc.arthsathi.com/status work with
       no code change — canonical, og:url and og:image follow the real host.

       Set it to an absolute URL ONLY if you must pin it (for example when the
       portal sits behind a proxy that rewrites the path).

       allowed_hosts: when non-empty, a request whose Host header is not in the
       list falls back to the first entry. This blocks Host-header injection
       poisoning your canonical and og: tags. Leave empty to accept any host —
       fine while migrating, worth filling in once the domain settles.        */
    'site' => [
        'base_url'      => null,
        'allowed_hosts' => [
            // 'rc.arthsathi.com',
            // 'fusionlimited.in',
        ],
        'cookie_prefix' => 'fusion',   // session cookie names; change per deployment
    ],

    /* ── Locale — "AppLocale" ─────────────────────────────────────────────
       date_store is the format inside data/updates.json. DO NOT change it on
       an existing install: every stored date is written in it and parsing is
       keyed to it. date_display is what visitors see, and is safe to change.  */
    'locale' => [
        'lang'            => 'en-IN',
        'timezone'        => 'Asia/Kolkata',
        'date_store'      => 'd-M-Y',      // on disk: 08-Mar-2026
        'date_display'    => 'd M Y',      // on screen: 08 Mar 2026
        'datetime_display'=> 'd M Y, g:i A',
        'currency'        => 'INR',
        'currency_symbol' => '₹',
        'phone_cc'        => '+91',
    ],

    /* ── Public feed ──────────────────────────────────────────────────────
       per_component is the fix for the old global cap: the public page used to
       show only the newest 50 updates across the whole site, so once the
       archive grew past that, older towers rendered an empty panel when
       filtered. The cap is now applied PER TOWER, so every tower in the
       sidebar always has content. max_total is a safety ceiling on page size. */
    'feed' => [
        'per_component' => 12,
        'max_total'     => 400,
    ],

    /* ── Admin ────────────────────────────────────────────────────────────
       Set the ADMIN_HASH environment variable in Plesk
       (Websites & Domains → PHP Settings → Additional directives, or the
       Environment Variables panel) and delete the fallback below.

       Generate one with:
         php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"   */
    'admin' => [
        'hash'            => getenv('ADMIN_HASH')
                             ?: '$2y$10$vyFc6KJajkm4sEwUf8jN9e.DhnZKbNRtoXyMJWaMum51/WhOw3VB6', // lifeisgood — override via ADMIN_HASH in production
        'idle_timeout'    => 28800,   // 8 hours
        'login_attempts'  => 5,
        'login_lockout'   => 900,     // 15 minutes
    ],

    /* ── Analytics ────────────────────────────────────────────────────────
       enabled=false makes index.php stop emitting tracking calls entirely.    */
    'analytics' => [
        'enabled'        => true,
        'cap'            => 10000,  // FIFO ceiling on visitor_logs.json
        'rate_per_min'   => 60,     // per IP; excess is dropped, not stored
    ],

    /* ── Canonical host and SEO ───────────────────────────────────────────
       canonical_base is the ONE address search engines should index. The portal
       is reachable on more than one host (rc.arthsathi.com and
       rc.fusionlimited.in), and identical content on two hosts splits ranking
       signals and lets Google pick a canonical for you.

       Any host that is not the canonical one still serves the site normally but
       is sent  <link rel="canonical"> pointing here  AND  noindex, follow  —
       so it works for visitors and stays out of the index.

       Set canonical_base to null to treat whatever host serves the request as
       canonical (useful on a staging copy you do not want pinned).            */
    'seo' => [
        'canonical_base'   => 'https://rc.arthsathi.com/status/',
        'indexable'        => true,     // false on a staging deployment
        'twitter_site'     => '',       // e.g. '@fusionlimited'
        'allow_ai_crawlers'=> true,     // GPTBot, ClaudeBot, PerplexityBot, …
        'og_image'         => 'assets/og.jpg',
    ],

    /* ── Resource-centre integration ──────────────────────────────────────
       The portal runs standalone AND as a module inside the Arthsathi resource
       centre (github.com/MannishGupta/RC). Verified against that codebase:

         module_id        matches ModuleRegistry::catalogue() — its isEnabled()
                          returns true for ids it does not know, so the tab
                          works before the catalogue is edited.
         respect_registry when true, status-tab.php renders nothing if the host
                          app reports the module disabled in modules.json.
         inherit_shell    true means the fragment emits no <html>/<head> and
                          leaves chrome to the host app.                        */
    'integration' => [
        // When true (default under RC), JSON + uploads live in tenants/{id}/data/status/
        'tenant_data'      => true,

        'module_id'        => 'status',
        'module_label'     => 'Construction Status',
        'module_group'     => 'Projects',
        'respect_registry' => true,
        'inherit_shell'    => true,
    ],

    /* ── Developer footer ─────────────────────────────────────────────────
       Mirrors the resource centre's own footer convention
       ("Powered by Arthsathi · Privacy · Terms") so the two read as one
       product. show_version appends the synchronised build number.            */
    'footer' => [
        'developer'      => 'Arthsathi',
        'developer_url'  => 'https://arthsathi.com/',
        'show_version'   => true,
        'support_email'  => '',
    ],

    /* ── DPDP Act 2023 — data protection ─────────────────────────────────
       India's Digital Personal Data Protection Act. The portal's analytics
       records online identifiers (a visitor id, a hashed IP, user agent,
       referrer), which are personal data under the Act. That makes consent,
       notice, purpose limitation, retention limits and a grievance channel
       mandatory rather than optional.

       fiduciary_*  — the Data Fiduciary (you) as named in the notice.
       grievance_*  — the Act requires a reachable grievance officer.
       retention_days — analytics rows older than this are purged by Optimise
                        and on a daily tick. Purpose limitation means you keep
                        traffic data only as long as the purpose needs it.
       require_consent — when true, NOTHING is tracked until the visitor opts
                        in. Leave it true; false is for internal builds only. */
    'dpdp' => [
        'require_consent'  => true,
        'retention_days'   => 180,
        'fiduciary_name'   => 'Fusion Limited',
        'fiduciary_email'  => 'privacy@fusionlimited.in',
        'fiduciary_address'=> 'Fusion Limited, Greater Noida West, Uttar Pradesh, India',
        'grievance_officer'=> 'Grievance Officer',
        'grievance_email'  => 'grievance@fusionlimited.in',
        'grievance_phone'  => '+91 00000 00000',
        'notice_updated'   => '11 Oct 2026',
    ],

    /* ── Theme ────────────────────────────────────────────────────────────
       Fluent 2 ships light and dark. 'auto' follows the visitor's OS setting;
       'light' or 'dark' pins it. Both themes are verified at WCAG 2.2 AA.     */
    'theme' => [ /* mode: auto | light | dark | reserve — auto uses rc-theme localStorage */
        'mode' => 'auto',
    ],

    /* ── Reminder e-mail (reminder.php) ───────────────────────────────────
       All overridable by environment variable. reminder.php is CLI-only unless
       you set a REMINDER_TOKEN and call it with ?token=…                      */
    'reminder' => [
        'smtp_host'  => getenv('SMTP_HOST')     ?: 'smtp.fusionlimited.in',
        'smtp_user'  => getenv('SMTP_USER')     ?: 'system@fusionlimited.in',
        'smtp_pass'  => getenv('SMTP_PASSWORD') ?: '',
        'smtp_port'  => (int)(getenv('SMTP_PORT') ?: 587),
        'to_email'   => getenv('ADMIN_EMAIL')   ?: 'admin@fusionlimited.in',
        'to_name'    => getenv('ADMIN_NAME')    ?: 'Ami Panda',
        'every_days' => 13,
        'web_token'  => getenv('REMINDER_TOKEN') ?: '',
    ],
];
