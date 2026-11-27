<?php
// Copy this file to app/config.php on the server and fill it in.
// app/config.php is ignored by git and blocked from the web. Never commit it.
return [
    'base_url' => 'https://call4counsel.com',
    'debug' => false,
    'timezone' => 'UTC',

    // MySQL on Hostinger: create the database in hPanel, then use host 127.0.0.1.
    'db_driver' => 'mysql',
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'u000000000_c4c',
    'db_user' => 'u000000000_c4c',
    'db_pass' => 'CHANGE-ME',

    // A long random string. Generate one with: php -r "echo bin2hex(random_bytes(32));"
    'app_key' => 'CHANGE-ME',

    // Needed once for /install. Remove it (or set it to '') after installing.
    'install_key' => 'CHANGE-ME',

    // Email. The From address must be a mailbox that exists on your domain.
    'mail_from' => 'noreply@call4counsel.com',
    'mail_from_name' => 'Call4Counsel website',
    // Recommended: send through the mailbox over SMTP for reliable delivery.
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_secure' => 'ssl',
    'smtp_user' => 'noreply@call4counsel.com',
    'smtp_pass' => 'CHANGE-ME',
];
