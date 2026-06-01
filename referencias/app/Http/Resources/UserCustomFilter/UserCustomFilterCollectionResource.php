<?php

namespace App\Http\Resources\UserCustomFilter;

use App\Http\Resources\Traits\HandlePagination;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\UserCustomFilter\UserCustomFilterResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCustomFilterCollectionResource extends ResourceCollection
{
    use VisibleFieldsFilter, HandlePagination;

    public function toArray($request)
    {
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        foreach ($this->collection as $entity) {
            $rs = new UserCustomFilterResource($entity);
            $rs->setVisibleFields($visibleFields);
            $response[] = $rs;
        }

        $response = $this->addPaginationInfo($this->resource, $response);
        return $response;
    }
}
