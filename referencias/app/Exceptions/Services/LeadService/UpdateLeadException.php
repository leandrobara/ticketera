<?php

namespace App\Exceptions\Services\LeadService;

use Exception;
use App\Models\Lead;


class UpdateLeadException extends Exception
{

    private $lead = null;


    public function setLead(Lead $lead): UpdateLeadException
    {
        $this->lead = $lead;
        return $this;
    }


    public function getLead()
    {
        return $this->lead;
    }

}
