<?php

namespace App\Http\Resources\Integration;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadItemResourceCollection extends ResourceCollection
{
    use HandlePagination;

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $lead) {
            $leadListItemArr = (new LeadResource($lead))->toArray();
            $response[] = $leadListItemArr;
        }

        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }
}
