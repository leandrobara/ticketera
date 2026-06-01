<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\WAutomationAfterSend;


class WAutomationAfterSendObserver
{

    public function deleted(WAutomationAfterSend $wAutomation)
    {
        $wAutomation->deleted_at_ts = Carbon::now()->timestamp;
        $wAutomation->save();
    }

}
