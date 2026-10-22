<?php
declare(strict_types=1);

/*
 * Example only. Copy to a private config.php if your host supports it.
 * The current deployment can also read compatible settings from private
 * confirm.php. Never commit either file or production credentials.
 */
return [
    'db_host' => 'localhost',
    'db_name' => 'pluschat',
    'db_user' => 'Admin',
    'db_password' => '',
    'mail_from' => 'register@xn--80avljg2a1c.xn--p1ai',
    'app_base_url' => 'https://xn--80avljg2a1c.xn--p1ai',
    'allowed_origins' => [
        'https://xn--80avljg2a1c.xn--p1ai',
    ],
    // Optional: keep the GIPHY key private on the host.
    'giphy_api_key' => '',
    // Optional but recommended: set a long random application secret.
    // If absent, the current implementation derives its 2FA key from the DB password.
    'app_secret' => '',
];
