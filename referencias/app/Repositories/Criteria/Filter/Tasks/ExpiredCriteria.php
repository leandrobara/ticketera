<?php

namespace App\Repositories\Criteria\Filter\Tasks;

use DateTime;
use DateTimeZone;
use App\Models\Client;
use App\Repositories\Criteria\Filter\SQLFilterCriteria;


class ExpiredCriteria implements SQLFilterCriteria
{

    public function __construct(?Client $client = null)
    {
        $this->value = new DateTime('now');

        // $client = $client ?? request()->client;
        // if ($client) {
        //     $timezone = $client->timezone;
        //     $this->value->setTimezone(new DateTimeZone($timezone));
        // }

        // // $hour = (int) $this->value->format('H');
        // // $this->value->setTime($hour, 0, 0);

        // $this->value->setTimezone(new DateTimeZone('UTC'));
    }


    // Could be Illuminate\Database\Query\Builder or Illuminate\Database\Eloquent\Builder
    public function filterSQLQuery(object $builder): object
    {
        return $builder
            ->where('status', '<>', 'completed')
            ->where('limit_date', '<', $this->value->format('Y-m-d H:i:s'))
        ;
    }

}
