<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\AutomationEmailSendStep;


class AutomationEmailSendStepObserver
{

    public function deleted(AutomationEmailSendStep $step)
    {
        $step->deleted_at_ts = Carbon::now()->timestamp;
        $step->save();
    }

}
