<?php

namespace App\Http\Resources\Views\AutomationLogList;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class AutomationLogListResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        
        $response = [];
        foreach ($this->collection as $automationLog) {
            $response[] = new AutomationLogListItemResource($automationLog);
        }

        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }

}
