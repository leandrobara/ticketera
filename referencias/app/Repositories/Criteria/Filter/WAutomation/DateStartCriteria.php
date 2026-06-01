<?php

namespace App\Repositories\Criteria\Filter\WAutomation;

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
        return $builder->where('created_at', '>=', $this->value->format('Y-m-d H:i:s'));
    }

}
