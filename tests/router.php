<?php
declare(strict_types=1);
// Development only. Apache .htaccess performs these checks in production.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
if (str_contains($path,'..') || str_ends_with($path,'.php')) {
    http_response_code(404);
    exit;
}
if (str_starts_with($path,'/assets/') && is_file(dirname(__DIR__) . '/public' . $path)) {
    return false;
}
require dirname(__DIR__) . '/public/index.php';
