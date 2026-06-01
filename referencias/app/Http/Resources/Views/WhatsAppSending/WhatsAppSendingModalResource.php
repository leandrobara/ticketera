<?php

namespace App\Http\Resources\Views\WhatsAppSending;

use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WhatsAppSendingModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        if (!$this->resource) {
            return [];
        }
        $response = $this->resource->attributesToArray();

        $response = $this->loadUserField($response);
        $response = $this->loadMessageField($response);
        $response = $this->loadProposalInfo($response);
        $response = $this->loadWhatsAppAttachment($response);
        $response = $this->loadWhatsAppSendingMessagesField($response);
        return $response;
    }
    

    private function loadWhatsAppSendingMessagesField(array $response): array
    {
        if (!$this->resource->relationLoaded('whatsAppSendingMessages')) {
            $this->resource->load('whatsAppSendingMessages');
        }
        $visibleFields = [
            'id',
            'type',
            'lead_id',
            'success',
            'sent_date',
            'created_at',
            'meta_status',
            'paused_date',
            'phone_number',
            'error_message',
            'cancelled_date',
            'dispatched_date',
            'mainLeadContact',
            'leadContactPhone',
            'whatsapp_sending_id',
            'last_dispatched_date',
        ];
        $rs = new WhatsAppSendingMessageResourceCollection($this->whatsAppSendingMessages);
        $rs->setVisibleFields($visibleFields);
        $response['whatsAppSendingMessages'] = $rs;
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


    private function loadWhatsAppAttachment(array $response): array
    {
        if (!$this->resource->relationLoaded('whatsAppAttachment')) {
            $this->resource->load('whatsAppAttachment');
        }
        $response['whatsAppAttachment'] = $this->resource->whatsAppAttachment;
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


    private function loadProposalInfo(array $response): array
    {
        if (!$this->resource->relationLoaded('proposalInfo')) {
            $this->resource->load('proposalInfo');
        }
        $response['proposalInfo'] = $this->resource->proposalInfo;
        return $response;
    }

}
