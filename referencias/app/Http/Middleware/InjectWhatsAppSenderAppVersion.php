<?php

namespace App\Http\Middleware;

use Closure;
use DateTime;
use App\Models\User;
use App\Exceptions\Middleware\WhatsAppSenderExtensionException;


class InjectWhatsAppSenderAppVersion
{

    public function handle($request, Closure $next)
    {
        $appVersion = (int) $request->header('app-version');
        if (!$appVersion) {
            // throw new WhatsAppSenderExtensionException('missing_whatsapp_sender_app_version_header', 401);
        }
        $request->merge(['whatsAppSenderAppVersion' => $appVersion]);

        return $next($request);
    }

}
