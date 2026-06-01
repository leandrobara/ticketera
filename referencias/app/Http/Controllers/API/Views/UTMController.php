<?php

namespace App\Http\Controllers\API\Views;

use App\Models\Lead;
use Illuminate\Http\Request;
use App\Services\API\Views\UTMService;
use App\Http\Controllers\API\BaseAPIController;
use App\Http\Resources\Views\LeadQuickSearch\LeadQuickSearchResourceCollection;


class UTMController extends BaseAPIController
{

    public function listUTMCampaign(Request $req)
    {
        $utmCampaigns = resolve(UTMService::class)->findAllUTMCampaigns($req->client);
        return $this->getSuccessResponse($utmCampaigns);
    }

}
