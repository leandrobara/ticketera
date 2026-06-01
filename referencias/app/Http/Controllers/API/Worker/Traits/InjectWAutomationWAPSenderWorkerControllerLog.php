<?php

namespace App\Http\Controllers\API\Worker\Traits;

use Illuminate\Support\Facades\Log;


trait InjectWAutomationWAPSenderWorkerControllerLog
{

    public function getInfoLog()
    {
        return Log::channel('wautomation_wap_sender_worker_controller_info');
    }

    public function getErrorLog()
    {
        return Log::channel('wautomation_wap_sender_worker_controller_errors');
    }

}
