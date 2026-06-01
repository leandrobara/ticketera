<?php

namespace App\Repositories\Criteria\Filter\Emails;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class OpenedOnlyCriteria implements SQLFilterCriteria
{

    private $openedOnly;


    public function __construct($openedOnly)
    {
        $this->openedOnly = $openedOnly;
    }


    public function filterSQLQuery(object $builder): object
    {
        return $this->openedOnly ? $builder->whereNotNull('opened_date') : $builder->whereNull('opened_date');
    }

}
