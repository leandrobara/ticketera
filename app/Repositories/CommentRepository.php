<?php

namespace App\Repositories;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentRepository
{
    public function countForBuyerShow(int $buyerId, int $showId): int
    {
        return Comment::withTrashed()
            ->where('buyer_id', $buyerId)
            ->where('show_id', $showId)
            ->count();
    }

    public function store(array $attrs): Comment
    {
        return Comment::create($attrs)->load(['show']);
    }

    public function listAdmin(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Comment::query()
            ->with([
                'buyer:id,name,last_name,email,phone,dni',
                'order:id,code,status',
                'show:id,title,slug',
            ])
            ->when($filters['show_id'] ?? null, fn ($query, int $showId) => $query->where('show_id', $showId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('comment', 'like', "%{$search}%")
                        ->orWhereHas('buyer', function ($query) use ($search) {
                            $query
                                ->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('order', fn ($query) => $query->where('code', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($limit);
    }

    public function update(Comment $comment, array $attrs): Comment
    {
        $comment->update($attrs);
        return $comment->fresh(['buyer', 'order', 'show']);
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }
}
