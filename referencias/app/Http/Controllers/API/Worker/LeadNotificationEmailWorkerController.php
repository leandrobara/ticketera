<?php

namespace App\Http\Controllers\API\Worker;

use Illuminate\Http\Request;
use App\Helpers\SystemHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\API\LeadNotificationEmailService;


class LeadNotificationEmailWorkerController extends BaseAPIController
{

    public function __construct()
    {
        \Debugbar::disable();
        SystemHelper::setManualFlush();
        SystemHelper::setTimeLimit(900);
        SystemHelper::setMemoryLimitMB(2000);
    }


    public function sendScheduledGrouped(Request $req)
    {
        $service = resolve(LeadNotificationEmailService::class);
        $notifs = $service->findGroupedToSend();
        $sentNotifs = $service->sendGroupedNewLeadNotificationEmailToLeadUsers($notifs);
        return $this->getSuccessResponse($sentNotifs);
    }

}
