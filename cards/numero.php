<?php  
declare(strict_types=1);

if (!defined('BASE_PATH')) exit;

 
 
 
 
 

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
