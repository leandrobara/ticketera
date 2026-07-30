<?php

namespace App\Services\Api\Site;

use App\Models\Season;
use App\Models\Venue;
use App\Repositories\Site\SiteVenueRepositoryInterface;

class VenueService
{
    public function __construct(
        private readonly SiteVenueRepositoryInterface $venueRepository,
    ) {
        //
    }

    public function getBySeason(Season $season): Venue
    {
        return $this->venueRepository->getBySeason($season);
    }
}
