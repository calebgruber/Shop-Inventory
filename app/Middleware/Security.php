<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Services\Config;
use App\Services\HttpException;
use App\Services\Auth;

final class Security
{
    public static function start(): void
    {
        if (!session()->isStarted()) {
            session()->start();
        }
        $now = time();
        if (session()->has('user_id') &&
            ($now-session('last_seen',0)>Config::get('session_idle_seconds') ||
                $now-session('authenticated_at',0)>Config::get('session_absolute_seconds'))) {
            Auth::logout(false);
        }
        if ($now-session('rotated_at',0)>900) {
            self::rotate();
        }
        if (!request()->is('livewire/update') && request()->header('X-Shop-Live-Refresh')!=='1') {
            session()->put('last_seen',$now);
        }
        self::token();
        if (session()->has('user_id')) {
            Auth::user();
        }
    }

    public static function token(): string
    {
        if (!session()->has('_token')) {
            session()->regenerateToken();
        }
        return session()->token();
    }

    public static function rotate(): void
    {
        if (!session()->migrate(true)) {
            throw new \RuntimeException('Secure session rotation failed.');
        }
        session()->put('rotated_at',time());
    }

    public static function verify(): void
    {
        $token = request()->header('X-CSRF-TOKEN') ?? request()->input('_csrf') ?? request()->input('_token','');
        if (!is_string($token) || !hash_equals(self::token(),$token)) {
            throw new HttpException(419,'Your session token expired. Refresh and try again.');
        }
    }
}
