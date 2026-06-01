<?php

namespace App\Http\Resources\Views\WAutomationLogList;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationProposalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'enabled' => $this->enabled,
                'created_at' => $this->created_at,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }
        $response = $this->filterVisibleFields($response);
        return $response;
    }

}
