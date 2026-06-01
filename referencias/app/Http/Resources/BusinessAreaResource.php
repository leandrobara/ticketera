<?php

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class BusinessAreaResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $visibleFields = $this->getFieldsToShow();
        $response = $this->resource->attributesToArray();
        $response = $this->filterVisibleFields($response);
        $response = $this->loadBusinessAreaChildren($response);
        return $response;
    }


    private function loadBusinessAreaChildren(array $response)
    {
        if (!$this->resource->relationLoaded('businessAreaChildren')) {
            $this->resource->load('businessAreaChildren');
        }
        $response['businessAreaChildren'] = $this->resource->businessAreaChildren;
        return $response;
    }

}
