<?php

namespace App\Http\Middleware;

use Closure;
use DateTime;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Exceptions\Middleware\AuthenticationException;


class ValidateAuthCookieWeb
{

    public function handle($request, Closure $next)
    {
        $landedUri = $request->path();
        $rememberToken = $request->cookie(config('auth.remember_token_cookie_name'));
        $loginExpirationDateStr = $request->cookie(config('auth.expiration_date_cookie_name'));
        if (!$rememberToken || !$loginExpirationDateStr) {
            return redirect(RouteServiceProvider::LOGIN)->withCookie(
                cookie('landedUri', $landedUri, 5, null, null, false, false)
            );
        }

        $dateNow = new DateTime('now');
        $expirationDate = new DateTime($loginExpirationDateStr);
        if (!$expirationDate || ($dateNow > $expirationDate)) {
            return redirect(RouteServiceProvider::LOGIN)->withCookie(
                cookie('landedUri', $landedUri, 5, null, null, false, false)
            );
        }
        
        return $next($request);
    }

}
