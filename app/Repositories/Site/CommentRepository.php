<?php

namespace App\Repositories\Site;

use App\Models\Show;
use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommentRepository
{
    public function listByShow(Show $show, array $options = []): LengthAwarePaginator {

        $showId = $show->id;
        $page = $options['page'] ?? 1;
        $limit = $options['limit'] ?? 3;
        $sort = $options['sort'] ?? 'desc';

        return Comment::query()
            ->select(['id', 'show_id', 'name', 'rating', 'comment', 'created_at'])
            ->where('show_id', $showId)
            ->where('status', 'visible')
            ->orderBy('created_at', $sort)
            ->orderBy('id', $sort)
            ->paginate($limit, ['*'], 'page', $page)
        ;
    }

    public function getCommentsCountAndAverageRatingByShow(Show $show): array
    {
        $showId = $show->id;

        $summary = Comment::query()
            ->where('show_id', $showId)
            ->where('status', 'visible')
            ->selectRaw('COUNT(*) as count, AVG(rating) as average_rating')
            ->first()
        ;

        $count = (int) ($summary?->count ?? 0);
        $averageRating = $summary?->average_rating === null ? null : (float) $summary->average_rating;

        return [
            'count' => $count,
            'average_rating' => $averageRating,
        ];
    }
}
