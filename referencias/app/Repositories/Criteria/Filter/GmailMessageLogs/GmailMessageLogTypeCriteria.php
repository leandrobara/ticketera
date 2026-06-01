<?php

namespace App\Repositories\Criteria\Filter\GmailMessageLogs;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class GmailMessageLogTypeCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly string $type)
    {
    }


    public function filterMongoQuery(Builder $builder): Builder
    {
        if ($this->type == 'only_sent') {
            return $builder->where('isResponseFromClientyUser', true);
        }
        if ($this->type == 'only_responses') {
            return $builder->where('isResponseToClientyUser', true);
        }
    }

}