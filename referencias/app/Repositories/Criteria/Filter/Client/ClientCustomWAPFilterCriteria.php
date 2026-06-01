<?php

namespace App\Repositories\Criteria\Filter\Client;

use Illuminate\Database\Query\Builder as QueryBuilder;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;


class ClientCustomWAPFilterCriteria implements SQLFilterCriteria
{

    public function __construct(protected string $customWapFilterValue)
    {
    }


    public function filterSQLQuery(object $builder): QueryBuilder | EloquentBuilder
    {
        if ($this->customWapFilterValue == 'enabled_wapi') {
            return $builder->whereHas('clientSettings', function ($q) {
                $q->where('enable_wapi', true);
            });
        }
        if ($this->customWapFilterValue == 'enabled_wap_sender') {
            return $builder->whereHas('clientSettings', function ($q) {
                $q->where('enable_whatsapp_sender_extension', true);
            });
        }
        if ($this->customWapFilterValue == 'disabled_wapi_and_wap_sender') {
            return $builder->whereHas('clientSettings', function ($q) {
                $q->where('enable_wapi', false)->where('enable_whatsapp_sender_extension', false);
            });
        }
        return $builder;
    }

}
