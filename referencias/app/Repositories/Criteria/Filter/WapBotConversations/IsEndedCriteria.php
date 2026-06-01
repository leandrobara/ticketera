<?php

namespace App\Repositories\Criteria\Filter\WapBotConversations;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class IsEndedCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly bool $isEnded)
    {
    }

    public function filterMongoQuery(Builder $queryBuilder): Builder
    {
        $queryBuilder->where('isEnded', $this->isEnded);
        return $queryBuilder;
    }

}

