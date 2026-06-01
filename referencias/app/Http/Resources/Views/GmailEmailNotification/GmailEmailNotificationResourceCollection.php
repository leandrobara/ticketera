<?php

namespace App\Http\Resources\Views\GmailEmailNotification;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class GmailEmailNotificationResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $gmailEmailNotification) {
            $response[] = new GmailEmailNotificationItemResource($gmailEmailNotification);
        }
        return $response;
    }

}
