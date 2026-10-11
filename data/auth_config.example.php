<?php
// Copy to data/auth_config.php (or tenants/{id}/data/auth_config.php) and set hashes via first-run setup.
// Do NOT commit real auth_config.php.
if (!defined('BASE_PATH')) {
    exit('No direct script access');
}
return [
    // 'super_admin' => password_hash('…', PASSWORD_DEFAULT),
    // 'admin'       => password_hash('…', PASSWORD_DEFAULT),
    // 'public'      => password_hash('…', PASSWORD_DEFAULT),
];
