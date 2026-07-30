<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PresentationResourceCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        $presentations = [];

        foreach ($this->collection as $presentationSalesData) {
            $presentations[] = (new PresentationResource($presentationSalesData))->toArray($request);
        }

        return $presentations;
    }
}
