<?php

namespace App\Repositories\Site;

use App\Models\Season;
use App\Models\Venue;

interface SiteVenueRepositoryInterface
{
    public function getBySeason(Season $season): Venue;
}
