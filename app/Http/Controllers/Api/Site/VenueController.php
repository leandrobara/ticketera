<?php

namespace App\Http\Controllers\Api\Site;

use App\Models\Season;
use App\Services\Api\Site\VenueService;
use App\Http\Resources\Site\VenueResource;
use App\Http\Controllers\Api\BaseAPIController;


class VenueController extends BaseAPIController
{
    public function getBySeason(Season $season): array
    {
        $venue = resolve(VenueService::class)->getBySeason($season);
        $venueResource = new VenueResource($venue);

        return $this->getSuccessResponse($venueResource);
    }
}
