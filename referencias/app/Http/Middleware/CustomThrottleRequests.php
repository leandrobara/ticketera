<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;


class CustomThrottleRequests extends ThrottleRequests
{

    //@Override
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        try {
            $result = parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
            return $result;
        } catch (\Exception $exception) {
            report($exception);
            return $next($request);
        }
    }

}
