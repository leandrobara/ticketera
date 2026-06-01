<?php

namespace App\DTO\WhatsAppMetaAPI;

use DateTime;
use App\Models\WAutomationLog;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Collection;


class WhatsAppMetaAPINewSendingParametersDTO
{

    public bool $isMassive = false;
    public bool $isProposal = false;
    public Collection $leadContactPhones;
    public DateTime | null $sendDate = null;
    public string | null $chatMessage = null;
    public WAutomationLog | null $wAutomationLog = null;
    public WhatsAppAttachment | null $whatsAppAttachment = null;

    public float $proposalAmount = 0;
    public string | null $proposalDescription = null;


    public static function buildFromRequestArray(array $requestArray)
    {
        $dto = new WhatsAppMetaAPINewSendingParametersDTO();
        $dto->sendDate = $requestArray['sendDate'];
        $dto->isProposal = $requestArray['isProposal'];
        $dto->leadContactPhones = $requestArray['leadContactPhones'];
        $dto->wAutomationLog = $requestArray['wAutomationLog'] ?? null;
        $dto->isMassive = $dto->leadContactPhones->pluck('lead_id')->unique()->values()->count() > 1;

        $dto->chatMessage = $requestArray['chatMessage'] ?? null;
        $dto->proposalAmount = $requestArray['proposalInfo']['amount'] ?? 0;
        $dto->whatsAppAttachment = $requestArray['whatsAppAttachment'] ?? null;
        $dto->proposalDescription = $requestArray['proposalInfo']['description'] ?? null;
        return $dto;
    }


    public function isScheduled(): bool
    {
        return $this->sendDate ? true : false;
    }

}
