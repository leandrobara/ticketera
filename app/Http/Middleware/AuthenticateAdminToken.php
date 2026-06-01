<?php

namespace App\Http\Middleware;

use App\Models\AdminAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdminToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (!$plainToken) {
            return $this->unauthenticatedResponse();
        }

        $accessToken = AdminAccessToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first()
        ;

        if (!$accessToken || $accessToken->user->role !== 'admin') {
            return $this->unauthenticatedResponse();
        }

        if (! $accessToken->expires_at || $accessToken->expires_at->isPast()) {
            $accessToken->delete();

            return $this->unauthenticatedResponse();
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $accessToken->user);
        $request->attributes->set('admin_access_token', $accessToken);

        return $next($request);
    }

    private function unauthenticatedResponse(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 401,
                'message' => 'Unauthenticated.',
            ],
        ], 401);
    }
}
