<?php

namespace App\Exceptions\Services\LeadService;

use Exception;
use App\Models\Lead;


class ExistentLeadException extends Exception
{

    private $lead = null;


    public function setLead(Lead $lead): ExistentLeadException
    {
        $this->lead = $lead;
        return $this;
    }


    public function getLead()
    {
        return $this->lead;
    }

}
