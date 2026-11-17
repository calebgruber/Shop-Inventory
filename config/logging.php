<?php
return [
    'default'=>'single',
    'channels'=>[
        'single'=>['driver'=>'single','path'=>storage_path('logs/laravel.log'),'level'=>'info','replace_placeholders'=>true],
        'stderr'=>['driver'=>'monolog','handler'=>Monolog\Handler\StreamHandler::class,'with'=>['stream'=>'php://stderr']],
    ],
];
