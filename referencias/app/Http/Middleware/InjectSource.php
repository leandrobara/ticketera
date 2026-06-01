<?php

namespace App\Http\Middleware;

use Closure;


class InjectSource
{

    public function handle($request, Closure $next)
    {
        $source = [
            'userAgent' => null,
            'isFromZapier' => false
        ];

        $userAgent = $request->header('user-agent');
        $source['userAgent'] = $userAgent;
        if ($userAgent && $userAgent == 'Zapier') {
            $source['isFromZapier'] = true;
        }

        $request->merge(['source' => $source]);

        return $next($request);
    }

}
