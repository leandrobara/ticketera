<?php

namespace App\Http\Resources\Actions\LeadsBulkUpload;

use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadsBulkUploadPreviewResourceCollection extends ResourceCollection
{

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $dto) {
            $response[] = new LeadsBulkUploadPreviewItemResource($dto);
        }
        return $response;
    }

}
