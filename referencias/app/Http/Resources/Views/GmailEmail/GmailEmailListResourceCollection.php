<?php

namespace App\Http\Resources\Views\GmailEmail;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class GmailEmailListResourceCollection extends ResourceCollection
{

    use HandlePagination;


    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $gmailEmailDto) {
            $response[] = new GmailEmailListItemResource($gmailEmailDto);
        }
        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }

}
