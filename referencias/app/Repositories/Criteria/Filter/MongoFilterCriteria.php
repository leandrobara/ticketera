<?php

namespace App\Repositories\Criteria\Filter;

use MongoDB\Laravel\Eloquent\Builder;


interface MongoFilterCriteria
{

    public function filterMongoQuery(Builder $builder): Builder;

}
