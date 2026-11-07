<?php
declare(strict_types=1);

ini_set('display_errors','0');
ini_set('log_errors','1');
try {
    require dirname(__DIR__) . '/bootstrap.php';
    App\Middleware\Security::start();
    if (!is_file(ROOT . '/config/local.php') &&
        (parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/') !== '/setup') {
        redirect('/setup');
    }
    $router = new App\Services\Router();
    require ROOT . '/routes/web.php';
    $router->dispatch();
} catch (Throwable $error) {
    if (!function_exists('view')) {
        error_log('Theatre Shop bootstrap failed: ' . $error->getMessage());
        http_response_code(503);
        echo 'The shop is not configured. Install the PHP dependencies and configure the application before opening it.';
        exit;
    }
    $status = $error instanceof App\Services\HttpException ? $error->status : 500;
    if ($error instanceof JsonException) {
        $status = 422;
    }
    if ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062) {
        $status = 409;
    }
    if ($status === 500) {
        $reference = bin2hex(random_bytes(6));
        error_log("[$reference] " . $error);
        file_put_contents(ROOT . '/storage/logs/application.log', date('c') . " [$reference] " . $error . "\n", FILE_APPEND | LOCK_EX);
        $message = "The request could not be completed. Reference: $reference. Contact your shop administrator.";
    } else {
        $message = match (true) {
            $error instanceof JsonException => 'Invalid JSON request.',
            $status === 409 => 'A record with this unique identifier already exists. Refresh and use a different identifier.',
            default => $error->getMessage(),
        };
    }
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        json_response(['error' => $message], $status);
    }
    if ($status === 401) {
        redirect('/login');
    }
    http_response_code($status);
    view('error', compact('message', 'status'), false);
}
