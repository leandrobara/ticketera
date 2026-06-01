<?php

namespace App\Http\Controllers\API\External\Legacy;

use Illuminate\Http\Request;
use App\Services\API\ClientInteractionService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\Views\ClientInteraction\External\Legacy\InteractionsResourceCollection;
use App\Http\Resources\Views\ClientInteraction\External\Legacy\LastInteractionsResourceCollection;


class ClientDataController extends BaseAPIController
{

    public function interactions(Request $request, ClientInteractionService $service)
    {
        $interactions = $service->findInteractionsByWeeksAgo(3);
        $rs = (new InteractionsResourceCollection($interactions))->setWeeksAgo(3);
        return $this->getSuccessResponse($rs);
    }


    public function lastInteractions(Request $request, ClientInteractionService $service)
    {
        $lastInteractions = $service->findLastInteractionsFromEachClient();
        $rs = new LastInteractionsResourceCollection($lastInteractions);
        return $this->getSuccessResponse($rs->getResponseAsObject());
    }

}