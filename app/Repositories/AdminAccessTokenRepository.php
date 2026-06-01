<?php

namespace App\Repositories;

use App\Models\AdminAccessToken;
use App\Models\User;

class AdminAccessTokenRepository
{
    public function createForUser(User $user, string $plainToken): AdminAccessToken
    {
        return AdminAccessToken::create([
            'user_id' => $user->id,
            'name' => 'admin',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(config('auth.admin_access_tokens.ttl_minutes', 480)),
        ]);
    }

    public function delete(?AdminAccessToken $accessToken): void
    {
        $accessToken?->delete();
    }
}
