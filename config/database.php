<?php

$shop = require __DIR__ . '/shop.php';
$db = $shop['database'];

return [
    'default'=>'mysql',
    'connections'=>[
        'mysql'=>[
            'driver'=>'mysql','host'=>$db['host'],'port'=>$db['port'],'database'=>$db['name'],
            'username'=>$db['username'],'password'=>$db['password'],'unix_socket'=>env('DB_SOCKET',''),
            'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true,
            'timezone'=>'+00:00','engine'=>'InnoDB',
            'options'=>[PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>10],
        ],
    ],
    'migrations'=>['table'=>'laravel_migrations','update_date_on_publish'=>true],
];
