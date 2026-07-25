<?php

namespace App\Repositories;

use App\Models\PresentationTicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PresentationTicketTypeRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return PresentationTicketType::query()
            ->with(['presentation.season.show', 'presentation.season.venue'])
            ->withCount([
                'tickets as sold_tickets_count' => function ($query) {
                    $query->whereIn('status', ['VALID', 'USED']);
                },
            ])
            ->when($filters['presentation_id'] ?? null, function ($query, int $presentationId) {
                $query->where('presentation_id', $presentationId);
            })
            ->when($filters['show_id'] ?? null, function ($query, int $showId) {
                $query->whereHas('presentation.season', fn ($query) => $query->where('show_id', $showId));
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

    public function getOne(PresentationTicketType $presentationTicketType): PresentationTicketType
    {
        return $presentationTicketType->load(['presentation.season.show', 'presentation.season.venue']);
    }

    public function store(array $attrs): PresentationTicketType
    {
        $ticketType = PresentationTicketType::create($attrs);
        return $ticketType->load(['presentation.season.show', 'presentation.season.venue']);
    }

    public function update(PresentationTicketType $presentationTicketType, array $attrs): PresentationTicketType
    {
        $presentationTicketType->update($attrs);
        return $presentationTicketType->fresh(['presentation.season.show', 'presentation.season.venue']);
    }

    public function delete(PresentationTicketType $presentationTicketType): void
    {
        $presentationTicketType->delete();
    }
}
