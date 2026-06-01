<?php

namespace App\Http\Controllers\API\Views;

use App\Models\AutomationEmailSend;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Views\AutomationEmailSendService;
use App\Http\Requests\Automations\AutomationEmailSendFlowChartModalRequest;
use App\Http\Resources\Views\AutomationEmailSend\AutomationEmailSendCollectionResource;


class AutomationEmailSendController extends BaseAPIController
{

    public function show()
    {
        $result = resolve(AutomationEmailSendService::class)->findAutomationsByClient();
        return $this->getSuccessResponse(new AutomationEmailSendCollectionResource($result));
    }


    public function getFlowChartMarkdownModal(
        AutomationEmailSend $automationEmailSend,
        AutomationEmailSendFlowChartModalRequest $req
    ) {
        $markdown = resolve(AutomationEmailSendService::class)->getFlowChartMarkdownString($automationEmailSend);
        return $this->getSuccessResponse(['markdown' => $markdown, 'automationEmailSend' => $automationEmailSend]);
    }

}
