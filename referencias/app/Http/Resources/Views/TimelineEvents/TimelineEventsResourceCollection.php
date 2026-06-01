<?php

namespace App\Http\Resources\Views\TimelineEvents;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TimelineEventsResourceCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $timelineEvent) {
            $response[] = new TimelineEventsItemResource($timelineEvent);
        }

        return $response;
    }
}
