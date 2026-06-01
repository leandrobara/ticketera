<?php

namespace App\DTO\Automations;

use Illuminate\Support\Collection;


class AutomationProposalResendDTO
{

    public $client;
    public $enabled;
    public $sendHour;
    public $sendDelayDays;
    public $cancellingTags;
    public $cancellingStatus;
    public $cancellingEnabled;
    public $sendEmailTemplate;
    public $automationProposal;
    public $addOriginalAttachments;
    public $cancelIfProposalWasOpened;
    public $cancelIfProposalWasAlreadySent;


    public static function build($data)
    {
        $dto = new AutomationProposalResendDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->enabled = $data['enabled'] ?? false;
        $this->sendHour = $data['send_hour'] ?? null;
        $this->sendEmailTemplate = $data['sendEmailTemplate'];
        $this->sendDelayDays = $data['send_delay_days'] ?? null;
        $this->cancellingEnabled = $data['cancelling_enabled'] ?? false;
        $this->cancellingTags = $data['cancellingTags'] ?? new Collection();
        $this->cancellingStatus = $data['cancellingStatus'] ?? new Collection();
        $this->addOriginalAttachments = $data['add_original_attachments'] ?? false;
        $this->cancelIfProposalWasOpened = $data['cancel_if_proposal_was_opened'] ?? false;
        $this->cancelIfProposalWasAlreadySent = $data['cancel_if_proposal_was_already_sent'] ?? false;
    }

}
