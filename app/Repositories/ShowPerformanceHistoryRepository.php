<?php

namespace App\Repositories;

use App\Models\ShowPerformanceHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowPerformanceHistoryRepository
{
    public function listPaginated(array $filters, int $limit = 100): LengthAwarePaginator
    {
        return ShowPerformanceHistory::query()
            ->with('show')
            ->when($filters['show_id'] ?? null, fn ($query, int $showId) => $query->where('show_id', $showId))
            ->orderBy('sort_order')
            ->orderByDesc('year')
            ->orderBy('id')
            ->paginate($limit);
    }

    public function getOne(ShowPerformanceHistory $history): ShowPerformanceHistory
    {
        return $history->load('show');
    }

    public function store(array $attributes): ShowPerformanceHistory
    {
        return ShowPerformanceHistory::create($attributes)->load('show');
    }

    public function update(ShowPerformanceHistory $history, array $attributes): ShowPerformanceHistory
    {
        $history->update($attributes);

        return $this->getOne($history->fresh());
    }

    public function delete(ShowPerformanceHistory $history): void
    {
        $history->delete();
    }
}
