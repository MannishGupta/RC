<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPCACHE_RESET_OK";
} else {
    echo "NO_OPCACHE";
}
@unlink(__FILE__);
?>
