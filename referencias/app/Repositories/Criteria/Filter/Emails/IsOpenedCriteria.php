<?php

namespace App\Repositories\Criteria\Filter\Emails;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class IsOpenedCriteria implements SQLFilterCriteria
{

    public function __construct(protected readonly ?bool $isOpened)
    {
    }


    public function filterSQLQuery(object $builder): object
    {
        if ($this->isOpened === false) {
            return $builder->whereNull('opened_date');
        }
        if ($this->isOpened === true) {
            return $builder->whereNotNull('opened_date');
        }
        return $builder;
    }
}
