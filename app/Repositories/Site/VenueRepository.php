<?php

namespace App\Repositories\Site;

use App\Models\Season;
use App\Models\Venue;

class VenueRepository implements SiteVenueRepositoryInterface
{
    public function getBySeason(Season $season): Venue
    {
        return Venue::query()
            ->whereKey($season->venue_id)
            ->whereHas('seasons', function ($seasonQuery) use ($season) {
                $seasonQuery
                    ->whereKey($season->id)
                    ->whereIn('status', ['published', 'finished']);
            })
            ->firstOrFail();
    }
}
