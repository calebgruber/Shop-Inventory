<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/bootstrap.php';
$command = in_array('--status',$argv,true) ? 'migrate:status' : 'migrate';
exit(Illuminate\Support\Facades\Artisan::call($command,$command==='migrate'?['--force'=>true]:[],new Symfony\Component\Console\Output\ConsoleOutput()));
