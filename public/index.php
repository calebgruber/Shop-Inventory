<?php
declare(strict_types=1);
define('LARAVEL_START',microtime(true));
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handleRequest(Illuminate\Http\Request::capture());
