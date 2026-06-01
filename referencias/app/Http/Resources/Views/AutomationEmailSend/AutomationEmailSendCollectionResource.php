<?php

namespace App\Http\Resources\Views\AutomationEmailSend;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Views\AutomationEmailSend\AutomationEmailSendItemResource;


class AutomationEmailSendCollectionResource extends ResourceCollection
{

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $automation) {
            $response[] = new AutomationEmailSendItemResource($automation);
        }
        return $response;
    }

}
