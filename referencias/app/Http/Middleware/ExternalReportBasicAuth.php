<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;


class ExternalReportBasicAuth
{

    public function handle(Request $request, Closure $next)
    {
        $expectedUser = 'Evereportes';
        $expectedPass = '12ReportesEve34#';

        $auth = $request->header('Authorization');

        if ($auth && Str::startsWith($auth, 'Basic ')) {
            $decoded = base64_decode(substr($auth, 6));
            [$user, $pass] = array_pad(explode(':', $decoded, 2), 2, '');

            // Comparación constante para evitar timing attacks
            $okUser = hash_equals((string) $expectedUser, (string) $user);
            $okPass = hash_equals((string) $expectedPass, (string) $pass);

            if ($okUser && $okPass) {
                return $next($request);
            }
        }

        // Hace que el navegador muestre el prompt de usuario/clave
        return response('Unauthorized', 401)->header('WWW-Authenticate', 'Basic realm="Godixital Reports"');
    }

}
