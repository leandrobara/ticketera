<?php

namespace App\Http\Controllers\API\Automations;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Automations\AutomationProposalService;
use App\Http\Resources\Automations\AutomationProposalResource;
use App\Http\Requests\Automations\SaveAutomationProposalRequest;
use App\Http\Requests\Automations\AutomationProposalFlowChartModalRequest;
use App\Services\API\Views\AutomationProposalService as ViewsAutomationProposalService;


class AutomationProposalController extends BaseAPIController
{

    public function save(SaveAutomationProposalRequest $request)
    {
        $result = resolve(AutomationProposalService::class)->saveAutomationProposal($request->validatedDTO());
        $rs = (new AutomationProposalResource($result))->loadOptionsFromRequest($request);
        return $this->getSuccessResponse($rs);
    }


    public function getFlowChartMarkdownModal(
        AutomationProposalFlowChartModalRequest $request
    ) {
        $automationProposal = resolve(ViewsAutomationProposalService::class)->findAutomationProposal();
        $markdown = resolve(AutomationProposalService::class)->getFlowChartMarkdownString($automationProposal);
        return $this->getSuccessResponse(['markdown' => $markdown, 'automationProposal' => $automationProposal]);
    }

}
