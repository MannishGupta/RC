<?php
declare(strict_types=1);
/**
 * Super Admin — per-tenant module matrix API.
 * GET  → catalogue + current config + write probe
 * POST → save modules.json for active tenant under DATA_PATH/config/
 * Version: 20261002.07
 *
 * CRITICAL: Must use AppAuth::initSession() so session name/path match the
 * dashboard (RCSESSID / RCSESS_{tenant}). A bare session_start() reads a
 * different cookie → "Super Admin required" while the UI is clearly logged in.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('BASE_PATH', dirname(__FILE__));
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (is_file(BASE_PATH . '/app/bootstrap.php')) {
    require_once BASE_PATH . '/app/bootstrap.php';
}
if (is_file(BASE_PATH . '/app/ModuleRegistry.php')) {
    require_once BASE_PATH . '/app/ModuleRegistry.php';
}

// Same session cookie as index.php / dashboard
if (class_exists('AppAuth')) {
    AppAuth::initSession();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/**
 * Super Admin — same conventions as index.php / dashboard.
 */
$isSuper = false;
$user = (string)($_SESSION['user'] ?? '');
$true = (string)($_SESSION['true_role'] ?? '');
$role = (string)($_SESSION['role'] ?? '');

if ($user === 'super_admin' || $true === 'super_admin' || $role === 'super_admin') {
    $isSuper = true;
}
// Original password role preserved when switched to Company Admin / Visitor
if ($true === 'super_admin') {
    $isSuper = true;
}
if (!empty($_SESSION['is_super']) || !empty($_SESSION['isSuperAdmin'])) {
    $isSuper = true;
}

if (!$isSuper) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Super Admin required',
        'hint' => 'Sign in with Super Admin (blsbls), open Monitor on this same host, then Save again. If you used role-switch to Company Admin, switch back to Super-Admin first.',
        'debug' => [
            'session_name' => session_name(),
            'user' => $user !== '' ? $user : null,
            'true_role' => $true !== '' ? $true : null,
            'tenant' => defined('TENANT_ID') ? TENANT_ID : null,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!class_exists('ModuleRegistry')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'ModuleRegistry missing']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $path = ModuleRegistry::configPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    echo json_encode([
        'ok' => true,
        'tenant' => defined('TENANT_ID') ? TENANT_ID : null,
        'data_path' => defined('DATA_PATH') ? DATA_PATH : null,
        'config_path' => $path,
        'config_dir_writable' => is_dir($dir) ? is_writable($dir) : false,
        'catalogue' => ModuleRegistry::catalogue(),
        'config' => ModuleRegistry::load(),
        'client' => ModuleRegistry::clientPayload(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode((string)$raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $modules = $body['modules'] ?? null;
    if (!is_array($modules)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'modules object required']);
        exit;
    }

    $cfg = ModuleRegistry::defaults();
    foreach ($cfg['modules'] as $id => $def) {
        if (!isset($modules[$id]) || !is_array($modules[$id])) {
            continue;
        }
        $en = $modules[$id]['enabled'] ?? false;
        if (is_string($en)) {
            $en = filter_var($en, FILTER_VALIDATE_BOOLEAN);
        } else {
            $en = (bool) $en;
        }
        $cfg['modules'][$id]['enabled'] = $en;
        $feats = is_array($modules[$id]['features'] ?? null) ? $modules[$id]['features'] : [];
        foreach ($def['features'] as $fid => $_) {
            if (array_key_exists($fid, $feats)) {
                $fv = $feats[$fid];
                $cfg['modules'][$id]['features'][$fid] = is_string($fv)
                    ? (bool) filter_var($fv, FILTER_VALIDATE_BOOLEAN)
                    : (bool) $fv;
            }
        }
    }

    $path = ModuleRegistry::configPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (is_dir($dir) && !is_writable($dir)) {
        @chmod($dir, 0775);
    }

    $ok = ModuleRegistry::save($cfg);
    if (!$ok) {
        http_response_code(500);
        $writable = is_dir($dir) && is_writable($dir);
        echo json_encode([
            'ok' => false,
            'error' => 'Could not write module matrix',
            'config_path' => $path,
            'config_dir' => $dir,
            'dir_writable' => $writable,
            'hint' => $writable
                ? 'Directory is writable but file write failed — check disk space and open_basedir.'
                : 'Grant write access on tenant data/config (chmod 775 or IIS Modify ACL). Path: ' . $dir,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'tenant' => defined('TENANT_ID') ? TENANT_ID : null,
        'config_path' => $path,
        'config' => ModuleRegistry::load(),
        'client' => ModuleRegistry::clientPayload(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
