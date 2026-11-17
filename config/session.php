<?php
$shop = require __DIR__ . '/shop.php';
$secure = str_starts_with($shop['url'],'https://');
return [
    'driver'=>'file','lifetime'=>(int)ceil($shop['session_idle_seconds']/60),'expire_on_close'=>true,
    'encrypt'=>true,'files'=>storage_path('framework/sessions'),'connection'=>null,'table'=>'sessions',
    'store'=>null,'lottery'=>[2,100],'cookie'=>$secure?'__Host-theatre_laravel':'theatre_laravel_dev',
    'path'=>'/','domain'=>null,'secure'=>$secure,'http_only'=>true,'same_site'=>'strict','partitioned'=>false,
];
