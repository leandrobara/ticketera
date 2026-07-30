<?php

namespace App\Http\Controllers\Api\Site;

use App\Models\Show;
use App\Services\Api\Site\ShowService;
use App\Http\Resources\Site\ShowResource;
use App\Http\Controllers\Api\BaseAPIController;


class ShowController extends BaseAPIController
{
    public function getShowProfileData(Show $show): array
    {
        $showRs = new ShowResource(resolve(ShowService::class)->getShowProfileData($show));
        return $this->getSuccessResponse($showRs);
    }
}
