<?php

namespace App\Http\Controllers\API\Views;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListWAutomationLogRequest;
use App\Services\API\WAutomations\WAutomationLogService;
use App\Http\Resources\Views\WAutomationLogList\WAutomationLogListResourceCollection;


class WAutomationLogController extends BaseAPIController
{

    public function list(ListWAutomationLogRequest $request)
    {
        $automationLogs = resolve(WAutomationLogService::class)->list($request->validated());
        return $this->getSuccessResponse(new WAutomationLogListResourceCollection($automationLogs));
    }

}
