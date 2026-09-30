<?php 
// Version: 260917.10
// dashboard.php - V2027.309 India-first complete self-hosted app.css (no Tailwind Play CDN)
//
// CHANGELOG v2027.303:
//  - Emoji removed entirely from ALL WhatsApp share text (team, bank, docs,
//    events, locations, statutory, and the fallback case), in both
//    conclusively (via a temporary debug marker) to WhatsApp's own wa.me →
//    api.whatsapp.com redirect corrupting emoji-range characters
//    server-side — NOT a bug in this codebase. Plain rule/separator
//    characters (─, ·) carry the visual hierarchy instead and are
//    unaffected. window.WA_EMOJIS has been removed as fully dead code.
//  - dbd/tab_events.php's separate window.__shareEventWA() updated to match.
//
// CHANGELOG v2027.301 (superseded by the above, kept for history):
//  - WhatsApp share text for 'team' briefly used an emoji-based
//    "Professional Modern" template with a 🏷️ tag icon — since removed.
//  - $customTabs now includes 'departments' and 'designations', which were
//    generic table view and never loaded dbd/tab_departments.php /
//    dbd/tab_designations.php, even though those files existed.
//  - 'bank' uses inline dashboard markup (UPI QR via qr_image + rcPaintBankQr).

if (!defined('BASE_PATH')) exit; 
if (!isset($viewData) || !is_array($viewData)) die('Critical Error: View Contract Missing.');

class DashboardEngine {
    public static function normalizeData(array $raw): array {
        $data = $raw; 
        
        // Force list namespaces to sequential arrays (associative JSON breaks Alpine x-for)
        $asList = static function ($v): array {
            if (!is_array($v)) return [];
            return array_values(array_filter($v, static fn($x) => is_array($x)));
        };
        $data['events'] = $asList($raw['events'] ?? []);
        $data['team']   = $asList($raw['team'] ?? []);
        $data['locations'] = $asList($raw['locations'] ?? []);
        $data['departments'] = $asList($raw['departments'] ?? []);
        $data['designations'] = $asList($raw['designations'] ?? []);
        $data['bank'] = $asList($raw['bank'] ?? []);
        $data['docs'] = $asList($raw['docs'] ?? []);

        $data['locationsById'] = [];
        $data['departmentsById'] = [];
        $data['designationsById'] = [];

        if (!empty($data['locations'])) {
            foreach ($data['locations'] as $l) {
                if (!empty($l['id'])) $data['locationsById'][strtolower(trim((string)$l['id']))] = $l;
                if (!empty($l['slug'])) $data['locationsById'][strtolower(trim((string)$l['slug']))] = $l;
                if (!empty($l['name'])) $data['locationsById'][strtolower(trim((string)$l['name']))] = $l;
            }
        }
        if (!empty($data['departments'])) {
            foreach ($data['departments'] as $d) {
                if (!empty($d['id'])) $data['departmentsById'][strtolower(trim((string)$d['id']))] = $d;
                if (!empty($d['code'])) $data['departmentsById'][strtolower(trim((string)$d['code']))] = $d;
                if (!empty($d['slug'])) $data['departmentsById'][strtolower(trim((string)$d['slug']))] = $d;
                if (!empty($d['name'])) $data['departmentsById'][strtolower(trim((string)$d['name']))] = $d;
            }
        }
        // ── Designations: merge code ↔ hierarchy rank ─────────────────────
        // rank comes from designation.rank when set, else stable order after
        // sorting by rank → code → name. Every team member inherits that rank
        // when person.rank is empty (fixes permanent "999" sort).
        $desigRows = [];
        if (!empty($raw['designations']) && is_array($raw['designations'])) {
            $desigRows = array_values($raw['designations']);
        }
        usort($desigRows, static function ($a, $b) {
            $ra = is_numeric($a['rank'] ?? null) ? (int)$a['rank'] : PHP_INT_MAX;
            $rb = is_numeric($b['rank'] ?? null) ? (int)$b['rank'] : PHP_INT_MAX;
            if ($ra !== $rb) return $ra <=> $rb;
            $ca = strtolower(trim((string)($a['code'] ?? '')));
            $cb = strtolower(trim((string)($b['code'] ?? '')));
            if ($ca !== $cb) return $ca <=> $cb;
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $hierarchyByKey = []; // lowercase id|code|name → int rank
        $canonicalByKey = []; // lowercase key → canonical designation id (or code)
        foreach ($desigRows as $idx => $d) {
            if (!is_array($d)) continue;
            $hier = null;
            // Treat 999/9999 as unset sentinel (legacy Schema default)
            $rawRank = (isset($d['rank']) && is_numeric($d['rank'])) ? (int)$d['rank'] : 0;
            $rawHier = (isset($d['hierarchy_rank']) && is_numeric($d['hierarchy_rank'])) ? (int)$d['hierarchy_rank'] : 0;
            if ($rawRank > 0 && $rawRank < 900) {
                $hier = $rawRank;
            } elseif ($rawHier > 0 && $rawHier < 900) {
                $hier = $rawHier;
            } elseif (isset($d['code']) && preg_match('/^\d+$/', trim((string)$d['code']))) {
                $hier = (int)$d['code'];
            } else {
                $hier = $idx + 1; // stable hierarchy position
            }
            // Persist computed rank back onto the designation row for the UI
            $d['hierarchy_rank'] = $hier;
            if (!isset($d['rank']) || $d['rank'] === '' || $d['rank'] === null) {
                $d['rank'] = $hier;
            }
            $desigRows[$idx] = $d;

            $canon = (string)($d['id'] ?? $d['code'] ?? $d['slug'] ?? '');
            foreach (['id', 'code', 'slug', 'name'] as $fld) {
                $k = strtolower(trim((string)($d[$fld] ?? '')));
                if ($k === '') continue;
                $hierarchyByKey[$k] = $hier;
                $canonicalByKey[$k] = $canon;
                $data['designationsById'][$k] = $d;
            }
        }
        // Replace designations list with ordered, rank-enriched rows
        $data['designations'] = $desigRows;
        $data['_designationHierarchy'] = $hierarchyByKey;

        if (!empty($data['team']) && is_array($data['team'])) {
            foreach ($data['team'] as &$member) {
                if (!is_array($member)) continue;

                $desigRaw = trim((string)($member['designation_id'] ?? $member['designation_code'] ?? $member['designation'] ?? $member['labels'] ?? $member['designation_name'] ?? ''));
                $desigKey = strtolower($desigRaw);
                $desigRow = $data['designationsById'][$desigKey] ?? null;
                // Fuzzy: match designation by name when id/code not linked
                if (!$desigRow && $desigKey !== '') {
                    foreach ($data['designationsById'] as $dk => $dv) {
                        if ($dk === $desigKey) { $desigRow = $dv; break; }
                        if (strtolower(trim((string)($dv['name'] ?? ''))) === $desigKey) { $desigRow = $dv; break; }
                        if (strtolower(trim((string)($dv['code'] ?? ''))) === $desigKey) { $desigRow = $dv; break; }
                    }
                }

                // Normalize FK to canonical designation id so <select> matches
                if ($desigRow) {
                    $member['designation_id'] = (string)($desigRow['id'] ?? $desigRow['code'] ?? $desigRaw);
                    $member['designation_code'] = (string)($desigRow['code'] ?? $member['designation_code'] ?? '');
                    $member['designation_name'] = (string)($desigRow['name'] ?? $desigRaw);
                    if ($member['designation_name'] === '') {
                        $member['designation_name'] = 'Designation Pending';
                    }
                } else {
                    // Keep raw human label if present; only show Pending when truly empty
                    $member['designation_name'] = $desigRaw !== '' ? $desigRaw : 'Designation Pending';
                }

                // Display rank: person.rank if real, else designation hierarchy.
                // Sentinel values 999 / 9999 / 0 mean "unset" (legacy Schema default).
                $personRank = $member['rank'] ?? $member['sort_order'] ?? $member['order'] ?? null;
                $pr = ($personRank !== null && $personRank !== '' && is_numeric($personRank)) ? (int)$personRank : 0;
                $personRankIsReal = ($pr > 0 && $pr < 900); // 999/9999 are placeholders
                $effective = null;
                if ($personRankIsReal) {
                    $effective = $pr;
                } elseif ($desigKey !== '' && isset($hierarchyByKey[$desigKey])) {
                    $effective = (int)$hierarchyByKey[$desigKey];
                } elseif ($desigRow && isset($desigRow['hierarchy_rank'])) {
                    $effective = (int)$desigRow['hierarchy_rank'];
                } elseif ($desigRow && isset($desigRow['rank']) && is_numeric($desigRow['rank']) && (int)$desigRow['rank'] > 0 && (int)$desigRow['rank'] < 900) {
                    $effective = (int)$desigRow['rank'];
                }
                $member['hierarchy_rank'] = $effective ?? 9999;
                // Always surface the resolved rank on the member for edit form + cards
                if ($effective !== null) {
                    $member['rank'] = $effective;
                } elseif ($personRankIsReal === false) {
                    $member['rank'] = '';
                }

                $deptRaw = trim((string)($member['department_id'] ?? $member['department_code'] ?? $member['department'] ?? $member['department_name'] ?? ''));
                $deptId = strtolower($deptRaw);
                $dept = ($deptId !== '' && isset($data['departmentsById'][$deptId])) ? $data['departmentsById'][$deptId] : null;
                if (!$dept && $deptId !== '') {
                    foreach ($data['departmentsById'] as $dk => $dv) {
                        if (strtolower(trim((string)($dv['name'] ?? ''))) === $deptId || strtolower(trim((string)($dv['code'] ?? ''))) === $deptId) {
                            $dept = $dv;
                            break;
                        }
                    }
                }
                if ($dept) {
                    $member['department_id'] = (string)($dept['id'] ?? $dept['code'] ?? $member['department_id'] ?? '');
                    $member['department_name'] = (string)($dept['name'] ?? $deptRaw);
                    if ($member['department_name'] === '') $member['department_name'] = 'Department Pending';
                } else {
                    $member['department_name'] = $deptRaw !== '' ? $deptRaw : 'Department Pending';
                }

                $locId = strtolower(trim((string)($member['location_id'] ?? $member['location'] ?? '')));
                $locName = $data['locationsById'][$locId]['name'] ?? '';
                $locRow = $data['locationsById'][$locId] ?? null;
                if (!$locRow && $locId) {
                    foreach ($data['locationsById'] as $lk => $lv) {
                        if ($lk === $locId || strtolower((string)($lv['slug'] ?? '')) === $locId || strtolower((string)($lv['name'] ?? '')) === $locId) {
                            $locRow = $lv;
                            $locName = $lv['name'] ?? '';
                            break;
                        }
                    }
                }
                if ($locRow) {
                    $member['location_id'] = (string)($locRow['id'] ?? $locRow['slug'] ?? $member['location_id'] ?? '');
                    $locName = (string)($locRow['name'] ?? $locName);
                }
                if ($locName === '' && !empty($member['location_name'])) {
                    $locName = trim((string)$member['location_name']);
                }
                if ($locName === '' && !empty($member['location']) && !is_numeric($member['location'])) {
                    $locName = trim((string)$member['location']);
                }
                $member['location_name'] = $locName !== '' ? $locName : 'Location Unassigned';

                // House / premise number for search (optional field)
                if (empty($member['house_no']) && !empty($member['house_number'])) {
                    $member['house_no'] = $member['house_number'];
                }
            }
            unset($member);
        }

        // Final list shape guarantee
        foreach (['team', 'locations', 'departments', 'designations', 'bank', 'docs', 'events'] as $ns) {
            if (isset($data[$ns]) && is_array($data[$ns])) {
                $data[$ns] = array_values(array_filter($data[$ns], static fn($x) => is_array($x)));
            } else {
                $data[$ns] = [];
            }
        }

        return $data;
    }

    public static function injectBirthdays(array &$events, array $team, array $locMap): void {
        $now = new DateTime('today');
        $currentYear = (int)$now->format('Y');

        foreach ($team as $member) {
            if (!empty($member['dob']) && !empty($member['name'])) {
                try {
                    $dob = new DateTime($member['dob']);
                    $nextBday = new DateTime($currentYear . '-' . $dob->format('m-d'));
                    if ($nextBday < $now) $nextBday->modify('+1 year');
                    $nextBday->setTime(10, 0);

                    $locId = strtolower(trim((string)($member['location_id'] ?? '')));
                    $locName = (!empty($locId) && isset($locMap[$locId])) ? ($locMap[$locId]['name'] ?? 'Unknown') : 'Not Assigned';

                    $events[] = [
                        'id' => 'bday_' . ($member['id'] ?? uniqid()),
                        'name' => "\u{1F382} " . $member["name"] . "'s Birthday",
                        'date' => $nextBday->format('Y-m-d\TH:i'),
                        'location' => $locName,
                        'is_virtual' => true
                    ];
                } catch (Exception $e) {}
            }
        }
        if (!empty($events)) {
            usort($events, function($a, $b) {
                return (!empty($a['date']) ? strtotime($a['date']) : PHP_INT_MAX) <=> (!empty($b['date']) ? strtotime($b['date']) : PHP_INT_MAX);
            });
        }
    }
}

$isAdmin = !empty($viewData['isAdmin']);
$isSuperAdmin = !empty($viewData['isSuperAdmin']);
$isPublic = !empty($viewData['isPublic']);
// Super admin always has write + global ops
if ($isSuperAdmin) {
    $isAdmin = true;
}

$_rcChangelogRaw = '';
$_rcVerFile = BASE_PATH . '/VERSION';
if (is_file($_rcVerFile)) {
    $_rcChangelogRaw = (string)@file_get_contents($_rcVerFile);
}

if (!class_exists('RolePack') && is_file(BASE_PATH . '/app/RolePack.php')) {
    require_once BASE_PATH . '/app/RolePack.php';
}
$rcUser = $_SESSION['user'] ?? ($isSuperAdmin ? 'super_admin' : ($isAdmin ? 'admin' : 'public'));

$currentTab = $viewData['currentTab'] ?? 'team';

$normalizedData = DashboardEngine::normalizeData($viewData['jsData'] ?? []);
DashboardEngine::injectBirthdays($normalizedData['events'], $normalizedData['team'], $normalizedData['locationsById']);

// $customTabs, plus navGroups below. 'cartags'/'cctv' were added everywhere
// the tab file was never included — the tabs rendered blank.
// Three-tier tab gates
// Super Admin  → control plane only (tenants, monitor/opt, access)
// Co. Admin    → full tenant modules + setup
// Visitor      → read-only operational modules (no company/setup)
// Super Admin: control plane + full active-tenant modules (so ?tab=team works on current host)
// Co. Admin: full tenant CRUD. Visitor: read-only operational modules.
$tenantOpsTabs = [
    'team', 'bank', 'docs', 'events', 'locations', 'statutory', 'numero', 'terms',
    'cartags', 'status', 'tracking', 'dispatch', 'ops', 'assets', 'expiry', 'org',
    'audit', 'health', 'leads', 'settings', 'company', 'designations', 'departments',
    'opt', 'cctv', 'access', 'statistics',
];
if ($isSuperAdmin) {
    $validTabs = array_values(array_unique(array_merge(
        ['tenants', 'monitor', 'opt', 'access'],
        $tenantOpsTabs
    )));
} elseif ($isAdmin) {
    $validTabs = $tenantOpsTabs;
} else {
    // Visitor — view / share / print; no company/setup panels
    $validTabs = [
        'team', 'bank', 'docs', 'events', 'locations', 'statutory', 'numero', 'terms',
        'cartags', 'status', 'tracking', 'dispatch', 'ops', 'assets', 'expiry', 'org',
        'health', 'access', 'statistics',
    ];
}
if (class_exists('RolePack') && !$isSuperAdmin) {
    $validTabs = RolePack::filterTabs($rcUser ?? null, $validTabs);
    if (!in_array('terms', $validTabs, true)) {
        $validTabs[] = 'terms';
    }
}
if (!in_array($currentTab, $validTabs, true)) {
    $currentTab = $validTabs[0] ?? 'team';
}


$titles = [
    'team' => 'Human Capital Index',
    'bank' => 'Treasury & Banking Ledger',
    'docs' => 'Corporate Document Vault',
    'events' => 'Corporate Calendar & Observances',
    'locations' => 'Enterprise Premises Registry',
    'statutory' => 'Regulatory Compliance Register',
    'terms' => 'Governance & Acceptable Use Policy',
    'company' => 'Organizational Configuration',
    'designations' => 'Role Taxonomy & Hierarchy',
    'departments' => 'Organizational Units',
    'numero' => 'Numerological Intelligence Report',
    'opt' => 'Platform Optimization Suite',
    'monitor' => 'Infrastructure Telemetry & Diagnostics',
    'cartags' => 'Fleet Asset Registry',
    'cctv' => 'Surveillance Access Control',
    'status' => 'Programme Delivery Status',
    'leads' => 'Opportunity Pipeline',
    'settings' => 'Enterprise Integrations',
    'tracking' => 'Workforce Geolocation & Compliance',
    'dispatch' => 'Dispatch & Live Routes',
    'ops' => 'Field Operations Hub',
    'assets' => 'Asset Checkout Registry',
    'expiry' => 'Document Expiry Radar',
    'org' => 'Organizational Chart',
    'audit' => 'Audit Ledger',
    'health' => 'Platform Health & Backup',
    'tenants' => 'Tenant Setup',
    'access' => 'Access Mode',
];


// Project count for Status tab badge (reads standalone /status data if present)
$statusProjectCount = 0;
$_stProjects = BASE_PATH . '/status/data/projects.json';
if (!is_file($_stProjects)) {
    $_stProjects = BASE_PATH . '/status/projects.json';
}
if (is_file($_stProjects)) {
    $_pj = json_decode((string)@file_get_contents($_stProjects), true);
    if (is_array($_pj)) {
        $statusProjectCount = count(array_filter($_pj, 'is_array'));
    }
}

$DASHBOARD_STATE = [
    'context' => [
        'tab'   => $currentTab,
        'title' => $titles[$currentTab] ?? 'Dashboard',
        'v'     => defined('APP_VERSION') ? APP_VERSION : 'unknown',
        'vdate' => defined('APP_VERSION_DATE') ? APP_VERSION_DATE : '',
        'changelog' => $_rcChangelogRaw ?? ''
    ],
    'auth' => [
        'role'         => $isSuperAdmin ? 'super_admin' : ($isAdmin ? 'admin' : 'public'),
        'isAdmin'      => $isAdmin,
        'isSuperAdmin' => $isSuperAdmin,
        'isPublic'     => $isPublic,
        'pack'         => class_exists('RolePack') ? RolePack::packIdForUser($rcUser ?? null) : ($isAdmin ? 'admin' : 'public'),
        'allowedTabs'  => class_exists('RolePack') ? RolePack::allowedTabs($rcUser ?? null) : null,
    ],
    'data'    => $normalizedData,
    'company' => $viewData['company'] ?? [],
    'statusProjectCount' => $statusProjectCount,
    'csrf' => (string)($_SESSION['csrf_token'] ?? ''),
];
?>
<!DOCTYPE html>
<html lang="en-IN" data-tz="Asia/Kolkata" data-theme="light">
<head>
    <script>
    (function(){
      try {
        var k = 'rc-theme';
        var stored = null;
        try { stored = localStorage.getItem(k); } catch (e) {}
        // Default = device preference when user has not chosen
        var theme = (stored === 'light' || stored === 'dark')
          ? stored
          : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.classList.toggle('dark', theme === 'dark');
        window.rcSetTheme = function(t) {
          if (t !== 'light' && t !== 'dark') t = 'light';
          document.documentElement.setAttribute('data-theme', t);
          document.documentElement.classList.toggle('dark', t === 'dark');
          try { localStorage.setItem(k, t); } catch (e) {}
        };
        window.rcToggleTheme = function() {
          var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
          window.rcSetTheme(cur === 'dark' ? 'light' : 'dark');
        };
        // Follow OS changes only when user has not manually overridden
        if (!stored && window.matchMedia) {
          try {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
              if (!localStorage.getItem(k)) {
                var nt = e.matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', nt);
                document.documentElement.classList.toggle('dark', nt === 'dark');
              }
            });
          } catch (e) {}
        }
      } catch (e) {}
    })();
    </script>

    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    
    <?= class_exists('AppSEO') ? AppSEO::generateTags(['tab' => $currentTab], true) : '<title>' . htmlspecialchars(($titles[$currentTab] ?? 'Portal') . ' · Fusion Resource Centre', ENT_QUOTES) . '</title>' ?>
    
    <!-- ── Installable-web-app metadata ───────────────────────────────────
         Makes the dashboard installable via "Add to Home Screen" on iOS and
         Android today: home-screen icon, standalone window, no browser chrome.
         Offline access and push additionally require a service worker, which
         is intentionally not registered yet — see PWA.md. -->
    <link rel="manifest" href="/manifest.php">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- iOS ignores the manifest for standalone mode and needs its own tags. -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(mb_substr((string)($viewData['company']['name'] ?? 'Directory'), 0, 12)) ?>">
    <link rel="apple-touch-icon" href="/tools/pwa_icon.php?size=192">

    <!-- Performance: warm DNS/TLS for CDNs before first paint -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.sheetjs.com">

    <!-- Fonts: display=swap avoids FOIT; subset weights used in UI -->
    <!-- Fewer font files: 2 weights only; display=swap already in URL -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>

    <!--
      Libraries (pinned stable versions, 2026):
        - Tailwind Play CDN  — keep until a build step ships compiled CSS (biggest future win)
        - Alpine.js 3.17.4   — LTS-class 3.x; defer so it does not block parse
        - Font Awesome 6.7.2 — widely cached 6.x line (7.x CDN path was unstable on some hosts)
        - SheetJS 0.20.3     — defer; only needed for Import/Export
        - EasyQRCodeJS 4.5.1 — defer; only needed for Bank / Vehicle Tags QR
    -->
    <!-- Self-hosted design system (compiled Tailwind 3.4 + tokens). Faster than Play CDN. -->
    <link rel="preload" href="/assets/app.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" as="style">
    <link rel="stylesheet" href="/assets/app.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>">
<style id="rc-modal-viewport-fix">
/* record_editor.php owns modal layout; keep visibility safety net only */
#enterprise-editor-root.is-open{display:flex!important}
</style>

    <!-- FA non-blocking (media=print onload swap); Alpine defer; XLSX/QR on-demand -->
    <link rel="stylesheet" href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="/assets/vendor/fontawesome.min.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>"></noscript>
    <script defer src="/assets/dashboard-app.js?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>"></script>
    <script defer src="/assets/vendor/alpine.min.js?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>"></script>
    <script>document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('img:not([loading])').forEach(function(img){if(!img.closest('[data-no-lazy]'))img.setAttribute('loading','lazy');if(!img.getAttribute('decoding'))img.setAttribute('decoding','async');});});</script>
    <script>
    // Lazy-load heavy libs only when first needed (export / import / QR)
    window.__loadXlsx = function () {
        if (window.XLSX) return Promise.resolve();
        if (window.__xlsxP) return window.__xlsxP;
        window.__xlsxP = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = 'https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js';
            s.onload = function () { resolve(); };
            s.onerror = reject;
            document.head.appendChild(s);
        });
        return window.__xlsxP;
    };
    window.__loadQr = function () {
        if (window.EasyQRCode || window.QRCode) return Promise.resolve();
        if (window.__qrP) return window.__qrP;
        window.__qrP = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = '/assets/vendor/easy.qrcode.min.js';
            s.onload = function () { resolve(); };
            s.onerror = reject;
            document.head.appendChild(s);
        });
        return window.__qrP;
    };
    /** Paint UPI QR into a bank card node (static qr_image preferred; else EasyQRCode / API). */
    window.rcPaintBankQr = async function (el, item, forceClient) {
        if (!el || !item) return;
        var upi = String(item.upi_id || item.upi || '').trim();
        var holder = String(item.holder_name || item.account_holder || '').trim();
        if (!forceClient && item.qr_image) {
            var existing = el.querySelector('img');
            if (existing && existing.getAttribute('src')) return;
        }
        if (!upi) return;
        var payload = 'upi://pay?pa=' + encodeURIComponent(upi)
            + (holder ? '&pn=' + encodeURIComponent(holder) : '')
            + '&cu=INR';
        try { await window.__loadQr(); } catch (e) {}
        el.innerHTML = '';
        var QR = window.QRCode || window.EasyQRCode;
        if (typeof QR === 'function') {
            try {
                new QR(el, {
                    text: payload,
                    width: 112,
                    height: 112,
                    colorDark: '#0f172a',
                    colorLight: '#ffffff',
                    correctLevel: (QR.CorrectLevel && QR.CorrectLevel.M) ? QR.CorrectLevel.M : 1,
                    quietZone: 6,
                    quietZoneColor: '#ffffff'
                });
                return;
            } catch (e) {}
        }
        var img = document.createElement('img');
        img.alt = 'UPI QR for ' + (holder || upi);
        img.width = 112;
        img.height = 112;
        img.className = 'w-full h-full object-contain';
        img.loading = 'lazy';
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=224x224&margin=8&ecc=M&data=' + encodeURIComponent(payload);
        el.appendChild(img);
    };
    </script>
    <script>
    window.APP_LOCALE = <?= json_encode(
        class_exists('AppLocale') ? AppLocale::jsConfig() : ['tz'=>'Asia/Kolkata','lang'=>'en-IN','currency'=>'INR','phoneCode'=>'+91','labels'=>[]],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
    window.formatIST = function(d, style) {
        style = style || 'datetime';
        try {
            // Prefer strict DD-MM-YYYY for Indian regional display
            if (style === 'date' || style === 'dmy' || !style) {
                try {
                    var x = d instanceof Date ? d : new Date(d);
                    if (!isNaN(x.getTime())) {
                        var dd = String(x.getDate()).padStart(2,'0');
                        var mm = String(x.getMonth()+1).padStart(2,'0');
                        var yy = x.getFullYear();
                        return dd + '-' + mm + '-' + yy;
                    }
                } catch (e) {}
            }
            const opts = style === 'time'
                ? { timeZone:'Asia/Kolkata', hour:'numeric', minute:'2-digit', hour12:true }
                : style === 'date'
                ? { timeZone:'Asia/Kolkata', day:'numeric', month:'short', year:'numeric' }
                : { timeZone:'Asia/Kolkata', weekday:'short', day:'numeric', month:'short', year:'numeric', hour:'numeric', minute:'2-digit', hour12:true };
            return new Intl.DateTimeFormat('en-IN', opts).format(d instanceof Date ? d : new Date(d));
        } catch (e) { return String(d); }
    };
    window.formatINR = function(n) {
        try {
            return new Intl.NumberFormat('en-IN', { style:'currency', currency:'INR', maximumFractionDigits:2 }).format(Number(n)||0);
        } catch (e) { return '₹' + n; }
    };
    window.formatPhoneIN = function(raw) {
        const d = String(raw||'').replace(/\D/g,'');
        let x = d;
        if (x.length === 12 && x.startsWith('91')) x = x.slice(2);
        if (x.length === 10) return '+91 ' + x.slice(0,5) + ' ' + x.slice(5);
        return String(raw||'');
    };

    </script>

    
    <script>
        // ── Service worker registration ─────────────────────────────────
        // Registered with ?v=<APP_VERSION> so a deploy produces a new worker
        // URL and the browser refreshes the cache automatically. Failure is
        // non-fatal: the app works exactly as before without it.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker
                    .register('/sw.js?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : 'dev') ?>')
                    .catch(function (e) { console.warn('Offline mode unavailable:', e); });
            });
        }

        // rules as CardContext::get() — street, city, state + PIN, with any
        // leading copy of the site name stripped.
        //
        // The share text previously emitted `location_name || location_id`,
        // which produces the SITE NAME ("Head Office") or, when that is blank,
        // the raw record id. Neither is an address. Someone pasting a shared
        // contact into a courier form got a label or a slug.
        window.APP_ADDR = <?php
            $_addrMap = [];
            foreach ((AppDB::read('locations') ?: []) as $_l) {
                if (empty($_l['id'])) continue;
                $_street = trim((string)($_l['address'] ?? ''));
                $_site   = trim((string)($_l['name'] ?? ''));
                if ($_site !== '' && $_street !== '') {
                    $_street = preg_replace('/^\s*' . preg_quote($_site, '/') . '\s*[,\-–—:]\s*/iu', '', $_street) ?? $_street;
                    if (strcasecmp(trim($_street), $_site) === 0) $_street = '';
                }
                $_addrMap[(string)$_l['id']] = implode(', ', array_filter([
                    $_street,
                    trim((string)($_l['city'] ?? '')),
                    trim(trim((string)($_l['state'] ?? '')) . ' ' . trim((string)($_l['pincode'] ?? ''))),
                ], fn($v) => $v !== ''));
            }
            echo json_encode($_addrMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;

        // Resolve a team record to its full address, falling back to any
        // address stored directly on the person.
        window.fullAddress = function (item) {
            if (!item) return '';
            var own = (item.address || '').trim();
            if (own) return own;
            var id = item.location_id || '';
            return (window.APP_ADDR && window.APP_ADDR[id]) ? window.APP_ADDR[id] : '';
        };

        window.APP = window.APP || {};
        window.APP.csrf = "<?= class_exists('AppAuth') ? AppAuth::csrf_token() : ($_SESSION['csrf_token'] ?? '') ?>";
        window.CSRF_TOKEN = window.APP.csrf;
        window.__DASHBOARD_STATE__ = <?= json_encode($DASHBOARD_STATE, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?>;
        window.__RC_TEAM_COUNT__ = <?= (int)count($normalizedData['team'] ?? []) ?>;
        window.__RC_LOC_COUNT__ = <?= (int)count($normalizedData['locations'] ?? []) ?>;
        window.rcTrackMetric = function(key) {
          try {
            fetch('index.php', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
              body: JSON.stringify({
                action: 'analytics_inc',
                key: key,
                csrf_token: (window.__DASHBOARD_STATE__ && window.__DASHBOARD_STATE__.csrf) || ''
              })
            }).catch(function(){});
          } catch (e) {}
        };
        document.addEventListener('click', function(ev) {
          var t = ev.target && ev.target.closest ? ev.target.closest('[data-rc-track]') : null;
          if (!t) return;
          var k = t.getAttribute('data-rc-track');
          if (k === 'share' || k === 'share_clicks') window.rcTrackMetric('share_clicks');
          if (k === 'print' || k === 'print_clicks') window.rcTrackMetric('print_clicks');
        }, true);

                window.openModalEditor = function(isEdit, type, item) {
            if (typeof item === 'undefined') item = null;
            var safeItem = null;
            try {
                safeItem = item ? JSON.parse(JSON.stringify(item)) : null;
            } catch (e) {
                safeItem = item || null;
            }
            var detail = { isEdit: !!isEdit, type: type || 'team', item: safeItem };
            var opened = false;
            var tryOpen = function () {
                try {
                    window.dispatchEvent(new CustomEvent('open-editor', { detail: detail, bubbles: true }));
                } catch (e1) {}
                try {
                    if (window.Alpine) {
                        var root = document.getElementById('enterprise-editor-root')
                            || document.querySelector('[x-data*="enterpriseEditor"]');
                        if (root) {
                            var ed = Alpine.$data(root);
                            if (ed && typeof ed.openEditor === 'function') {
                                ed.openEditor(detail);
                                opened = true;
                                // Ensure visible even if x-show lag
                                root.classList.add('is-open');
                                // Let Alpine x-show control display; only hint flex if still hidden
                                if (getComputedStyle(root).display === 'none') root.style.display = 'flex';
                            }
                        }
                    }
                } catch (e2) {}
            };
            tryOpen();
            if (!opened) {
                // Alpine may still be booting (defer) — retry briefly
                var n = 0;
                var t = setInterval(function () {
                    n++;
                    tryOpen();
                    if (opened || n >= 20) {
                        clearInterval(t);
                        if (!opened) {
                            alert('Editor failed to load. Hard-refresh (Ctrl+Shift+R). Ensure app/views/partials/record_editor.php is uploaded.');
                        }
                    }
                }, 100);
            }
        };


        // window.runOptimise — proxy to dashboardApp.runOptimise() callable from
        // any Alpine scope (e.g. tab_monitor.php x-data blocks that have their
        // own inner scope and can't reach the parent dashboardApp method directly).
        window.runOptimise = function() {
            const app = Alpine.store('appState');
            if (app && typeof app.runOptimise === 'function') {
                app.runOptimise();
            } else {
                // Fallback: dispatch a custom event that dashboardApp listens to
                window.dispatchEvent(new CustomEvent('trigger-optimise'));
            }
        };

        // window.toggleSlugLockGlobal — called by tab_team.php toggleSlugLock()
        // when it needs to persist slug_locked to the backend. Reuses dashboardApp's
        // postReq (JSON + CSRF) so the request format is consistent with all other
        // AJAX calls to index.php.
        window.toggleSlugLockGlobal = async function(id, locked) {
            try {
                const r = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
                    body: JSON.stringify({ action: 'toggle_slug_lock', ns: 'team', id: id, locked: locked })
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return await r.json();
            } catch(e) {
                return { status: 'error', message: e.message };
            }
        };

        // WA_EMOJIS removed entirely (v2027.303): every share template that
        // WhatsApp's own wa.me → api.whatsapp.com redirect corrupts
        // emoji-range characters server-side — confirmed via a debug
        // marker that our JS-generated emoji (via String.fromCodePoint)
        // were correct right up until they passed through WhatsApp's own
        // infrastructure. This is not fixable from application code, so
        // all share text now relies on plain rule/separator characters
        // (─, ·) instead, which are unaffected.

        // window.__shareWA: global callable from any Alpine scope.
        // tab_team and tab_terms have own x-data and can't call parent shareItem().
        // This function is accessible from all scopes via window.
        window.__shareWA = function(type, item) {
            var eSep = '\n───────────────────────────\n';
            var v    = function(val) { return (val && String(val).trim() !== '' && val !== '-') ? String(val).trim() : null; };
            var text = '';

            if (type === 'team') {
                // Emoji-free "Executive Minimalist" template. WhatsApp's own
                // wa.me → api.whatsapp.com redirect was found to corrupt
                // emoji characters server-side (confirmed: our JS correctly
                // builds them via String.fromCodePoint, but they arrive as
                // U+FFFD after passing through WhatsApp's own infrastructure).
                // Structure/hierarchy is preserved via plain rule dividers,
                // which are unaffected since they're outside emoji ranges.
                var name    = v(item.name)   || 'Team Member';
                var desig   = v(item.designation_name);
                var dept    = v(item.department_name);
                var phone   = v(item.phone   || item.mobile);
                var email   = v(item.email   || item.email_address);
                var loc     = v(window.fullAddress(item));
                var cardUrl = window.location.origin + '/?card=business&slug=' + encodeURIComponent(item.slug || item.id);
                var rule    = '──────────────';

                text  = '*' + name + '*\n';
                if (desig || dept) text += [desig, dept].filter(Boolean).join(' · ') + '\n';
                text += rule + '\n';
                if (phone) text += phone + '\n';
                if (email) text += email + '\n';
                if (loc)   text += loc    + '\n';
                text += rule + '\n';
                text += 'View full profile\n' + cardUrl;
            } else {
                // Fallback for unexpected types
                text = '*' + (v(item.name||item.title)||'Record') + '*';
            }
            window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
        };

    </script>
    
    <link rel="stylesheet" href="/assets/dashboard.css?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>">

    <link rel="stylesheet" href="/assets/a11y.css?v=20260928.07">
    <link rel="stylesheet" href="/assets/contrast-lock.css?v=20260928.07">
    <script src="assets/a11y-focus-trap.js?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" defer></script>
    <script src="assets/a11y-tooltip.js?v=<?= rawurlencode(defined('APP_VERSION') ? APP_VERSION : '1') ?>" defer></script>
</head>

<body x-data="dashboardApp" 
      @company-updated.window="company = JSON.parse(JSON.stringify($event.detail))" 
      @keydown.window.cmd.k.prevent="$refs.searchInput.focus()"
      @delete-record.window="deleteItem($event.detail.id, $event.detail.ns)"
      @share-record.window="shareItem($event.detail.type, $event.detail.item)"
      @trigger-optimise.window="runOptimise()"
>

        <!-- Super Admin exclusive: ultra-narrow vertical role rail (~2.75rem) -->
        <?php if (!empty($isSuperAdmin) || (($_SESSION['true_role'] ?? '') === 'super_admin')): ?>
        <style>
          #rc-role-switch {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: 2.75rem; /* ~11–12pt column */
            z-index: 80;
            background: #0f172a;
            border-right: 1px solid #1e293b;
            display: flex; flex-direction: column; align-items: center;
            padding: 0.5rem 0.2rem; gap: 0.35rem;
          }
          #rc-role-switch .rc-role-btn {
            writing-mode: vertical-rl; text-orientation: mixed;
            transform: rotate(180deg);
            font-size: 0.65rem; font-weight: 800; letter-spacing: 0.04em;
            padding: 0.65rem 0.15rem; border-radius: 0.4rem;
            border: 1px solid rgba(255,255,255,0.15);
            color: #e2e8f0; background: transparent; cursor: pointer;
            line-height: 1.1; max-height: 9rem;
          }
          #rc-role-switch .rc-role-btn:hover { background: rgba(255,255,255,0.08); color: #fff; }
          #rc-role-switch .rc-role-btn.is-active { color: #fff; }
          #rc-role-switch .rc-role-btn[data-role="super_admin"].is-active { background: #2563eb; border-color: #60a5fa; }
          #rc-role-switch .rc-role-btn[data-role="admin"].is-active { background: #059669; border-color: #34d399; }
          #rc-role-switch .rc-role-btn[data-role="public"].is-active { background: #d97706; border-color: #fbbf24; }
          body.rc-has-role-rail { padding-left: 2.75rem; }
          @media print { #rc-role-switch { display: none !important; } body.rc-has-role-rail { padding-left: 0 !important; } }
        </style>
        <div class="no-print" id="rc-role-switch" title="View as (Super Admin)">
          <button type="button" data-role="super_admin" class="rc-role-btn <?= (($_SESSION['user']??'')==='super_admin')?'is-active':'' ?>">Super-Admin</button>
          <button type="button" data-role="admin" class="rc-role-btn <?= (($_SESSION['user']??'')==='admin')?'is-active':'' ?>">Company-Admin</button>
          <button type="button" data-role="public" class="rc-role-btn <?= (($_SESSION['user']??'')==='public')?'is-active':'' ?>">General HR</button>
        </div>
        <script>
        document.body.classList.add('rc-has-role-rail');
        (function(){
          var bar = document.getElementById('rc-role-switch');
          if (!bar) return;
          bar.querySelectorAll('[data-role]').forEach(function(btn){
            btn.addEventListener('click', function(){
              var role = btn.getAttribute('data-role');
              var csrf = (window.APP && window.APP.csrf) || window.APP_CSRF || '';
              btn.disabled = true;
              fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':csrf},
                body: JSON.stringify({action:'switch_role', role: role, csrf_token: csrf, csrf: csrf})
              }).then(function(r){ return r.json().then(function(j){ return {ok:r.ok, j:j}; }); }).then(function(x){
                if (x.j && (x.j.status==='success'||x.j.status==='ok')) location.reload();
                else { alert((x.j && x.j.message) || 'Switch failed'); btn.disabled = false; }
              }).catch(function(e){ alert('Switch failed: '+e.message); btn.disabled = false; });
            });
          });
        })();
        </script>
        <?php endif; ?>

<a class="rc-skip-link" href="#rc-main-content">Skip to main content</a>

<?php if (!empty($psiPreviewActive)): ?>
<div class="no-print" style="position:sticky;top:0;z-index:9999;background:#b45309;color:#fff;text-align:center;font:600 12px/1.4 system-ui,sans-serif;padding:6px 10px">
  PSI PREVIEW MODE — read-only · noindex · delete data/psi_token.txt when finished
</div>
<?php endif; ?>

    
    <!-- ═══════════════════════════════════════════════════════════
         SIDEBAR
    ═══════════════════════════════════════════════════════════════ -->
    <aside id="rc-sidebar" class="sidebar no-print" role="complementary" aria-label="Application modules" :class="sidebarOpen ? 'w-60' : 'w-[4.5rem] collapsed'">
        
        <!-- Logo Area -->
        <div class="h-16 flex items-center justify-between border-b border-[#1e293b] px-4 shrink-0 overflow-hidden">
            <div x-show="sidebarOpen" class="flex-1 flex items-center h-full w-full pr-2 overflow-hidden">
                <a :href="company.website || '#'" target="_blank" class="block w-full h-full flex items-center">
                    <template x-if="company.logo">
                        <span class="rc-logo-shell inline-flex items-center justify-center max-h-10 max-w-full px-2 py-1 rounded-lg bg-white/95 border border-slate-200/80 shadow-sm">
                            <img :src="'images/'+company.logo+'?v='+ts" alt="" class="max-h-7 max-w-[140px] object-contain object-left">
                        </span>
                    </template>
                    <template x-if="!company.logo">
                        <div class="font-bold text-slate-300 tracking-wide truncate text-sm" x-text="company.name || 'ENTERPRISE'"></div>
                    </template>
                </a>
            </div>
            <div x-show="!sidebarOpen" class="flex-1 flex items-center justify-center w-full h-full">
                <a :href="company.website || '#'" target="_blank" class="flex items-center justify-center">
                    <template x-if="company.favicon">
                        <span class="rc-logo-shell inline-flex items-center justify-center w-9 h-9 rounded-lg bg-white/95 border border-slate-200/80 shadow-sm">
                            <img :src="'images/'+company.favicon+'?v='+ts" alt="" class="w-6 h-6 object-contain">
                        </span>
                    </template>
                    <template x-if="!company.favicon">
                        <i class="fa-solid fa-cube text-lg text-slate-400 hover:text-blue-400 transition-colors"></i>
                    </template>
                </a>
            </div>
            <button type="button" @click="sidebarOpen=!sidebarOpen; $nextTick(() => { window.dispatchEvent(new Event('resize')); this.recalculatePerPage && this.recalculatePerPage(); })" class="text-slate-600 hover:text-blue-400 transition-colors shrink-0 w-6 h-6 flex items-center justify-center" aria-label="Toggle navigation pane">
                <i class="fa-solid text-xs" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
            </button>
        </div>

        <!-- Navigation -->
        <nav x-ref="sidebarNav" class="flex-1 overflow-y-auto px-3 py-4 scroll-area hide-scrollbar" id="rc-sidebar-nav" aria-label="Module navigation">
            <template x-for="group in navGroups" :key="group.title">
                <div class="mb-2">
                    <div class="section-label" x-text="group.title"></div>
                    <template x-for="n in group.items" :key="n.id">
                        <a :href="n.url ? n.url : '?tab='+n.id" 
                           class="sidebar-link" 
                           :class="cur===n.id ? 'active' : ''">
                            <i :class="n.icon" class="w-4 text-center shrink-0" :class="sidebarOpen ? 'mr-3' : 'mx-auto'"></i>
                            <span x-show="sidebarOpen" x-text="n.label" class="link-text truncate"></span>
                            <span x-show="sidebarOpen && counts[n.id] > 0" class="count-badge" x-text="counts[n.id]"></span>
                        </a>
                    </template>
                </div>
            </template>
        </nav>
        
        <!-- Footer -->
        <div class="border-t border-[#1e293b] p-4 flex items-center justify-between shrink-0">
            <div x-show="sidebarOpen" class="px-2 py-1 space-y-1 min-w-0 flex-1">
                <div x-show="isSuperAdmin" class="px-2 pb-1 text-[9px] leading-snug text-slate-500" x-cloak>
                Site Developer: Arthsathi Limited
            </div>
            <button type="button" x-show="isSuperAdmin" @click="showChangelog = true"
                        class="block w-full text-left rounded-lg px-2 py-1.5 bg-slate-800/80 border border-slate-600 hover:border-sky-400 hover:bg-slate-800 transition"
                        title="Open version changelog">
                    <span class="block text-[9px] uppercase tracking-wider text-slate-400 font-bold">Version</span>
                    <span class="block text-sm font-black text-sky-300 font-mono leading-tight">v<span x-text="state.context.v"></span></span>
                    <span class="block text-[10px] text-slate-400 mt-0.5">Changelog ↗</span>
                </button>
                <a href="?tab=terms" class="block text-[10px] text-slate-400 hover:text-blue-300 px-1">Governance</a>
                <a href="?tab=terms&amp;policy=privacy" class="block text-[10px] text-slate-400 hover:text-blue-300 px-1">Privacy &amp; DPDP</a>
            </div>
            <button type="button" x-show="!sidebarOpen && isSuperAdmin" @click="showChangelog = true"
                    class="text-center w-full rounded-md py-1 text-[10px] font-mono font-bold text-sky-300 hover:text-white bg-slate-800 border border-slate-600"
                    title="Changelog">v<span x-text="state.context.v"></span></button>
            <button type="button" onclick="window.rcToggleTheme && window.rcToggleTheme()"
                        class="ctrl-btn rc-hit-lg" title="Toggle Interface Theme" aria-label="Toggle colour theme">
                    <i class="fa-solid fa-moon text-xs" aria-hidden="true"></i>
                </button>
                <button type="button" @click="logout()" class="rc-hit-lg text-slate-600 hover:text-red-400 transition-colors shrink-0 ml-auto flex items-center justify-center" title="Terminate Session" aria-label="Terminate session and sign out">
                <i class="fa-solid fa-power-off text-xs" aria-hidden="true"></i>
            </button>
        </div>
    </aside>

    <!-- ═══════════════════════════════════════════════════════════
         MAIN CONTENT
    ═══════════════════════════════════════════════════════════════ -->
    <div id="rc-status-live" class="rc-live rc-live-a11y" role="status" aria-live="polite" aria-atomic="true"></div>
    <main id="rc-main-content" class="flex flex-col flex-1 min-w-0 h-screen bg-slate-50" tabindex="-1">
        
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 shrink-0 z-30 relative" role="banner">
            <div class="h-16 flex items-center justify-between px-4 md:px-6 gap-2">
            <div class="flex items-center gap-3 min-w-0">
                <h1 class="text-lg font-extrabold text-slate-800 truncate tracking-tight" x-text="state.context.title"></h1>
                <template x-if="!['company','opt','numero','statistics','monitor','terms','cctv','status','leads','settings','tracking','dispatch','ops','assets','expiry','org','audit','health'].includes(cur)">
                    <span class="rc-count-badge hidden sm:inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold tabular-nums border"
                          x-text="filteredList.length + (filteredList.length === 1 ? ' entry' : ' entries')"></span>
                </template>
                <span class="rc-header-clock hidden md:inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold tabular-nums border"
                      :title="'Indian Standard Time (Asia/Kolkata)'"
                      x-text="clock"></span>
            </div>
            
            <div class="flex items-center gap-1.5 md:gap-3">
                <!-- Search (hidden on Super Admin tool tabs — avoids double chrome on Monitor) -->
                <div class="relative" x-show="!['monitor','opt','tenants','access','statistics'].includes(cur)" x-cloak>
                    <label for="rc-global-search" class="sr-only">Search current registry</label>
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none" aria-hidden="true"></i>
                    <input id="rc-global-search"
                           x-ref="searchInput"
                           x-model.debounce.120ms="search"
                           @focus="globalSearchOpen = true"
                           @keydown.escape.window="globalSearchOpen = false"
                           @keydown.escape="globalSearchOpen = false"
                           type="search"
                           list="global-suggest"
                           autocomplete="off"
                           spellcheck="false"
                           inputmode="search"
                           class="pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm outline-none w-28 sm:w-48 focus:border-blue-400 focus:bg-white focus:w-56 transition-all duration-200 font-medium placeholder:text-slate-400"
                           placeholder="Search name, house no, phone…"
                           aria-controls="rc-global-hits"
                           aria-autocomplete="list"
                           aria-expanded="false"
                           :aria-expanded="globalSearchOpen && globalHits.length > 0"
                           :aria-description="'Showing ' + (filteredList.length || 0) + ' entries'"
                           :title="'Type to filter this tab · ' + (filteredList.length || 0) + ' shown'">

                    <datalist id="global-suggest">
                        <template x-for="s in searchSuggestions" :key="s">
                            <option :value="s"></option>
                        </template>
                    </datalist>
                    <div id="rc-global-hits" x-show="globalSearchOpen && search.trim().length >= 2" x-cloak
                         @click.outside="globalSearchOpen = false"
                         class="absolute right-0 top-full mt-1 w-72 sm:w-96 max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl z-[60]"
                         role="listbox" aria-label="Search results across registries">
                      <template x-if="globalHits.length === 0">
                        <p class="px-3 py-3 text-xs text-slate-500 m-0">No matches in people, documents, vehicles, treasury, or locations.</p>
                      </template>
                      <template x-for="(hit, hi) in globalHits" :key="(hit.ns + '-' + hit.id + '-' + hi)">
                        <button type="button" role="option"
                                class="w-full text-left px-3 py-2.5 hover:bg-slate-50 border-b border-slate-50 last:border-0 flex gap-2 items-start"
                                @click="openGlobalHit(hit)">
                          <span class="text-[9px] font-black uppercase tracking-wide text-slate-500 bg-slate-100 rounded px-1.5 py-0.5 shrink-0 mt-0.5" x-text="hit.kind"></span>
                          <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900 truncate" x-text="hit.title"></span>
                            <span class="block text-[11px] text-slate-500 truncate" x-text="hit.sub"></span>
                          </span>
                        </button>
                      </template>
                    </div>
                </div>

                <!-- Global sort — list tabs except team (team sorts via column/chip headers only) -->
                <div class="flex items-center gap-1 no-print"
                     x-show="!['opt','numero','statistics','monitor','company','terms','status','settings','cctv','team','tracking','dispatch','ops','assets','expiry','org','audit','health'].includes(cur)"
                     x-cloak>
                    <label for="rc-global-sort" class="text-[10px] font-bold uppercase tracking-wider text-slate-400 hidden sm:inline">Sort</label>
                    <select id="rc-global-sort"
                            class="h-8 max-w-[9.5rem] text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 px-2 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
                            x-model="sortCol"
                            @change="onSortColChange()"
                            aria-label="Sort registry entries">
                        <template x-for="opt in sortOptions" :key="opt.id">
                            <option :value="opt.id" x-text="opt.label"></option>
                        </template>
                    </select>
                    <button type="button" @click="toggleSortDir()"
                            class="h-8 w-8 rounded-lg border border-slate-200 text-slate-600 bg-white hover:bg-blue-50 hover:border-blue-300 flex items-center justify-center"
                            :title="sortAsc ? 'Ascending — click for descending' : 'Descending — click for ascending'">
                        <i class="fa-solid text-xs" :class="sortAsc ? 'fa-arrow-up-short-wide' : 'fa-arrow-down-wide-short'"></i>
                    </button>
                </div>
                
                <div class="h-5 w-px bg-slate-200 hidden md:block"></div>
                
                <!-- Action buttons -->
                <template x-if="!['company','opt','numero','statistics','monitor','terms','cartags','cctv','status','leads','settings','tracking','dispatch','ops','assets','expiry','org','audit','health'].includes(cur)">
                    <div class="hidden md:flex items-center gap-1">
                        <?php if($isAdmin): ?>
                        <button type="button" @click="downloadImportTemplate()"
                                class="h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-emerald-600 flex items-center justify-center transition"
                                title="Download Data Ingestion Template (XLSX)">
                            <i class="fa-solid fa-file-arrow-down text-sm"></i>
                        </button>
                        <button type="button" @click="$refs.importInput.click()" 
                                class="h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-blue-600 flex items-center justify-center transition" 
                                title="Data Ingestion & Reconciliation (XLSX)">
                            <i class="fa-solid fa-file-import text-sm"></i>
                        </button>
                        <?php endif; ?>
                        <button @click="exportExcel()" 
                                class="h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-blue-600 flex items-center justify-center transition" 
                                title="Extract Dataset (XLSX)">
                            <i class="fa-solid fa-file-export text-sm"></i>
                        </button>
                    </div>
                </template>
                
                <button type="button" @click="printActiveTab()"
                        class="hidden md:flex h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-blue-600 items-center justify-center transition no-print"
                        title="Generate Print-Ready Register">
                    <i class="fa-solid fa-print text-sm"></i>
                </button>

                <!-- Export every contact as one vCard 3.0 file, photos embedded.
                     VISIBLE ON ALL SCREEN SIZES — unlike Print, which stays
                     desktop-only. This was originally copied with Print's
                     `hidden md:flex`, which hid it on phones: exactly the
                     device where importing contacts is the whole point.
                     Also carries a visible text label rather than being
                     icon-only, so it cannot disappear if an icon font fails
                     to load. -->
                <button x-show="cur === 'team'" x-cloak onclick="if(confirm('Generate a consolidated vCard export of the Human Capital Index?\n\nEmbedded imagery may increase file size. Deploy the package on authorized devices to synchronize contacts.')) window.location.href='tools/export_vcf.php'"
                        class="flex items-center gap-1.5 h-8 px-2.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-300 transition shrink-0"
                        title="Export Contact Card Registry (vCard)">
                    <i class="fa-solid fa-address-book text-sm"></i>
                    <span class="hidden sm:inline text-[11px] font-bold">vCard Export</span>
                </button>
                
                <?php if($isAdmin): ?>
                <button type="button" 
                        onclick="window.openModalEditor(false, '<?= $currentTab ?>')" 
                        x-show="!['opt','numero','company','monitor','terms','cartags','cctv','status','leads','settings','tracking','dispatch','ops','assets','expiry','org','audit','health'].includes(cur)" 
                        class="h-8 px-3 bg-blue-600 text-white rounded-lg flex items-center gap-1.5 hover:bg-blue-700 transition shadow-sm text-sm font-bold cursor-pointer">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span class="hidden sm:inline">Add</span>
                </button>
                <button type="button" x-show="cur === 'team'" x-cloak
                        @click="window.__teamApi && window.__teamApi.edit()"
                        class="team-act-edit h-8 px-2.5 rounded-lg text-xs font-bold border flex items-center gap-1 transition text-slate-400 bg-slate-50 border-slate-200 opacity-40 cursor-not-allowed"
                        data-team-act="edit" disabled>
                    <i class="fa-solid fa-pen text-[10px]"></i> <span class="hidden sm:inline">Modify</span>
                </button>
                <button type="button" x-show="cur === 'team'" x-cloak
                        @click="window.__teamApi && window.__teamApi.del()"
                        class="team-act-del h-8 px-2.5 rounded-lg text-xs font-bold border flex items-center gap-1 transition text-slate-400 bg-slate-50 border-slate-200 opacity-40 cursor-not-allowed"
                        data-team-act="del" disabled>
                    <i class="fa-solid fa-trash text-[10px]"></i> <span class="hidden sm:inline">Retire</span>
                </button>
                <?php endif; ?>
            </div>
            </div><!-- /row 1 — secondary team toolbar removed -->
        </header>

        <input type="file" x-ref="importInput" class="hidden" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" @change="importExcel">

        <!-- One-Step Import preview: Select All / Unselect All / Import
             Selected, requested explicitly since the old flow had no
             review step for these controls to act on at all. -->
        <div x-show="importPreviewOpen" x-cloak
             class="fixed inset-0 z-[99998] flex items-center justify-center p-4"
             style="background:rgba(15,23,42,.55)"
             @keydown.escape.window="cancelImportPreview()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden"
                 role="dialog" aria-modal="true" aria-label="Review import before applying">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between shrink-0">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Review Import</h2>
                        <p class="text-xs text-slate-500 mt-0.5" x-text="importPreviewRows.length + ' row(s) found — ' + importSelectedCount + ' selected'"></p>
                    </div>
                    <button type="button" @click="cancelImportPreview()" class="w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition" aria-label="Close import preview">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2 shrink-0">
                    <button type="button" @click="selectAllImportRows()" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">Select All</button>
                    <button type="button" @click="unselectAllImportRows()" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">Unselect All</button>
                </div>

                <div class="overflow-y-auto flex-1">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="w-10 px-3 py-2"></th>
                                <template x-for="h in importPreviewHeaders" :key="h">
                                    <th class="px-3 py-2 text-left font-bold text-slate-500 uppercase tracking-wide text-[10px]" x-text="h"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(r, idx) in importPreviewRows" :key="idx">
                                <tr class="border-b border-slate-100" :class="r._selected ? '' : 'opacity-40'">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" x-model="r._selected" class="w-4 h-4 rounded border-slate-300" :aria-label="'Include row ' + (idx + 1)">
                                    </td>
                                    <template x-for="h in importPreviewHeaders" :key="h">
                                        <td class="px-3 py-2 text-slate-600 truncate max-w-[180px]" x-text="r._row[h]"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-3 border-t border-slate-200 flex items-center justify-end gap-2 shrink-0">
                    <button type="button" @click="cancelImportPreview()" class="px-4 py-2 rounded-lg text-slate-600 hover:bg-slate-100 text-sm font-bold transition">Cancel</button>
                    <button type="button" @click="confirmImportSelected()" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold transition">
                        Import Selected (<span x-text="importSelectedCount"></span>)
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div id="rc-content" data-rc-list class="flex-1 min-h-0 overflow-auto p-4 md:p-6 pb-24 md:pb-6">
                    <?php
                    // Statistics: use ?tab=statistics only

                    // Public / visitor live widgets
                    if (!empty($isPublic) && empty($isAdmin) && empty($isSuperAdmin)) {
                        $wxPartial = __DIR__ . '/partials/public_live_widgets.php';
                        if (is_file($wxPartial)) {
                            require $wxPartial;
                        }
                    }
                    // Celebrations · next 7 days — Team Directory only
                    if (($currentTab ?? '') === 'team') {
                        $bdayPartial = __DIR__ . '/partials/value_birthday_strip.php';
                        if (is_file($bdayPartial)) {
                            require $bdayPartial;
                        }
                    }
                    // Search is the single header control only (no second people-search strip).
                    // Co. Admin: Celebrations strip only (birthday partial above).
                    ?>

            <div class="w-full max-w-full block">
                <?php 
                    // fell through to the generic table view further down and never
                    // loaded dbd/tab_departments.php / dbd/tab_designations.php at all.
                    $customTabs = ['team', 'events', 'company', 'numero', 'statistics', 'opt', 'docs', 'statutory', 'monitor', 'tenants', 'access', 'locations', 'terms', 'departments', 'designations', 'cartags', 'cctv', 'status', 'leads', 'settings', 'tracking', 'dispatch', 'ops', 'assets', 'expiry', 'org', 'audit', 'health'];
                    if (in_array($currentTab, ['monitor', 'opt', 'tenants', 'access'], true) && empty($isSuperAdmin)) {
                        echo '<div class="p-8 text-center text-red-700 font-bold bg-red-50 rounded-xl border border-red-200 m-4">Access Denied — Super Admin only.</div>';
                    } elseif (in_array($currentTab, $customTabs)) {
                        $fileName = ($currentTab === 'statutory') ? 'tab_stat.php' : 'tab_' . $currentTab . '.php';
                        $tabFilePrimary   = BASE_PATH . '/app/views/tabs/' . $fileName;
                        $tabFileSecondary = BASE_PATH . '/app/views/tabs/' . $fileName;
                        
                        if (file_exists($tabFilePrimary)) { include $tabFilePrimary; } 
                        elseif (file_exists($tabFileSecondary)) { include $tabFileSecondary; } 
                        else { echo "<div class='p-8 text-center text-rose-950 font-bold bg-rose-50 border-2 border-dashed border-rose-700 rounded-xl' role='alert'>⚠ Error: Missing UI Component ({$fileName})</div>"; }
                    } else {
                ?>
                <div class="w-full flex flex-col block">
                    <template x-if="paginatedList.length === 0">
                        <div class="flex flex-col items-center justify-center h-64 text-slate-400 border-2 border-dashed border-slate-200 rounded-xl w-full bg-white/50">
                            <i class="fa-solid fa-folder-open text-4xl mb-4 opacity-50"></i>
                            <p class="text-sm font-semibold">No records found.</p>
                        </div>
                    </template>
                    
                    <!-- Bank Tab — Treasury & UPI QR -->
                    <template x-if="cur === 'bank'">
                        <div class="w-full flex flex-col gap-4">
                            <template x-for="i in paginatedList" :key="i.id">
                                <div class="w-full bg-white rounded-xl shadow-sm border border-slate-200 p-5 border-l-4 border-l-blue-500 relative overflow-hidden group hover:border-blue-300 hover:shadow-md transition-all flex flex-col md:flex-row md:items-start justify-between gap-4">
                                    <div class="flex flex-col md:flex-row gap-6 w-full flex-1 min-w-0">
                                        <div class="md:w-36 shrink-0 md:border-r border-slate-100 md:pr-4 min-w-0">
                                            <div class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mb-1">Reference ID</div>
                                            <div class="font-mono text-base font-bold text-slate-700 truncate" x-text="i.slug || i.id || '-'"></div>
                                        </div>
                                        <div class="flex-1 min-w-0 md:border-r border-slate-100 md:px-4 space-y-2">
                                            <div><div class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">Account Holder</div><div class="font-bold text-slate-800 text-base truncate" x-text="i.holder_name"></div></div>
                                            <div><div class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">Account Number</div><div class="font-mono font-bold text-blue-600 tracking-wider truncate" x-text="i.acc_no"></div></div>
                                        </div>
                                        <div class="flex-1 min-w-0 md:pl-4 space-y-2">
                                            <div><div class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">Bank & Branch</div><div class="font-bold text-slate-800 truncate" x-text="i.bank_name"></div><div class="text-xs text-slate-500 truncate" x-text="i.branch || 'Branch N/A'"></div></div>
                                            <div class="flex gap-4">
                                                <div class="min-w-0"><div class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">IFSC</div><div class="font-mono font-bold text-slate-600 truncate text-sm" x-text="i.ifsc"></div></div>
                                                <div x-show="i.upi_id" class="min-w-0"><div class="text-[9px] text-slate-400 uppercase font-bold tracking-wider">UPI</div><div class="font-mono font-bold text-purple-600 truncate text-sm" x-text="i.upi_id"></div></div>
                                            </div>
                                        </div>
                                        <!-- UPI QR (static file or client-painted) -->
                                        <div class="shrink-0 flex flex-col items-center justify-center gap-1.5 md:border-l border-slate-100 md:pl-4"
                                             x-show="i.upi_id || i.qr_image"
                                             x-cloak>
                                            <div class="w-[7.5rem] h-[7.5rem] rounded-xl border-2 border-slate-200 bg-white p-1.5 shadow-sm flex items-center justify-center overflow-hidden"
                                                 :data-bank-id="i.id"
                                                 x-init="$nextTick(() => window.rcPaintBankQr && window.rcPaintBankQr($el, i))">
                                                <template x-if="i.qr_image">
                                                    <img :src="(window.location.origin || '') + '/images/' + String(i.qr_image).replace(/^\/+/, '')"
                                                         :alt="'UPI QR for ' + (i.holder_name || i.upi_id || 'account')"
                                                         class="w-full h-full object-contain"
                                                         width="112" height="112"
                                                         loading="lazy"
                                                         @error="window.rcPaintBankQr && window.rcPaintBankQr($el.parentElement || $el, i, true)">
                                                </template>
                                            </div>
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-600">Scan to pay (UPI)</span>
                                            <span class="text-[10px] font-mono text-purple-700 max-w-[7.5rem] truncate" x-text="i.upi_id" x-show="i.upi_id"></span>
                                        </div>
                                    </div>
                                    <div class="absolute top-3 right-3 flex gap-1.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition z-20">
                                        <button type="button" @click.stop="shareItem(cur, i)" class="ctrl-btn hover:text-green-600" title="Share on WhatsApp" aria-label="Share bank details"><i class="fa-brands fa-whatsapp text-xs"></i></button>
                                        <?php if($isAdmin): ?>
                                        <button type="button" @click.stop="window.openModalEditor(true, cur, i)" class="ctrl-btn hover:text-blue-600" title="Edit" aria-label="Edit bank record"><i class="fa-solid fa-pen text-xs"></i></button>
                                        <button type="button" @click.stop="deleteItem(i.id, cur)" class="ctrl-btn hover:text-red-600" title="Delete" aria-label="Delete bank record"><i class="fa-solid fa-trash text-xs"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    
                    <!-- Generic Table (departments, designations) -->
                    <template x-if="!['bank', 'docs', 'statutory'].includes(cur) && paginatedList.length > 0">
                        <div class="w-full bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="overflow-x-auto w-full">
                                <table class="w-full text-left min-w-full">
                                    <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500 tracking-wider">
                                        <tr>
                                            <th @click="sortBy('id')" class="p-4 w-24 font-bold cursor-pointer hover:bg-slate-100 transition select-none group">
                                                ID/Code <i class="fa-solid ml-1" :class="sortCol==='id' ? (sortAsc?'fa-sort-up text-blue-500':'fa-sort-down text-blue-500') : 'fa-sort opacity-30 group-hover:opacity-60'"></i>
                                            </th>
                                            <th @click="sortBy('name')" class="p-4 font-bold cursor-pointer hover:bg-slate-100 transition select-none group">
                                                Record Name <i class="fa-solid ml-1" :class="sortCol==='name' ? (sortAsc?'fa-sort-up text-blue-500':'fa-sort-down text-blue-500') : 'fa-sort opacity-30 group-hover:opacity-60'"></i>
                                            </th>
                                            <th class="p-4 font-bold">Details</th>
                                            <?php if($isAdmin): ?><th class="p-4 text-right font-bold w-32">Actions</th><?php else: ?><th class="p-4 text-right font-bold w-20">Share</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-sm">
                                        <template x-for="i in paginatedList" :key="i.id">
                                            <tr class="hover:bg-blue-50/30 transition group">
                                                <td class="p-4 font-mono text-xs text-slate-400 whitespace-nowrap" x-text="i.code || i.slug || i.id || '-'"></td>
                                                <td class="p-4 font-bold text-slate-700 whitespace-nowrap" x-text="i.name || i.title || '-'"></td>
                                                <td class="p-4 text-slate-500 truncate max-w-xs">
                                                    <span x-text="i.address || i.city || '-'"></span>
                                                    <template x-if="i.map_url">
                                                        <a :href="i.map_url" target="_blank" class="ml-2 text-blue-500 hover:text-blue-700 font-bold text-xs bg-blue-50 px-2 py-0.5 rounded">
                                                            <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Maps
                                                        </a>
                                                    </template>
                                                </td>
                                                <?php if($isAdmin): ?>
                                                <td class="p-4 text-right cursor-default" @click.stop>
                                                    <div class="flex justify-end gap-1.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition">
                                                        <button @click.stop="shareItem(cur, i)" class="ctrl-btn hover:text-green-600"><i class="fa-brands fa-whatsapp text-xs"></i></button>
                                                        <button @click.stop="window.openModalEditor(true, cur, i)" class="ctrl-btn hover:text-blue-600"><i class="fa-solid fa-pen text-xs"></i></button>
                                                        <button @click.stop="deleteItem(i.id, cur)" class="ctrl-btn hover:text-red-600"><i class="fa-solid fa-trash text-xs"></i></button>
                                                    </div>
                                                </td>
                                                <?php else: ?>
                                                <td class="p-4 text-right cursor-default" @click.stop>
                                                    <div class="flex justify-end gap-1.5 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition">
                                                        <button @click.stop="shareItem(cur, i)" class="ctrl-btn hover:text-green-600"><i class="fa-brands fa-whatsapp text-xs"></i></button>
                                                    </div>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
                <?php } ?>

                <!-- Pagination -->
                <template x-if="!['company','opt','numero','statistics','monitor','terms','cartags','cctv','status','leads','settings','tracking','dispatch','ops','assets','expiry','org','audit','health'].includes(cur) && filteredList.length > perPage">
                    <div class="w-full flex items-center justify-between mt-4 bg-white px-4 py-3 rounded-xl border border-slate-200 shadow-sm text-sm">
                        <div class="text-slate-500 font-medium text-xs">
                            Showing <span class="font-bold text-slate-900" x-text="((page-1)*perPage)+1"></span>–<span class="font-bold text-slate-800" x-text="Math.min(page*perPage, filteredList.length)"></span> of <span class="font-bold text-slate-800" x-text="filteredList.length"></span><span class="ml-2 text-slate-400">· <span class="font-mono" x-text="perPage"></span> / page</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="page--" :disabled="page <= 1" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-slate-600 transition text-xs font-bold">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <span class="font-mono font-bold text-slate-700 text-xs px-1" x-text="page + ' / ' + totalPages"></span>
                            <button @click="page++" :disabled="page >= totalPages" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-slate-600 transition text-xs font-bold">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </main>

    <!-- ═══════════════════════════════════════════════════════════
         MOBILE BOTTOM NAV
    ═══════════════════════════════════════════════════════════════ -->

    <!-- Version changelog modal -->
    <div x-show="showChangelog" x-cloak class="rc-changelog-overlay fixed inset-0 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="rc-changelog-title"
         @keydown.escape.window="showChangelog = false"
         style="overflow: hidden;">
        <div class="absolute inset-0 bg-slate-900/60" @click="showChangelog = false" aria-hidden="true"></div>
        <div class="rc-changelog-modal relative w-full max-w-2xl flex flex-col"
             style="max-height: min(80vh, 720px); min-height: 0; overflow: hidden;"
             @click.stop>
            <div class="rc-cl-head flex items-center justify-between px-5 py-3 shrink-0">
                <h2 id="rc-changelog-title" class="rc-cl-title text-sm font-bold truncate pr-2">
                    Version changelog · v<span x-text="state.context.v"></span>
                </h2>
                <button type="button" class="rc-cl-close w-9 h-9 shrink-0 rounded-full flex items-center justify-center" @click="showChangelog = false" aria-label="Close changelog">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="rc-cl-body flex-1 min-h-0 overflow-y-auto overflow-x-hidden" style="-webkit-overflow-scrolling: touch;">
                <pre class="rc-cl-pre"
                     x-text="state.context.changelog || 'No VERSION file found.'"></pre>
            </div>
            <div class="rc-cl-foot px-5 py-3 text-[11px] shrink-0">
                Released <span x-text="state.context.vdate || '—'"></span> · File: VERSION
            </div>
        </div>
    </div>

    <nav class="bottom-nav md:hidden hide-scrollbar" aria-label="Primary">
        <!-- Four primary tabs + More. Full grouped list opens in a bottom sheet
             so no tab is unreachable on mobile (sidebar is hidden <768px). -->
        <template x-for="n in primaryNav" :key="n.id">
            <a :href="'?tab='+n.id" class="nav-btn" :class="cur===n.id ? 'active' : ''">
                <i :class="n.icon"></i>
                <span x-text="n.label"></span>
            </a>
        </template>
        <button type="button" class="nav-btn" :class="moreNavActive || mobileMore ? 'active' : ''"
                @click="mobileMore = !mobileMore" title="More tabs">
            <i class="fa-solid fa-ellipsis"></i>
            <span>More</span>
        </button>
    </nav>

    <!-- Mobile full navigation sheet -->
    <div class="mobile-more-sheet md:hidden" x-show="mobileMore" x-cloak
         x-transition.opacity @keydown.escape.window="mobileMore = false">
        <div class="mobile-more-backdrop" @click="mobileMore = false"></div>
        <div class="mobile-more-panel" @click.stop data-rc-dialog="mobile-more" role="dialog" aria-modal="true" aria-label="All sections"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">
            <div class="mobile-more-handle"></div>
            <div class="flex items-center justify-between mb-2 px-1">
                <div class="text-sm font-bold text-slate-200">All sections</div>
                <button type="button" class="rc-hit-lg w-11 h-11 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 flex items-center justify-center"
                        @click="mobileMore = false" title="Close" aria-label="Close more sections menu">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <template x-for="g in navGroups" :key="g.title">
                <div class="mobile-more-group">
                    <h4 x-text="g.title"></h4>
                    <template x-for="n in g.items" :key="n.id">
                        <a :href="'?tab='+n.id" class="mobile-more-item" :class="cur===n.id ? 'active' : ''"
                           @click="mobileMore = false">
                            <i :class="n.icon"></i>
                            <span x-text="n.label"></span>
                            <span class="ml-auto text-[10px] font-bold text-slate-500" x-show="counts[n.id]" x-text="counts[n.id]"></span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <?php 
        $modalFile = BASE_PATH . '/app/views/partials/record_editor.php';
        if (file_exists($modalFile)) { require_once $modalFile; } 
    ?>

    <!-- Doc Preview Modal -->
    <div x-cloak>
        <div x-show="docPreviewUrl" 
             class="fixed inset-0 z-[120] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4 md:p-8" 
             @click.self="docPreviewUrl = null" 
             @keydown.escape.window="docPreviewUrl = null">
            <div class="bg-white w-full max-w-5xl h-[85vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden relative" @click.stop>
                <div class="h-14 border-b border-slate-100 flex items-center justify-between px-4 bg-slate-50 shrink-0">
                    <div class="font-bold text-slate-700 flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-eye text-blue-500"></i> Document Preview
                    </div>
                    <div class="flex items-center gap-3">
                        <a :href="docPreviewUrl" target="_blank" download class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-sm transition text-xs font-bold flex items-center gap-2">
                            <i class="fa-solid fa-download"></i> Save File
                        </a>
                        <button type="button" @click="docPreviewUrl = null" class="rc-hit-lg w-11 h-11 rounded-full bg-slate-100 hover:bg-red-100 hover:text-red-600 flex items-center justify-center transition text-slate-500" aria-label="Close document preview">
                            <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-1 bg-slate-100 relative w-full h-full flex items-center justify-center overflow-hidden">
                    <template x-if="isPreviewImage">
                        <div class="w-full h-full p-4 flex items-center justify-center">
                            <img :src="docPreviewUrl" class="max-w-full max-h-full object-contain rounded-lg drop-shadow-md" alt="Preview">
                        </div>
                    </template>
                    <template x-if="!isPreviewImage && docPreviewUrl">
                        <object :data="docPreviewUrl" class="w-full h-full border-none" type="application/pdf">
                            <iframe :src="docPreviewUrl" class="w-full h-full border-none bg-white">
                                <div class="flex flex-col items-center justify-center h-full gap-4 text-slate-500 bg-white">
                                    <i class="fa-solid fa-triangle-exclamation text-4xl"></i>
                                    <p>Preview not available.</p>
                                    <a :href="docPreviewUrl" target="_blank" class="text-blue-500 underline font-bold">Download Instead</a>
                                </div>
                            </iframe>
                        </object>
                    </template>
                </div>
            </div>
        </div>
    </div>

        <!-- dashboardApp: assets/dashboard-app.js -->

<?php $cp = __DIR__ . '/partials/command_palette.php'; if (is_file($cp)) require $cp; ?>
</body>
</html>