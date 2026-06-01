<?php

namespace App\Repositories\Criteria\Filter\WapBotConversations;

use DateTime;
use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class DateEndCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly DateTime $dateEnd)
    {
    }
    

    public function filterMongoQuery(Builder $queryBuilder): Builder
    {
        $queryBuilder->where('createdAt', '<=', $this->dateEnd);
        return $queryBuilder;
    }

}

