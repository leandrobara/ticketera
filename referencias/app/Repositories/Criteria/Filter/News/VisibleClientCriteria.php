<?php

namespace App\Repositories\Criteria\Filter\News;

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
        //Historial ayuda - get news by client id
        if ($clientId) {
            return $builder->whereHas('newsNotifications', function (object $query) use ($clientId) {
                $query->where('client_id', $clientId);
            });
        }

        //Gestión Clienty - get all news
        return $builder->has('newsNotifications');
    }

}
