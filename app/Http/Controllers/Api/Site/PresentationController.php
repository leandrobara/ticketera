<?php

namespace App\Http\Controllers\Api\Site;

use App\Models\Season;
use App\Services\Api\Site\PresentationService;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Site\ListPresentationsRequest;
use App\Http\Resources\Site\PresentationResourceCollection;

class PresentationController extends BaseAPIController
{
    public function list(Season $season, ListPresentationsRequest $request): array
    {
        return $this->getSuccessResponse(
            new PresentationResourceCollection(
                resolve(PresentationService::class)->list($season, $request->validated())
            )
        );
    }
}
