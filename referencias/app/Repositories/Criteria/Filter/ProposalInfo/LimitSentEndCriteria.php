<?php

namespace App\Repositories\Criteria\Filter\ProposalInfo;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LimitSentEndCriteria implements SQLFilterCriteria
{

    public function __construct($value)
    {
        $this->value = new DateTime($value);
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        return $builder->whereRaw(
            sprintf("sent_date <= '%s'", $this->value->format('Y-m-d H:i:s'))
        );
    }

}
