<?php

namespace App\Http\Resources\Views\WAutomationSequence;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\Views\WAutomationSequence\WAutomationSequenceItemResource;


class WAutomationSequenceCollectionResource extends ResourceCollection
{

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $wAutomation) {
            $response[] = new WAutomationSequenceItemResource($wAutomation);
        }
        return $response;
    }

}
