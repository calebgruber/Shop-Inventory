<?php
declare(strict_types=1);
namespace App\Middleware;

use App\Services\Config;
use App\Services\HttpException;

final class Security
{
    public static function start(): void
    {
        $secure = str_starts_with((string) Config::get('url'), 'https://');
        if (Config::get('environment') === 'production' && !$secure) {
            throw new \RuntimeException('Production requires an HTTPS application URL.');
        }
        if (Config::get('environment') === 'production' && ($_SERVER['HTTPS'] ?? '') !== 'on') {
            throw new HttpException(400, 'HTTPS is required. Open the shop using its configured secure URL.');
        }
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; object-src 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-store, private');
        if ($secure) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($secure ? '__Host-theatre' : 'theatre_dev');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
        if (!session_start()) {
            throw new \RuntimeException('Secure session storage is unavailable.');
        }
        $now = time();
        if (isset($_SESSION['user_id']) &&
            ($now - ($_SESSION['last_seen'] ?? 0) > Config::get('session_idle_seconds') ||
                $now - ($_SESSION['authenticated_at'] ?? 0) > Config::get('session_absolute_seconds'))) {
            $_SESSION = [];
            self::rotate();
        }
        if ($now - ($_SESSION['rotated_at'] ?? 0) > 900) {
            self::rotate();
        }
        $_SESSION['last_seen'] = $now;
        self::token();
    }

    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function rotate(): void
    {
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Secure session rotation failed.');
        }
        $_SESSION['rotated_at'] = time();
    }

    public static function verify(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
        if (!is_string($token) || !hash_equals(self::token(), $token)) {
            throw new HttpException(419, 'Your session token expired. Refresh and try again.');
        }
    }
}
