<?php
declare(strict_types=1);
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(fn(\Illuminate\Http\Request $request) =>
            \App\Services\Auth::validateCredentials(
                \App\Services\Validation::text($request->input('username'),'Username',254),
                \App\Services\Validation::passwordInput($request->input('password'))
            ));
    }
}
