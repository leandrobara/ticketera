<?php

namespace App\Repositories\Criteria\Filter\WAutomation;

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
            case 'wautomation_after_send':
                return $builder->whereNotNull('wautomation_after_send_id');
            case 'wautomation_proposal':
                return $builder->whereNotNull('wautomation_proposal_id');
            case 'wautomation_sequence':
                return $builder->whereNotNull('wautomation_sequence_id');
        }
    }

}
