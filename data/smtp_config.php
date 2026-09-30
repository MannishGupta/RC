<?php
// data/smtp_config.php — Version: 260916.14
//
// Outbound email (lead notifications, compliance alerts). DISABLED by
// default. Edit via the Settings tab, or by hand — this file is a plain
// array return with no dynamic parts, safe to edit directly over FTP.
//
// Common providers:
//   Gmail / Workspace : smtp.gmail.com,      587, tls  (needs an App Password,
//                        not your normal password, if 2FA is on)
//   Outlook / M365     : smtp.office365.com,  587, tls
//   SendGrid           : smtp.sendgrid.net,   587, tls  (username is literally
//                        "apikey", password is your API key)
//   Zoho Mail          : smtp.zoho.com,       587, tls

if (!defined('BASE_PATH')) exit('No direct script access');

return [
    'enabled'    => false,
    'host'       => '',
    'port'       => 587,
    'encryption' => 'tls',        // 'tls' | 'ssl'
    'username'   => '',
    'password'   => '',
    'from_email' => '',
    'from_name'  => '',
];
