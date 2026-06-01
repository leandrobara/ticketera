<?php

namespace App\Repositories\Criteria\Sort\AutomationLog;

use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Criteria\Sort\SortCriteria;


class SortByCreated extends SortCriteria
{

    protected function applySortDesc($builder): Builder
    {
        return $builder->orderByRaw('created_at DESC, id DESC');
    }


    protected function applySortAsc($builder): Builder
    {
        return $builder->orderByRaw('created_at ASC, id ASC');
    }

}
