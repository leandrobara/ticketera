<?php

namespace App\Http\Resources\FacebookPage;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClientFacebookPageResourceCollection extends ResourceCollection
{
    use HandlePagination;

    public function toArray($request)
    {
        $response = [];

        foreach ($this->collection as $page) {
            $response[] = new ClientFacebookPageItemResource($page);
        }

        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }
}
