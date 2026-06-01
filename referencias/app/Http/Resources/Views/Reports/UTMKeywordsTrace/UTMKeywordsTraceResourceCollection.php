<?php

namespace App\Http\Resources\Views\Reports\UTMKeywordsTrace;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class UTMKeywordsTraceResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        $response = $this->collection->toArray();
        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }

}
