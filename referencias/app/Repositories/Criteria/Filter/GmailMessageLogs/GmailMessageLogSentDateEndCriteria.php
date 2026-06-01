<?php

namespace App\Repositories\Criteria\Filter\GmailMessageLogs;

use DateTime;
use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class GmailMessageLogSentDateEndCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly DateTime $dateEnd)
    {
    }


    public function filterMongoQuery(Builder $builder): Builder
    {
        return $builder->where('createdAtTs', '<=', $this->dateEnd->getTimestamp());
    }

}