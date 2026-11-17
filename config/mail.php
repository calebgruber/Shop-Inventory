<?php
$shop = require __DIR__ . '/shop.php';
return [
    'default'=>env('MAIL_MAILER','sendmail'),
    'mailers'=>[
        'sendmail'=>['transport'=>'sendmail','path'=>env('MAIL_SENDMAIL_PATH','/usr/sbin/sendmail -bs -i')],
        'smtp'=>[
            'transport'=>'smtp','scheme'=>env('MAIL_SCHEME'),'host'=>env('MAIL_HOST','localhost'),
            'port'=>env('MAIL_PORT',587),'username'=>env('MAIL_USERNAME'),'password'=>env('MAIL_PASSWORD'),'timeout'=>15,
        ],
        'log'=>['transport'=>'log'],
        'array'=>['transport'=>'array'],
    ],
    'from'=>['address'=>$shop['mail_from'],'name'=>$shop['name']],
];
