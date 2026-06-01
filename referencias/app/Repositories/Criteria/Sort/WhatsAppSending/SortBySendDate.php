<?php

namespace App\Repositories\Criteria\Sort\WhatsAppSending;

use Illuminate\Database\Eloquent\Builder;
use App\Repositories\Criteria\Sort\SortCriteria;


class SortBySendDate extends SortCriteria
{

    protected function applySortDesc($builder): Builder
    {
        return $builder->orderByRaw('send_date DESC, id DESC');
    }


    protected function applySortAsc($builder): Builder
    {
        return $builder->orderByRaw('send_date ASC, id ASC');
    }

}
