<?php

namespace App\Http\Resources\Views\Reports;

use Illuminate\Http\Resources\Json\JsonResource;


class WapBotConversationItemResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'userId' => $this->userId,
            'leadId' => $this->leadId,
            'clientId' => $this->clientId,
            'wapBotId' => $this->wapBotId,
            'isEnded' => $this->isEnded,
            'threadId' => $this->threadId,
            'botPhoneNumber' => $this->botPhoneNumber,
            'lastActivityAt' => $this->lastActivityAt,
            'customerPhoneNumber' => $this->customerPhoneNumber,
            'botMetaPhoneNumberId' => $this->botMetaPhoneNumberId,
            'whatsAppConnectionId' => $this->whatsAppConnectionId,
            'isPermanentSeedConversation' => $this->isPermanentSeedConversation,
            'isEndedByLeadAutoCreationCron' => $this->isEndedByLeadAutoCreationCron,
            'isCreatedFromOutgoingEchoMessage' => $this->isCreatedFromOutgoingEchoMessage,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'deletedAt' => $this->deletedAt,
        ];
    }

}

