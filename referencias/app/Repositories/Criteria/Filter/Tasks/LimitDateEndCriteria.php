<?php

namespace App\Repositories\Criteria\Filter\Tasks;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LimitDateEndCriteria implements SQLFilterCriteria
{

    public function __construct($value)
    {
        $this->value = new DateTime($value);
    }


    public function filterSQLQuery(object $builder): object
    {
        return $builder->whereRaw(
            sprintf("limit_date <= '%s'", $this->value->format('Y-m-d H:i:s'))
        );
    }

}
