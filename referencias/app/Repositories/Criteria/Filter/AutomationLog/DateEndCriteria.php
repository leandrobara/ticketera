<?php

namespace App\Repositories\Criteria\Filter\AutomationLog;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class DateEndCriteria implements SQLFilterCriteria
{

    private $value;


    public function __construct($value)
    {
        $this->value = new DateTime($value);
    }


    public function filterSQLQuery(object $builder): object
    {
        return $builder->whereRaw("DATE(created_at) <= '{$this->value->format('Y-m-d')}'");
    }

}
