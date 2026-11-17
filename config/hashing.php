<?php
return [
    'driver'=>'argon2id','rehash_on_login'=>true,
    'argon'=>['memory'=>65536,'threads'=>1,'time'=>4,'verify'=>true],
];
