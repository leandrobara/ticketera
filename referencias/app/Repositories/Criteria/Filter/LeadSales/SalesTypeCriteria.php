<?php

namespace App\Repositories\Criteria\Filter\LeadSales;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class SalesTypeCriteria implements SQLFilterCriteria
{

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function filterSQLQuery(object $builder): object
    {
        // detect "null" like string
        if (!empty($this->value)) {
            $comparison = $this->value == 'new_customer' ? '=' : '>';

            return $builder->withCount('lead')->having('id', $comparison, 1);
        }
        return $builder;
    }

}
