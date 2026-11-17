<?php
declare(strict_types=1);
namespace App\Http\Middleware;

final class VerifyShopCsrfToken extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken
{
    protected $addHttpCookie = false;

    protected function getTokenFromRequest($request)
    {
        return $request->input('_csrf') ?: parent::getTokenFromRequest($request);
    }
}
