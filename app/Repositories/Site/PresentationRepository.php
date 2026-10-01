<?php

namespace App\Repositories\Site;

use App\Models\Presentation;
use App\Models\Season;
use App\Models\Ticket;
use Illuminate\Support\Collection;

class PresentationRepository
{
    public function listBySeason(Season $season): Collection
    {
        $presentations = Presentation::query()
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
            ->get();
        // Each season type has an independent balance for each presentation.
        $counts = Ticket::query()->whereIn('presentation_id', $presentations->pluck('id'))
            ->whereIn('status', ['VALID', 'USED'])
            ->selectRaw('presentation_id, season_ticket_type_id, COUNT(*) AS sold')
            ->groupBy('presentation_id', 'season_ticket_type_id')->get()
            ->keyBy(fn ($row) => $row->presentation_id.':'.$row->season_ticket_type_id);
        foreach ($presentations as $presentation) {
            $presentation->setRelation('ticketTypes', $presentation->ticketTypes->map(function ($type) use ($presentation, $counts) {
                $copy = clone $type;
                $copy->sold_tickets_count = (int) ($counts->get($presentation->id.':'.$type->id)?->sold ?? 0);

                return $copy;
            }));
        }

        return $presentations;
    }
}
