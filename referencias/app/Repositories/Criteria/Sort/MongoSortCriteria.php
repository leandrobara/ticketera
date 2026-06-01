<?php

namespace App\Repositories\Criteria\Sort;

use MongoDB\Laravel\Eloquent\Builder;


abstract class MongoSortCriteria
{

    protected $direction;


    public function __construct(string $direction = 'desc')
    {
        $this->direction = $direction;
    }


    public function applySort(Builder $builder): Builder
    {
        if ($this->direction === 'desc') {
            return $this->applySortDesc($builder);
        }
        return $this->applySortAsc($builder);
    }


    abstract protected function applySortDesc(Builder $builder): Builder;
    abstract protected function applySortAsc(Builder $builder): Builder;

}
