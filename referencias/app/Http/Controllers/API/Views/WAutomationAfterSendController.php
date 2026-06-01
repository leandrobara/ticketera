<?php

namespace App\Http\Controllers\API\Views;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WAutomations\WAutomationAfterSendService;
use App\Http\Resources\WAutomations\WAutomationAfterSendResource;


class WAutomationAfterSendController extends BaseAPIController
{

    public function show(Request $req)
    {
        $automation = resolve(WAutomationAfterSendService::class)->findOneByClient($req->client);
        $rs = $automation ? (new WAutomationAfterSendResource($automation))->loadOptionsFromRequest($req) : null;
        return $this->getSuccessResponse($rs);
    }

}
