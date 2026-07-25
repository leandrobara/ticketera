<?php

namespace App\Http\Requests\Site\Concerns;

use App\Models\Comment;
use App\Models\CommentToken;
use App\Models\Order;

trait ValidatesCommentToken
{
    protected function validateCommentToken($validator): void
    {
        $commentToken = CommentToken::query()
            ->with('buyer')
            ->where('token_hash', hash('sha256', (string) $this->route('token')))
            ->first();

        if (!$commentToken) {
            $validator->errors()->add('token', 'invalid_comment_token');
            return;
        }

        if ($commentToken->used_at || $commentToken->revoked_at) {
            $validator->errors()->add('token', 'comment_token_not_available');
            return;
        }

        if ($commentToken->expires_at->isPast()) {
            $validator->errors()->add('token', 'comment_token_expired');
            return;
        }

        if (!$commentToken->buyer) {
            $validator->errors()->add('token', 'comment_buyer_not_available');
            return;
        }

        $hasApprovedOrder = Order::query()
            ->where('buyer_id', $commentToken->buyer_id)
            ->where('show_id', $commentToken->show_id)
            ->where('status', 'APPROVED')
            ->exists();

        if (!$hasApprovedOrder) {
            $validator->errors()->add('token', 'comment_order_not_approved');
            return;
        }

        $commentCount = Comment::withTrashed()
            ->where('buyer_id', $commentToken->buyer_id)
            ->where('show_id', $commentToken->show_id)
            ->count();

        if ($commentCount >= (int) config('comments.max_per_buyer_show')) {
            $validator->errors()->add('token', 'comment_limit_reached');
        }
    }
}
