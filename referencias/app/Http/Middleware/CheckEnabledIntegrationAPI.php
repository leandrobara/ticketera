<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Models\Client;
use App\Exceptions\Services\AuthService\AuthorizationException;


class CheckEnabledIntegrationAPI
{

    public function handle($request, Closure $next)
    {
        if (!$request->client->clientSettings->enable_integration_api) {
            throw new AuthorizationException('integration_api_is_not_enabled_for_client');
        }
        return $next($request);
    }

}
