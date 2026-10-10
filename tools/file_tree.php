<?php // Version: 260916.14

/**
 * Directory Tree Generator
 *
 * CHANGELOG v1.4: previously had ZERO authentication — anyone requesting
 * this URL could see your entire directory structure, including data
 * filenames like team.json / banking.json. Now requires an active
 * admin/crm dashboard session.
 */

$rootPath = file_exists(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);
define('BASE_PATH_TREE', $rootPath);

if (file_exists($rootPath . '/app/bootstrap.php')) {
    define('BASE_PATH', $rootPath);
    require_once BASE_PATH . '/app/tenant_bootstrap.php';
    // BUG FIX: defined DATA_PATH directly from BASE_PATH, bypassing tenant
    if (!defined('DATA_PATH')) define('DATA_PATH', BASE_PATH . '/data');
    if (!defined('SESSION_PATH')) define('SESSION_PATH', DATA_PATH . '/sessions');
    if (!file_exists(SESSION_PATH)) @mkdir(SESSION_PATH, 0755, true);
    require_once $rootPath . '/app/bootstrap.php';

    if (session_status() === PHP_SESSION_NONE) AppAuth::initSession();
    if (empty($_SESSION['user']) || ($_SESSION['user'] !== 'admin' && $_SESSION['user'] !== 'crm')) {
        http_response_code(403);
        die("<div style='padding:20px;font-family:sans-serif;color:red;font-weight:bold;'>Access Denied. Please log in via the main dashboard.</div>");
    }
} else {
    // app/bootstrap.php not found alongside this script — fail closed rather than open.
    http_response_code(403);
    die("<div style='padding:20px;font-family:sans-serif;color:red;font-weight:bold;'>Access Denied.</div>");
}

class DirectoryTreeGenerator {
    private array $ignoredDirectories = [
        '.git',
        'node_modules',
        'vendor',
        '.idea',
        '.vscode',
        '__pycache__'
    ];

    private array $ignoredFiles = [
        '.DS_Store'
    ];

    private array $ignoredPrefixes = [];

    public function __construct(array $customIgnoredDirs = [], array $customIgnoredFiles = [], bool $ignoreSessions = true) {
        if (!empty($customIgnoredDirs)) {
            $this->ignoredDirectories = array_merge($this->ignoredDirectories, $customIgnoredDirs);
        }
        if (!empty($customIgnoredFiles)) {
            $this->ignoredFiles = array_merge($this->ignoredFiles, $customIgnoredFiles);
        }
        if ($ignoreSessions) {
            $this->ignoredPrefixes[] = 'sess_';
        }
    }

    private function formatSize(int $bytes): string {
        if ($bytes === 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        $power = max(0, min($power, count($units) - 1));
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    public function generateTree(string $dir): string {
        if (!is_dir($dir)) {
            return "Error: Directory does not exist or is not accessible.\n";
        }

        $rootName = basename((string)realpath($dir));
        if (empty($rootName) || $rootName === '/' || $rootName === '\\') {
            $rootName = (string)realpath($dir);
        }

        $output = "| Structure | Type | Size |" . PHP_EOL;
        $output .= "| :--- | :--- | :--- |" . PHP_EOL;
        $output .= "| 📁 " . $rootName . " | Directory | - |" . PHP_EOL;

        $output .= $this->buildTree($dir, '');

        return $output;
    }

    private function buildTree(string $dir, string $prefix): string {
        $output = '';
        $files = @scandir($dir);

        if ($files === false) {
            return "| {$prefix}└── [Access Denied] | Error | - |" . PHP_EOL;
        }

        $filteredFiles = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($fullPath)) {
                if (in_array($file, $this->ignoredDirectories, true)) {
                    continue;
                }
            } else {
                if (in_array($file, $this->ignoredFiles, true)) {
                    continue;
                }

                $skipPrefix = false;
                foreach ($this->ignoredPrefixes as $ignoredPrefix) {
                    if (str_starts_with($file, $ignoredPrefix)) {
                        $skipPrefix = true;
                        break;
                    }
                }
                if ($skipPrefix) {
                    continue;
                }
            }

            $filteredFiles[] = $file;
        }

        natcasesort($filteredFiles);
        $filteredFiles = array_values($filteredFiles);

        $totalFiles = count($filteredFiles);

        foreach ($filteredFiles as $index => $file) {
            $isLast = ($index === $totalFiles - 1);
            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;

            $pointer = $isLast ? '└── ' : '├── ';

            if (is_dir($fullPath)) {
                $icon = '📁 ';
                $type = 'Directory';
                $size = '-';

                $output .= "| {$prefix}{$pointer}{$icon}{$file} | {$type} | {$size} |" . PHP_EOL;

                $extension = $isLast ? '    ' : '│   ';
                $output .= $this->buildTree($fullPath, $prefix . $extension);
            } else {
                $icon = '📄 ';
                $type = 'File';
                $sizeBytes = (int)@filesize($fullPath);
                $size = $this->formatSize($sizeBytes);

                $output .= "| {$prefix}{$pointer}{$icon}{$file} | {$type} | {$size} |" . PHP_EOL;
            }
        }

        return $output;
    }
}

$ignoreSessions = isset($_GET['ignore_sess']) ? $_GET['ignore_sess'] === '1' : true;

$baseDirectory = __DIR__;
$thisFileName = basename(__FILE__);

$treeGenerator = new DirectoryTreeGenerator([], [$thisFileName], $ignoreSessions);
$treeOutput = $treeGenerator->generateTree($baseDirectory);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Resource Centre file tree — restricted operator tool.">
<meta name="robots" content="noindex,nofollow">
<title>Directory Tree Generator</title>
    <style>
        :root {
            --bg-color: #f3f4f6;
            --container-bg: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --code-bg: #1e1e1e;
            --code-text: #d4d4d4;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
        }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            padding: 2rem;
            line-height: 1.5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: var(--container-bg);
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        h1 { font-size: 1.75rem; margin: 0; color: var(--text-main); }
        .action-group { display: flex; gap: 0.75rem; }
        .btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn:hover { background-color: var(--accent-hover); }
        .btn:active { transform: scale(0.98); }
        .btn-toggle { background-color: <?php echo $ignoreSessions ? 'var(--success)' : 'var(--warning)'; ?>; }
        .btn-toggle:hover { filter: brightness(0.9); }
        .btn.copied { background-color: var(--success); }
        .meta-info {
            background-color: #f8fafc;
            border: 1px solid var(--border-color);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        .meta-info strong { color: var(--accent); }
        .meta-note { display: block; margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-muted); }
        .tree-display {
            background-color: var(--code-bg);
            color: var(--code-text);
            padding: 1.5rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            font-family: "Consolas", "Monaco", "Courier New", monospace;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
            white-space: pre;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Directory Tree Viewer</h1>
            <div class="action-group">
                <button id="toggleSessBtn" class="btn btn-toggle">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"></path>
                    </svg>
                    <?php echo $ignoreSessions ? 'Filter ON (sess_* hidden)' : 'Filter OFF (sess_* visible)'; ?>
                </button>
                <button id="copyBtn" class="btn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    Copy as Text
                </button>
            </div>
        </header>

        <div class="meta-info">
            <div>Target Directory: <strong><?php echo htmlspecialchars($baseDirectory); ?></strong></div>
            <span class="meta-note">
                Note: Dependency folders (e.g., node_modules) and the generator script itself are always filtered out.
            </span>
        </div>

        <pre id="treeOutput" class="tree-display"><?php echo htmlspecialchars($treeOutput); ?></pre>
    </div>

    <script>
        document.getElementById('toggleSessBtn').addEventListener('click', function() {
            const currentUrl = new URL(window.location.href);
            const isCurrentlyIgnoring = <?php echo $ignoreSessions ? 'true' : 'false'; ?>;
            currentUrl.searchParams.set('ignore_sess', isCurrentlyIgnoring ? '0' : '1');
            window.location.href = currentUrl.toString();
        });

        document.getElementById('copyBtn').addEventListener('click', function() {
            const treeText = document.getElementById('treeOutput').textContent;
            const btn = this;
            navigator.clipboard.writeText(treeText).then(function() {
                const originalText = btn.innerHTML;
                btn.classList.add('copied');
                btn.innerHTML = `
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Copied!
                `;
                setTimeout(function() {
                    btn.classList.remove('copied');
                    btn.innerHTML = originalText;
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                alert('Failed to copy text. Please try selecting and copying manually.');
            });
        });
    </script>
</body>
</html>
