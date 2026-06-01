<?php

namespace App\Repositories\Criteria\Filter\TaskNotificationEmail;

use DateTime;
use DateTimeZone;
use App\Models\Client;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ScheduledTodayCriteria implements SQLFilterCriteria
{

    protected $scheduledDateEnd = null;
    protected $scheduledDateStart = null;


    public function __construct(?Client $client = null)
    {
        $dateNow = new DateTime('now');
        $this->scheduledDateEnd = (clone $dateNow);
        $this->scheduledDateStart = (clone $dateNow);

        $client = $client ?? request()->client;
        if ($client) {
            $this->scheduledDateEnd->setTimezone(new DateTimeZone($client->timezone));
            $this->scheduledDateStart->setTimezone(new DateTimeZone($client->timezone));
        }

        $this->scheduledDateStart->setTime(0, 0, 0);
        $this->scheduledDateEnd->setTime(23, 59, 59);

        $this->scheduledDateStart->setTimezone(new DateTimeZone('UTC'));
        $this->scheduledDateEnd->setTimezone(new DateTimeZone('UTC'));
    }


    public function filterSQLQuery(object $builder): object
    {
        return $builder
            ->where('scheduled_date', '<=', $this->scheduledDateEnd->format('Y-m-d H:i:s'))
            ->where('scheduled_date', '>=', $this->scheduledDateStart->format('Y-m-d H:i:s'))
        ;
    }

}
