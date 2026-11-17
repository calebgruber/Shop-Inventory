<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
$parameters = match ($argv[1] ?? '') {
    'create-admin'=>['command'=>'shop:create-admin','username'=>$argv[2] ?? '','email'=>$argv[3] ?? ''],
    'cleanup'=>['command'=>'shop:cleanup'],
    default=>['command'=>'list'],
};
$input = new Symfony\Component\Console\Input\ArrayInput($parameters);
exit(app(Illuminate\Contracts\Console\Kernel::class)->handle($input,new Symfony\Component\Console\Output\ConsoleOutput()));
