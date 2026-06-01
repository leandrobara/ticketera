<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Models\Client;
use App\Exceptions\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Exceptions\Services\AuthService\AuthorizationException;


class CheckEnabledWhatsAppSender
{

    public function handle($request, Closure $next)
    {
        $clientSettings = $request->client->clientSettings;
        if (!$clientSettings->enable_whatsapp_sender_extension) {
            throw new AuthorizationException('whatsapp_sender_extension_is_not_enabled');
        };
        return $next($request);
    }

}
