<?php

namespace App\Http\Resources\Views\Reports\SalesHistory;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SalesHistoryResourceCollection extends ResourceCollection
{
    use HandlePagination;

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $leadSale) {
            $response[] = new SalesHistoryItemResource($leadSale);
        }
        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }
}
