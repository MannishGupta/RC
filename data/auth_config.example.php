<?php
/**
 * auth_config.example.php — copy to data/auth_config.php (or tenants/{id}/data/).
 * Generate hashes: php -r "echo password_hash('YOUR_SECRET', PASSWORD_DEFAULT), PHP_EOL;"
 * Rotate after every install. Never commit real auth_config.php.
 */
if (!defined('BASE_PATH')) {
    exit('No direct script access');
}
return [
    'super_admin' => '$2y$10$REPLACE_WITH_password_hash______________________.',
    'admin'       => '$2y$10$REPLACE_WITH_password_hash______________________.',
    'public'      => '$2y$10$REPLACE_WITH_password_hash______________________.',
    'crm'         => '$2y$10$REPLACE_WITH_password_hash______________________.',
];
