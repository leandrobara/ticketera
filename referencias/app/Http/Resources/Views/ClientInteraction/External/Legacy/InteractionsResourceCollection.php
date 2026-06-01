<?php

namespace App\Http\Resources\Views\ClientInteraction\External\Legacy;

use DateTime;
use StdClass;
use Illuminate\Http\Resources\Json\ResourceCollection;


class InteractionsResourceCollection extends ResourceCollection
{

    private $weeksAgo = 3;


    public function toArray($request)
    {
        $response = $this->createInitializedResponse();
        foreach ($this->collection as $entity) {
            $leadsClientId = "{$entity->client->leads_client_id}";
            $weekDateIndex = str_replace('-0', '-', $entity->week_date);
            $response[$weekDateIndex]->$leadsClientId = (new InteractionsItemResource($entity));
        }
        return $response;
    }


    public function setWeeksAgo(int $weeksAgo): InteractionsResourceCollection
    {
        $this->weeksAgo = $weeksAgo;
        return $this;
    }


    protected function createInitializedResponse(): array
    {
        $response = [];
        $weekDate = $this->getCurrentWeekDate();
        for ($i = 0; $i < $this->weeksAgo; $i++) {
            $weekDateStr = $weekDate->format('Y-n-j');
            // To force indexes to be strings and not integers.
            $response[$weekDateStr] = new StdClass();
            $weekDate->modify('-7 days');
        }
        $response = array_reverse($response);
        return $response;
    }


    protected function getCurrentWeekDate(): DateTime
    {
        $date = new DateTime('now');
        $currentDayName = $date->format('l');
        if ($currentDayName != 'Monday') {
            $date = $date->modify('last Monday');
        }
        return $date;
    }

}
