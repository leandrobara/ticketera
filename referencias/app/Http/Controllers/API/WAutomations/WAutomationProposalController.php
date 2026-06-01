<?php

namespace App\Http\Controllers\API\WAutomations;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WAutomations\WAutomationProposalService;
use App\Http\Resources\WAutomations\WAutomationProposalResource;
use App\Http\Requests\WAutomations\SaveWAutomationProposalRequest;
use App\Http\Requests\WAutomations\WAutomationProposalFlowChartModalRequest;
use App\Services\API\Views\WAutomationProposalService as ViewsWAutomationProposalService;


class WAutomationProposalController extends BaseAPIController
{

    public function save(SaveWAutomationProposalRequest $req)
    {
        $result = resolve(WAutomationProposalService::class)->saveWAutomationProposal($req->validatedDTO());
        $rs = (new WAutomationProposalResource($result))->loadOptionsFromRequest($req);
        return $this->getSuccessResponse($rs);
    }


    public function getFlowChartMarkdownModal(
        WAutomationProposalFlowChartModalRequest $request
    ) {
        $wAutomationProposal = resolve(ViewsWAutomationProposalService::class)->findWAutomationProposal();
        $markdown = resolve(WAutomationProposalService::class)->getFlowChartMarkdownString($wAutomationProposal);
        return $this->getSuccessResponse(['markdown' => $markdown, 'wAutomationProposal' => $wAutomationProposal]);
    }

}
