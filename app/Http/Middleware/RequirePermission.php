<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class RequirePermission
{
    public function handle(Request $request,Closure $next,string $permission): \Symfony\Component\HttpFoundation\Response
    {
        \App\Middleware\Permissions::require($permission);
        return $next($request);
    }
}
