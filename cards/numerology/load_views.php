<?php  

if (!defined('BASE_PATH') || !isset($report, $lang, $p, $core)) exit;

 
 
 
$_TMPL = str_replace('\\', '/', __DIR__) . '/views';

 
require $_TMPL . '/head.php';

 
require $_TMPL . '/actionbar.php';

 
require $_TMPL . '/profile_header.php';
?>

    <div class="p-4 md:p-6 space-y-6">

<?php
 
require $_TMPL . '/tab_executive.php';
require $_TMPL . '/tab_timeline.php';
require $_TMPL . '/tab_forecast.php';
require $_TMPL . '/tab_structural.php';

 
require $_TMPL . '/tab_corrections.php';      
require $_TMPL . '/tab_mobile_matrix.php';    
require $_TMPL . '/tab_personality.php';
require $_TMPL . '/tab_remedies.php';
require $_TMPL . '/tab_compatibility.php';
require $_TMPL . '/tab_muhurat.php';
require $_TMPL . '/tab_vastu.php';
?>

    </div><!-- /p-4 md:p-6 space-y-6 -->

<?php
 
require $_TMPL . '/footer_ui.php';
?>
