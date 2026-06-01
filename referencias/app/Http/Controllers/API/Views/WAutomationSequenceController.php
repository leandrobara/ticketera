<?php

namespace App\Http\Controllers\API\Views;

use Illuminate\Http\Request;
use App\Models\WAutomationSequence;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\WAutomations\WAutomationSequenceService;
use App\Http\Requests\Automations\WAutomationSequenceFlowChartModalRequest;
use App\Http\Resources\Views\WAutomationSequence\WAutomationSequenceCollectionResource;


class WAutomationSequenceController extends BaseAPIController
{

    public function show(Request $req)
    {
        $result = resolve(WAutomationSequenceService::class)->findByClient($req->client);
        return $this->getSuccessResponse(new WAutomationSequenceCollectionResource($result));
    }


    public function getFlowChartMarkdownModal(
        WAutomationSequence $wAutomationSequence,
        WAutomationSequenceFlowChartModalRequest $req
    ) {
        $markdown = resolve(WAutomationSequenceService::class)->getFlowChartMarkdownString($wAutomationSequence);
        return $this->getSuccessResponse(['markdown' => $markdown, 'wAutomationSequence' => $wAutomationSequence]);
    }

}
