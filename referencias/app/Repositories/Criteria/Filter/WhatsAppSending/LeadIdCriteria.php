<?php

namespace App\Repositories\Criteria\Filter\WhatsAppSending;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LeadIdCriteria implements SQLFilterCriteria
{

    public function __construct(protected readonly int $leadId)
    {
    }


    public function filterSQLQuery(object $builder): object
    {
        return $builder->whereHas('whatsAppSendingMessages', function ($query) {
            $query->where('lead_id', $this->leadId);
        });
        return $builder;
    }

}