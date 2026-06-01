<?php

namespace App\Http\Middleware;

use Closure;
use DateTime;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Exceptions\Middleware\AuthenticationException;


class InjectUserWeb
{

    public function handle($req, Closure $next)
    {
        $landedUri = $req->path();
        $rememberToken = $req->cookie(config('auth.remember_token_cookie_name'));
        $user = User::where('remember_token', $rememberToken)->first();
        if (!$user) {
            return redirect(RouteServiceProvider::LOGIN)->withCookie(
                cookie('landedUri', $landedUri, 5, null, null, false, false)
            );
        }
        $req->merge(['user' => $user]);
        return $next($req);
    }

}
