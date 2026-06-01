<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\WAutomationSequence;


class WAutomationSequenceObserver
{

    public function deleting(WAutomationSequence $wAutomation)
    {
        // $automation->deleted_at_ts = Carbon::now()->timestamp;
    }

}
