<?php

namespace App\DTO\WAPI;

use DateTime;
use App\Models\User;
use App\Models\Client;
use App\Models\WAutomationLog;
use App\Models\WhatsAppSending;
use App\Models\LeadContactPhone;
use Illuminate\Support\Collection;
use App\Models\WhatsAppAttachment;
use App\DTO\WAPI\WAPINewWAutomationSendingIndividualData;


class WAPINewWAutomationSendingParametersDTO
{

    public User $user;
    public Client $client;
    public bool $isMassive;
    public string $chatMessage;
    public bool $isProposal = false;
    public DateTime | null $sendDate = null;
    public WhatsAppAttachment | null $attachment = null;

    // Collection<WAPINewWAutomationSendingIndividualData>
    public Collection $wAutomationWAPSendingDataCollection;


    public function __construct()
    {
        $this->wAutomationWAPSendingDataCollection = new Collection();
    }


    public function addIndividualData(WAutomationLog $wAutomationLog, LeadContactPhone $leadContactPhone): void
    {
        $this->wAutomationWAPSendingDataCollection->push(
            new WAPINewWAutomationSendingIndividualData($wAutomationLog, $leadContactPhone)
        );
    }

}
