<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\WAutomationSequenceStep;


class WAutomationSequenceStepObserver
{

    public function deleted(WAutomationSequenceStep $step)
    {
        $step->deleted_at_ts = Carbon::now()->timestamp;
        $step->save();
    }

}
