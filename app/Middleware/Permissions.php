<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Services\Auth;
use App\Services\HttpException;

final class Permissions
{
    public const ROLES = [
        'Super Admin' => ['*'],
        'Shop Manager' => ['shows.view','shows.edit','orders.edit','orders.approve','inventory.view','inventory.edit','pulls.edit','returns.edit','transfers.edit','repairs.edit','reports.view','users.view'],
        'Inventory Manager' => ['shows.view','inventory.view','inventory.edit','pulls.edit','returns.edit','transfers.edit','repairs.edit','reports.view'],
        'Shop Staff' => ['shows.view','inventory.view','pulls.edit','returns.edit','repairs.edit','reports.view'],
        'Read Only' => ['shows.view','inventory.view','reports.view'],
    ];

    public static function allows(string $permission): bool
    {
        $user = Auth::user();
        if ($permission === 'authenticated') {
            return $user !== null;
        }
        $permissions = self::ROLES[$user['role'] ?? ''] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public static function require(string $permission): void
    {
        if (!Auth::user()) {
            throw new HttpException(401, 'Sign in to continue.');
        }
        if (!self::allows($permission)) {
            throw new HttpException(403, 'You do not have permission for this action.');
        }
    }
}
