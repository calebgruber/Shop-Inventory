<?php
declare(strict_types=1);

define('ROOT', __DIR__);
ini_set('zend.exception_ignore_args', '1');
require ROOT . '/vendor/autoload.php';
$config = require ROOT . '/config/app.php';
if (is_file(ROOT . '/config/local.php')) {
    $config = array_replace_recursive($config, require ROOT . '/config/local.php');
}
App\Services\Config::set($config);
date_default_timezone_set($config['timezone']);
require ROOT . '/app/helpers.php';
