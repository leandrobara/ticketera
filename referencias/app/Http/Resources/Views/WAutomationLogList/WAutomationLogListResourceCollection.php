<?php

namespace App\Http\Resources\Views\WAutomationLogList;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class WAutomationLogListResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $wAutomationLog) {
            $response[] = new WAutomationLogListItemResource($wAutomationLog);
        }
        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }

}
