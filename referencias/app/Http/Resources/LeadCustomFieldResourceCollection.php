<?php

namespace App\Http\Resources;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Http\Resources\Traits\HandlePagination;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadCustomFieldResourceCollection extends ResourceCollection
{

    use VisibleFieldsFilter;

    private $leadCustomFieldValues;


    public function toArray($request)
    {
        $response = [];
        $visibleFields = $this->getFieldsToShow();
        foreach ($this->collection as $entity) {
            if (is_a($entity, Model::class)) {
                $rs = new LeadCustomFieldResource($entity);
            } else {
                $rs = $entity;
            }
            $rs->setVisibleFields($visibleFields);
            $response[] = $rs;
        }
        $response = $this->assignLeadCustomFieldValuesIfExist($response);
        return $response;
    }


    public function setLeadCustomFieldValues(Collection $leadCustomFieldValues): LeadCustomFieldResourceCollection
    {
        $this->leadCustomFieldValues = $leadCustomFieldValues;
        return $this;
    }


    protected function assignLeadCustomFieldValuesIfExist(array $response): array
    {
        if (!$this->leadCustomFieldValues) {
            return $response;
        }
        $visibleFields = ['id', 'value', 'hash'];
        foreach ($response as $i => $leadCustomFieldRs) {
            $customValue = $this->leadCustomFieldValues->where('lead_custom_field_id', $leadCustomFieldRs->id)->first();
            $leadCustomFieldRs->setLeadCustomFieldValue($customValue);
            $response[$i] = $leadCustomFieldRs;
        }
        return $response;
    }

}
