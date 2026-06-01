<?php

namespace App\DTO\WAPSender;

use App\Models\WAutomationLog;
use App\Models\LeadContactPhone;


class WAPSenderNewWAutomationSendingIndividualData
{

    public function __construct(
        public readonly WAutomationLog $wAutomationLog,
        public readonly LeadContactPhone $leadContactPhone,
    ) {
    }
    
}
