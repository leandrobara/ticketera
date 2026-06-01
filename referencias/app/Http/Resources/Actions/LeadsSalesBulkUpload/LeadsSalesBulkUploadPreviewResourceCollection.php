<?php

namespace App\Http\Resources\Actions\LeadsSalesBulkUpload;

use Illuminate\Http\Resources\Json\ResourceCollection;


class LeadsSalesBulkUploadPreviewResourceCollection extends ResourceCollection
{

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $dto) {
            $response[] = new LeadsSalesBulkUploadPreviewItemResource($dto);
        }
        return $response;
    }

}
