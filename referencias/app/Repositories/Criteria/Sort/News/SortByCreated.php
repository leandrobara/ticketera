<?php

namespace App\Repositories\Criteria\Sort\News;

use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Criteria\Sort\SortCriteria;


class SortByCreated extends SortCriteria
{

    protected function applySortDesc($builder): Builder
    {
        return $builder->orderByRaw('(IFNULL(updated_at, created_at)) DESC, id DESC');
    }


    protected function applySortAsc($builder): Builder
    {
        return $builder->orderByRaw('(IFNULL(updated_at,created_at)) ASC, id ASC');
    }

}
