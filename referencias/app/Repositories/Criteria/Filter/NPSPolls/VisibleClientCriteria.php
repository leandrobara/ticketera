<?php

namespace App\Repositories\Criteria\Filter\NPSPolls;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class VisibleClientCriteria implements SQLFilterCriteria
{

    private $clientId;

    public function __construct(?int $clientId = null)
    {
        $this->clientId = $clientId;
    }

    public function filterSQLQuery(object $builder): object
    {
        $clientId = $this->clientId;
        if ($clientId) {
            return $builder->whereHas('NPSPollAnswers', function (object $query) use ($clientId) {
                $query->where('client_id', $clientId);
            });
        }

        return $builder->has('NPSPollAnswers');
    }

}
