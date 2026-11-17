<?php
declare(strict_types=1);
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once ROOT . '/app/helpers.php';
    }

    public function boot(): void
    {
        foreach (array_unique(array_merge(...array_values(\App\Middleware\Permissions::ROLES))) as $permission) {
            if ($permission!=='*') {
                Gate::define($permission,fn(\App\Models\User $user) => $user->active &&
                    (in_array('*',\App\Middleware\Permissions::ROLES[$user->role] ?? [],true) ||
                    in_array($permission,\App\Middleware\Permissions::ROLES[$user->role] ?? [],true)));
            }
        }
        Gate::before(fn(\App\Models\User $user) => $user->active && $user->role==='Super Admin' ? true : null);
        \Livewire\Livewire::addPersistentMiddleware([\App\Http\Middleware\ShopSecurity::class]);
    }
}
