<?php

namespace App\Repositories\Criteria\Filter\WapBotConversations;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class TypeCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly string $type)
    {
    }

    public function filterMongoQuery(Builder $queryBuilder): Builder
    {
        $queryBuilder->where('type', $this->type);
        return $queryBuilder;
    }

}

