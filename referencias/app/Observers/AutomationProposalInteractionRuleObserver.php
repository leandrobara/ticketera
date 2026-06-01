<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\AutomationProposalInteractionRule;


class AutomationProposalInteractionRuleObserver
{

    public function deleted(AutomationProposalInteractionRule $rule)
    {
        $rule->deleted_at_ts = Carbon::now()->timestamp;
        $rule->save();
    }

}
