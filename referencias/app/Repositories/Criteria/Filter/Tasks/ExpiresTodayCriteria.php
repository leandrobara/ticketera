<?php

namespace App\Repositories\Criteria\Filter\Tasks;

use DateTime;
use DateTimeZone;
use App\Models\Client;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ExpiresTodayCriteria implements SQLFilterCriteria
{

    protected $limitDatetime = null;
    protected $limitDateToday = null;


    public function __construct(?Client $client = null)
    {
        $this->limitDatetime = new DateTime('now');
        $this->limitDateToday = new DateTime('now');

        $client = $client ?? request()->client;
        if ($client) {
            $timezone = $client->timezone;
            $this->limitDatetime->setTimezone(new DateTimeZone($timezone));
            $this->limitDateToday->setTimezone(new DateTimeZone($timezone));
        }

        $hour = (int) $this->limitDatetime->format('H');
        $this->limitDatetime->setTime($hour, 0, 0);

        $this->limitDateToday->setTime(23, 59, 59);

        $this->limitDatetime->setTimezone(new DateTimeZone('UTC'));
        $this->limitDateToday->setTimezone(new DateTimeZone('UTC'));
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        return $builder
            ->where('status', '<>', 'completed')
            // ->where('limit_date', '>=', $this->value->format('Y-m-d H:i:s'))
            ->where('limit_date', '>=', $this->limitDatetime->format('Y-m-d H:00:00'))
            ->where('limit_date', '<=', $this->limitDateToday->format('Y-m-d H:i:s'))
        ;
    }

}
