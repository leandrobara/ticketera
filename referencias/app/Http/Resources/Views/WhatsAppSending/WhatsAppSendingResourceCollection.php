<?php

namespace App\Http\Resources\Views\WhatsAppSending;

use App\Http\Resources\Traits\HandlePagination;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\UserCustomFilter\UserCustomFilterResource;


class WhatsAppSendingResourceCollection extends ResourceCollection
{

    use VisibleFieldsFilter, HandlePagination;


    public function toArray($request)
    {
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        
        foreach ($this->collection as $entity) {
            if (is_a($entity, Model::class)) {
                $rs = new WhatsAppSendingResource($entity);
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
