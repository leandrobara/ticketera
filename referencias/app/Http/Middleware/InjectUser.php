<?php

namespace App\Http\Middleware;

use Closure;
use DateTime;
use App\Models\User;
use App\Services\API\UserService;
use App\Repositories\UserRepository;
use App\Exceptions\Middleware\AuthenticationException;


class InjectUser
{

    public function handle($req, Closure $next)
    {
        $jwtPayload = $req->jwtPayload['api_token'];
        
        // No usar services en middlewares (pueden inicializar cosas que asumen que middlewares ya finalizaron)
        $user = resolve(UserRepository::class)->findOneByClientAndAPIToken($req->client, $jwtPayload);
        if (!$user) {
            throw new AuthenticationException('token_user_not_found');
        }
        if ((new DateTime()) > $user->api_token_expiration_date) {
            throw new AuthenticationException('api_token_expired');
        }
        $req->merge(['user' => $user]);

        return $next($req);
    }

}
