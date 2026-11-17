<?php

$shop = require __DIR__ . '/shop.php';

return [
    'name'=>$shop['name'],
    'env'=>$shop['environment'],
    'debug'=>(bool)env('APP_DEBUG',false),
    'url'=>$shop['url'],
    'timezone'=>'UTC',
    'locale'=>'en',
    'fallback_locale'=>'en',
    'faker_locale'=>'en_US',
    'key'=>env('APP_KEY') ?: \App\Services\InstallationKey::get(),
    'cipher'=>'AES-256-CBC',
    'maintenance'=>['driver'=>'file'],
];
