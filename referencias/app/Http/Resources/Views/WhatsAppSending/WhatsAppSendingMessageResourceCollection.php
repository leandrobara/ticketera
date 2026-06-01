<?php

namespace App\Http\Resources\Views\WhatsAppSending;

use Illuminate\Database\Eloquent\Model;
use App\Http\Resources\Traits\HandlePagination;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Http\Resources\UserCustomFilter\UserCustomFilterResource;


class WhatsAppSendingMessageResourceCollection extends ResourceCollection
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        foreach ($this->collection as $entity) {
            if (is_a($entity, Model::class)) {
                $rs = new WhatsAppSendingMessageResource($entity);
            } else {
                $rs = $entity;
            }
            $rs->setVisibleFields($visibleFields);
            $response[] = $rs;
        }
        return $response;
    }

}
