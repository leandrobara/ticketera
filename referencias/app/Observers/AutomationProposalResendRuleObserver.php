<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\AutomationProposalResendRule;


class AutomationProposalResendRuleObserver
{

    public function deleted(AutomationProposalResendRule $rule)
    {
        $rule->deleted_at_ts = Carbon::now()->timestamp;
        $rule->save();
    }

}
