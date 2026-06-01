<?php

namespace App\Repositories\Criteria\Filter\NPSPolls;

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class DateStartCriteria implements SQLFilterCriteria
{

    private $value;


    public function __construct($value)
    {
        $this->value = new DateTime($value);
    }


    public function filterSQLQuery(object $builder): object
    {
        return $builder->whereRaw(
            "DATE(IFNULL(updated_at, created_at)) >= '{$this->value->format('Y-m-d')}'"
        );
    }

}
