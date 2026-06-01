<?php

namespace App\Http\Resources\Views\LeadAttachment;

use App\Http\Resources\Traits\VisibleFieldsFilter;
use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadAttachmentResourceCollection extends ResourceCollection
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        foreach ($this->collection as $entity) {
            if (is_a($entity, Model::class)) {
                $rs = new LeadAttachmentResource($entity);
            } else {
                $rs = $entity;
            }
            $rs->setVisibleFields($visibleFields);
            $response[] = $rs;
        }

        return $response;
    }
}
