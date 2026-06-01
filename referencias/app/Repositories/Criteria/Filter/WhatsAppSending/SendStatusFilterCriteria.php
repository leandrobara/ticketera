<?php

namespace App\Repositories\Criteria\Filter\WhatsAppSending;

use DateTime;
use DateTimeZone;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class SendStatusFilterCriteria implements SQLFilterCriteria
{

    public function __construct(protected readonly ?string $sendStatus)
    {
    }


    public function filterSQLQuery(object $builder): object
    {
        if ($this->sendStatus === 'sent') {
            return $builder->where('send_date', '<', new DateTime('now'));
        }
        if ($this->sendStatus === 'scheduled') {
            return $builder->where('send_date', '>', new DateTime('now'));
        }
        if ($this->sendStatus === 'failed_messages') {
            return $builder->whereHas('whatsAppSendingMessages', function (object $query) {
                $query->whereNotNull('error_message');
            });
        }
        return $builder;
    }

}
