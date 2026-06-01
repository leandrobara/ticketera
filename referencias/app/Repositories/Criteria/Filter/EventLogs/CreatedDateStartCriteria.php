<?php

namespace App\Repositories\Criteria\Filter\EventLogs;

use DateTime;
use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class CreatedDateStartCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly DateTime $dateStart)
    {
    }


    public function filterMongoQuery(Builder $builder): Builder
    {
        return $builder->where('createdAtTs', '>=', $this->dateStart->getTimestamp());
    }

}
