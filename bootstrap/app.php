<?php
declare(strict_types=1);

use App\Http\Middleware\ShopSecurity;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\VerifyShopCsrfToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

defined('ROOT') || define('ROOT',dirname(__DIR__));
ini_set('zend.exception_ignore_args','1');

return Application::configure(basePath: ROOT)
    ->withRouting(web: ROOT . '/routes/web.php',commands: ROOT . '/routes/console.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove([
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);
        $middleware->web(
            append: [ShopSecurity::class],
            replace: [\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class=>VerifyShopCsrfToken::class]
        );
        $middleware->alias(['shop.permission'=>RequirePermission::class]);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Services\HttpException $error,Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['error'=>$error->getMessage()],$error->status);
            }
            if ($error->status===401) {
                return redirect('/login');
            }
            return response()->view('error',['message'=>$error->getMessage(),'status'=>$error->status],$error->status);
        });
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $error,Request $request) {
            $message = 'Your session token expired. Refresh and try again.';
            return $request->expectsJson()
                ? response()->json(['error'=>$message],419)
                : response()->view('error',['message'=>$message,'status'=>419],419);
        });
        $exceptions->render(function (\JsonException $error) {
            return response()->json(['error'=>'Invalid JSON request.'],422);
        });
        $exceptions->render(function (\PDOException $error,Request $request) {
            if (($error->errorInfo[1] ?? null)!==1062) {
                return null;
            }
            $message = 'A record with this unique identifier already exists.';
            return $request->expectsJson()
                ? response()->json(['error'=>$message],409)
                : response()->view('error',['message'=>$message,'status'=>409],409);
        });
    })->create();
