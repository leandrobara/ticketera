<?php

namespace App\Http\Resources\Views\Reports;

use Illuminate\Http\Resources\Json\JsonResource;


class WapBotConversationModalResource extends JsonResource
{

    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'type' => $this->type,
            'userId' => $this->userId,
            'leadId' => $this->leadId,
            'isEnded' => $this->isEnded,
            'clientId' => $this->clientId,
            'wapBotId' => $this->wapBotId,
            'openAIHistory' => $this->openAIHistory,
            'botPhoneNumber' => $this->botPhoneNumber,
            'extractedParameters' => $this->extractedParameters,
            'customerPhoneNumber' => $this->customerPhoneNumber,
            'botMetaPhoneNumberId' => $this->botMetaPhoneNumberId,
            'whatsAppConnectionId' => $this->whatsAppConnectionId,
            'isEndedByLeadAutoCreationCron' => $this->isEndedByLeadAutoCreationCron,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
            'lastActivityAt' => $this->lastActivityAt,
        ];

        $response['lead'] = $this->resource->lead;
        $response['lead']['user'] = $this->resource?->lead?->user;
        $response['lead']['tags'] = $this->resource?->lead?->tags;
        $response['lead']['status'] = $this->resource?->lead?->status;
        $response['lead']['leadContacts'] = $this->resource?->lead?->leadContacts;
        $response['lead']['mainLeadContact'] = $this->resource?->lead?->mainLeadContact;
        return $response;
    }

}

