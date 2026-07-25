<?php

namespace App\Repositories;

use App\Models\ShowLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShowLinkRepository
{
    public function listPaginated(array $filters, int $limit = 100): LengthAwarePaginator
    {
        return ShowLink::query()
            ->with('show')
            ->when($filters['show_id'] ?? null, fn ($query, int $showId) => $query->where('show_id', $showId))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($limit);
    }

    public function getOne(ShowLink $showLink): ShowLink
    {
        return $showLink->load('show');
    }

    public function store(array $attributes): ShowLink
    {
        return ShowLink::create($attributes)->load('show');
    }

    public function update(ShowLink $showLink, array $attributes): ShowLink
    {
        $showLink->update($attributes);

        return $this->getOne($showLink->fresh());
    }

    public function delete(ShowLink $showLink): void
    {
        $showLink->delete();
    }
}
