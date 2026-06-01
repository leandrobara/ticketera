<?php

namespace App\Repositories\Criteria\Filter\WapBotConversations;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class HasLeadCriteria implements MongoFilterCriteria
{


    public function __construct(protected readonly bool $hasLead)
    {
    }


    public function filterMongoQuery(Builder $queryBuilder): Builder
    {
        if ($this->hasLead === true) {
            $queryBuilder->whereNotNull('leadId');
        } else {
            $queryBuilder->whereNull('leadId');
        }
        return $queryBuilder;
    }

}

