<?php

namespace App\Repositories\Criteria\Filter\GmailMessageLogs;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class GmailMessageLogGmailIdCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly string $gmailId)
    {
    }


    public function filterMongoQuery(Builder $builder): Builder
    {
        return $builder->where('gmailId', $this->gmailId);
    }

}