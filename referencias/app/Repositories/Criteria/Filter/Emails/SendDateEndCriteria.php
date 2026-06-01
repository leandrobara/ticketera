<?php

namespace App\Repositories\Criteria\Filter\Emails;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class SendDateEndCriteria implements SQLFilterCriteria
{

    public function __construct($value)
    {
        $this->value = new DateTime($value);
    }


    public function filterSQLQuery(object $builder): object
    {
        return $builder->where('send_date', '<=', $this->value->format('Y-m-d H:i:s'));
    }

}
