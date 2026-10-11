<?php
/* ─────────────────────────────────────────────────────────────────────────
   Construction Progress Portal | version.php
   Synchronised versioning — deliberately mirrors the resource centre's own
   app/../version.php convention (github.com/MannishGupta/RC):

       if (!defined('APP_VERSION')) define('APP_VERSION', 'YYYYMMDD.build');
       if (!defined('APP_VERSION_DATE')) define('APP_VERSION_DATE', 'd M Y');

   The !defined() guard is what makes the two safe to load together: when the
   portal runs as a module inside the resource centre, the host app has already
   defined APP_VERSION, so this file defers to it and the footer shows ONE
   version for the whole product. Standalone, it defines its own.

   STATUS_VERSION is always the portal's own build, whichever way it loaded, so
   support can tell which portal is deployed even when embedded.

   Served directly it prints a machine-readable report for the host app or a
   monitor to poll. NOTE: it is a .php endpoint, not version.json, because the
   resource centre's root web.config disallows the .json extension via
   requestFiltering — a version.json here would return 404.
   ───────────────────────────────────────────────────────────────────────── */
declare(strict_types=1);

const STATUS_VERSION      = '20261011.1';
const STATUS_VERSION_DATE = '11 Oct 2026';
const STATUS_RELEASE      = '14.1.0';

if (!defined('APP_VERSION'))      { define('APP_VERSION', STATUS_VERSION); }
if (!defined('APP_VERSION_DATE')) { define('APP_VERSION_DATE', STATUS_VERSION_DATE); }

/** True when the host application supplied the version instead of this file. */
function statusVersionIsInherited(): bool {
    return APP_VERSION !== STATUS_VERSION;
}

/** One line for a footer: "v20261011.1 · 11 Oct 2026". */
function statusVersionLabel(): string {
    return 'v' . APP_VERSION . ' · ' . APP_VERSION_DATE;
}

// Direct request → machine-readable report. Included → definitions only.
if (PHP_SAPI !== 'cli' && realpath(__FILE__) === realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'module'            => 'status',
        'release'           => STATUS_RELEASE,
        'version'           => STATUS_VERSION,
        'version_date'      => STATUS_VERSION_DATE,
        'effective_version' => APP_VERSION,
        'inherited'         => statusVersionIsInherited(),
        'php'               => PHP_VERSION,
        'endpoints'         => [
            'dashboard' => 'index.php',
            'report'    => 'report.php',
            'privacy'   => 'privacy.php',
            'sitemap'   => 'sitemap.php',
            'fragment'  => 'status-tab.php',
            'health'    => 'version.php',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
