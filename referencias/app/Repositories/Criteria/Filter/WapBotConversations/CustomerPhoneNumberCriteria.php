<?php

namespace App\Repositories\Criteria\Filter\WapBotConversations;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class CustomerPhoneNumberCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly string $customerPhoneNumber)
    {
    }

    public function filterMongoQuery(Builder $queryBuilder): Builder
    {
        $queryBuilder->where('customerPhoneNumber', $this->customerPhoneNumber);
        return $queryBuilder;
    }

}

