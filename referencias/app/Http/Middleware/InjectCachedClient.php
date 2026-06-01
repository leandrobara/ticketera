<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;


class InjectCachedClient
{

    public function handle($request, Closure $next)
    {
        try {
            $subdomain = base64_decode($request->input('state'));
            $client = Cache::store('redis')->get('FB_' . $subdomain);
            if (!$client) {
                Log::alert(
                    'Facebook inject cached client: not found in cache',
                    ['subdomain' => $subdomain],
                    ['request' => $request->toArray()]
                );
            }
            $request->merge(['client' => $client]);
        } catch (Exception $e) {
            Log::alert(
                'Inject cached client: Unexpected error',
                [
                    'message' => $e->getMessage(),
                    'stacktrace' => $e->getTraceAsString()
                ]
            );
        }
        return $next($request);
    }

}
