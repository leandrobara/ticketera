<?php

namespace App\Http\Resources\Views\WhatsAppTemplateModal;

use App\Models\ClientyConfigWhatsAppTemplate;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class ClientyConfigWhatsAppTemplateModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'body' => $this->body,
            'title' => $this->title,
            'business_area_id' => $this->business_area_id,
            'business_area_child_id' => $this->business_area_child_id,
        ];

        return $response;
    }

}
