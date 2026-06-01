<?php

namespace App\Http\Controllers\API\Views;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Views\AutomationProposalService;
use App\Http\Resources\Views\AutomationProposal\AutomationProposalResource;


class AutomationProposalController extends BaseAPIController
{

    public function show()
    {
        $result = resolve(AutomationProposalService::class)->findAutomationProposal();
        return $this->getSuccessResponse(new AutomationProposalResource($result));
    }

}
