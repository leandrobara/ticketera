<?php

namespace App\DTO\WAutomations;

use Illuminate\Support\Collection;


class WAutomationProposalResendDTO
{

    public $enabled;
    public $sendHour;
    public $sendDelayDays;
    public $cancellingTags;
    public $cancellingStatus;
    public $cancellingEnabled;
    public $doNotSendWeekends;
    public $wAutomationProposal;
    public $sendWhatsAppTemplate;
    public $addOriginalAttachments;
    public $cancelIfProposalWasAlreadySent;


    public static function build($data)
    {
        $dto = new WAutomationProposalResendDTO($data);
        return $dto;
    }


    public function __construct($data)
    {
        $this->enabled = $data['enabled'] ?? false;
        $this->sendHour = $data['send_hour'] ?? null;
        $this->sendDelayDays = $data['send_delay_days'] ?? null;
        $this->sendWhatsAppTemplate = $data['sendWhatsAppTemplate'];
        $this->cancellingEnabled = $data['cancelling_enabled'] ?? false;
        $this->doNotSendWeekends = $data['do_not_send_weekends'] ?? false;
        $this->cancellingTags = $data['cancellingTags'] ?? new Collection();
        $this->cancellingStatus = $data['cancellingStatus'] ?? new Collection();
        $this->addOriginalAttachments = $data['add_original_attachments'] ?? false;
        $this->cancelIfProposalWasAlreadySent = $data['cancel_if_proposal_was_already_sent'] ?? false;
    }

}
