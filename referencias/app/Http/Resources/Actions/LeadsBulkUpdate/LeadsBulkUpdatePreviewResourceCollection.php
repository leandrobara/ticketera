<?php

namespace App\Http\Resources\Actions\LeadsBulkUpdate;

use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadsBulkUpdatePreviewResourceCollection extends ResourceCollection
{

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $dto) {
            $response[] = new LeadsBulkUpdatePreviewItemResource($dto);
        }
        return $response;
    }

}
