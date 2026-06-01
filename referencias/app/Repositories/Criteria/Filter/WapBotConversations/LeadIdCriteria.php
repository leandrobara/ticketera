<?php

namespace App\Repositories\Criteria\Filter\WapBotConversations;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class LeadIdCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly int $leadId)
    {
    }

    public function filterMongoQuery(Builder $queryBuilder): Builder
    {
        $queryBuilder->where('leadId', $this->leadId);
        return $queryBuilder;
    }

}

