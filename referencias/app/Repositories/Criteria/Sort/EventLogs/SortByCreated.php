<?php

namespace App\Repositories\Criteria\Sort\EventLogs;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Sort\MongoSortCriteria;


class SortByCreated extends MongoSortCriteria
{

    protected function applySortDesc($builder): Builder
    {
        return $builder->orderBy('createdAtTs', $this->direction);
    }


    protected function applySortAsc($builder): Builder
    {
        return $builder->orderBy('createdAtTs', $this->direction);
    }

}
