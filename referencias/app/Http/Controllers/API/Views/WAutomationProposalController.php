<?php

namespace App\Http\Controllers\API\Views;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Views\WAutomationProposalService;
use App\Http\Resources\Views\WAutomationProposal\WAutomationProposalResource;


class WAutomationProposalController extends BaseAPIController
{

    public function show()
    {
        $result = resolve(WAutomationProposalService::class)->findWAutomationProposal();
        return $this->getSuccessResponse(new WAutomationProposalResource($result));
    }

}
