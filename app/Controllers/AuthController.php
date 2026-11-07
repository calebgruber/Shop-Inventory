<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\Auth;
use App\Services\Passkeys;
use App\Services\Validation;
use App\Services\Settings;

final class AuthController
{
    public function login(): void
    {
        if (Auth::user()) {
            \redirect('/dashboard');
        }
        \view('auth/login', ['title'=>'Sign in'], false);
    }

    public function password(): void
    {
        $data = \input();
        Auth::login(Validation::text($data['username'] ?? '', 'Username', 254), Validation::passwordInput($data['password'] ?? ''));
        \json_response(['redirect'=>'/dashboard']);
    }

    public function forgot(): void
    {
        \view('auth/reset', ['reset'=>false], false);
    }

    public function requestReset(): void
    {
        $data = \input();
        $email = Validation::text($data['email'] ?? '', 'Email', 254);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \App\Services\HttpException(422, 'Enter a valid email address.');
        }
        Auth::requestReset($email);
        \json_response(['message'=>'If an active account matches, a reset link will be sent to its email address.']);
    }

    public function resetForm(): void
    {
        \view('auth/reset', ['reset'=>true], false);
    }

    public function reset(): void
    {
        $data = \input();
        Auth::reset(Validation::text($data['token'] ?? '', 'Reset token', 64), Validation::passwordInput($data['password'] ?? ''));
        \json_response(['redirect'=>'/login']);
    }

    public function passkeyBegin(bool $register): void
    {
        \json_response(Passkeys::begin($register));
    }

    public function passkeyFinish(bool $register): void
    {
        Passkeys::finish(\input(), $register);
        \json_response($register ? ['message'=>'Passkey registered.'] : ['redirect'=>'/dashboard']);
    }
}
