<?php

namespace App\Http\Controllers\Api\Site;

use App\Models\Season;
use App\Services\Api\Site\PresentationService;
use App\Http\Controllers\Api\BaseAPIController;
use App\Http\Requests\Site\ListPresentationsRequest;
use App\Http\Resources\Site\PresentationResourceCollection;


class PresentationController extends BaseAPIController
{
    public function listBySeason(Season $season, ListPresentationsRequest $request): array
    {
        $presentations = resolve(PresentationService::class)->listBySeason($season, $request->validated());
        $presentationRs = new PresentationResourceCollection($presentations);
        return $this->getSuccessResponse($presentationRs);
    }
}
