<?php

namespace App\Http\Resources\Views\LeadQuickSearch;

use App\Http\Resources\LeadContactResource;
use Illuminate\Http\Resources\Json\JsonResource;


class LeadQuickSearchItemResource extends JsonResource
{

    public function toArray($request)
    {
        $response = [
            'id' => $this->resource->id,
            'method' => $this->resource->method,
            'company' => $this->resource->company,
            'created_at' => $this->resource->created_at,
            // 'is_whatsapp_form' => $this->resource->is_whatsapp_form,
            // 'is_facebook_form' => $this->resource->is_facebook_form,
            // 'is_manually_created' => $this->resource->is_manually_created,
        ];
        $response = $this->loadMainLeadContact($response);
        return $response;
    }


    private function loadMainLeadContact(array $response)
    {
        if (!$this->resource->relationLoaded('leadContacts')) {
            $this->resource->load('leadContacts');
        }
        $leadContact = $this->resource->leadContacts->filter(function ($leadContact) {
            return $leadContact->is_main;
        })->first();
        $visibleFields = ['id', 'name', 'last_name', 'leadContactEmails', 'leadContactPhones'];
        $leadContactRs = new LeadContactResource($leadContact);
        $leadContactRs->setVisibleFields($visibleFields);
        $response['mainLeadContact'] = $leadContactRs;
        return $response;
    }

}
