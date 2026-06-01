<?php

namespace App\Repositories\Criteria\Filter\WapBotConversations;

use DateTime;
use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class DateStartCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly DateTime $dateStart)
    {
    }


    public function filterMongoQuery(Builder $queryBuilder): Builder
    {
        $queryBuilder->where('createdAt', '>=', $this->dateStart);
        return $queryBuilder;
    }

}

