<?php

namespace App\Http\Resources\Views\LeadAttachment;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class LeadAttachmentResource extends JsonResource
{

    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'lead_id' => $this->lead_id,
            'hash' => $this->hash,
            'bucket_name' => $this->bucket_name,
            'bucket_filepath' => $this->bucket_filepath,
            'original_filename' => $this->original_filename,
            'extension' => $this->extension,
            'size' => $this->size,
        ];

        $visibleFields = $this->getFieldsToShow();

        $response = $this->filterVisibleFields($data);

        return $response;
    }

}
