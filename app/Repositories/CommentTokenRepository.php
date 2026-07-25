<?php

namespace App\Repositories;

use App\Models\CommentToken;

class CommentTokenRepository
{
    public function store(array $attrs): CommentToken
    {
        return CommentToken::create($attrs);
    }

    public function findByHash(string $tokenHash, bool $lockForUpdate = false): ?CommentToken
    {
        return CommentToken::query()
            ->with(['buyer', 'show'])
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->where('token_hash', $tokenHash)
            ->first();
    }

    public function revokeActive(int $buyerId, int $showId): void
    {
        CommentToken::query()
            ->where('buyer_id', $buyerId)
            ->where('show_id', $showId)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function markUsed(CommentToken $commentToken): CommentToken
    {
        $commentToken->update(['used_at' => now()]);
        return $commentToken->fresh();
    }
}
