<?php

namespace App\Repositories\Site;

use App\Models\Season;
use App\Models\Presentation;
use Illuminate\Support\Collection;

class PresentationRepository
{
    public function listBySeason(Season $season): Collection
    {
        return Presentation::query()
            ->where('season_id', $season->id)
            ->whereIn('status', ['published', 'sold_out'])
            ->whereHas('season', function ($query) {
                $query->whereIn('status', ['published', 'finished']);
            })
            ->withCount([
                'tickets as sold_tickets_count' => function ($query) {
                    $query->whereIn('status', ['VALID', 'USED']);
                },
            ])
            ->with([
                'ticketTypes' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->withCount([
                            'tickets as sold_tickets_count' => function ($query) {
                                $query->whereIn('status', ['VALID', 'USED']);
                            },
                        ])
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('starts_at')
            ->get()
        ;
    }
}
