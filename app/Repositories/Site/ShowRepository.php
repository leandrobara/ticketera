<?php

namespace App\Repositories\Site;

use App\Models\Season;

class ShowRepository
{
    public function getPublicSeason(Season $season): Season
    {
        return Season::query()
            ->whereIn('status', ['published', 'finished'])
            ->with([
                'venue',
                'show.mainImage',
                'show.images' => function ($query) {
                    $query->orderBy('sort_order');
                },
                'show.credits.person',
                'show.performanceHistories' => function ($query) {
                    $query
                        ->orderBy('sort_order')
                        ->orderByDesc('year')
                        ->orderBy('id');
                },
                'show.links' => function ($query) {
                    $query
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->findOrFail($season->id);
    }
}
