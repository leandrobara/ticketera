<?php

namespace App\Repositories\Criteria\Filter\Tasks;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class TaskStatusCriteria implements SQLFilterCriteria
{

    public function __construct(protected string|array $taskStatus)
    {
    }


    public function filterSQLQuery(object $builder): object
    {
        if (!is_array($this->taskStatus)) {
            $taskStatusArr = [$this->taskStatus];
        } else {
            $taskStatusArr = $this->taskStatus;
        }
        return $builder->where(function ($q) use ($taskStatusArr) {
            foreach ($taskStatusArr as $statusStr) {
                if (in_array($statusStr, ['pending', 'completed'])) {
                    $q->orWhere('status', $statusStr);
                }
                if ($statusStr == 'expired') {
                    $q->orWhere(function ($q2) {
                        $q2->where('status', 'pending')->where('limit_date', '<', new DateTime('now'));
                    });
                }
                if ($statusStr == 'non_expired') {
                    $q->orWhere(function ($q2) {
                        $q2->where('status', 'pending')->where('limit_date', '>', new DateTime('now'));
                    });
                }
            }
        });
    }

}
