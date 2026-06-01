<?php

namespace App\Http\Controllers\API\Views;

use App\Services\API\Views\StatusService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListStatusTimeRequest;
use App\Http\Resources\Views\StatusTime\ListSTatusTimeResourceCollection;


class StatusController extends BaseAPIController
{

    public function listLeadsStatusHistory(ListStatusTimeRequest $request)
    {
        $result = resolve(StatusService::class)->findLeadsStatusTimes($request->getLeadIds());
        return $this->getSuccessResponse(new ListSTatusTimeResourceCollection($result));
    }

}
