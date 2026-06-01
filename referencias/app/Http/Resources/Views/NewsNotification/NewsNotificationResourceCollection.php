<?php

namespace App\Http\Resources\Views\NewsNotification;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class NewsNotificationResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $newsNotification) {
            $response[] = new NewsNotificationItemResource($newsNotification);
        }
        return $response;
    }

}
