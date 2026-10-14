<?php  
declare(strict_types=1);

if (!defined('BASE_PATH')) exit;

$_ENGINE = str_replace('\\', '/', __DIR__) . '/engine';

 
require_once $_ENGINE . '/core.php';

 
require_once $_ENGINE . '/grid.php';

 
require_once $_ENGINE . '/compat.php';

 
require_once $_ENGINE . '/compound.php';

 
require_once $_ENGINE . '/mobile_engine.php';

 
require_once $_ENGINE . '/scoring.php';

 
require_once $_ENGINE . '/derived.php';

 
require_once $_ENGINE . '/extended.php';

 
require_once $_ENGINE . '/chaldean_mobile.php';

 
require_once $_ENGINE . '/dasha.php';
