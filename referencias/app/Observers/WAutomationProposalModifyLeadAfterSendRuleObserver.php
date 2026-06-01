<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\WAutomationProposalModifyLeadAfterSendRule;


class WAutomationProposalModifyLeadAfterSendRuleObserver
{

    public function deleted(WAutomationProposalModifyLeadAfterSendRule $rule)
    {
        $rule->deleted_at_ts = Carbon::now()->timestamp;
        $rule->save();
    }

}
