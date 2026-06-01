<?php

namespace App\Repositories\Criteria\Filter;


interface SQLFilterCriteria
{

    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object;

}
