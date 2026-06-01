<?php

namespace App\Repositories\Criteria\Filter\TaskNotificationWhatsAppMessage;

use DateTime;
use DateTimeZone;
use App\Models\Client;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class DispatchedTodayCriteria implements SQLFilterCriteria
{

    protected $dispatchedDateEnd = null;
    protected $dispatchedDateStart = null;


    public function __construct(?Client $client = null)
    {
        $dateNow = new DateTime('now');
        $this->dispatchedDateEnd = (clone $dateNow);
        $this->dispatchedDateStart = (clone $dateNow);

        $client = $client ?? request()->client;
        if ($client) {
            $this->dispatchedDateEnd->setTimezone(new DateTimeZone($client->timezone));
            $this->dispatchedDateStart->setTimezone(new DateTimeZone($client->timezone));
        }

        $this->dispatchedDateStart->setTime(0, 0, 0);
        $this->dispatchedDateEnd->setTime(23, 59, 59);

        $this->dispatchedDateStart->setTimezone(new DateTimeZone('UTC'));
        $this->dispatchedDateEnd->setTimezone(new DateTimeZone('UTC'));
    }


    public function filterSQLQuery(object $builder): object
    {
        return $builder
            ->where('dispatched_date', '<=', $this->dispatchedDateEnd->format('Y-m-d H:i:s'))
            ->where('dispatched_date', '>=', $this->dispatchedDateStart->format('Y-m-d H:i:s'))
        ;
    }

}
