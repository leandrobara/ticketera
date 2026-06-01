<?php

namespace App\Repositories\Criteria\Filter\AutomationLog;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TypeLogCriteria implements SQLFilterCriteria
{

    private $value;


    public function __construct($value)
    {
        $this->value = $value;
    }


    public function filterSQLQuery(object $builder): object
    {
        switch ($this->value) {
            case 'automation_task':
                return $builder->whereNotNull('automation_task_id');
            case 'automation_new_lead':
                return $builder->whereNotNull('automation_new_lead_id');
            case 'automation_proposal':
                return $builder->whereNotNull('automation_proposal_id');
            case 'automation_email_send':
                return $builder->whereNotNull('automation_email_send_id');
        }
    }

}
