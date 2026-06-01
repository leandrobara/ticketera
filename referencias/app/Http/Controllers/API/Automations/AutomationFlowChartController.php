<?php

namespace App\Http\Controllers\API\Automations;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\Automations\AutomationFlowChartService;
use App\Http\Requests\Automations\AutomationFlowChartRequest;


class AutomationFlowChartController extends BaseAPIController
{

    public function getFlowChartsMarkdownString(AutomationFlowChartRequest $req)
    {
        $markdown = resolve(AutomationFlowChartService::class)->getFlowChartsMarkdownString(
            $req->client, $req->getFlowChartType()
        );
        return $this->getSuccessResponse(['markdown' => $markdown]);
    }

}
