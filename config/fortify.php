<?php
return [
    'guard'=>'web','middleware'=>['web'],'auth_middleware'=>'auth',
    'passwords'=>'users','username'=>'username','email'=>'email','lowercase_usernames'=>false,
    'home'=>'/dashboard','prefix'=>'','domain'=>null,'views'=>false,'features'=>[],
    'limiters'=>['login'=>'login','two-factor'=>'two-factor'],
];
