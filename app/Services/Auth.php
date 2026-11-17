<?php
declare(strict_types=1);
namespace App\Services;

use App\Models\Database as DB;
use App\Middleware\Security;

final class Auth
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$S0pNMVZoaVJ4VnZLQ2pIdA$siLsk+kexNCOfWzIyrCmKo2KIuhJw7ALPQZjm3lxVDI';

    public static function user(): ?array
    {
        $id = \Illuminate\Support\Facades\Auth::id();
        if (!$id) {
            return null;
        }
        $user = DB::one('SELECT * FROM users WHERE id=? AND active=1',[$id]);
        if (!$user || (int)$user['auth_version']!==(int)session('auth_version')) {
            self::logout(false);
            return null;
        }
        return $user;
    }

    public static function throttle(string $identifier): void
    {
        $buckets = [
            hash('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli')),
            hash('sha256', 'account:' . mb_strtolower($identifier)),
        ];
        DB::transaction(function () use ($buckets): void {
            foreach ($buckets as $bucket) {
                DB::execute('INSERT INTO login_attempts(bucket) VALUES (?)', [$bucket]);
            }
        });
        foreach ($buckets as $bucket) {
            $count = DB::one('SELECT COUNT(*) AS total FROM login_attempts WHERE bucket=? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)', [$bucket]);
            if ((int) $count['total'] > 20) {
                throw new HttpException(429, 'Too many authentication attempts. Try again in 15 minutes.');
            }
        }
    }

    public static function login(string $username, string $password): void
    {
        $request = \Illuminate\Http\Request::create('/login','POST',compact('username','password'));
        $request->setLaravelSession(session()->driver());
        app(\Illuminate\Pipeline\Pipeline::class)->send($request)->through([
            \Laravel\Fortify\Actions\AttemptToAuthenticate::class,
            \Laravel\Fortify\Actions\PrepareAuthenticatedSession::class,
        ])->then(function (): void {
            self::recordAuthentication(\Illuminate\Support\Facades\Auth::user()->toArray());
        });
    }

    public static function validateCredentials(string $username,string $password): \App\Models\User
    {
        self::throttle($username);
        $id = DB::one('SELECT id FROM users WHERE username=? OR email=?', [$username, $username]);
        $user = null;
        $valid = DB::transaction(function () use ($id, $password, &$user): bool {
            $user = $id ? DB::one('SELECT * FROM users WHERE id=? FOR UPDATE', [$id['id']]) : null;
            $verified = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);
            $locked = $user && $user['locked_until'] && strtotime($user['locked_until'] . ' UTC') > time();
            if (!$user || !$user['active'] || $locked || !$verified) {
                if ($user && !$locked) {
                    DB::execute("UPDATE users SET failed_logins=failed_logins+1, locked_until=IF(failed_logins>=5,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),NULL) WHERE id=?", [$user['id']]);
                }
                Audit::record('login.failed', 'user', (int) ($user['id'] ?? 0));
                return false;
            }
            DB::execute('UPDATE users SET failed_logins=0,locked_until=NULL WHERE id=?', [$user['id']]);
            if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
                DB::execute('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_ARGON2ID), $user['id']]);
            }
            return true;
        });
        if (!$valid) {
            throw new HttpException(422, 'Unable to sign in with these credentials. The account may be temporarily locked.');
        }
        return \App\Models\User::findOrFail($user['id']);
    }

    public static function establish(array $user): void
    {
        session()->invalidate();
        \Illuminate\Support\Facades\Auth::login(\App\Models\User::findOrFail($user['id']));
        Security::rotate();
        self::recordAuthentication($user);
    }

    private static function recordAuthentication(array $user): void
    {
        session()->put([
            'user_id' => $user['id'], 'auth_version' => $user['auth_version'],
            'authenticated_at' => time(), 'last_seen' => time(), 'rotated_at' => time(),
        ]);
        session()->regenerateToken();
        try {
            Audit::record('login.success', 'user', (int) $user['id']);
        } catch (\Throwable $error) {
            self::logout(false);
            throw $error;
        }
    }

    public static function logout(bool $audit = true): void
    {
        try {
            if ($audit && session()->has('user_id')) {
                Audit::record('logout', 'user', (int)session('user_id'));
            }
        } finally {
            \Illuminate\Support\Facades\Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }
    }

    public static function password(string $password): string
    {
        if (strlen($password) < 12 || strlen($password) > 1024) {
            throw new HttpException(422, 'Passwords must be between 12 and 1024 characters.');
        }
        return \Illuminate\Support\Facades\Hash::make($password);
    }

    public static function uniqueIdentifiers(string $username,string $email,?int $exclude = null): void
    {
        foreach (DB::all('SELECT id,username,email FROM users ORDER BY id FOR UPDATE') as $user) {
            if ((int)$user['id']===$exclude) {
                continue;
            }
            $identifiers = array_map('mb_strtolower',[$user['username'],$user['email']]);
            if (in_array(mb_strtolower($username),$identifiers,true) || in_array(mb_strtolower($email),$identifiers,true)) {
                throw new HttpException(409,'This username or email conflicts with an existing account identifier.');
            }
        }
    }

    public static function requestReset(string $email): void
    {
        self::throttle('reset:' . $email);
        $user = DB::one('SELECT * FROM users WHERE email=? AND active=1', [$email]);
        if (!$user) {
            Audit::record('password_reset.requested', 'user', 0);
            return;
        }
        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($user, $token): void {
            DB::execute('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL', [$user['id']]);
            DB::execute('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))', [$user['id'],hash('sha256',$token)]);
            Audit::record('password_reset.requested', 'user', (int) $user['id']);
        });
        $url = rtrim(Config::get('url'), '/') . '/reset-password?token=' . $token;
        Mail::passwordReset($user['email'], $user['name'], $url);
    }

    public static function reset(string $token, string $password): void
    {
        $hash = self::password($password);
        DB::transaction(function () use ($token, $hash): void {
            $reset = DB::one('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE', [hash('sha256',$token)]);
            if (!$reset) {
                throw new HttpException(422, 'This password reset link has expired or has already been used.');
            }
            DB::execute('UPDATE users SET password_hash=?,auth_version=auth_version+1,failed_logins=0,locked_until=NULL WHERE id=?', [$hash,$reset['user_id']]);
            DB::execute('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL', [$reset['user_id']]);
            Audit::record('password_reset.completed', 'user', (int) $reset['user_id']);
        });
    }
}
