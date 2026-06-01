<?php

namespace App\Http\Resources\Automations;

use Illuminate\Database\Eloquent\Model;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use Illuminate\Http\Resources\Json\ResourceCollection;


class AutomationTaskResourceCollection extends ResourceCollection
{
    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        foreach ($this->collection as $entity) {
            if (is_a($entity, Model::class)) {
                $rs = new AutomationTaskResource($entity);
            } else {
                $rs = $entity;
            }
            $rs->setVisibleFields($visibleFields);
            $response[] = $rs;
        }
        return $response;
    }

}
