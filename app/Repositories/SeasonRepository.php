<?php

namespace App\Repositories;

use App\Models\Season;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SeasonRepository
{
    public function getIdsByShowId(int $showId): array
    {
        return Season::query()
            ->where('show_id', $showId)
            ->pluck('id')
            ->all();
    }

    public function getIdsByVenueId(int $venueId): array
    {
        return Season::query()
            ->where('venue_id', $venueId)
            ->pluck('id')
            ->all();
    }

    public function getShowIdById(int $seasonId): ?int
    {
        return Season::query()
            ->whereKey($seasonId)
            ->value('show_id');
    }

    public function getShowIdsByVenueId(int $venueId): array
    {
        return Season::query()
            ->where('venue_id', $venueId)
            ->pluck('show_id')
            ->unique()
            ->values()
            ->all();
    }

    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Season::query()
            ->with(['show', 'venue'])
            ->withCount('presentations')
            ->when($filters['show_id'] ?? null, fn ($query, int $showId) => $query->where('show_id', $showId))
            ->when($filters['venue_id'] ?? null, fn ($query, int $venueId) => $query->where('venue_id', $venueId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('show', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('venue', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate($limit);
    }

    public function getOne(Season $season): Season
    {
        return $season->load(['show', 'venue', 'presentations']);
    }

    public function store(array $attributes): Season
    {
        return Season::create($attributes)->load(['show', 'venue']);
    }

    public function update(Season $season, array $attributes): Season
    {
        $season->update($attributes);

        return $season->fresh(['show', 'venue']);
    }

    public function delete(Season $season): void
    {
        $season->delete();
    }
}
