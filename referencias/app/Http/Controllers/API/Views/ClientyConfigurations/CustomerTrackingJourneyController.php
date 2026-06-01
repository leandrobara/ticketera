<?php

namespace App\Http\Controllers\API\Views\ClientyConfigurations;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\ClientyConfigCustomerTrackingJourneyService;
use App\Http\Requests\Views\ClientyConfigurations\CustomerTrackingJourney\ShowCustomerTrackingJourneyRequest;


class CustomerTrackingJourneyController extends BaseAPIController
{

    public function getJourneyData(ShowCustomerTrackingJourneyRequest $req)
    {
        $response = resolve(ClientyConfigCustomerTrackingJourneyService::class)->getJourneyData(
            $req->getSearchTerm()
        );
        return $this->getSuccessResponse($response);
    }

}
