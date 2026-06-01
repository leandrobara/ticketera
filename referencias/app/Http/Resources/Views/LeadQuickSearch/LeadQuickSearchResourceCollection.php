<?php

namespace App\Http\Resources\Views\LeadQuickSearch;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadQuickSearchResourceCollection extends ResourceCollection
{

    public function toArray($request)
    {
        $response = collect([]);
        foreach ($this->collection as $leadDocument) {
            $response->push(new LeadQuickSearchItemResource($leadDocument));
        }
        return $response;
    }

}
