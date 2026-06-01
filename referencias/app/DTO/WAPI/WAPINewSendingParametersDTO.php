<?php

namespace App\DTO\WAPI;

use DateTime;
use App\Models\WAutomationLog;
use App\Models\WhatsAppAttachment;
use Illuminate\Support\Collection;


class WAPINewSendingParametersDTO
{

    public string $chatMessage;
    public bool $isMassive = false;
    public bool $isProposal = false;
    public Collection $leadContactPhones;
    public DateTime | null $sendDate = null;
    public WAutomationLog | null $wAutomationLog = null;
    public WhatsAppAttachment | null $attachment = null;

    public float $proposalAmount = 0;
    public string | null $proposalDescription = null;


    public static function buildFromRequestArray(array $requestArray)
    {
        $dto = new WAPINewSendingParametersDTO();
        $dto->sendDate = $requestArray['sendDate'];
        $dto->attachment = $requestArray['attachment'];
        $dto->isProposal = $requestArray['isProposal'];
        $dto->chatMessage = $requestArray['chatMessage'];
        $dto->leadContactPhones = $requestArray['leadContactPhones'];
        $dto->wAutomationLog = $requestArray['wAutomationLog'] ?? null;
        $dto->isMassive = $dto->leadContactPhones->pluck('lead_id')->unique()->values()->count() > 1;

        $dto->proposalAmount = $requestArray['proposalInfo']['amount'] ?? 0;
        $dto->proposalDescription = $requestArray['proposalInfo']['description'] ?? null;
        return $dto;
    }


    public function isScheduled(): bool
    {
        return $this->sendDate ? true : false;
    }

}
