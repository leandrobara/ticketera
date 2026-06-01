<?php

namespace App\Repositories\Criteria\Filter\ProposalInfo;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class LandingCriteria implements SQLFilterCriteria
{

    public function __construct($value)
    {
        if (!is_array($value) && $value) {
            $value = collect($value);
        }
        $this->value = $value;
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        $landingIds = $this->value;
        if (!$landingIds) {
            return $builder;
        }
        return $builder->whereHas('lead', function (object $query) use ($landingIds) {
            $query->whereIn('landing_id', $landingIds);
        });
    }

}
