<?php

namespace App\Http\Resources\Views\LeadList;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadListResourceCollection extends ResourceCollection
{
    use HandlePagination;

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $lead) {
            $response[] = new LeadListItemResource($lead);
        }

        $response = $this->addPaginationInfo($this->resource, $response);

        return $response;
    }
}
