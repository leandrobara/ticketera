<?php

namespace App\Repositories\Criteria\Filter\WapBotConversations;

use MongoDB\Laravel\Eloquent\Builder;
use App\Repositories\Criteria\Filter\MongoFilterCriteria;


class BotMetaPhoneNumberIdCriteria implements MongoFilterCriteria
{

    public function __construct(protected readonly array $botMetaPhoneNumberIds)
    {
    }


    public function filterMongoQuery(Builder $queryBuilder): Builder
    {
        if (is_array($this->botMetaPhoneNumberIds) && count($this->botMetaPhoneNumberIds) > 0) {
            $queryBuilder->whereIn('botMetaPhoneNumberId', $this->botMetaPhoneNumberIds);
        }
        return $queryBuilder;
    }

}

