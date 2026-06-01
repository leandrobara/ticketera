<?php

namespace App\Http\Controllers\API\WAutomations;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WAutomations\WAutomationAfterSendService;
use App\Http\Resources\WAutomations\WAutomationAfterSendResource;
use App\Http\Requests\WAutomations\SaveWAutomationAfterSendRequest;


class WAutomationAfterSendController extends BaseAPIController
{

    public function save(SaveWAutomationAfterSendRequest $req)
    {
        $result = resolve(WAutomationAfterSendService::class)->save($req->validatedDTO());
        $rs = (new WAutomationAfterSendResource($result))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }

}
