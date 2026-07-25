<?php

namespace App\Repositories\Site;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentRepository
{
    public function listPublic(
        int $showId,
        int $page,
        int $limit,
        string $sort,
    ): LengthAwarePaginator {
        return Comment::query()
            ->select(['id', 'show_id', 'name', 'rating', 'comment', 'created_at'])
            ->where('show_id', $showId)
            ->where('status', 'visible')
            ->orderBy('created_at', $sort)
            ->orderBy('id', $sort)
            ->paginate($limit, ['*'], 'page', $page);
    }

    public function getPublicSummary(int $showId): array
    {
        $summary = Comment::query()
            ->where('show_id', $showId)
            ->where('status', 'visible')
            ->selectRaw('COUNT(*) as count, AVG(rating) as average_rating')
            ->first();

        return [
            'count' => (int) ($summary?->count ?? 0),
            'average_rating' => $summary?->average_rating === null
                ? null
                : (float) $summary->average_rating,
        ];
    }
}
