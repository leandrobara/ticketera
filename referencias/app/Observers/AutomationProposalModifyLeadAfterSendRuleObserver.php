<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\AutomationProposalModifyLeadAfterSendRule;


class AutomationProposalModifyLeadAfterSendRuleObserver
{

    public function deleted(AutomationProposalModifyLeadAfterSendRule $rule)
    {
        $rule->deleted_at_ts = Carbon::now()->timestamp;
        $rule->save();
    }

}
