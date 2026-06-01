<?php

namespace App\Http\Resources\Views\EmailTemplateModal;

use App\Models\ClientyConfigEmailTemplate;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class ClientyConfigEmailTemplateModalResource extends JsonResource
{

    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'body' => $this->body,
            'title' => $this->title,
            'subject' => $this->subject,
            'is_proposal' => $this->is_proposal,
            'business_area_id' => $this->business_area_id,
            'business_area_child_id' => $this->business_area_child_id,
        ];

        return $response;
    }
}
