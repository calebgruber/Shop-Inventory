<?php
declare(strict_types=1);

return [
    'name' => 'Theatre Shop',
    'url' => 'https://shop.example.edu',
    'environment' => 'production',
    'timezone' => 'UTC',
    'database' => [
        'host' => 'localhost', 'port' => 3306, 'name' => 'theatre_shop',
        'username' => '', 'password' => '',
    ],
    'mail_from' => 'shop@example.edu',
    'session_idle_seconds' => 1800,
    'session_absolute_seconds' => 28800,
];
