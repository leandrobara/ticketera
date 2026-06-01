<?php

namespace App\Http\Middleware;

use Closure;
use App\Exceptions\Middleware\AuthenticationException;


class ValidateAuthCookie
{

    public function handle($request, Closure $next)
    {
        $env = config('app.env');
        $envIsNotProd = $env !== 'production' && $env !== 'prod';
        if ($envIsNotProd && $request->input('hasNotHttpOnlyCookie')) {
            return $next($request);
        }

        if (!isset($request->jwtPayload['hasNotHttpOnlyCookie'])) {
            if ($request->user->remember_token !== $request->cookie(config('auth.remember_token_cookie_name'))) {
                throw new AuthenticationException();
            }
        }
        return $next($request);
    }

}
