<?php

namespace App\Http\Resources;

use App\Http\Resources\Traits\HandlePagination;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\UserCustomFilter\UserCustomFilterResource;


class WhatsAppSendingResourceCollection extends ResourceCollection
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        foreach ($this->collection as $entity) {
            $rs = new WhatsAppSendingResource($entity);
            $rs->setVisibleFields($visibleFields);
            $response[] = $rs;
        }
        return $response;
    }

}
