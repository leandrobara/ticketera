<?php

namespace App\Repositories\Criteria\Filter\WhatsAppSending;

use DateTime;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class IsAutomationCriteria implements SQLFilterCriteria
{

    
    public function __construct(protected readonly ?bool $isAutomation)
    {
    }


    public function filterSQLQuery(object $builder): object
    {
        if ($this->isAutomation === false) {
            return $builder->where('is_automation', false);
        }
        if ($this->isAutomation === true) {
            return $builder->where('is_automation', true);
        }
        return $builder;
    }

}
