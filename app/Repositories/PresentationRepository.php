<?php

namespace App\Repositories;

use App\Models\Presentation;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PresentationRepository
{
    public function listPaginated(array $filters, int $limit = 20): LengthAwarePaginator
    {
        return Presentation::query()
            ->with(['season.show', 'season.venue'])
            ->withCount([
                'tickets as sold_tickets_count' => function ($query) {
                    $query->whereIn('status', ['VALID', 'USED']);
                },
            ])
            ->addSelect([
                'revenue_amount' => Ticket::query()
                    ->selectRaw('COALESCE(SUM(order_items.total_amount / NULLIF(order_items.quantity, 0)), 0)')
                    ->join('order_items', 'order_items.id', '=', 'tickets.order_item_id')
                    ->whereColumn('tickets.presentation_id', 'presentations.id')
                    ->whereIn('tickets.status', ['VALID', 'USED'])
                    ->whereNull('order_items.deleted_at'),
            ])
            ->when($filters['show_id'] ?? null, function ($query, int $showId) {
                $query->whereHas('season', fn ($query) => $query->where('show_id', $showId));
            })
            ->when($filters['venue_id'] ?? null, function ($query, int $venueId) {
                $query->whereHas('season', fn ($query) => $query->where('venue_id', $venueId));
            })
            ->when($filters['season_id'] ?? null, fn ($query, int $seasonId) => $query->where('season_id', $seasonId))
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
        return $presentation->load(['season.show', 'season.venue', 'ticketTypes']);
    }

    public function store(array $attrs): Presentation
    {
        $presentation = Presentation::create($attrs);
        return $presentation->load(['season.show', 'season.venue']);
    }

    public function update(Presentation $presentation, array $attrs): Presentation
    {
        $presentation->update($attrs);
        return $presentation->fresh(['season.show', 'season.venue', 'ticketTypes']);
    }

    public function delete(Presentation $presentation): void
    {
        $presentation->delete();
    }
}
