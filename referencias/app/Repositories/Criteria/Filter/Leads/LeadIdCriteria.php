<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LeadIdCriteria implements SQLFilterCriteria
{

    public function __construct(protected array $ids)
    {
    }

    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        return $builder->whereIn('id', $this->ids);
    }

}
