<?php
declare(strict_types=1);
namespace App\Http\Middleware;

use App\Services\Config;
use App\Middleware\Security;
use Closure;
use Illuminate\Http\Request;

final class ShopSecurity
{
    public function handle(Request $request,Closure $next): \Symfony\Component\HttpFoundation\Response
    {
        if (Config::get('environment')==='production' &&
            (!str_starts_with((string)Config::get('url'),'https://') || !$request->isSecure())) {
            throw new \App\Services\HttpException(400,'HTTPS is required. Open the shop using its configured secure URL.');
        }
        if (!is_file(ROOT . '/config/local.php') && !env('DB_USERNAME') && !$request->is('setup')) {
            return redirect('/setup');
        }
        Security::start();
        $response = $next($request);
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'");
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Referrer-Policy','same-origin');
        $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cache-Control','no-store, private');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security','max-age=31536000');
        }
        return $response;
    }
}
