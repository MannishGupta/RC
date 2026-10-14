<?php  
declare(strict_types=1);

if (!defined('BASE_PATH') || !class_exists('AppNumeroEngine')) exit;

if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
}

$_CTRL = str_replace('\\', '/', __DIR__) . '/controller';

 
require $_CTRL . '/audit.php';

 
require $_CTRL . '/ajax.php';

 
require $_CTRL . '/wacard.php';

 
require $_CTRL . '/profile.php';

 
require $_CTRL . '/transit.php';

 
require $_CTRL . '/meta.php';

 
require $_CTRL . '/mobile_vars.php';
