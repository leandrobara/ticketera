<?php

namespace App\Repositories\Criteria\Filter\GmailMessageLogs;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class GmailMessageLogLeadCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly int $leadId)
    {
    }


    public function filterMongoQuery(Builder $builder): Builder
    {
        return $builder->where('clientyMetadata.lead.id', (string) $this->leadId);
    }

}