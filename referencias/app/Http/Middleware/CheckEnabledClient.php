<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Models\Client;
use App\Exceptions\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Exceptions\Services\AuthService\AuthorizationException;


class CheckEnabledClient
{

    public function handle($request, Closure $next)
    {
        $isSuperUser = $request->jwtPayload['is_super_user'] ?? false;
        if (!$request->client->enabled && !$isSuperUser) {
            throw new AuthorizationException('client_is_disabled');
        };
        return $next($request);
    }

}
