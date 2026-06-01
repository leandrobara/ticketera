<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Http\Resources\Traits\HandlePagination;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use Illuminate\Http\Resources\Json\ResourceCollection;


class StatusCategoryResourceCollection extends ResourceCollection
{
    use VisibleFieldsFilter, HandlePagination;

    public function toArray($request)
    {
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        foreach ($this->collection as $entity) {
            if (is_a($entity, Model::class)) {
                $rs = new StatusCategoryResource($entity);
            } else {
                $rs = $entity;
            }
            $rs->setVisibleFields($visibleFields);
            $response[] = $rs;
        }

        $response = $this->addPaginationInfo($this->resource, $response);

        return $response;
    }
}
