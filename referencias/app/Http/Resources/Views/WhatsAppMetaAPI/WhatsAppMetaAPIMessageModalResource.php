<?php

namespace App\Http\Resources\Views\WhatsAppMetaAPI;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WhatsAppMetaAPIMessageModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $leadContactPhones = $this->resource->leadContactPhones->map(function ($leadContactPhone) {
            return [
                'id' => $leadContactPhone->id,
                'phone' => $leadContactPhone->phone,
                'lead_id' => $leadContactPhone->lead_id,
                'lead_contact_id' => $leadContactPhone->lead_contact_id,
            ];
        });
        $response = [
            'metaError' => $this->resource->metaError,
            'leadContactPhones' => $leadContactPhones,
            'whatsAppMetaAPIConnection' => $this->resource->whatsAppMetaAPIConnection?->only([
                'id', 'waba_id', 'phone_number_id', 'waba_name', 'phone_number'
            ]),
            'associatedMetaPhoneNumberData' => $this->resource->associatedMetaPhoneNumberData,
        ];
        return $response;
    }

}
