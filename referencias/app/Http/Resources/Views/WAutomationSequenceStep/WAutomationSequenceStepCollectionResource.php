<?php

namespace App\Http\Resources\Views\WAutomationSequenceStep;

use Illuminate\Http\Resources\Json\ResourceCollection;


class WAutomationSequenceStepCollectionResource extends ResourceCollection
{

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $wAutomation) {
            $response[] = new WAutomationSequenceStepItemResource($wAutomation);
        }
        return $response;
    }

}
