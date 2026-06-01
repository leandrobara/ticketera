<?php

namespace App\Http\Controllers\API\Views;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Views\ListAutomationLogRequest;
use App\Services\API\Automations\AutomationLogService;
use App\Http\Resources\Views\AutomationLogList\AutomationLogListResourceCollection;


class AutomationLogController extends BaseAPIController
{

    public function list(ListAutomationLogRequest $request)
    {
        $automationLogs = resolve(AutomationLogService::class)->list($request->validated());
        return $this->getSuccessResponse(new AutomationLogListResourceCollection($automationLogs));
    }

}
