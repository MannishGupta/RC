<?php

declare(strict_types=1);

return [

    

    'brand' => [
        'company'      => 'Organization',   
        'company_url'  => '',
        'project'      => 'Project',
        'dashboard'    => 'Official Construction Progress Dashboard',
        'report_title' => 'Official Progress Report',
        'locality'     => '',
    ],

    

    'site' => [
        'base_url'      => null,
        'allowed_hosts' => [
             
             
        ],
        'cookie_prefix' => 'rc',    
    ],

    

    'locale' => [
        'lang'            => 'en-IN',
        'timezone'        => 'Asia/Kolkata',
        'date_store'      => 'd-M-Y',       
        'date_display'    => 'd M Y',       
        'datetime_display'=> 'd M Y, g:i A',
        'currency'        => 'INR',
        'currency_symbol' => '₹',
        'phone_cc'        => '+91',
    ],

    

    'feed' => [
        'per_component' => 12,
        'max_total'     => 400,
    ],

    

    'admin' => [
        'hash'            => getenv('ADMIN_HASH')
                             ?: '$2y$10$vyFc6KJajkm4sEwUf8jN9e.DhnZKbNRtoXyMJWaMum51/WhOw3VB6',  
        'idle_timeout'    => 28800,    
        'login_attempts'  => 5,
        'login_lockout'   => 900,      
    ],

    

    'analytics' => [
        'enabled'        => true,
        'cap'            => 10000,   
        'rate_per_min'   => 60,      
    ],

    

    'seo' => [
        'canonical_base'   => null,   
        'indexable'        => true,      
        'twitter_site'     => '',
        'allow_ai_crawlers'=> true,      
        'og_image'         => 'assets/og.jpg',
    ],

    

    'integration' => [
         
        'tenant_data'      => true,

        'module_id'        => 'status',
        'module_label'     => 'Construction Status',
        'module_group'     => 'Projects',
        'respect_registry' => true,
        'inherit_shell'    => true,
    ],

    

    'footer' => [
        'developer'      => 'Arthsathi Limited',   
        'developer_url'  => 'https://arthsathi.com/',
        'show_version'   => true,
        'support_email'  => '',
    ],

    

    'dpdp' => [
        'require_consent'  => true,
        'retention_days'   => 180,
        'fiduciary_name'   => '',   
        'fiduciary_email'  => '',
        'fiduciary_address'=> '',
        'grievance_officer'=> 'Grievance Officer',
        'grievance_email'  => '',
        'grievance_phone'  => '+91 00000 00000',
        'notice_updated'   => '11 Oct 2026',
    ],

    

    'theme' => [  
        'mode' => 'auto',
    ],

    

    'reminder' => [
        'smtp_host'  => getenv('SMTP_HOST')     ?: '',
        'smtp_user'  => getenv('SMTP_USER')     ?: '',
        'smtp_pass'  => getenv('SMTP_PASSWORD') ?: '',
        'smtp_port'  => (int)(getenv('SMTP_PORT') ?: 587),
        'to_email'   => getenv('ADMIN_EMAIL')   ?: '',
        'to_name'    => getenv('ADMIN_NAME')    ?: '',
        'every_days' => 13,
        'web_token'  => getenv('REMINDER_TOKEN') ?: '',
    ],
];
