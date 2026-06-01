<?php

namespace App\Repositories\Criteria\Filter\GmailMessageLogs;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class GmailMessageLogUserCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly int $userId)
    {
    }


    public function filterMongoQuery(Builder $builder): Builder
    {
        return $builder->where('clientyMetadata.user.id', (string) $this->userId);
    }

}