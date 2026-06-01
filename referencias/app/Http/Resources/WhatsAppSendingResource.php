<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WhatsAppSendingResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        if (!$this->resource) {
            return [];
        }
        $response = $this->resource->attributesToArray();
        
        $response = $this->loadMessageField($response);
        $response = $this->loadWhatsAppSendingMessagesField($response);
        return $response;
    }
    

    private function loadMessageField(array $response): array
    {
        if (!$this->resource->relationLoaded('whatsAppSendingMessageText')) {
            $this->resource->load('whatsAppSendingMessageText');
        }
        $response['message'] = $this->resource->whatsAppSendingMessageText->message;
        return $response;
    }


    private function loadWhatsAppSendingMessagesField(array $response): array
    {
        if (!$this->resource->relationLoaded('whatsAppSendingMessages')) {
            $this->resource->load('whatsAppSendingMessages');
        }
        $rs = new WhatsAppSendingMessageResourceCollection($this->whatsAppSendingMessages);
        // $userRs->setVisibleFields($visibleFields);
        $response['whatsAppSendingMessages'] = $rs;
        return $response;
    }


}
