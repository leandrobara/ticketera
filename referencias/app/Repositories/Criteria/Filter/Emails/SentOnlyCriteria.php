<?php

namespace App\Repositories\Criteria\Filter\Emails;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class SentOnlyCriteria implements SQLFilterCriteria
{

    public function filterSQLQuery(object $builder): object
    {
        return $builder->whereNotNull('sent_date');
    }

}
