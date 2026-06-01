<?php

namespace App\Http\Resources\Views\AutomationEmailSendStep;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AutomationEmailSendStepCollectionResource extends ResourceCollection
{
    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $automation) {
            $response[] = new AutomationEmailSendStepItemResource($automation);
        }

        return $response;
    }
}
