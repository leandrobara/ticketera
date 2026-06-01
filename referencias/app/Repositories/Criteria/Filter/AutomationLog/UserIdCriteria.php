<?php

namespace App\Repositories\Criteria\Filter\AutomationLog;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class UserIdCriteria implements SQLFilterCriteria
{

    public function __construct(private ?int $userId)
    {
    }


    public function filterSQLQuery(object $builder): object
    {
        if ($this->userId) {
            return $builder->whereHas('lead', function (object $query) {
                $query->where('user_id', $this->userId);
            });
        }
    }

}
