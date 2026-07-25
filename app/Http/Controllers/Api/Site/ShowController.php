<?php

namespace App\Http\Controllers\Api\Site;

use App\Http\Controllers\Api\BaseAPIController;
use App\Models\Season;
use App\Services\Api\Site\ShowService;

class ShowController extends BaseAPIController
{
    public function show(Season $season): array
    {
        return $this->getSuccessResponse(
            resolve(ShowService::class)->getPublicShow($season)
        );
    }
}
