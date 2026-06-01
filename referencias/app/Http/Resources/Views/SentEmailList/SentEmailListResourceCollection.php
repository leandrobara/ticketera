<?php

namespace App\Http\Resources\Views\SentEmailList;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class SentEmailListResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $email) {
            $response[] = new SentEmailListItemResource($email);
        }

        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }

}
