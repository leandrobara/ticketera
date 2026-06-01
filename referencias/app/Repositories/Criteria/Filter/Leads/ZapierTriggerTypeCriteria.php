<?php

namespace App\Repositories\Criteria\Filter\Leads;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ZapierTriggerTypeCriteria implements SQLFilterCriteria
{

    public function __construct(protected readonly string $triggerType)
    {
    }


    // Could be Illuminate\Database\Query\Builder or  Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        // Por el momento, devuelvo lo mismo para cualquier $triggerType.
        $builder->where('is_from_zapier_app', false)->where('is_from_zapier_webhook', false);
        return $builder;
    }

}
