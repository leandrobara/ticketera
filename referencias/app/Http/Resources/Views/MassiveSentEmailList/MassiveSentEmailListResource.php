<?php

namespace App\Http\Resources\Views\MassiveSentEmailList;

use App\Http\Resources\Traits\HandlePagination;
use App\Http\Resources\Views\MassiveSentEmailList\MassiveSentEmailListItemResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MassiveSentEmailListResource extends ResourceCollection
{
    use HandlePagination;

    public function toarray($request)
    {
        $response = [];
        foreach ($this->collection as $sentemail) {
            $response[] = new MassiveSentEmailListItemResource($sentemail);
        }

        $response = $this->addpaginationinfo($this->resource, $response);

        return $response;
    }
}
