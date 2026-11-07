<?php
declare(strict_types=1);

// Copy to local.php outside public/ and use your cPanel database credentials.
return [
    'url' => 'https://shop.your-theatre.edu',
    'database' => [
        'host' => 'localhost', 'port' => 3306, 'name' => 'cpaneluser_shop',
        'username' => 'cpaneluser_shop', 'password' => 'CHANGE_ME',
    ],
    'mail_from' => 'shop@your-theatre.edu',
];
