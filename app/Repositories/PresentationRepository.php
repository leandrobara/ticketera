<?php

namespace App\Repositories;

use App\Models\Presentation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PresentationRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Presentation::query()
            ->with(['show', 'venue'])
            ->when($filters['show_id'] ?? null, function ($query, int $showId) {
                $query->where('show_id', $showId);
            })
            ->when($filters['venue_id'] ?? null, function ($query, int $venueId) {
                $query->where('venue_id', $venueId);
            })
            ->when($filters['status'] ?? null, function ($query, string $status) {
                $query->where('status', $status);
            })
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where('notes', 'like', "%{$search}%");
            })
            ->orderBy('starts_at')
            ->paginate($limit);
    }

    public function getOne(Presentation $presentation): Presentation
    {
        return $presentation->load(['show', 'venue', 'ticketTypes']);
    }

    public function store(array $attrs): Presentation
    {
        $presentation = Presentation::create($attrs);
        return $presentation->load(['show', 'venue']);
    }

    public function update(Presentation $presentation, array $attrs): Presentation
    {
        $presentation->update($attrs);
        return $presentation->fresh(['show', 'venue', 'ticketTypes']);
    }

    public function delete(Presentation $presentation): void
    {
        $presentation->delete();
    }
}

