<?php

namespace App\Observers;

use Carbon\Carbon;
use App\Models\WAutomationProposalResendRule;


class WAutomationProposalResendRuleObserver
{

    public function deleted(WAutomationProposalResendRule $rule)
    {
        $rule->deleted_at_ts = Carbon::now()->timestamp;
        $rule->save();
    }

}
