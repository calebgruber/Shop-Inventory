<?php
declare(strict_types=1);

$defaults = [
    'name' => env('APP_NAME','Theatre Shop'),
    'url' => env('APP_URL','https://shop.example.edu'),
    'environment' => env('APP_ENV','production'),
    'timezone' => 'UTC',
    'database' => [
        'host' => env('DB_HOST','localhost'), 'port' => env('DB_PORT',3306), 'name' => env('DB_DATABASE','theatre_shop'),
        'username' => env('DB_USERNAME',''), 'password' => env('DB_PASSWORD',''),
    ],
    'mail_from' => env('MAIL_FROM_ADDRESS','shop@example.edu'),
    'session_idle_seconds' => 1800,
    'session_absolute_seconds' => 28800,
];

return is_file(__DIR__ . '/local.php')
    ? array_replace_recursive($defaults,require __DIR__ . '/local.php')
    : $defaults;
