<?php

namespace App\Repositories\Criteria\Sort\Leads;

use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Criteria\Sort\SortCriteria;


class SortByLastStatusChanged extends SortCriteria
{

    protected function applySortDesc($builder): Builder
    {
        return $builder->orderByRaw('(IFNULL(last_status_changed_at, created_at)) ASC, id ASC');
    }


    protected function applySortAsc($builder): Builder
    {
        return $builder->orderByRaw('(IFNULL(last_status_changed_at, created_at)) DESC, id DESC');
    }

}
