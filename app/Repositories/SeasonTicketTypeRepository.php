<?php

namespace App\Repositories;

use App\Models\Presentation;
use App\Models\SeasonTicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SeasonTicketTypeRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return SeasonTicketType::query()
            ->with(['season.show', 'season.venue'])
            ->withCount([
                'tickets as sold_tickets_count' => function ($query) use ($filters) {
                    $query->where('presentation_id', $filters['presentation_id'] ?? 0);
                    $query->whereIn('status', ['VALID', 'USED']);
                },
            ])
            ->when($filters['season_id'] ?? null, fn ($query, $id) => $query->where('season_id', $id))
            ->when($filters['presentation_id'] ?? null, function ($query, int $presentationId) {
                $query->where('season_id', Presentation::findOrFail($presentationId)->season_id);
            })
            ->when($filters['show_id'] ?? null, function ($query, int $showId) {
                $query->whereHas('season', fn ($query) => $query->where('show_id', $showId));
            })
            ->when(array_key_exists('is_active', $filters), function ($query) use ($filters) {
                $query->where('is_active', $filters['is_active']);
            })
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? $limit);
    }

    public function findAndLockOrFail(int $ticketTypeId): SeasonTicketType
    {
        return SeasonTicketType::query()
            ->lockForUpdate()
            ->findOrFail($ticketTypeId);
    }

    public function getOne(SeasonTicketType $seasonTicketType): SeasonTicketType
    {
        return $seasonTicketType->load(['season.show', 'season.venue']);
    }

    public function store(array $attrs): SeasonTicketType
    {
        $ticketType = SeasonTicketType::create($attrs);

        return $ticketType->load(['season.show', 'season.venue']);
    }

    public function update(SeasonTicketType $seasonTicketType, array $attrs): SeasonTicketType
    {
        $seasonTicketType->update($attrs);

        return $seasonTicketType->fresh(['season.show', 'season.venue']);
    }

    public function delete(SeasonTicketType $seasonTicketType): void
    {
        $seasonTicketType->delete();
    }
}
