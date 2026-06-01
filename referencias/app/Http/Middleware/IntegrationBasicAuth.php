<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use App\Services\API\AuthService;
use App\Exceptions\HttpException;
use App\Exceptions\Middleware\AuthenticationException;
use App\Exceptions\Services\AuthService\AuthorizationException;


class IntegrationBasicAuth
{

    public function handle($request, Closure $next)
    {
        try {
            if (!$this->hasHeaderBasicAuth($request)) {
                throw new AuthenticationException('user_not_authenticated', 403);
            }
            if ($this->isEmptyUserOrPassword($request)) {
                throw new AuthorizationException('invalid_user_or_password', 401);
            }

            $username = Str::of($request->getUser())->trim();
            $password = Str::of($request->getPassword())->trim();
            $loggedUser = resolve(AuthService::class)->loginUserBasicAuth([
                'username' => $username, 'password' => $password
            ]);

            $request->merge(['user' => $loggedUser]);
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode());
        }
        return $next($request);
    }


    private function hasHeaderBasicAuth($request): Bool
    {
        return Str::startsWith($request->header('Authorization'), 'Basic');
    }


    private function isEmptyUserOrPassword($request): Bool
    {
        return (
            Str::of($request->getUser())->trim()->isEmpty() ||
            Str::of($request->getPassword())->trim()->isEmpty()
        );
    }

}
