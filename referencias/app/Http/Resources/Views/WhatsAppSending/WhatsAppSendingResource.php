<?php

namespace App\Http\Resources\Views\WhatsAppSending;

use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WhatsAppSendingResource extends JsonResource
{

    use VisibleFieldsFilter;

    public $opts = [];


    public function toArray($request)
    {
        if (!$this->resource) {
            return [];
        }
        $response = $this->resource->attributesToArray();
        $response = $this->loadUserField($response);
        $response = $this->loadMessageField($response);
        if ($this->opts['loadWapSendingMsgs'] ?? true) {
            $response = $this->loadWhatsAppSendingMessagesField($response);
        }

        return $response;
    }
    

    private function loadWhatsAppSendingMessagesField(array $response): array
    {
        if (!$this->resource->relationLoaded('whatsAppSendingMessages')) {
            $this->resource->load('whatsAppSendingMessages');
        }
        $rs = new WhatsAppSendingMessageResourceCollection($this->whatsAppSendingMessages);
        $response['whatsAppSendingMessages'] = $rs;
        return $response;
    }

    
    private function loadUserField(array $response): array
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = ['id', 'type', 'name', 'last_name'];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);

        $response['user'] = $userRs;
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

}
