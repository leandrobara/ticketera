<?php

namespace App\Repositories\Criteria\Filter\WhatsAppSending;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class IsMassiveCriteria implements SQLFilterCriteria
{

    
    public function __construct(protected readonly ?bool $isMassive)
    {
    }


    public function filterSQLQuery(object $builder): object
    {
        if ($this->isMassive === false) {
            return $builder->where('is_massive', false);
        }
        if ($this->isMassive === true) {
            return $builder->where('is_massive', true)->has('whatsAppSendingMessages', '>', 1);
        }
        return $builder;
    }

}