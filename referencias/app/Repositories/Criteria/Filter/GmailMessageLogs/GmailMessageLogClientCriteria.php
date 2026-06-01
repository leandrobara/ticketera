<?php

namespace App\Repositories\Criteria\Filter\GmailMessageLogs;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class GmailMessageLogClientCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly int $clientId)
    {
    }


    public function filterMongoQuery(Builder $builder): Builder
    {
        return $builder->where('clientyMetadata.client.id', (string) $this->clientId);
    }

}