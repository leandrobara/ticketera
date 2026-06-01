<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\AutomationEmailSend;


class AutomationEmailSendObserver
{

    public function deleting(AutomationEmailSend $automation)
    {
        // $automation->deleted_at_ts = Carbon::now()->timestamp;
    }

}
