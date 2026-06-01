<?php

namespace App\DTO\WAPI;

use App\Models\WAutomationLog;
use App\Models\LeadContactPhone;


class WAPINewWAutomationSendingIndividualData
{

    public function __construct(
        public readonly WAutomationLog $wAutomationLog,
        public readonly LeadContactPhone $leadContactPhone,
    ) {
    }
    
}
