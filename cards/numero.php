<?php // Version: 260916.14
declare(strict_types=1);
/**
 * numero.php — Arthsathi Vedic Numero Card V2027.400
 *
 * Thin bootstrap. Dependency order:
 *
 *   MobileRecommendationEngine.php  — Root suggestion logic
 *   MobileEvaluationEngine.php      — PHP-side mobile scoring (mirrors JS engine)
 *   NumerologyValidator.php         — Report integrity validator
 *   num_engine.php                  — Engine loader  → engine/*.php classes
 *   numero_helpers.php              — Template helper functions
 *   num_controller.php              — Controller loader → controller/*.php vars
 *   num_template.php                — Template orchestrator → tmpl/*.php partials
 *
 * V2027.400:
 *   - Added MobileEvaluationEngine to the dependency map (was missing, causing
 *     ChaldeaMobileEngine->validate() score parity issues in some edge calls).
 *   - Windows-safe paths via str_replace('\\', '/') instead of DIRECTORY_SEPARATOR.
 */
if (!defined('BASE_PATH')) exit;

// Diagnostic capture that briefly lived here (260916.01/02) is superseded
// by a proper, permanent, app-wide fatal-error handler in index.php
// (260916.10) — every page gets this coverage now, not just this one, and
// it correctly logs full detail server-side without ever printing it to a
// visitor. See index.php's register_shutdown_function() for the real fix.

$_d = str_replace('\\', '/', rtrim(__DIR__, '/\\'));

$_required = [
    'mobile_rec'  => $_d . '/numerology/engine/MobileRecommendationEngine.php',
    'validator'   => $_d . '/numerology/engine/NumerologyValidator.php',
    'engine'      => $_d . '/numerology/load_engine.php',
    'helpers'     => $_d . '/numerology/helpers.php',
    'controller'  => $_d . '/numerology/load_controller.php',
    'template'    => $_d . '/numerology/load_views.php',
];
$_optional = [
    'mobile_eval' => $_d . '/numerology/engine/MobileEvaluationEngine.php',
];

foreach ($_required as $_label => $_path) {
    if (!is_file($_path)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Resource Centre setup error: missing '{$_label}'.\n";
        echo "Expected: {$_path}\n";
        echo "Upload the full cards/numerology/ tree from the RC package and reload.\n";
        exit;
    }
}
foreach ($_optional as $_label => $_path) {
    if (!is_file($_path)) {
        if (class_exists('AppLog')) {
            AppLog::error('Numerology optional engine missing', ['file' => $_path, 'label' => $_label]);
        }
    } else {
        require_once $_path;
    }
}
unset($_label, $_path);

require_once $_required['mobile_rec'];
require_once $_required['validator'];
require_once $_required['engine'];
require_once $_required['helpers'];
require      $_required['controller'];
require      $_required['template'];
