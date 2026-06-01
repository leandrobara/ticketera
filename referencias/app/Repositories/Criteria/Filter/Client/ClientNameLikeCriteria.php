<?php

namespace App\Repositories\Criteria\Filter\Client;

use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ClientNameLikeCriteria implements SQLFilterCriteria
{

    public function __construct(protected string $clientName)
    {
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        if ($this->clientName) {
            return $builder->where('name', 'like', '%' . $this->clientName . '%');
        }
        return $builder;
    }

}
