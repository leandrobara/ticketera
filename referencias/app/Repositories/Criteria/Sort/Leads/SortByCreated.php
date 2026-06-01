<?php

namespace App\Repositories\Criteria\Sort\Leads;

use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Criteria\Sort\SortCriteria;


class SortByCreated extends SortCriteria
{

    protected function applySortDesc($builder): Builder
    {
        return $builder->orderByRaw('lead_created_at DESC, id DESC');
    }


    protected function applySortAsc($builder): Builder
    {
        return $builder->orderByRaw('lead_created_at ASC, id ASC');
    }

}
