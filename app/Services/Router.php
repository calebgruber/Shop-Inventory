<?php
declare(strict_types=1);
namespace App\Services;

use App\Middleware\Permissions;
use App\Middleware\Security;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler, ?string $permission = null): void
    {
        $pattern = preg_replace('/\{[a-z_]+\}/', '([0-9]+)', $path);
        $this->routes[] = [$method, '#^' . $pattern . '$#D', $handler, $permission];
    }

    public function dispatch(): void
    {
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
        foreach ($this->routes as [$method, $pattern, $handler, $permission]) {
            if ($method !== $_SERVER['REQUEST_METHOD'] || !preg_match($pattern, $path, $matches)) {
                continue;
            }
            if ($permission !== null) {
                Permissions::require($permission);
            }
            if ($method !== 'GET') {
                Security::verify();
            }
            array_shift($matches);
            $handler(...array_map('intval', $matches));
            return;
        }
        throw new HttpException(404, 'This page could not be found.');
    }
}
